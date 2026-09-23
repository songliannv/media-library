<?php
/**
 * 媒资资料库 · 每小时采集（宝塔「计划任务」调用）
 * ==================================================================
 * 作用：按「各源官方要求」限流，只取**最热门 / 最多人看**的内容，
 *       已存在的条目**直接跳过**，并为新增条目生成静态页。
 *
 * 宝塔面板添加计划任务：
 *   面板 →「计划任务」→ 添加任务
 *     任务类型：Shell 脚本
 *     任务名称：媒资库-每小时采集
 *     执行周期：N 小时 → 1 小时（或选「每小时」）
 *     脚本内容： php /www/wwwroot/你的站点/cron/sync_hourly.php
 *   （命令里 php 用绝对路径更稳，例如 /www/server/php/72/bin/php；
 *     后台「同步采集」页有现成的、可直接复制的命令。）
 *
 * 命令行参数（都可省略）：
 *   --limit=20        每个源本次最多入库多少条
 *   --window=day      今日 hot 榜（day|week）
 *   --order=both      hot|popular|both
 *   --sources=tmdb,hongguoduanju
 *   --interval=55     与上次同步的最小间隔（分钟），0 = 不限
 *   --max-requests=8  单次上游请求总数硬闸
 *   --force           忽略「最小间隔」强制跑一次
 *   --no-build        不生成静态页（默认会为新增条目生成）
 *   --dry             只探测接口、不入库（排错用）
 *   --quiet           只输出一行结果
 *   --help            显示帮助
 *
 * 退出码：0 = 成功（含"因间隔跳过"）；1 = 失败（上游全挂 / 配置错）。
 *
 * 安全：本文件只允许命令行执行；用网址访问会被 core/Guard.php 挡成 404。
 */

require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

/* 公网访问（非 CLI）直接 404 —— 防止有人远程触发采集烧掉上游配额 */
if (PHP_SAPI !== 'cli') {
    ml_guard_deny();
}

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Sync.php';

use Core\Sync;

/* ---------------- 解析参数 ---------------- */
$opts  = array('task' => 'hourly');
$quiet = false;
$argv  = isset($argv) ? $argv : array();
$args  = array_slice($argv, 1);

foreach ($args as $a) {
    if ($a === '--force') {
        $opts['force'] = true;
    } elseif ($a === '--quiet' || $a === '-q') {
        $quiet = true;
    } elseif ($a === '--dry') {
        $opts['dry'] = true;
    } elseif ($a === '--no-build') {
        $opts['build'] = 0;
    } elseif ($a === '--build') {
        $opts['build'] = 1;
    } elseif (strpos($a, '--limit=') === 0) {
        $opts['limit'] = (int) substr($a, 8);
    } elseif (strpos($a, '--window=') === 0) {
        $opts['window'] = substr($a, 9);
    } elseif (strpos($a, '--order=') === 0) {
        $opts['order'] = substr($a, 8);
    } elseif (strpos($a, '--interval=') === 0) {
        $opts['interval'] = (int) substr($a, 11);
    } elseif (strpos($a, '--max-requests=') === 0) {
        $opts['max_requests'] = (int) substr($a, 15);
    } elseif (strpos($a, '--sources=') === 0) {
        $list = array();
        foreach (explode(',', substr($a, 10)) as $x) {
            $x = trim($x);
            if ($x !== '') { $list[] = $x; }
        }
        $opts['sources'] = $list;
    } elseif ($a === '--help' || $a === '-h') {
        echo "媒资资料库 · 每小时采集\n\n";
        echo "用法： php cron/sync_hourly.php [选项]\n\n";
        echo "  --limit=20        每个源本次最多入库多少条（默认取后台设置，20）\n";
        echo "  --window=day      今日热榜（day|week，默认取后台设置）\n";
        echo "  --order=both      hot（最热）/ popular（最多人看）/ both（默认）\n";
        echo "  --sources=tmdb,hongguoduanju   只跑指定源（默认跑后台启用的全部源）\n";
        echo "  --interval=55     与上次同步的最小间隔（分钟），0 = 不限\n";
        echo "  --max-requests=8  单次上游请求总数硬闸\n";
        echo "  --force           忽略最小间隔，强制跑一次\n";
        echo "  --no-build        不为新增条目生成静态页\n";
        echo "  --dry             只探测接口、不入库\n";
        echo "  --quiet           只输出一行结果\n";
        exit(0);
    }
}

/* ---------------- 跑一次 ---------------- */
$r = Sync::run($opts);

if ($quiet) {
    echo sprintf(
        "%s  inserted=%d skipped=%d fetched=%d requests=%d built=%d ms=%d%s\n",
        $r['ok'] ? 'OK' : 'FAIL',
        (int) $r['inserted'], (int) $r['skipped_dup'], (int) $r['fetched'],
        (int) $r['requests'], (int) $r['built'], (int) $r['ms'],
        $r['reason'] !== '' ? ('  note=' . $r['reason']) : ''
    );
    exit($r['ok'] ? 0 : 1);
}

echo "==== 媒资资料库 · 采集开始 " . date('Y-m-d H:i:s') . " ====\n";
echo sprintf("参数：每源上限 %d 条 · 时间窗 %s · 口径 %s · 最低投票 %d · 重复处理 %s\n",
    (int) $r['params']['limit'], $r['params']['window'], $r['params']['order'],
    (int) $r['params']['min_votes'], $r['params']['dedupe']);
echo "说明：按各源官方限制限流（TMDB 0.3s/请求、RAWG 1.5s、红果短剧需配置），只取最热与最多人看的榜。\n\n";

if (!empty($r['skipped'])) {
    echo "[跳过] " . $r['reason'] . "\n";
    echo "本次未采集。（要立刻跑请加 --force）\n";
    exit(0);
}

foreach ($r['sources'] as $s) {
    echo "── " . (isset($s['label']) ? $s['label'] : $s['source']) . "\n";
    if ($s['endpoints']) {
        foreach ($s['endpoints'] as $ep) {
            echo sprintf("   · %-26s 抓到 %d 条%s\n",
                $ep['path'],
                (int) $ep['count'],
                $ep['error'] !== '' ? ('   ✗ ' . $ep['error']) : ''
            );
        }
    }
    echo sprintf("   新增 %d · 重复跳过 %d · 低于票数门槛 %d · 请求 %d 次\n",
        (int) $s['inserted'], (int) $s['skipped_dup'], (int) $s['skipped_low'], (int) $s['requests']);
    if (isset($s['msg']) && $s['msg'] !== '') {
        echo "   ⚠ " . $s['msg'] . "\n";
    }
    echo "\n";
}

echo sprintf("汇总：抓到 %d 条 → 新增 %d 条，重复跳过 %d 条，低于票数门槛 %d 条；上游请求 %d 次，静态页 %d 个，耗时 %.1fs\n",
    (int) $r['fetched'], (int) $r['inserted'], (int) $r['skipped_dup'],
    (int) $r['skipped_low'], (int) $r['requests'], (int) $r['built'], $r['ms'] / 1000);

if ($r['reason'] !== '') {
    echo "提示：" . $r['reason'] . "\n";
}
if (!$r['ok']) {
    echo "结果：失败（详见上面的提示）\n";
}
echo "==== 采集结束 " . date('Y-m-d H:i:s') . " ====\n";

exit($r['ok'] ? 0 : 1);
