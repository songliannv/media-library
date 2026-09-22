<?php
/**
 * 静态页生成入口（宝塔计划任务可调用）：
 *   php /www/wwwroot/你的站点/cron/build_static.php
 * 也可由后台「重新生成静态页」按钮通过 /api/v1/admin/build 触发（直接调 Core\StaticGen）。
 */

/* 安全守卫：本文件只应被命令行（cron）或入口引入。
   若有人直接用网址请求本文件（会触发采集/重建），一律返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../core/CacheStore.php';
require_once __DIR__ . '/../core/Views.php';
require_once __DIR__ . '/../core/StaticGen.php';

use Core\StaticGen;

$n = StaticGen::buildAll();
fwrite(STDOUT, "built static pages: " . $n . PHP_EOL);
