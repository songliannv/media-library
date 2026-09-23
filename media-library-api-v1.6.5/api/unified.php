<?php
namespace Api;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

require_once __DIR__ . '/../core/Sync.php';

use Core\Config;
use Core\Json;
use Core\CacheStore;
use Core\DB;
use Core\Categories;
use Core\Sync;
use Adapters\Tmdb;
use Adapters\Rawg;
use Adapters\Bangumi;

/**
 * 统一 API + 后台管理端点
 *
 * 公开：
 *   GET /api/v1/search?q=&type=&source=&page=   跨源搜索
 *   GET /api/v1/items?type=&page=&q=            本地资料库列表
 *   GET /api/v1/item/{source}/{type}/{id}      单条原始数据
 *   GET /api/v1/trending                       各源热门汇总
 *   GET /api/v1/genres                         全部类型标签
 *
 * 后台（需 X-Admin-Token / ?token=）：
 *   GET  /api/v1/admin/stats
 *   GET  /api/v1/admin/items?page=&type=&q=
 *   GET  /api/v1/admin/item/{id}
 *   POST /api/v1/admin/item            （手动新增，body 为归一化字段）
 *   PUT  /api/v1/admin/item/{id}       （编辑）
 *   DELETE /api/v1/admin/item/{id}
 *   GET  /api/v1/admin/sync_log
 *   GET  /api/v1/admin/sync_plan        （采集参数 + 各源官方限制对比 + 最近记录 + 计划任务命令）
 *   POST /api/v1/admin/sync             （立即触发一次采集；body 可带 force/limit/window/order/sources/dry）
 *   POST /api/v1/admin/check_links      （下载链接失效检查：单条 / 批量）
 *   GET  /api/v1/admin/settings         （系统设置：令牌 / API Key / 数据源 / 网盘类型）
 *   POST /api/v1/admin/settings         （保存设置，写入 app_settings 表）
 *   POST /api/v1/admin/settings_reset   （清除某项/全部覆盖，回退 config.php）
 *   POST /api/v1/admin/settings_test    （探测上游 Key 是否可用）
 *   GET  /api/v1/admin/req_list|req_stats （资源请求列表 / 统计）
 *   POST /api/v1/admin/req_save|req_del   （资源请求：改状态、写回复、删除）
 *   GET  /api/v1/admin/user_list          （注册用户列表）
 *   POST /api/v1/admin/user_save|user_del （注册用户：启用/禁用/审核/重置密码/删除）
 */
class Unified
{
    public static function handle($path)
    {
        $p    = trim($path, '/');            // api/v1/items
        $rest = substr($p, strlen('api/v1/'));
        $segA = explode('/', $rest);
        $seg  = isset($segA[0]) ? $segA[0] : '';

        if ($seg === 'admin') { self::admin($rest); return; }

        $q = self::query();

        if ($seg === 'search') {
            $items = CacheStore::searchItems(
                (isset($q['q']) ? $q['q'] : ''), (isset($q['type']) ? $q['type'] : ''), (isset($q['source']) ? $q['source'] : ''), (int) ((isset($q['page']) ? $q['page'] : 1)), 20
            );
            Json::ok(['page' => (int) ((isset($q['page']) ? $q['page'] : 1)), 'items' => $items]);
        }

        if ($seg === 'items') {
            $page = (int) ((isset($q['page']) ? $q['page'] : 1));
            Json::ok([
                'page'  => $page,
                'total' => CacheStore::countItems((isset($q['type']) ? $q['type'] : ''), (isset($q['q']) ? $q['q'] : '')),
                'items' => CacheStore::listItems((isset($q['type']) ? $q['type'] : ''), $page, 20, (isset($q['q']) ? $q['q'] : '')),
            ]);
        }

        if ($seg === 'item') {
            $parts = explode('/', $rest);   // [item, source, type, id]
            CacheStore::bumpApiCall((isset($parts[1]) ? $parts[1] : ''), (isset($parts[2]) ? $parts[2] : ''), (isset($parts[3]) ? $parts[3] : ''));
            $row = CacheStore::getRaw((isset($parts[1]) ? $parts[1] : ''), (isset($parts[2]) ? $parts[2] : ''), (isset($parts[3]) ? $parts[3] : ''));
            if ($row === null) { Json::error(404, 'not found'); }
            Json::ok($row);
        }

        if ($seg === 'metric') {
            $segB = explode('/', $rest);
            $id   = (int) (isset($segB[1]) ? $segB[1] : 0);
            Json::ok(CacheStore::metric($id));
        }

        if ($seg === 'trending') {
            $out = [];
            foreach (self::adapters() as $a) { $out = array_merge($out, $a->trending('week')); }
            Json::ok(['items' => $out]);
        }

        if ($seg === 'genres') {
            Json::ok(['items' => CacheStore::allGenres()]);
        }

        if ($seg === 'categories') {
            $schema = Categories::schema();
            $schema['pan_types'] = Config::panTypes();
            Json::ok($schema);
        }

        Json::error(404, 'unknown endpoint');
    }

    /** @return AdapterInterface[] */
    private static function adapters()
    {
        $enabled = Config::get('adapters', ['tmdb']);
        $map = ['tmdb' => Tmdb::class, 'rawg' => Rawg::class, 'bangumi' => Bangumi::class];
        $out = [];
        foreach ($enabled as $e) {
            if (isset($map[$e])) { $cls = $map[$e]; $out[] = new $cls(); }
        }
        return $out;
    }

    private static function admin($rest)
    {
        self::requireAdmin();
        $parts  = explode('/', $rest);   // [admin, action, id?]
        $action = (isset($parts[1]) ? $parts[1] : '');
        $method = $_SERVER['REQUEST_METHOD'];

        if ($action === 'stats' && $method === 'GET') {
            Json::ok(CacheStore::stats());
        }

        if ($action === 'items' && $method === 'GET') {
            $q    = self::query();
            $page = (int) ((isset($q['page']) ? $q['page'] : 1));
            $type = (isset($q['type']) ? $q['type'] : '');
            $qs   = (isset($q['q']) ? $q['q'] : '');
            Json::ok([
                'page'  => $page,
                'total' => CacheStore::countItems($type, $qs),
                'items' => CacheStore::listItems($type, $page, 30, $qs),
            ]);
        }

        if ($action === 'item') {
            $id = (int) ((isset($parts[2]) ? $parts[2] : 0));
            $body = self::query();
            if ($method === 'POST' && $id === 0) {
                $newId = CacheStore::insertManual($body);
                Json::ok(['id' => $newId]);
            }
            if ($method === 'PUT') {
                CacheStore::updateById($id, $body);
                Json::ok(['id' => $id]);
            }
            if ($method === 'DELETE') {
                CacheStore::deleteById($id);
                Json::ok(['id' => $id]);
            }
            if ($method === 'GET') {
                $row = CacheStore::getRowById($id);
                if (!$row) { Json::error(404, 'not found'); }
                $row['link_checks'] = CacheStore::getLinkChecks($id);
                Json::ok($row);
            }
        }

        if ($action === 'sync_log' && $method === 'GET') {
            Json::ok(['items' => CacheStore::syncLog()]);
        }

        if ($action === 'sync' && $method === 'POST') {
            $body = self::query();
            $opts = array(
                'task'  => 'manual',
                'force' => !empty($body['force']),
                'dry'   => !empty($body['dry']),
            );
            if (isset($body['limit'])  && $body['limit'] !== '')  { $opts['limit']  = (int) $body['limit']; }
            if (isset($body['window']) && $body['window'] !== '') { $opts['window'] = (string) $body['window']; }
            if (isset($body['order'])  && $body['order'] !== '')  { $opts['order']  = (string) $body['order']; }
            if (isset($body['build']))                            { $opts['build']  = (int) $body['build']; }
            if (isset($body['sources']) && is_array($body['sources'])) { $opts['sources'] = $body['sources']; }

            /* 整个采集过程兜底：即使上游超时 / 单条写入失败，也只返回 JSON，
               不会把 PHP 致命错误页（含服务器路径）丢给浏览器 —— 那正是
               「点同步没反应」的元凶（前端 json() 解析失败 → 连提示都不弹）。 */
            try {
                $report = Sync::run($opts);
            } catch (\Exception $e) {
                Json::error(500, '同步失败：' . $e->getMessage());
            } catch (\Error $e) {
                Json::error(500, '同步中断：' . $e->getMessage());
            }
            $report['synced'] = (int) $report['inserted'];   // 兼容旧前端字段
            Json::ok($report);
        }

        if ($action === 'sync_plan' && $method === 'GET') {
            $plan = Sync::plan();
            $plan['recent'] = Sync::recent(12);
            Json::ok($plan);
        }

        if ($action === 'build' && $method === 'POST') {
            $body = self::query();
            if (!empty($body['id'])) {
                $ok = \Core\StaticGen::buildOne((int) $body['id']);
                Json::ok(['built' => $ok ? 1 : 0, 'id' => (int) $body['id']]);
            }
            $n = \Core\StaticGen::buildAll();
            Json::ok(['built' => $n]);
        }

        // 链接失效检查：传 id 检查单条，否则批量检查（每批 limit 条，返回 remaining）
        if ($action === 'check_links' && $method === 'POST') {
            $body = self::query();
            $id   = !empty($body['id']) ? (int) $body['id'] : 0;
            if ($id > 0) {
                $row     = CacheStore::getRowById($id);
                $results = $row ? self::checkItemLinks($row) : [];
                Json::ok(['item_id' => $id, 'results' => $results]);
            }
            $limit   = max(1, min(200, (int) ((isset($body['limit']) ? $body['limit'] : 40))));
            $rows    = CacheStore::itemsWithLinks($limit);
            $ok = $dead = $checked = 0;
            $start   = time();
            $budget  = 20; // 秒：单次请求的时间预算，避免 PHP/网关超时
            foreach ($rows as $row) {
                if ($checked > 0 && (time() - $start) >= $budget) { break; }
                $results  = self::checkItemLinks($row);
                $checked++;
                foreach ($results as $x) { if (!empty($x['ok'])) { $ok++; } else { $dead++; } }
            }
            $remaining = CacheStore::countPendingLinkChecks();
            Json::ok(['checked' => $checked, 'ok' => $ok, 'dead' => $dead, 'remaining' => $remaining]);
        }

        // 读取某条目的检测结果
        if ($action === 'link_checks' && $method === 'GET') {
            $id = (int) ((isset($parts[2]) ? $parts[2] : 0));
            Json::ok(['items' => CacheStore::getLinkChecks($id)]);
        }

        // 列表页状态汇总
        if ($action === 'link_status_map' && $method === 'GET') {
            Json::ok(['map' => CacheStore::linkStatusMap()]);
        }

        /* ---- 系统设置：后台「系统设置」页读写 ---- */
        if ($action === 'settings' && $method === 'GET') {
            Json::ok(self::settingsView());
        }

        if ($action === 'settings' && $method === 'POST') {
            $body = self::query();
            $err  = self::saveSettings($body);
            if ($err !== '') { Json::error(400, $err); }
            $view = self::settingsView();
            Json::ok([
                'saved' => true,
                'token' => $view['items']['admin_token']['value'],
                'items' => $view['items'],
            ]);
        }

        // 恢复某项设置：留空即回退到 config.php；传 all=1 清空全部覆盖
        if ($action === 'settings_reset' && $method === 'POST') {
            $body = self::query();
            if (!empty($body['all'])) {
                \Core\Settings::clear();
            } else {
                $k = (string) ((isset($body['key']) ? $body['key'] : ''));
                if (!in_array($k, \Core\Settings::keys(), true)) { Json::error(400, '未知设置项'); }
                \Core\Settings::clear($k);
            }
            $view = self::settingsView();
            Json::ok(['reset' => true, 'token' => $view['items']['admin_token']['value'], 'items' => $view['items']]);
        }

        // 测试上游 Key 是否可用（TMDB / RAWG / Bangumi）
        if ($action === 'settings_test' && $method === 'POST') {
            $body = self::query();
            $kind = (string) ((isset($body['kind']) ? $body['kind'] : ''));
            Json::ok(self::testUpstream($kind, (isset($body['key']) ? (string) $body['key'] : '')));
        }

        /* ---- 站点设置：图片上传（LOGO / icon / 群二维码） ---- */
        if (($action === 'site_upload' || $action === 'upload') && $method === 'POST') {
            require_once __DIR__ . '/../core/Site.php';
            $kind = isset($_POST['kind']) ? (string) $_POST['kind'] : 'site';
            $file = null;
            foreach (array('file', 'image', 'logo', 'favicon', 'qr') as $fn) {
                if (isset($_FILES[$fn])) { $file = $_FILES[$fn]; break; }
            }
            try {
                Json::ok(\Core\Site::upload($file, $kind));
            } catch (\Exception $e) {
                Json::error(400, $e->getMessage());
            } catch (\Error $e) {
                Json::error(500, '上传失败：' . $e->getMessage());
            }
        }

        /* ---- 站点设置：SEO 实时预览（拿站内最新一条真实数据套模板） ---- */
        if ($action === 'seo_preview' && $method === 'GET') {
            require_once __DIR__ . '/../core/Site.php';
            $data = array(
                'id' => 1, 'title' => '示例作品', 'type_label' => '电影',
                'year' => '2026', 'overview' => '这是一段示例简介，用于预览 SEO 描述的实际效果。',
            );
            try {
                $st  = \Core\DB::pdo()->query('SELECT `id`,`title`,`type`,`year`,`overview` FROM `media_items` ORDER BY `id` DESC LIMIT 1');
                $row = $st->fetch();
                if ($row) {
                    $data = array(
                        'id'         => (int) $row['id'],
                        'title'      => (string) $row['title'],
                        'type_label' => \Core\Categories::label((string) $row['type']),
                        'year'       => (string) $row['year'],
                        'overview'   => (string) $row['overview'],
                    );
                }
            } catch (\Exception $e) {
                // 用上面的示例数据兜底
            }
            Json::ok(array(
                'base'   => \Core\Site::baseUrl(),
                'home'   => \Core\Site::seo('home'),
                'list'   => \Core\Site::seo('list', array('type_label' => '电影', 'sort_label' => '热度', 'page' => 1)),
                'item'   => \Core\Site::seo('item', $data),
                'robots' => \Core\Site::robotsTxt(),
                'sample' => $data,
            ));
        }

        /* ---- 站点设置：PanSou 接口 ---- */
        if ($action === 'pansou_state' && $method === 'GET') {
            require_once __DIR__ . '/../core/PanSou.php';
            Json::ok(array(
                'cfg'     => \Core\PanSou::cfg(),
                'types'   => \Core\PanSou::typeMap(),
                'pending' => \Core\PanSou::pendingCount(),
            ));
        }

        if ($action === 'pansou_test' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            try {
                Json::ok(\Core\PanSou::test());
            } catch (\Exception $e) {
                Json::error(400, $e->getMessage());
            } catch (\Error $e) {
                Json::error(500, '测试失败：' . $e->getMessage());
            }
        }

        // 试搜：直接问 PanSou 能搜到什么（不写库、不影响已有数据）
        if ($action === 'pansou_search' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            $body = self::query();
            $kw   = trim((string) ((isset($body['kw']) ? $body['kw'] : '')));
            if ($kw === '') { Json::error(400, '请输入要搜索的关键词'); }
            try {
                $r     = \Core\PanSou::search($kw, !empty($body['refresh']));
                $tm    = \Core\PanSou::typeMap();
                $types = array();
                foreach ($r['types'] as $code => $list) {
                    $top = array();
                    foreach (array_slice($list, 0, 3) as $it) {
                        $top[] = array('note' => $it['note'], 'url' => $it['url']);
                    }
                    $types[] = array(
                        'type'  => $code,
                        'label' => isset($tm[$code]) ? $tm[$code] : $code,
                        'count' => count($list),
                        'top'   => $top,
                    );
                }
                Json::ok(array(
                    'keyword' => $kw,
                    'cached'  => !empty($r['cached']),
                    'total'   => isset($r['total']) ? (int) $r['total'] : 0,
                    'types'   => $types,
                    'picked'  => \Core\PanSou::pick($r['types'], $kw, '', 0),
                ));
            } catch (\Exception $e) {
                Json::error(400, $e->getMessage());
            } catch (\Error $e) {
                Json::error(500, '搜索失败：' . $e->getMessage());
            }
        }

        if ($action === 'pansou_cache_clear' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            Json::ok(array('cleared' => \Core\PanSou::cacheClear()));
        }

        /* ---- 站点设置：手动跑一次「补网盘下载地址」 ---- */
        if ($action === 'enrich_run' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            $body = self::query();
            $ids  = array();
            if (isset($body['ids'])) {
                $ids = is_array($body['ids']) ? $body['ids'] : preg_split('/[,\s]+/', (string) $body['ids']);
            }
            $limit = isset($body['limit']) ? (int) $body['limit'] : 15;
            @set_time_limit(300);
            try {
                Json::ok(\Core\PanSou::enrich($ids, $limit, !empty($body['refresh'])));
            } catch (\Exception $e) {
                Json::error(500, '补地址失败：' . $e->getMessage());
            } catch (\Error $e) {
                Json::error(500, '补地址中断：' . $e->getMessage());
            }
        }

        /* ---- 站点设置：待确认队列 ---- */
        if ($action === 'pending_list' && $method === 'GET') {
            require_once __DIR__ . '/../core/PanSou.php';
            Json::ok(array(
                'list'  => \Core\PanSou::pendingList(60),
                'count' => \Core\PanSou::pendingCount(),
            ));
        }

        if ($action === 'pending_apply' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            $body = self::query();
            $id   = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            try {
                Json::ok(array('id' => $id, 'added' => \Core\PanSou::pendingApply($id)));
            } catch (\Exception $e) {
                Json::error(400, $e->getMessage());
            }
        }

        if ($action === 'pending_drop' && $method === 'POST') {
            require_once __DIR__ . '/../core/PanSou.php';
            $body = self::query();
            $id   = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            Json::ok(array('id' => $id, 'dropped' => \Core\PanSou::pendingDrop($id)));
        }

        /* ---------------------------------------------------------------- */
        /* 注册用户与资源请求（后台「资源请求」页）                            */
        /*   数据表由 Core\User::ensureTables() 自动创建，升级后无需手工导 SQL */
        /* ---------------------------------------------------------------- */
        if ($action === 'req_stats' && $method === 'GET') {
            require_once __DIR__ . '/../core/User.php';
            Json::ok(array(
                'stats'   => \Core\User::stats(),
                'counts'  => \Core\User::statusCounts(0),
                'status'  => \Core\User::reqStatusText(),
                'ustatus' => \Core\User::userStatusText(),
                'cfg'     => \Core\User::cfg(),
                'tables'  => \Core\User::tablesOk(),
            ));
        }

        if ($action === 'req_list' && $method === 'GET') {
            require_once __DIR__ . '/../core/User.php';
            $q = self::query();
            $r = \Core\User::listRequests(
                (int) ((isset($q['page']) ? $q['page'] : 1)), 30, 0,
                (string) ((isset($q['status']) ? $q['status'] : '')),
                (string) ((isset($q['q']) ? $q['q'] : ''))
            );
            Json::ok(array(
                'items'  => $r['items'],
                'total'  => $r['total'],
                'status' => \Core\User::reqStatusText(),
            ));
        }

        if ($action === 'req_save' && $method === 'POST') {
            require_once __DIR__ . '/../core/User.php';
            $b  = self::query();
            $id = (int) ((isset($b['id']) ? $b['id'] : 0));
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            $err = \Core\User::saveRequest(
                $id,
                (string) ((isset($b['status']) ? $b['status'] : '')),
                (string) ((isset($b['admin_note']) ? $b['admin_note'] : ''))
            );
            if ($err !== '') { Json::error(400, $err); }
            Json::ok(array('id' => $id));
        }

        if ($action === 'req_del' && $method === 'POST') {
            require_once __DIR__ . '/../core/User.php';
            $b  = self::query();
            $id = (int) ((isset($b['id']) ? $b['id'] : 0));
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            Json::ok(array('id' => $id, 'deleted' => \Core\User::deleteRequest($id)));
        }

        if ($action === 'user_list' && $method === 'GET') {
            require_once __DIR__ . '/../core/User.php';
            $q = self::query();
            $r = \Core\User::listUsers(
                (int) ((isset($q['page']) ? $q['page'] : 1)), 30,
                (string) ((isset($q['q']) ? $q['q'] : '')),
                (string) ((isset($q['status']) ? $q['status'] : ''))
            );
            Json::ok(array(
                'items'  => $r['items'],
                'total'  => $r['total'],
                'ustatus' => \Core\User::userStatusText(),
            ));
        }

        if ($action === 'user_save' && $method === 'POST') {
            require_once __DIR__ . '/../core/User.php';
            $b  = self::query();
            $id = (int) ((isset($b['id']) ? $b['id'] : 0));
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            /* 状态与密码各自独立：只重置密码时不会顺手把「已禁用」改成「正常」 */
            if (array_key_exists('status', $b) && $b['status'] !== '' && $b['status'] !== null) {
                $err = \Core\User::saveUser($id, $b['status']);
                if ($err !== '') { Json::error(400, $err); }
            }
            $pwd = (string) ((isset($b['new_password']) ? $b['new_password'] : ''));
            if ($pwd !== '') {
                $perr = \Core\User::resetPassword($id, $pwd);
                if ($perr !== '') { Json::error(400, $perr); }
            }
            Json::ok(array('id' => $id, 'password_reset' => ($pwd !== '')));
        }

        if ($action === 'user_del' && $method === 'POST') {
            require_once __DIR__ . '/../core/User.php';
            $b  = self::query();
            $id = (int) ((isset($b['id']) ? $b['id'] : 0));
            if ($id < 1) { Json::error(400, '缺少记录 id'); }
            Json::ok(array('id' => $id, 'deleted' => \Core\User::deleteUser($id)));
        }

        /* ---- 安全防护状态：后台「安全防护」页用 ---- */
        if ($action === 'security' && $method === 'GET') {
            require_once __DIR__ . '/../core/Guard.php';
            $root = dirname(__DIR__);
            Json::ok(array(
                'version' => defined('ML_APP_VERSION') ? ML_APP_VERSION : '',
                'guard'   => ml_guard_status($root),
            ));
        }

        Json::error(404, 'admin: unknown');
    }

    /**
     * 旧版「一把梭」同步已由 Core\Sync 统一接管（限流 / 去重 / 只取最热）。
     * 后台按钮 → POST /api/v1/admin/sync，计划任务 → cron/sync_hourly.php，两者同一套逻辑。
     */

    /* ------------------------------------------------------------------ */
    /* 系统设置（后台可增删改：令牌 / API Key / 数据源 / 网盘类型）        */
    /* ------------------------------------------------------------------ */

    /**
     * 组装设置页需要的数据：每项给出「生效值 / 后台覆盖值 / 配置文件里的值」。
     * 生效值 = 后台覆盖值非空 ? 后台 : config.php
     */
    private static function settingsView()
    {
        $tableOk = \Core\Settings::ensureTable();   // 首次访问自动建表，免手工导 SQL

        $items = array();

        // 三个文本项：与 config.php 的二级键对应
        $map = array(
            'admin_token'  => array('admin', 'token'),
            'tmdb_api_key' => array('tmdb', 'api_key'),
            'rawg_api_key' => array('rawg', 'api_key'),
        );
        foreach ($map as $k => $p) {
            $db   = (string) \Core\Settings::get($k, '');
            $file = (string) Config::baseSub($p[0], $p[1], '');
            $items[$k] = array(
                'value'    => $db !== '' ? $db : $file,   // 当前真正生效的值
                'db'       => $db,                        // 后台保存的值（空 = 未覆盖）
                'file'     => $file,                      // config.php 里的值
                'override' => $db !== '',
            );
        }

        // 数据源开关
        $dbAd   = \Core\Settings::strToList(\Core\Settings::get('adapters', ''));
        $fileAd = Config::base('adapters', array());
        if (!is_array($fileAd)) { $fileAd = array(); }
        $items['adapters'] = array(
            'value'    => $dbAd ? $dbAd : array_values($fileAd),
            'db'       => $dbAd,
            'file'     => array_values($fileAd),
            'override' => (bool) $dbAd,
        );

        // 网盘类型
        $dbPan = \Core\Settings::strToList(\Core\Settings::get('pan_types', ''));
        $items['pan_types'] = array(
            'value'    => $dbPan ? $dbPan : Config::panTypes(),
            'db'       => $dbPan,
            'file'     => self::filePanTypes(),
            'override' => (bool) $dbPan,
        );

        // 采集（同步）参数：生效值 = 后台覆盖 > config.php 的 sync 段 > 内置默认
        $syncDefs = Sync::defaults();
        foreach (\Core\Settings::syncMap() as $k => $child) {
            $db   = (string) \Core\Settings::get($k, '');
            $file = Config::baseSub('sync', $child, null);
            $file = ($file === null) ? '' : (string) $file;
            $def  = array_key_exists($child, $syncDefs) ? (string) $syncDefs[$child] : '';
            $items[$k] = array(
                'value'    => $db !== '' ? $db : ($file !== '' ? $file : $def),
                'db'       => $db,
                'file'     => $file,
                'default'  => $def,
                'override' => $db !== '',
            );
        }

        return array(
            'items'      => $items,
            'meta'       => \Core\Settings::schema(),
            'table'      => \Core\Settings::TABLE,
            'table_ok'   => $tableOk,
            'adapters'   => array('tmdb', 'rawg', 'bangumi'),
            'config_php' => 'config/config.php',
        );
    }

    /** config/pan_types.php 里的原始列表（不含后台覆盖），供设置页对比展示 */
    private static function filePanTypes()
    {
        $file = __DIR__ . '/../config/pan_types.php';
        if (!file_exists($file)) { return array(); }
        $list = require $file;
        if (!is_array($list)) { return array(); }
        $out = array();
        foreach ($list as $x) {
            if (is_string($x) && $x !== '') { $out[] = $x; }
        }
        return $out;
    }

    /** 保存设置；返回空字符串表示成功，否则返回错误文案 */
    private static function saveSettings(array $body)
    {
        $set = array();

        // 1) 后台令牌：非空时必须够长，避免设成 1 个字符被撞开
        if (array_key_exists('admin_token', $body)) {
            $t = trim((string) $body['admin_token']);
            if ($t !== '' && strlen($t) < 6) {
                return '后台管理令牌至少 6 位；留空则沿用 config.php 里的值。';
            }
            $set['admin_token'] = $t;
        }

        if (array_key_exists('tmdb_api_key', $body)) {
            $set['tmdb_api_key'] = trim((string) $body['tmdb_api_key']);
        }
        if (array_key_exists('rawg_api_key', $body)) {
            $set['rawg_api_key'] = trim((string) $body['rawg_api_key']);
        }

        // 2) 数据源：勾了就存；一个都没勾视为无效（否则同步/采集会无事可做）
        if (array_key_exists('adapters', $body)) {
            $list = \Core\Settings::strToList($body['adapters']);
            $allow = array('tmdb', 'rawg', 'bangumi');
            $pick = array();
            foreach ($list as $x) {
                if (in_array($x, $allow, true)) { $pick[] = $x; }
            }
            if (!$pick) {
                return '请至少启用一个数据源（TMDB / RAWG / Bangumi）。';
            }
            $set['adapters'] = \Core\Settings::listToStr($pick);
        }

        // 3) 网盘类型：允许留空（= 回退 config/pan_types.php）
        if (array_key_exists('pan_types', $body)) {
            $set['pan_types'] = \Core\Settings::listToStr($body['pan_types']);
        }

        // 4) 采集（同步）参数：范围校验，避免填了离谱的值把上游配额打爆
        $ranges = array(
            'sync_limit'        => array(1, 200, '每个源每次条数'),
            'sync_max_pages'    => array(1, 5, '每个源最多翻页'),
            'sync_max_requests' => array(1, 60, '单次请求上限'),
            'sync_min_votes'    => array(0, 1000000, '最低投票数'),
            'sync_interval'     => array(0, 1440, '最小间隔(分钟)'),
            /* 站点设置 · SEO */
            'seo_item_desc_len' => array(0, 500, '内页描述截取字数'),
            'seo_sitemap_size'  => array(100, 50000, 'sitemap 收录条数'),
            /* 站点设置 · 接口配置 */
            'pansou_match'      => array(0, 100, '标题匹配相似度(%)'),
            'pansou_max'        => array(1, 10, '每条最多网盘链接数'),
            'pansou_delay'      => array(0, 10000, '补地址请求间隔(毫秒)'),
            'pansou_ttl'        => array(0, 43200, '搜索结果缓存(分钟)'),
            /* 站点设置 · 资源请求 */
            'req_daily'         => array(0, 999, '每人每天最多提交条数'),
            'req_mintitle'      => array(1, 60, '资源名称最少字数'),
        );
        $enums = array(
            'sync_window'          => array(array('day', 'week'), '时间窗口'),
            'sync_order'           => array(array('hot', 'popular', 'both'), '取值口径'),
            'sync_dedupe'          => array(array('skip', 'update'), '重复处理'),
            'sync_skip_same_title' => array(array('0', '1'), '同名是否跳过'),
            'sync_build'           => array(array('0', '1'), '是否生成静态页'),
            /* 站点设置 · 基础设置 */
            'site_name_hide'       => array(array('0', '1'), '隐藏网站名称'),
            /* 站点设置 · SEO */
            'seo_sitemap'          => array(array('0', '1'), '是否生成 sitemap'),
            /* 站点设置 · 跳转与扫码 / 前端模板 */
            'redirect_mode'        => array(array('jump_scan', 'jump_only', 'scan_only'), 'PC 端访问方式'),
            'redirect_mobile'      => array(array('0', '1'), '手机端是否同样处理'),
            'tpl_switch'           => array(array('classic', 'simple'), '前台模板'),
            'tpl_simple_mode'      => array(array('light', 'dark', 'auto'), '前台明暗模式'),
            'list_rank_style'      => array(array('grid', 'list'), '榜单样式'),
            'list_latest'          => array(array('0', '1'), '最新列表'),
            /* 站点设置 · 接口配置 */
            'pansou_enable'        => array(array('0', '1'), '启用 PanSou'),
            'pansou_mode'          => array(array('local', 'remote'), '部署方式'),
            'pansou_auto'          => array(array('0', '1'), '采集后自动补地址'),
            'pansou_policy'        => array(array('exact', 'write', 'pending'), '补地址写入策略'),
            /* 站点设置 · 资源请求 */
            'req_open'             => array(array('0', '1'), '是否开放注册'),
            'req_logon'            => array(array('0', '1'), '提交是否要求登录'),
            'req_audit'            => array(array('0', '1'), '注册是否需要审核'),
            'req_verify'           => array(array('0', '1'), '注册验证码'),
            'req_guest_view'       => array(array('0', '1'), '游客可否浏览列表'),
            'req_public'           => array(array('0', '1'), '是否展示大家都在找'),
            'req_contact'          => array(array('0', '1'), '是否收集联系方式'),
            'req_nav'              => array(array('0', '1'), '导航是否显示入口'),
        );

        /* 站点设置 5 组 + 采集参数，合并成一张表后走同一套「白名单 + 范围 + 枚举」规则，
           这样以后加设置项只要改 Core\Settings，不必再动这里。 */
        $allMaps = \Core\Settings::groupMaps();
        $allMaps['sync'] = \Core\Settings::syncMap();
        $flat = array();
        foreach ($allMaps as $gmap) {
            foreach ($gmap as $k => $child) {
                $flat[$k] = $child;
            }
        }
        foreach ($flat as $k => $child) {
            if (!array_key_exists($k, $body)) { continue; }
            $v = is_array($body[$k]) ? '' : trim((string) $body[$k]);
            if ($v === '') { $set[$k] = ''; continue; }          // 留空 = 回退默认/配置文件
            if (isset($ranges[$k])) {
                if (!is_numeric($v)) { return $ranges[$k][2] . '必须是数字。'; }
                $n = (int) $v;
                if ($n < $ranges[$k][0] || $n > $ranges[$k][1]) {
                    return $ranges[$k][2] . '需在 ' . $ranges[$k][0] . ' ~ ' . $ranges[$k][1] . ' 之间。';
                }
                $set[$k] = (string) $n;
                continue;
            }
            if (isset($enums[$k])) {
                if (!in_array($v, $enums[$k][0], true)) {
                    return $enums[$k][1] . '取值不合法。';
                }
                $set[$k] = $v;
                continue;
            }
            // 接口地址必须是个正经网址，不然等补地址时才发现就晚了
            if ($k === 'pansou_url' && !preg_match('#^https?://#i', $v)) {
                return 'PanSou 接口地址必须以 http:// 或 https:// 开头。';
            }
            $set[$k] = $v;
        }

        if (!$set) { return '没有需要保存的设置项。'; }

        try {
            \Core\Settings::set($set);
        } catch (\Exception $e) {
            return '保存失败：' . $e->getMessage();
        }
        return '';
    }

    /** 轻量探测上游 Key 是否可用（用 LinkChecker，失败即回退 GET） */
    private static function testUpstream($kind, $keyOverride = '')
    {
        $urls = array(
            'tmdb'    => 'https://api.themoviedb.org/3/configuration?api_key=%s',
            'rawg'    => 'https://api.rawg.io/api/games?page_size=1&key=%s',
            'bangumi' => 'https://api.bgm.tv/',
        );
        if (!isset($urls[$kind])) {
            return array('ok' => false, 'msg' => '未知的数据源');
        }

        if ($kind === 'bangumi') {
            $url = $urls[$kind];
        } else {
            $key = trim($keyOverride);
            if ($key === '') {
                $key = (string) Config::sub($kind === 'tmdb' ? 'tmdb' : 'rawg', 'api_key', '');
            }
            if ($key === '') {
                return array('ok' => false, 'code' => 0, 'msg' => '尚未填写 ' . strtoupper($kind) . ' API Key');
            }
            $url = sprintf($urls[$kind], urlencode($key));
        }

        $r = \Core\LinkChecker::check($url, 10);
        $msg = '';
        if (!$r['ok']) {
            if ((int) $r['code'] === 401) {
                $msg = 'Key 无效或被拒绝（HTTP 401）';
            } elseif ((int) $r['code'] === 0) {
                $msg = '连不上该站点：' . $r['msg'] . '（常见于服务器在境内且未放行境外请求）';
            } else {
                $msg = 'HTTP ' . $r['code'];
            }
        }
        return array(
            'kind' => $kind,
            'ok'   => (bool) $r['ok'],
            'code' => (int) $r['code'],
            'msg'  => $msg,
        );
    }

    /** 检测单条目全部下载链接并落库，返回结果数组 */
    private static function checkItemLinks(array $row)
    {
        $links = self::parseDownloadsForCheck((string) ((isset($row['download_url']) ? $row['download_url'] : '')));
        if (!$links) { return []; }
        $results = [];
        foreach ($links as $l) {
            $url = trim((string) ((isset($l['url']) ? $l['url'] : '')));
            if ($url === '') { continue; }
            $r = \Core\LinkChecker::check($url);
            $results[] = [
                'label' => (string) ((isset($l['label']) ? $l['label'] : '下载')),
                'url'   => $url,
                'ok'    => (bool) $r['ok'],
                'code'  => (int) $r['code'],
                'msg'   => (string) $r['msg'],
            ];
        }
        CacheStore::saveLinkChecks((int) $row['id'], $results);
        return $results;
    }

    /** 解析下载链接（新 JSON 数组 / 旧纯文本多行），供检测用 */
    private static function parseDownloadsForCheck($raw)
    {
        $raw = trim($raw);
        if ($raw === '') { return []; }
        if ($raw[0] === '[') {
            $a = json_decode($raw, true);
            if (is_array($a)) {
                $out = [];
                foreach ($a as $e) {
                    if (is_array($e)) { $out[] = ['label' => (isset($e['label']) ? $e['label'] : '下载'), 'url' => (isset($e['url']) ? $e['url'] : '')]; }
                    elseif (is_string($e)) { $out[] = ['label' => '下载', 'url' => $e]; }
                }
                return $out;
            }
        }
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), function ($x) { return $x !== ''; });
        $out = [];
        foreach ($lines as $u) {
            $out[] = ['label' => '下载', 'url' => $u];
        }
        return $out;
    }

    private static function requireAdmin()
    {
        $token  = Config::sub('admin', 'token', '');
        $header = (isset($_SERVER['HTTP_X_ADMIN_TOKEN']) ? $_SERVER['HTTP_X_ADMIN_TOKEN'] : '');
        $q      = self::query();
        $provided = $header !== '' ? $header : ((isset($q['token']) ? $q['token'] : ''));
        if ($token === '' || $provided !== $token) { Json::error(401, 'unauthorized'); }
    }

    private static function query()
    {
        $m = $_SERVER['REQUEST_METHOD'];
        $qsRaw = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str(($qsRaw === null ? '' : $qsRaw), $qs);
        if ($m === 'GET') { return $qs; }
        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        return array_merge($qs, $body);
    }
}
