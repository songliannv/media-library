<?php
/**
 * 媒资资料库 · 每日热门同步（适合放在宝塔计划任务里每天跑一次）
 *   php /www/wwwroot/你的站点/cron/sync_trending.php
 *
 * 与 cron/sync_hourly.php 是同一套引擎（core/Sync.php），区别只有默认参数：
 *   本脚本：本周窗口 + 每源 40 条 + 全量重建静态页（每天跑一次，把站点整体对齐）
 *   hourly ：今日窗口 + 每源 20 条 + 只生成新增条目的静态页（每小时轻量跑）
 *
 * 常用参数： --limit=40  --window=week  --order=both  --force  --no-build  --quiet
 * 想了解每个参数的含义，执行： php cron/sync_hourly.php --help
 *
 * 安全：本文件只允许命令行执行；用网址访问会被 core/Guard.php 挡成 404。
 */

require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

if (PHP_SAPI !== 'cli') {
    ml_guard_deny();
}

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Sync.php';
require_once __DIR__ . '/../core/StaticGen.php';

use Core\Sync;
use Core\StaticGen;

$quiet = in_array('--quiet', isset($argv) ? $argv : array(), true);

$opts = array(
    'task'   => 'trending',
    'force'  => in_array('--force', isset($argv) ? $argv : array(), true),
    'window' => 'week',
    'limit'  => 40,
    'build'  => 1,          // 先按条目生成，末尾再做一次全量，保证整站静态页齐全
);
if (in_array('--no-build', isset($argv) ? $argv : array(), true)) {
    $opts['build'] = 0;
}
foreach ((isset($argv) ? $argv : array()) as $a) {
    if (strpos($a, '--limit=') === 0)  { $opts['limit']  = (int) substr($a, 8); }
    if (strpos($a, '--window=') === 0) { $opts['window'] = substr($a, 9); }
    if (strpos($a, '--order=') === 0)  { $opts['order']  = substr($a, 8); }
}

$r = Sync::run($opts);

$built = 0;
if (!empty($opts['build'])) {
    try {
        $built = StaticGen::buildAll();          // 每日一次：全量重建（含老条目）
    } catch (\Exception $ex) {
        fwrite(STDERR, 'build static failed: ' . $ex->getMessage() . "\n");
    }
}

echo sprintf(
    "Synced: fetched=%d inserted=%d skipped=%d requests=%d | static pages=%d%s\n",
    (int) $r['fetched'], (int) $r['inserted'], (int) $r['skipped_dup'],
    (int) $r['requests'], (int) $built, $r['reason'] !== '' ? ('  note=' . $r['reason']) : ''
);

exit($r['ok'] ? 0 : 1);
