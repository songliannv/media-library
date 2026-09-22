<?php
/**
 * 刷新本地已过期条目（宝塔计划任务调用，建议每天执行）
 *   php /www/wwwroot/your-site/cron/sync_refresh.php
 * 仅处理 refresh_at 过期（或为空）的条目，每次最多 200 条，避免触发上游限流。
 */

/* 安全守卫：本文件只应被命令行（cron）或入口引入。
   若有人直接用网址请求本文件（会触发采集/重建），一律返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../core/Http.php';
require_once __DIR__ . '/../core/CacheStore.php';
require_once __DIR__ . '/../core/Categories.php';
require_once __DIR__ . '/../core/Views.php';
require_once __DIR__ . '/../core/StaticGen.php';
require_once __DIR__ . '/../adapters/Adapter.php';
require_once __DIR__ . '/../adapters/Tmdb.php';
require_once __DIR__ . '/../adapters/Rawg.php';
require_once __DIR__ . '/../adapters/Bangumi.php';

use Core\Config;
use Core\DB;
use Core\CacheStore;
use Core\StaticGen;
use Adapters\Tmdb;
use Adapters\Rawg;
use Adapters\Bangumi;

$map     = ['tmdb' => Tmdb::class, 'rawg' => Rawg::class, 'bangumi' => Bangumi::class];
$enabled = Config::get('adapters', ['tmdb']);
$limit   = 200;
$refreshed = 0;
$log = [];

$adapters = [];
foreach ($enabled as $e) { if (isset($map[$e])) { $cls = $map[$e]; $adapters[$e] = new $cls(); } }

$st = DB::pdo()->prepare("SELECT id,source,source_id,type FROM media_items WHERE refresh_at IS NULL OR refresh_at < NOW() ORDER BY updated_at ASC LIMIT ?");
$st->bindValue(1, $limit, \PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$ttl = (int) Config::sub('cache', 'ttl_days', 7);

foreach ($rows as $r) {
    $a = (isset($adapters[$r['source']]) ? $adapters[$r['source']] : null);
    if (!$a) { continue; }
    try {
        if ($r['source'] === 'tmdb') {
            $raw = $a->fetchRaw($r['type'], $r['source_id']);
            if (!empty($raw['id'])) {
                CacheStore::put('tmdb', $r['type'], $r['source_id'], $a->normalize($raw, $r['type']), $raw, $ttl);
                $refreshed++;
            }
        } else {
            $norm = $a->detail($r['type'], $r['source_id']);
            if ($norm) {
                CacheStore::put($r['source'], $r['type'], $r['source_id'], $norm, $norm, $ttl);
                $refreshed++;
            }
        }
    } catch (\Exception $ex) {
        $log[] = ['id' => $r['id'], 'status' => 'error', 'msg' => $ex->getMessage()];
    }
}

CacheStore::writeSyncLog('refresh', $log, $refreshed);

$built = 0;
try {
    $built = StaticGen::buildAll();
} catch (\Exception $ex) {
    fwrite(STDERR, 'build static failed: ' . $ex->getMessage() . "\n");
}

echo "Refreshed $refreshed items, built $built static pages\n";
