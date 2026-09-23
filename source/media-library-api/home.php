<?php
/**
 * 资料库主页：展示采集到的资料，支持按「API 调用次数 / 点击次数」排序 + 类型筛选 + 分页。
 * 路由：index.php 把根路径 / 交给本文件。
 */

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/core/Guard.php';
ml_guard_shield(__FILE__);
require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/CacheStore.php';
require_once __DIR__ . '/core/Site.php';   // ★ v1.6.4：下面用到 \Core\Site::flag()
require_once __DIR__ . '/core/Views.php';

use Core\Config;                          // ★ v1.6.4：全文用的是短类名 Config::，
use Core\Views;                           //    在全局命名空间下必须显式 use，
use Core\CacheStore;                      //    否则运行时报 Class 'Config' not found

$sortMap = ['api_calls' => 'api_calls', 'clicks' => 'clicks', 'updated_at' => 'updated_at', 'rating' => 'rating'];
$sort = (isset($sortMap[(isset($_GET['sort']) ? $_GET['sort'] : 'clicks')]) ? $sortMap[(isset($_GET['sort']) ? $_GET['sort'] : 'clicks')] : 'clicks');
$type = (isset($_GET['type']) ? $_GET['type'] : '');
$q    = trim((isset($_GET['q']) ? $_GET['q'] : ''));
$tag  = trim((isset($_GET['tag']) ? $_GET['tag'] : ''));   // ★ v1.8.8：首页标签云点进来的标签筛选
$page = max(1, (int) ((isset($_GET['page']) ? $_GET['page'] : 1)));
$per  = 24;

$rows  = CacheStore::listBySort($sort, $type, $page, $per, $q, $tag);
$total = CacheStore::countItems($type, $q, $tag);

/* 「最新更新」区块：只在首页（无筛选、无搜索、第一页）且后台开启时取数，
   避免每次翻页/筛选都多跑一次查询。 */
$latest = array();
if ($type === '' && $q === '' && $tag === '' && $page === 1
    && \Core\Site::flag(Config::sub('redirect', 'latest', 1))) {
    try {
        $latest = CacheStore::listBySort('updated_at', '', 1, 8, '');
    } catch (\Exception $e) {
        $latest = array();      // 取不到就不展示，不影响首页
    }
}

echo Views::homeHtml($rows, $sort, $type, $page, $total, $q, $per, $latest, $tag);
