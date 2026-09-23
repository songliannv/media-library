<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Site.php';

/**
 * PanSou 网盘搜索对接 + 「采集后自动补网盘下载地址」
 * ==================================================================
 * PanSou 是开源的网盘资源聚合搜索服务（聚合 TG 频道 + 各网盘插件），
 * 默认监听 8888 端口，对外只有两个接口：
 *   GET /api/health                       服务状态（频道数 / 插件数 / 版本）
 *   GET /api/search?kw=关键词&res=merge    搜索结果（merged_by_type 按网盘类型分组）
 *
 * 本类做四件事：
 *   1) 读接口配置（后台「接口配置」页写的 app_settings）
 *   2) test()     连通性 / 版本探测
 *   3) search()   搜索 + 结果缓存（同一关键词不重复请求）
 *   4) enrich()   采集入库后，用标题去搜，把命中的网盘链接写进 media_items.download_url
 *                 —— 写入策略三档：仅高度匹配自动写 / 搜到就写 / 一律进待确认队列
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / fn() / 返回类型 / 标量类型提示 等 7.0+ 语法）
 */
class PanSou
{
    const T_PENDING = 'pan_pending';
    const T_CACHE   = 'pan_search_cache';

    /** 网盘类型 code => 中文名（与后台「启用哪些网盘类型」的 options 一致） */
    public static function typeMap()
    {
        return array(
            'baidu'   => '百度',
            'aliyun'  => '阿里',
            'quark'   => '夸克',
            'guangya' => '光鸭',
            'tianyi'  => '天翼',
            'uc'      => 'UC',
            'mobile'  => '移动',
            '115'     => '115',
            'pikpak'  => 'PikPak',
            'xunlei'  => '迅雷',
            '123'     => '123',
            'magnet'  => '磁力',
            'ed2k'    => '电驴',
            'other'   => '其他',
        );
    }

    /** 写入下载地址时用的标签，例：quark -> 夸克下载 */
    public static function labelOf($code)
    {
        $m = self::typeMap();
        $name = isset($m[$code]) ? $m[$code] : $code;
        return $name . '下载';
    }

    /** 接口配置（含默认值） */
    public static function cfg()
    {
        $types = Settings::strToList(Config::sub('pansou', 'types', ''));
        return array(
            'enable' => Site::flag(Config::sub('pansou', 'enable', 0)),
            'mode'   => (string) Config::sub('pansou', 'mode', 'local'),
            'url'    => rtrim(trim((string) Config::sub('pansou', 'url', 'http://localhost:8888')), '/'),
            'user'   => (string) Config::sub('pansou', 'user', ''),
            'pass'   => (string) Config::sub('pansou', 'pass', ''),
            'line'   => (string) Config::sub('pansou', 'line', 'PanSou 主线路'),
            'types'  => $types,                       // 空 = 不过滤，全部类型都收
            'auto'   => Site::flag(Config::sub('pansou', 'auto', 1)),
            'policy' => (string) Config::sub('pansou', 'policy', 'exact'),
            'match'  => (int) Config::sub('pansou', 'match', 80),
            'max'    => (int) Config::sub('pansou', 'max', 5),
            'delay'  => (int) Config::sub('pansou', 'delay', 1000),
            'ttl'    => (int) Config::sub('pansou', 'ttl', 720),
        );
    }

    /* ================================================================ */
    /* 建表（首次使用自动创建，无需手工导 SQL）                          */
    /* ================================================================ */

    private static $ensured = false;

    public static function ensureTables()
    {
        if (self::$ensured) {
            return true;
        }
        self::$ensured = true;
        try {
            $pdo = DB::pdo();
            $pdo->exec('CREATE TABLE IF NOT EXISTS `' . self::T_CACHE . "` (
  `kw`         VARCHAR(191) NOT NULL,
  `payload`    MEDIUMTEXT,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`kw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec('CREATE TABLE IF NOT EXISTS `' . self::T_PENDING . "` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `title`      VARCHAR(512) NOT NULL DEFAULT '',
  `keyword`    VARCHAR(255) NOT NULL DEFAULT '',
  `links`      TEXT,
  `score`      INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /* ================================================================ */
    /* 请求                                                              */
    /* ================================================================ */

    /**
     * 调 PanSou。$path 形如 /api/health、/api/search。
     * 返回 array(http, json, raw)；json 为 null 表示返回体不是 JSON。
     */
    private static function request($path, array $query = array(), $timeout = 10)
    {
        $cfg = self::cfg();
        if ($cfg['url'] === '') {
            throw new \Exception('PanSou 接口地址未配置');
        }
        if (!preg_match('#^https?://#i', $cfg['url'])) {
            throw new \Exception('PanSou 接口地址必须以 http:// 或 https:// 开头');
        }
        $url = $cfg['url'] . $path;
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $raw  = '';
        $code = 0;
        $err  = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_USERAGENT, 'media-library-api/1.6');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            if ($cfg['user'] !== '') {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $cfg['user'] . ':' . $cfg['pass']);
            }
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = (string) curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                throw new \Exception('连不上 PanSou（' . $err . '）：请确认服务已启动、地址与端口正确');
            }
            $raw = (string) $body;
        } else {
            $ctx = stream_context_create(array('http' => array(
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header'  => "User-Agent: media-library-api/1.6\r\n",
            )));
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                throw new \Exception('连不上 PanSou：请确认服务已启动、地址与端口正确（建议开启 PHP 的 curl 扩展）');
            }
            $raw  = (string) $body;
            $code = 200;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = null;
        }
        return array('http' => $code, 'json' => $json, 'raw' => $raw);
    }

    /** 连通性 / 版本探测（后台「接口配置」页的「测试连接」按钮） */
    public static function test()
    {
        $cfg = self::cfg();
        $t0  = microtime(true);
        $out = array(
            'url'      => $cfg['url'],
            'mode'     => $cfg['mode'],
            'line'     => $cfg['line'],
            'ok'       => false,
            'http'     => 0,
            'channels' => 0,
            'plugins'  => 0,
            'version'  => '',
            'message'  => '',
            'ms'       => 0,
        );
        try {
            $r = self::request('/api/health', array(), 8);
            $out['http'] = $r['http'];
            $out['ms']   = (int) round((microtime(true) - $t0) * 1000);
            if ($r['json'] === null) {
                $out['message'] = '接口有响应，但返回的不是 JSON（HTTP ' . $r['http'] . '）。请确认地址指向 PanSou 服务根，而不是某个静态页。';
                return $out;
            }
            $j = $r['json'];
            if (isset($j['status']) && $j['status'] !== 'ok') {
                $out['message'] = '服务状态异常：' . (string) $j['status'];
                return $out;
            }
            $out['ok']       = true;
            $out['channels'] = isset($j['channels']) ? count((array) $j['channels']) : 0;
            $out['plugins']  = isset($j['plugin_count']) ? (int) $j['plugin_count']
                             : (isset($j['plugins']) ? count((array) $j['plugins']) : 0);
            $out['version']  = isset($j['version']) ? (string) $j['version'] : '';
            $out['message']  = '连通正常' . ($out['channels'] > 0 ? '（频道 ' . $out['channels'] . ' 个）' : '')
                             . ($out['plugins'] > 0 ? '（插件 ' . $out['plugins'] . ' 个）' : '');
        } catch (\Exception $e) {
            $out['ms']      = (int) round((microtime(true) - $t0) * 1000);
            $out['message'] = $e->getMessage();
        }
        return $out;
    }

    /* ================================================================ */
    /* 搜索（带缓存）                                                     */
    /* ================================================================ */

    /**
     * 搜索网盘资源。返回归一化结构：
     *   array('keyword' => 'xxx', 'cached' => bool, 'types' => array(code => array(links)))
     * 每条形如 array(url, password, note, datetime)
     */
    public static function search($kw, $force = false)
    {
        $cfg = self::cfg();
        $kw  = trim((string) $kw);
        if ($kw === '') {
            throw new \Exception('搜索关键词为空');
        }
        self::ensureTables();

        if (!$force && $cfg['ttl'] > 0) {
            $hit = self::cacheGet($kw, $cfg['ttl']);
            if ($hit !== null) {
                $hit['cached'] = true;
                return $hit;
            }
        }

        $q = array('kw' => $kw, 'res' => 'merge');
        if ($cfg['types']) {
            $q['cloud_types'] = implode(',', $cfg['types']);
        }
        $r = self::request('/api/search', $q, 20);
        $j = $r['json'];
        if ($j === null) {
            // 兼容旧版 fork：参数名是 keyword 而不是 kw
            $q2 = array('keyword' => $kw);
            if ($cfg['types']) {
                $q2['cloud_types'] = implode(',', $cfg['types']);
            }
            $r2 = self::request('/api/search', $q2, 20);
            $j  = $r2['json'];
        }
        if ($j === null) {
            throw new \Exception('PanSou 返回的不是 JSON（HTTP ' . $r['http'] . '），请检查接口地址是否正确');
        }
        if (isset($j['code']) && (int) $j['code'] >= 400) {
            throw new \Exception('PanSou 报错：' . (isset($j['message']) ? $j['message'] : ('code ' . $j['code'])));
        }

        $types = self::normalize($j);
        if ($cfg['types']) {
            foreach (array_keys($types) as $code) {
                if (!in_array($code, $cfg['types'], true) && $code !== 'other') {
                    unset($types[$code]);
                }
            }
        }
        $total = 0;
        foreach ($types as $list) {
            $total += count($list);
        }
        $out = array('keyword' => $kw, 'cached' => false, 'total' => $total, 'types' => $types);

        if ($cfg['ttl'] > 0) {
            self::cachePut($kw, $out);
        }
        return $out;
    }

    /**
     * 把 PanSou 的两种返回结构统一成 code => links
     *   merged_by_type: { "baidu": [ {url,password,note,datetime}, ... ] }
     *   results:        [ { title, links:[ {type,url,password} ] } ]
     */
    private static function normalize(array $j)
    {
        $out = array();
        if (isset($j['merged_by_type']) && is_array($j['merged_by_type'])) {
            foreach ($j['merged_by_type'] as $code => $list) {
                $code = strtolower(trim((string) $code));
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $it) {
                    if (!is_array($it) || empty($it['url'])) {
                        continue;
                    }
                    $out[$code][] = array(
                        'url'      => (string) $it['url'],
                        'password' => isset($it['password']) ? (string) $it['password'] : '',
                        'note'     => isset($it['note']) ? (string) $it['note'] : '',
                        'datetime' => isset($it['datetime']) ? (string) $it['datetime'] : '',
                    );
                }
            }
        }
        if (isset($j['results']) && is_array($j['results'])) {
            foreach ($j['results'] as $r) {
                if (!is_array($r) || empty($r['links']) || !is_array($r['links'])) {
                    continue;
                }
                $note = isset($r['title']) ? (string) $r['title'] : '';
                foreach ($r['links'] as $l) {
                    if (!is_array($l) || empty($l['url'])) {
                        continue;
                    }
                    $code = isset($l['type']) ? strtolower(trim((string) $l['type'])) : 'other';
                    if ($code === '') {
                        $code = 'other';
                    }
                    $out[$code][] = array(
                        'url'      => (string) $l['url'],
                        'password' => isset($l['password']) ? (string) $l['password'] : '',
                        'note'     => $note,
                        'datetime' => isset($r['datetime']) ? (string) $r['datetime'] : '',
                    );
                }
            }
        }
        return $out;
    }

    /* ================================================================ */
    /* 匹配打分                                                          */
    /* ================================================================ */

    /** 资源标题与条目标题的相似度（0~100） */
    public static function score($title, $note, $year = '')
    {
        $a = self::norm($title);
        $b = self::norm($note);
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        $pct = 0.0;
        similar_text($a, $b, $pct);
        // 包含关系是最可靠的信号：资源标题里通常带「1080P / 全集 / 更新至xx」等后缀
        if (strpos($b, $a) !== false) {
            $pct = max($pct, 88.0);
        }
        $year = trim((string) $year);
        if ($year !== '' && strlen($year) >= 4 && strpos($note, $year) !== false) {
            $pct = min(100.0, $pct + 6.0);
        }
        return (int) round($pct);
    }

    /** 归一化：小写、去掉空白与常见分隔符，便于比对 */
    private static function norm($s)
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower((string) $s, 'UTF-8') : strtolower((string) $s);
        $s = str_replace(array(' ', '　', "\t", '.', '·', '-', '_', ':', '：', '(', ')', '（', '）',
                               '[', ']', '【', '】', '{', '}', '/', '\\', '|', '＋', '+'), '', $s);
        return trim($s);
    }

    /**
     * 从搜索结果里挑出「每种网盘最优的一条」。
     * 返回 array(links => array(array(label,url,type,password,note,score)), best => 最高分)
     */
    public static function pick(array $types, $title, $year = '', $minMatch = 0)
    {
        $cfg    = self::cfg();
        $labels = self::typeMap();
        $picked = array();
        $best   = 0;
        foreach ($types as $code => $list) {
            $bestOne = null;
            foreach ($list as $it) {
                $sc = self::score($title, isset($it['note']) ? $it['note'] : '', $year);
                if ($bestOne === null || $sc > $bestOne['score']) {
                    $bestOne = array('score' => $sc, 'it' => $it);
                }
            }
            if ($bestOne === null) {
                continue;
            }
            if ($bestOne['score'] > $best) {
                $best = $bestOne['score'];
            }
            if ($bestOne['score'] < $minMatch) {
                continue;
            }
            $it   = $bestOne['it'];
            $url  = (string) $it['url'];
            if ($it['password'] !== '') {
                $url .= (strpos($url, '?') === false ? '?pwd=' : '&pwd=') . rawurlencode($it['password']);
            }
            $picked[] = array(
                'label'    => isset($labels[$code]) ? ($labels[$code] . '下载') : ($code . '下载'),
                'type'     => $code,
                'url'      => $url,
                'password' => (string) $it['password'],
                'note'     => (string) $it['note'],
                'score'    => (int) $bestOne['score'],
            );
        }
        usort($picked, array(__CLASS__, 'cmpScore'));
        if (count($picked) > $cfg['max']) {
            $picked = array_slice($picked, 0, $cfg['max']);
        }
        return array('links' => $picked, 'best' => (int) $best);
    }

    public static function cmpScore($a, $b)
    {
        if ($a['score'] === $b['score']) {
            return 0;
        }
        return ($a['score'] > $b['score']) ? -1 : 1;
    }

    /* ================================================================ */
    /* 写入下载地址                                                      */
    /* ================================================================ */

    /** 读出条目现有的下载地址（统一成数组） */
    public static function readLinks($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $j = json_decode($raw, true);
        if (is_array($j)) {
            $out = array();
            foreach ($j as $it) {
                if (!is_array($it)) {
                    continue;
                }
                if (empty($it['url'])) {
                    continue;
                }
                $out[] = array(
                    'label' => isset($it['label']) ? (string) $it['label'] : '下载',
                    'url'   => (string) $it['url'],
                );
            }
            return $out;
        }
        // 兼容旧版纯文本多行：一行一个地址
        $out = array();
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = array('label' => '下载', 'url' => $line);
            }
        }
        return $out;
    }

    /**
     * 把 links 追加进某条目的下载地址（按 label 去重，不覆盖站长手工填的）。
     * 返回实际新增的条数。
     */
    public static function attach($itemId, array $links)
    {
        $itemId = (int) $itemId;
        if ($itemId < 1 || !$links) {
            return 0;
        }
        $pdo = DB::pdo();
        $st  = $pdo->prepare('SELECT `download_url` FROM `media_items` WHERE `id` = ?');
        $st->execute(array($itemId));
        $raw = $st->fetchColumn();
        if ($raw === false) {
            return 0;
        }
        $cur    = self::readLinks($raw);
        $labels = array();
        foreach ($cur as $c) {
            $labels[] = $c['label'];
        }
        $added = 0;
        foreach ($links as $l) {
            if (!isset($l['label']) || !isset($l['url']) || $l['url'] === '') {
                continue;
            }
            if (in_array($l['label'], $labels, true)) {
                continue;                       // 该网盘已有链接，保留站长的
            }
            $cur[]    = array('label' => (string) $l['label'], 'url' => (string) $l['url']);
            $labels[] = (string) $l['label'];
            $added++;
        }
        if ($added < 1) {
            return 0;
        }
        $up = $pdo->prepare('UPDATE `media_items` SET `download_url` = ?, `updated_at` = NOW() WHERE `id` = ?');
        $up->execute(array(json_encode($cur), $itemId));
        return $added;
    }

    /* ================================================================ */
    /* 采集后自动补地址                                                  */
    /* ================================================================ */

    /**
     * 主入口：给「还没有下载地址」的条目补网盘链接。
     * $ids   指定条目（空 = 自动挑最近没有下载地址的）
     * $limit 本次最多处理多少条（每条约 1 次 PanSou 请求）
     */
    public static function enrich($ids = array(), $limit = 15, $force = false)
    {
        $cfg = self::cfg();
        $rep = array(
            'ok' => true, 'enabled' => $cfg['enable'], 'policy' => $cfg['policy'],
            'checked' => 0, 'written' => 0, 'queued' => 0, 'nomatch' => 0,
            'links' => 0, 'errors' => array(), 'requests' => 0, 'items' => array(),
            'ms' => 0,
        );
        $t0 = microtime(true);
        if (!$cfg['enable']) {
            $rep['ok'] = false;
            $rep['reason'] = '「接口配置」里没有启用 PanSou';
            return $rep;
        }
        if ($cfg['url'] === '') {
            $rep['ok'] = false;
            $rep['reason'] = '没有填 PanSou 接口地址';
            return $rep;
        }
        self::ensureTables();

        $rows = self::candidates($ids, $limit);
        if (!$rows) {
            $rep['reason'] = '没有需要补地址的条目';
            $rep['ms'] = (int) round((microtime(true) - $t0) * 1000);
            return $rep;
        }

        foreach ($rows as $row) {
            $rep['checked']++;
            $id     = (int) $row['id'];
            $title  = (string) $row['title'];
            $year   = isset($row['year']) ? (string) $row['year'] : '';
            $result = null;
            $used   = $title;
            try {
                $result = self::search($title, $force);
                $rep['requests']++;
                $pick   = self::pick($result['types'], $title, $year, $cfg['match']);
                // 中文标题搜不到高分时，用原始标题再试一次
                $orig = isset($row['original_title']) ? trim((string) $row['original_title']) : '';
                if ($pick['best'] < $cfg['match'] && $orig !== '' && $orig !== $title) {
                    $r2 = self::search($orig, $force);
                    $rep['requests']++;
                    $p2 = self::pick($r2['types'], $orig, $year, $cfg['match']);
                    if ($p2['best'] > $pick['best']) {
                        $pick = $p2;
                        $used = $orig;
                    }
                }
            } catch (\Exception $e) {
                $rep['errors'][] = $title . '：' . $e->getMessage();
                continue;
            }

            $n = count($pick['links']);
            if ($n < 1) {
                $rep['nomatch']++;
                $rep['items'][] = array('id' => $id, 'title' => $title, 'action' => '无匹配', 'score' => $pick['best'], 'n' => 0);
                continue;
            }

            $highEnough = ($pick['best'] >= $cfg['match']);
            $action = '';
            if ($cfg['policy'] === 'pending') {
                $action = 'queue';
            } elseif ($cfg['policy'] === 'write') {
                $action = 'write';
            } else {                    // exact：仅高度匹配自动写
                $action = $highEnough ? 'write' : 'queue';
            }

            if ($action === 'write') {
                $added = self::attach($id, $pick['links']);
                $rep['written']++;
                $rep['links'] += $added;
                $action = '已写入 ' . $added . ' 个';
            } else {
                self::pendingAdd($id, $title, $used, $pick['links'], $pick['best']);
                $rep['queued']++;
                $action = '待确认';
            }
            $rep['items'][] = array(
                'id' => $id, 'title' => $title, 'action' => $action,
                'score' => $pick['best'], 'n' => $n,
            );

            if ($cfg['delay'] > 0) {
                usleep($cfg['delay'] * 1000);
            }
        }

        $rep['ms'] = (int) round((microtime(true) - $t0) * 1000);
        return $rep;
    }

    /** 挑出待补地址的条目 */
    private static function candidates($ids, $limit)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 15;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $pdo = DB::pdo();
        $cols = '`id`,`title`,`original_title`,`year`,`type`,`source`,`overview`';
        if ($ids) {
            $clean = array();
            foreach ($ids as $x) {
                $x = (int) $x;
                if ($x > 0) {
                    $clean[] = $x;
                }
            }
            $clean = array_slice(array_unique($clean), 0, $limit);
            if (!$clean) {
                return array();
            }
            $in = implode(',', $clean);
            $st = $pdo->query('SELECT ' . $cols . ' FROM `media_items` WHERE `id` IN (' . $in . ')');
            return $st->fetchAll();
        }
        $sql = 'SELECT ' . $cols . ' FROM `media_items`'
             . " WHERE (`download_url` IS NULL OR `download_url` = '' OR `download_url` = '[]')"
             . ' ORDER BY `id` DESC LIMIT ' . $limit;
        $st = $pdo->query($sql);
        return $st->fetchAll();
    }

    /* ================================================================ */
    /* 待确认队列                                                        */
    /* ================================================================ */

    public static function pendingAdd($itemId, $title, $keyword, array $links, $score)
    {
        $pdo = DB::pdo();
        $del = $pdo->prepare('DELETE FROM `' . self::T_PENDING . '` WHERE `item_id` = ?');
        $del->execute(array((int) $itemId));          // 同一条目只留最新一次结果
        $ins = $pdo->prepare('INSERT INTO `' . self::T_PENDING . '` (`item_id`,`title`,`keyword`,`links`,`score`,`created_at`) VALUES (?,?,?,?,?,NOW())');
        $ins->execute(array((int) $itemId, (string) $title, (string) $keyword, json_encode($links), (int) $score));
        return (int) $pdo->lastInsertId();
    }

    public static function pendingList($limit = 50)
    {
        try {
            self::ensureTables();
            $limit = (int) $limit;
            if ($limit < 1 || $limit > 200) {
                $limit = 50;
            }
            $st = DB::pdo()->query('SELECT * FROM `' . self::T_PENDING . '` ORDER BY `id` DESC LIMIT ' . $limit);
            $rows = $st->fetchAll();
        } catch (\Exception $e) {
            return array();
        }
        $out = array();
        foreach ($rows as $r) {
            $links = json_decode(isset($r['links']) ? $r['links'] : '[]', true);
            $out[] = array(
                'id'      => (int) $r['id'],
                'item_id' => (int) $r['item_id'],
                'title'   => isset($r['title']) ? (string) $r['title'] : '',
                'keyword' => isset($r['keyword']) ? (string) $r['keyword'] : '',
                'score'   => isset($r['score']) ? (int) $r['score'] : 0,
                'links'   => is_array($links) ? $links : array(),
                'time'    => isset($r['created_at']) ? (string) $r['created_at'] : '',
            );
        }
        return $out;
    }

    public static function pendingCount()
    {
        try {
            self::ensureTables();
            return (int) DB::pdo()->query('SELECT COUNT(*) FROM `' . self::T_PENDING . '`')->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** 采纳一条：写进下载地址并出队。返回新增链接数 */
    public static function pendingApply($id)
    {
        $pdo = DB::pdo();
        $st  = $pdo->prepare('SELECT * FROM `' . self::T_PENDING . '` WHERE `id` = ?');
        $st->execute(array((int) $id));
        $row = $st->fetch();
        if (!$row) {
            throw new \Exception('这条待确认记录已经不存在了');
        }
        $links = json_decode(isset($row['links']) ? $row['links'] : '[]', true);
        if (!is_array($links)) {
            $links = array();
        }
        $added = self::attach((int) $row['item_id'], $links);
        $del = $pdo->prepare('DELETE FROM `' . self::T_PENDING . '` WHERE `id` = ?');
        $del->execute(array((int) $id));
        return $added;
    }

    public static function pendingDrop($id)
    {
        $st = DB::pdo()->prepare('DELETE FROM `' . self::T_PENDING . '` WHERE `id` = ?');
        $st->execute(array((int) $id));
        return (int) $st->rowCount();
    }

    /* ================================================================ */
    /* 搜索缓存                                                          */
    /* ================================================================ */

    private static function cacheGet($kw, $ttlMinutes)
    {
        try {
            $st = DB::pdo()->prepare('SELECT `payload`,`created_at` FROM `' . self::T_CACHE . '` WHERE `kw` = ?');
            $st->execute(array(self::keyOf($kw)));
            $row = $st->fetch();
            if (!$row) {
                return null;
            }
            $age = time() - strtotime((string) $row['created_at']);
            if ($age > ((int) $ttlMinutes * 60)) {
                return null;
            }
            $j = json_decode((string) $row['payload'], true);
            if (!is_array($j) || !isset($j['types'])) {
                return null;
            }
            return array('keyword' => $kw, 'cached' => true, 'total' => isset($j['total']) ? (int) $j['total'] : 0, 'types' => $j['types']);
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function cachePut($kw, array $out)
    {
        try {
            $pdo = DB::pdo();
            $del = $pdo->prepare('DELETE FROM `' . self::T_CACHE . '` WHERE `kw` = ?');
            $del->execute(array(self::keyOf($kw)));
            $ins = $pdo->prepare('INSERT INTO `' . self::T_CACHE . '` (`kw`,`payload`,`created_at`) VALUES (?,?,NOW())');
            $ins->execute(array(self::keyOf($kw), json_encode(array('total' => $out['total'], 'types' => $out['types']))));
        } catch (\Exception $e) {
            // 缓存失败不影响主流程
        }
    }

    /** 缓存键（截断到 180 字符内，兼容无 mbstring 的环境） */
    private static function keyOf($kw)
    {
        $kw = (string) $kw;
        if (function_exists('mb_substr')) {
            return mb_substr($kw, 0, 180, 'UTF-8');
        }
        return substr($kw, 0, 180);
    }

    public static function cacheClear()
    {
        try {
            self::ensureTables();
            return (int) DB::pdo()->exec('DELETE FROM `' . self::T_CACHE . '`');
        } catch (\Exception $e) {
            return 0;
        }
    }
}
