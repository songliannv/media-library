<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/CacheStore.php';
require_once __DIR__ . '/Views.php';
require_once __DIR__ . '/StaticGen.php';   // 生成静态页需要 Views，故 Views 必须在其前面
require_once __DIR__ . '/../adapters/Adapter.php';
require_once __DIR__ . '/../adapters/Tmdb.php';
require_once __DIR__ . '/../adapters/Rawg.php';
require_once __DIR__ . '/../adapters/Bangumi.php';

use Core\CacheStore;

/**
 * 采集（同步）引擎 —— 网页后台「同步热门」与命令行计划任务共用同一套逻辑
 * ==================================================================
 * 一次同步做四件事：
 *   1) 按「各源官方要求」限流：单次条数上限 + 单次请求数上限 + 每个请求之间 sleep；
 *   2) 只取**最热门 / 最多人看**的口径（TMDB trending+popular、RAWG -added/-rating、
 *      Bangumi 当日在播），不盲目翻页把站点灌满；
 *   3) **重复就跳过**：同源同 ID 已存在 → 跳过；同名同年（可选）→ 跳过；
 *   4) 只对**新增**条目生成静态页（不做全量重建，避免每小时跑一次拖垮站点）。
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / fn() / 返回类型 / 标量类型提示 等 7.0+ 语法）
 */
class Sync
{
    /* ================================================================== */
    /* 各源「官方要求」画像（后台「同步采集」页直接展示这张表作对比）     */
    /* ================================================================== */

    /**
     * 返回每个数据源的接口 / 限制 / 推荐口径。
     * 这里的 page_size、delay_ms 就是我们对自身下的限制，来源是各站官方文档的硬性要求。
     */
    public static function profiles()
    {
        return array(
            'tmdb' => array(
                'label'    => 'TMDB（影视 / 短剧 / 电视剧）',
                'docs'     => 'https://developer.themoviedb.org/docs/rate-limiting',
                'quota'    => '官方限流约 50 请求/秒；免费 Key 需署名「数据来自 TMDB」，不得缓存超过 6 个月',
                'page_size'=> 20,
                'delay_ms' => 300,
                'need_key' => true,
                'hot'      => '/trending/all/{window} —— 当下最热（官方 trending 榜）',
                'popular'  => '/movie/popular + /tv/popular —— 最多人看（按热度+投票量）',
                'note'     => '支持 language=zh-CN 中文标题；同一部影片只入库一次（按 TMDB ID 去重）',
            ),
            'rawg' => array(
                'label'    => 'RAWG（游戏）',
                'docs'     => 'https://rawg.io/apidocs',
                'quota'    => '免费额度 20,000 请求/月（约 27 请求/小时，本引擎按 1.5 秒/请求保守跑）',
                'page_size'=> 40,
                'delay_ms' => 1500,
                'need_key' => true,
                'hot'      => '/games?ordering=-added —— 最多人添加（最火）',
                'popular'  => '/games?ordering=-rating —— 评分人气最高',
                'note'     => '单页上限 40 条，故「每源条数」请勿超过 40',
            ),
            'bangumi' => array(
                'label'    => 'Bangumi（动漫 / 综艺）',
                'docs'     => 'https://bangumi.github.io/api/',
                'quota'    => '基础接口免 Key；官方要求自带 User-Agent，建议请求间隔 ≥1 秒',
                'page_size'=> 40,
                'delay_ms' => 1200,
                'need_key' => false,
                'hot'      => '/calendar —— 当日在播（当季最热）',
                'popular'  => '/calendar（该源只提供在播这一个热门口径）',
                'note'     => '返回按星期分组的在播列表，会自动摊平后按条数上限截断',
            ),
        );
    }

    /** 参数默认值（后台「同步采集」页可逐项覆盖） */
    public static function defaults()
    {
        return array(
            'limit'          => 20,     // 每源最多入库多少条
            'max_pages'      => 1,      // 每源最多翻几页（翻页越多越费上游配额）
            'max_requests'   => 8,      // 单次同步的**总**上游请求上限（硬闸）
            'window'         => 'week', // day / week（TMDB trending 时间窗）
            'order'          => 'both', // hot（最热）/ popular（最多人看）/ both（两者都取）
            'min_votes'      => 0,      // 少于这么多投票数的条目跳过（0 = 不限）
            'dedupe'         => 'skip', // skip（已存在就跳过）/ update（存在则刷新数据）
            'skip_same_title'=> 1,      // 同名同年（跨源）也跳过，避免站内重复内容
            'interval'       => 55,     // 两次自动同步之间的最小间隔（分钟），防频繁打上游
            'build'          => 1,      // 是否顺手生成新增条目的静态页
        );
    }

    /** 生效参数 = 后台覆盖值（app_settings → Config['sync']） 叠加 默认值 */
    public static function settings()
    {
        $d = self::defaults();
        $s = Config::get('sync', array());
        if (!is_array($s)) {
            $s = array();
        }
        $out = $d;
        foreach ($d as $k => $v) {
            if (!array_key_exists($k, $s) || $s[$k] === '' || $s[$k] === null) {
                continue;
            }
            $out[$k] = $s[$k];
        }
        // 规范化，避免后台填了离谱的值把上游打爆
        $out['limit']        = self::clamp((int) $out['limit'], 1, 200, $d['limit']);
        $out['max_pages']    = self::clamp((int) $out['max_pages'], 1, 5, $d['max_pages']);
        $out['max_requests'] = self::clamp((int) $out['max_requests'], 1, 60, $d['max_requests']);
        $out['min_votes']    = self::clamp((int) $out['min_votes'], 0, 1000000, 0);
        $out['interval']     = self::clamp((int) $out['interval'], 0, 1440, $d['interval']);
        $out['window']       = in_array($out['window'], array('day', 'week'), true) ? $out['window'] : 'week';
        $out['order']        = in_array($out['order'], array('hot', 'popular', 'both'), true) ? $out['order'] : 'both';
        $out['dedupe']       = ($out['dedupe'] === 'update') ? 'update' : 'skip';
        $out['build']        = $out['build'] ? 1 : 0;
        $out['skip_same_title'] = $out['skip_same_title'] ? 1 : 0;
        return $out;
    }

    /** 启用的数据源（后台「系统设置 → 启用哪些数据源」） */
    public static function enabledSources()
    {
        $on = Config::get('adapters', array('tmdb'));
        if (!is_array($on)) {
            $on = array('tmdb');
        }
        $prof = self::profiles();
        $out  = array();
        foreach ($on as $k) {
            if (isset($prof[$k])) {
                $out[] = $k;
            }
        }
        return $out;
    }

    /**
     * 「本次会怎么采」预案 —— 后台展示用（不发任何请求）。
     */
    public static function plan($opts = array())
    {
        $s    = array_merge(self::settings(), $opts);
        $prof = self::profiles();
        $src  = (isset($s['sources']) && $s['sources']) ? $s['sources'] : self::enabledSources();
        $rows = array();
        foreach ($src as $k) {
            if (!isset($prof[$k])) {
                continue;
            }
            $p    = $prof[$k];
            $page = ($p['page_size'] > 0) ? min((int) $p['page_size'], (int) $s['limit']) : (int) $s['limit'];
            $eps  = self::endpointsFor($k, $s['order'], $s['window'], $s['max_pages']);
            $rows[] = array(
                'source'    => $k,
                'label'     => $p['label'],
                'docs'      => $p['docs'],
                'quota'     => $p['quota'],
                'note'      => $p['note'],
                'delay_ms'  => (int) $p['delay_ms'],
                'page_size' => (int) $p['page_size'],
                'endpoints' => $eps,
                'requests'  => count($eps),
                'limit'     => (int) $s['limit'],
                'per_page'  => $page,
                'hot'       => $p['hot'],
                'popular'   => $p['popular'],
                'need_key'  => !empty($p['need_key']),
            );
        }
        return array(
            'params'   => $s,
            'sources'  => $rows,
            'requests' => self::countRequests($rows, (int) $s['max_requests']),
            'max_requests' => (int) $s['max_requests'],
            'enabled'  => self::enabledSources(),
            'all_sources' => array_keys($prof),
            'last'     => self::lastRun(),
            'minutes_since' => self::minutesSinceLast(),
            'locked'   => self::isLocked(),
            'cron_cmd' => self::cronCommand(),
            'php_bin'  => self::phpBinary(),
        );
    }

    /** 单次同步实际会发出的请求数（受 max_requests 硬闸约束） */
    private static function countRequests(array $rows, $cap)
    {
        $n = 0;
        foreach ($rows as $r) {
            $n += (int) $r['requests'];
        }
        return min($n, (int) $cap);
    }

    /**
     * 某源在某个口径下要打的接口清单（后台展示 + 真正执行都走这里，保证一致）。
     * @return array 每项 ['path'=>接口, 'label'=>说明, 'kind'=>hot/popular/discover, 'page'=>页码]
     */
    public static function endpointsFor($source, $order, $window, $maxPages)
    {
        $window   = in_array($window, array('day', 'week'), true) ? $window : 'week';
        $maxPages = self::clamp((int) $maxPages, 1, 5, 1);
        $hot      = ($order === 'hot' || $order === 'both');
        $pop      = ($order === 'popular' || $order === 'both');
        $out      = array();

        if ($source === 'tmdb') {
            if ($hot) {
                for ($p = 1; $p <= $maxPages; $p++) {
                    $out[] = array('path' => '/trending/all/' . $window . '?page=' . $p, 'label' => '热门趋势（' . ($window === 'day' ? '今日' : '本周') . '）第 ' . $p . ' 页', 'kind' => 'hot', 'page' => $p);
                }
            }
            if ($pop) {
                $out[] = array('path' => '/movie/popular', 'label' => '电影 · 最多人看', 'kind' => 'popular', 'page' => 1);
                $out[] = array('path' => '/tv/popular',    'label' => '剧集 · 最多人看', 'kind' => 'popular', 'page' => 1);
                $out[] = array('path' => '/discover/tv?with_genres=10764&sort_by=popularity.desc', 'label' => '综艺 · 最多人看', 'kind' => 'discover', 'page' => 1);
            }
        } elseif ($source === 'rawg') {
            if ($hot) {
                for ($p = 1; $p <= $maxPages; $p++) {
                    $out[] = array('path' => '/games?ordering=-added&page=' . $p, 'label' => '最多人添加 · 第 ' . $p . ' 页', 'kind' => 'hot', 'page' => $p);
                }
            }
            if ($pop) {
                $out[] = array('path' => '/games?ordering=-rating', 'label' => '评分人气最高', 'kind' => 'popular', 'page' => 1);
            }
        } elseif ($source === 'bangumi') {
            $out[] = array('path' => '/calendar', 'label' => '当日在播（当季最热）', 'kind' => 'hot', 'page' => 1);
            if ($pop) {
                $out[] = array('path' => '/search/subjects?type=2&sort=rank', 'label' => '动画排行榜（人气最高）', 'kind' => 'popular', 'page' => 1);
            }
        }
        return $out;
    }

    /* ================================================================== */
    /* 锁与上次运行（防止每小时任务被重复触发 / 并发打架）                */
    /* ================================================================== */

    public static function lockFile()
    {
        return __DIR__ . '/../config/sync.lock';
    }

    /** 锁是否有效（10 分钟内视为「正在跑」，超时视为上次崩溃留下的僵尸锁） */
    public static function isLocked()
    {
        $f = self::lockFile();
        if (!is_file($f)) {
            return false;
        }
        $raw = @file_get_contents($f);
        $d   = json_decode((string) $raw, true);
        $at  = (is_array($d) && isset($d['at'])) ? (int) $d['at'] : (int) @filemtime($f);
        return (time() - $at) < 600;
    }

    private static function acquireLock($task)
    {
        if (self::isLocked()) {
            return false;
        }
        $f = self::lockFile();
        @file_put_contents($f, json_encode(array(
            'at' => time(), 'task' => $task, 'pid' => function_exists('getmypid') ? getmypid() : 0,
        ), JSON_UNESCAPED_UNICODE));
        return true;
    }

    private static function releaseLock()
    {
        $f = self::lockFile();
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /** 最近一次同步记录（含手动 / 计划任务） */
    public static function lastRun()
    {
        try {
            $st = DB::pdo()->query("SELECT id,task,detail,items,created_at FROM sync_log
                WHERE task LIKE 'sync%' ORDER BY id DESC LIMIT 1");
            $row = $st->fetch();
            if (!$row) {
                return null;
            }
            $d = json_decode((string) $row['detail'], true);
            return array(
                'id'         => (int) $row['id'],
                'task'       => $row['task'],
                'items'      => (int) $row['items'],
                'created_at' => $row['created_at'],
                'inserted'   => (is_array($d) && isset($d['inserted'])) ? (int) $d['inserted'] : null,
                'skipped'    => (is_array($d) && isset($d['skipped'])) ? (int) $d['skipped'] : null,
                'ok'         => (is_array($d) && array_key_exists('ok', $d)) ? (bool) $d['ok'] : null,
                'reason'     => (is_array($d) && isset($d['reason'])) ? $d['reason'] : '',
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /** 距上次同步过了多少分钟（没有记录返回 null） */
    public static function minutesSinceLast()
    {
        $last = self::lastRun();
        if (!$last || empty($last['created_at'])) {
            return null;
        }
        $t = strtotime($last['created_at']);
        if (!$t) {
            return null;
        }
        return (int) floor((time() - $t) / 60);
    }

    /** 最近 N 条同步记录（后台「同步采集」页展示） */
    public static function recent($n = 12)
    {
        $out = array();
        try {
            $st = DB::pdo()->prepare("SELECT id,task,detail,items,created_at FROM sync_log ORDER BY id DESC LIMIT ?");
            $st->bindValue(1, (int) $n, \PDO::PARAM_INT);
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                $d = json_decode((string) $r['detail'], true);
                $out[] = array(
                    'id'         => (int) $r['id'],
                    'task'       => $r['task'],
                    'items'      => (int) $r['items'],
                    'created_at' => $r['created_at'],
                    'ok'         => (is_array($d) && array_key_exists('ok', $d)) ? (bool) $d['ok'] : true,
                    'reason'     => (is_array($d) && isset($d['reason'])) ? $d['reason'] : '',
                    'sources'    => (is_array($d) && isset($d['sources'])) ? $d['sources'] : array(),
                );
            }
        } catch (\Exception $e) {
            // 表不存在 / 库不可用：返回空，不影响后台其它功能
        }
        return $out;
    }

    /* ================================================================== */
    /* 真正执行                                                           */
    /* ================================================================== */

    /**
     * 执行一次同步。
     * @param array $opts task / force / limit / window / order / sources / build / max_requests / dry
     * @return array 报告（可直接 JSON 输出给后台，也用于 sync_log）
     */
    public static function run(array $opts = array())
    {
        @set_time_limit(180);
        $s    = array_merge(self::settings(), $opts);
        $task = isset($opts['task']) && $opts['task'] !== '' ? (string) $opts['task'] : 'manual';
        $force = !empty($opts['force']);
        $dry   = !empty($opts['dry']);

        $report = array(
            'task'     => $task,
            'ok'       => true,
            'skipped'  => false,
            'reason'   => '',
            'started'  => date('Y-m-d H:i:s'),
            'finished' => '',
            'ms'       => 0,
            'params'   => array(
                'limit' => (int) $s['limit'], 'window' => $s['window'], 'order' => $s['order'],
                'min_votes' => (int) $s['min_votes'], 'dedupe' => $s['dedupe'],
                'max_pages' => (int) $s['max_pages'], 'max_requests' => (int) $s['max_requests'],
                'interval' => (int) $s['interval'],
            ),
            'sources'  => array(),
            'fetched'  => 0,
            'inserted' => 0,
            'skipped_dup' => 0,
            'skipped_low' => 0,
            'errors'   => 0,
            'requests' => 0,
            'built'    => 0,
            'ids'      => array(),
            /* 采集后自动补网盘下载地址的结果（见 Core\PanSou::enrich） */
            'pan'      => array(),
        );

        $t0 = microtime(true);

        /* --- 闸门 1：最小间隔（避免手动连点 / 计划任务撞车，频繁请求上游） --- */
        $minInterval = (int) $s['interval'];
        if (!$force && $minInterval > 0) {
            $since = self::minutesSinceLast();
            if ($since !== null && $since < $minInterval) {
                $report['skipped']   = true;
                $report['ok']        = true;
                $report['reason']    = '距上次同步仅 ' . $since . ' 分钟（小于设定的 ' . $minInterval . ' 分钟），本次已跳过。'
                                     . '这样既保护上游配额，也避免站内重复采集；需要立刻跑请用「强制同步」。';
                $report['finished']  = date('Y-m-d H:i:s');
                $report['ms']        = (int) round((microtime(true) - $t0) * 1000);
                return $report;
            }
        }

        /* --- 闸门 2：进程锁（同一时刻只允许一个同步在跑） --- */
        if (!self::acquireLock($task)) {
            $report['skipped']  = true;
            $report['ok']       = true;
            $report['reason']   = '已有一个同步任务正在执行（锁未释放），本次不重复跑。';
            $report['finished'] = date('Y-m-d H:i:s');
            return $report;
        }

        $sources = (isset($s['sources']) && is_array($s['sources']) && $s['sources']) ? $s['sources'] : self::enabledSources();

        try {
            if (!$sources) {
                throw new \Exception('没有启用任何数据源：后台 →「系统设置 → 启用哪些数据源」至少勾一个。');
            }

            foreach ($sources as $key) {
                $prof = self::profiles();
                if (!isset($prof[$key])) {
                    continue;
                }
                $adapter = self::makeAdapter($key);
                if ($adapter === null) {
                    $report['sources'][] = array('source' => $key, 'status' => 'skip', 'msg' => '适配器不可用');
                    $report['errors']++;
                    continue;
                }

                $one = array(
                    'source' => $key, 'label' => $prof[$key]['label'], 'status' => 'ok',
                    'fetched' => 0, 'inserted' => 0, 'skipped_dup' => 0, 'skipped_low' => 0,
                    'requests' => 0, 'msg' => '', 'endpoints' => array(),
                );

                $endpoints = self::endpointsFor($key, $s['order'], $s['window'], $s['max_pages']);
                $seen      = array();   // 本次已处理的 source|type|id，防止多端点重复

                foreach ($endpoints as $ep) {
                    // 每源入库量到上限就停；total 请求数到硬闸也停
                    if ($one['inserted'] >= (int) $s['limit']) { break; }
                    if ($report['requests'] >= (int) $s['max_requests']) {
                        $one['msg'] = '达到单次请求上限（' . (int) $s['max_requests'] . '），剩余接口已跳过';
                        break;
                    }

                    try {
                        $items = $adapter->trendingFetch($ep, (int) $s['limit'], $s['order']);
                    } catch (\Exception $ex) {
                        $one['endpoints'][] = array('path' => $ep['path'], 'label' => $ep['label'], 'count' => 0, 'error' => $ex->getMessage());
                        $one['msg'] = '接口报错：' . $ex->getMessage();
                        $report['errors']++;
                        $report['requests']++;
                        if ($dry) { continue; }
                        usleep((int) $prof[$key]['delay_ms'] * 1000);
                        continue;
                    }

                    $report['requests']++;
                    $one['requests']++;
                    $n = count($items);
                    $one['fetched'] += $n;
                    $report['fetched'] += $n;
                    $one['endpoints'][] = array('path' => $ep['path'], 'label' => $ep['label'], 'count' => $n, 'error' => '');

                    if (!$dry) {
                        foreach ($items as $it) {
                            if ($one['inserted'] >= (int) $s['limit']) { break; }

                            $sid = isset($it['source_id']) ? (string) $it['source_id'] : '';
                            $typ = isset($it['type']) ? (string) $it['type'] : '';
                            $ttl = isset($it['title']) ? trim((string) $it['title']) : '';
                            if ($sid === '' || $ttl === '') { continue; }

                            $k = $key . '|' . $typ . '|' . $sid;
                            if (isset($seen[$k])) { continue; }   // 本次多端点重复
                            $seen[$k] = true;

                            // 「最多人看」的量化门槛：投票数太低的不入库
                            $votes = (int) (isset($it['vote_count']) ? $it['vote_count'] : 0);
                            if ((int) $s['min_votes'] > 0 && $votes > 0 && $votes < (int) $s['min_votes']) {
                                $one['skipped_low']++;
                                $report['skipped_low']++;
                                continue;
                            }

                            try {
                                if (CacheStore::exists($key, $typ, $sid)) {
                                    $one['skipped_dup']++;
                                    $report['skipped_dup']++;
                                    if ($s['dedupe'] === 'update') {
                                        CacheStore::put($key, $typ, $sid, $it, $it, (int) Config::sub('cache', 'ttl_days', 7));
                                    }
                                    continue;
                                }

                                // 跨源同名同年也跳过（站内不出现两遍同一个内容）
                                if (!empty($s['skip_same_title']) && CacheStore::existsSameTitle($ttl, (isset($it['year']) ? $it['year'] : null), $key)) {
                                    $one['skipped_dup']++;
                                    $report['skipped_dup']++;
                                    continue;
                                }

                                $newId = CacheStore::put($key, $typ, $sid, $it, $it, (int) Config::sub('cache', 'ttl_days', 7));
                                $one['inserted']++;
                                $report['inserted']++;
                                if ($newId) {
                                    $report['ids'][] = (int) $newId;
                                }
                            } catch (\Exception $ex) {
                                // 单条失败不影响整批
                                $one['msg'] = '写入失败：' . $ex->getMessage();
                                $report['errors']++;
                            }
                        }
                    }

                    // 每个请求之间 sleep，遵守各源的建议频率
                    usleep((int) $prof[$key]['delay_ms'] * 1000);
                }

                if ($one['inserted'] === 0 && $one['fetched'] === 0 && $one['msg'] === '') {
                    $php = isset($prof[$key]['need_key']) && $prof[$key]['need_key'];
                    $one['msg'] = $php
                        ? '上游没有返回数据：请检查该源的 API Key 是否已填（后台 →「系统设置 → 测试连通」），或服务器能否访问外网'
                        : '上游没有返回数据：请检查服务器能否访问外网';
                }
                $report['sources'][] = $one;
            }

            /* --- 静态页：只生成本次新增的（全量重建放到后台按钮 / 每日任务里） --- */
            if (!empty($s['build']) && !$dry && $report['ids']) {
                foreach ($report['ids'] as $id) {
                    try {
                        if (StaticGen::buildOne($id)) { $report['built']++; }
                    } catch (\Exception $ex) {
                        // 静态页失败不影响入库
                    }
                }
            }

            /* --- 网盘下载地址：本次新入库的条目，自动去 PanSou 搜一遍补下载地址 ---
               写入策略由后台「站点设置 → 接口配置」决定（仅高度匹配自动写 / 搜到就写 / 一律进待确认）。
               补到地址的条目会再生成一次静态页，否则页面上看不到下载按钮。 */
            if (!$dry && $report['ids']) {
                $psFile = __DIR__ . '/PanSou.php';
                if (file_exists($psFile)) {
                    require_once $psFile;
                    try {
                        $pc = \Core\PanSou::cfg();
                        if ($pc['enable'] && $pc['auto']) {
                            $prep = \Core\PanSou::enrich($report['ids'], max(1, (int) $s['limit']), false);
                            $report['pan'] = array(
                                'checked' => $prep['checked'],
                                'written' => $prep['written'],
                                'queued'  => $prep['queued'],
                                'nomatch' => $prep['nomatch'],
                                'links'   => $prep['links'],
                                'ms'      => $prep['ms'],
                                'items'   => $prep['items'],
                                'errors'  => $prep['errors'],
                                'reason'  => isset($prep['reason']) ? $prep['reason'] : '',
                            );
                            // 补上地址的条目要重生成静态页，不然前台看不到下载区
                            if (!empty($s['build']) && $prep['written'] > 0) {
                                foreach ($prep['items'] as $pi) {
                                    if (strpos($pi['action'], '已写入') !== 0) {
                                        continue;
                                    }
                                    try {
                                        StaticGen::buildOne((int) $pi['id']);
                                    } catch (\Exception $ex2) {
                                        // 单条静态页失败不影响其它
                                    }
                                }
                            }
                        } else {
                            $report['pan'] = array(
                                'skipped' => true,
                                'reason'  => $pc['enable'] ? '「接口配置」里没有开启「采集后自动补网盘下载地址」' : '「接口配置」里没有启用 PanSou',
                            );
                        }
                    } catch (\Exception $ex) {
                        $report['pan'] = array('ok' => false, 'errors' => array($ex->getMessage()));
                    } catch (\Error $ex) {   // PHP 7+ 的 TypeError 等
                        $report['pan'] = array('ok' => false, 'errors' => array($ex->getMessage()));
                    }
                }
            }
        } catch (\Exception $e) {
            $report['ok']     = false;
            $report['reason'] = $e->getMessage();
            $report['errors']++;
        } catch (\Error $e) {   // PHP 7+ 的 TypeError 等
            $report['ok']     = false;
            $report['reason'] = $e->getMessage();
            $report['errors']++;
        }

        self::releaseLock();

        $report['finished'] = date('Y-m-d H:i:s');
        $report['ms']       = (int) round((microtime(true) - $t0) * 1000);
        if ($report['reason'] === '' && $report['errors'] > 0) {
            $report['reason'] = '部分接口失败，详见各源明细（其余条目已正常入库）。';
        }

        // 记录到 sync_log（后台「同步日志」「同步采集 → 最近记录」都读它）
        try {
            CacheStore::writeSyncLog('sync:' . $task, $report, (int) $report['inserted']);
        } catch (\Exception $e) {
            // 日志写不进去不能反过来影响同步结果
        }

        return $report;
    }

    /** 造一个适配器实例（类不存在返回 null，避免致命错误） */
    private static function makeAdapter($key)
    {
        $map = array('tmdb' => 'Adapters\\Tmdb', 'rawg' => 'Adapters\\Rawg', 'bangumi' => 'Adapters\\Bangumi');
        if (!isset($map[$key]) || !class_exists($map[$key])) {
            return null;
        }
        $cls = $map[$key];
        try {
            return new $cls();
        } catch (\Exception $e) {
            return null;
        }
    }

    /* ================================================================== */
    /* 计划任务（宝塔）辅助                                                */
    /* ================================================================== */

    /** 站点根目录（用于拼计划任务命令） */
    public static function root()
    {
        return realpath(__DIR__ . '/..');
    }

    /**
     * PHP 可执行文件路径 —— 用于拼「宝塔计划任务」里那条命令。
     * 注意：PHP_BINARY 在 FPM / CGI 下指向 php-fpm / php-cgi，**不能**拿来跑脚本，
     * 所以顺序是：① 命令行环境直接用当前 php；② 宝塔的 /www/server/php/<版本>/bin/php；
     * ③ 最后退回字面量 php（PATH 里有就行）。
     */
    public static function phpBinary()
    {
        if (PHP_SAPI === 'cli' && defined('PHP_BINARY') && PHP_BINARY) {
            return PHP_BINARY;
        }
        $ver = str_replace('.', '', substr(PHP_VERSION, 0, 3));   // 7.2 → 72
        if (is_dir('/www/server/php')) {
            $cand = glob('/www/server/php/' . $ver . '/bin/php');
            if (!$cand) {
                $cand = glob('/www/server/php/*/bin/php');
            }
            if ($cand) {
                sort($cand);
                return $cand[count($cand) - 1];
            }
        }
        if (defined('PHP_BINARY') && PHP_BINARY
            && strpos(PHP_BINARY, 'fpm') === false && strpos(PHP_BINARY, 'cgi') === false) {
            return PHP_BINARY;
        }
        return 'php';
    }

    /** 可直接粘进宝塔「计划任务」的命令 */
    public static function cronCommand($extra = '')
    {
        return self::phpBinary() . ' ' . self::root() . '/cron/sync_hourly.php' . ($extra ? ' ' . $extra : '');
    }

    private static function clamp($v, $min, $max, $fallback)
    {
        if (!is_numeric($v)) {
            return $fallback;
        }
        $v = (int) $v;
        if ($v < $min) { return $min; }
        if ($v > $max) { return $max; }
        return $v;
    }
}
