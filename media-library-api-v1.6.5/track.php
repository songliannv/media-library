<?php
/**
 * 点击埋点：静态内页用 <img src="/track?id=N&t=click"> 调用，自增 clicks，返回 204。
 *
 * 说明：本文件是**公开埋点端点**（浏览器会被动请求它），因此刻意不加
 *       "反直接访问" 守卫；同时也兼容旧静态页里的 /track.php?... 写法。
 *       它只做一次 UPDATE 并返回 204，不输出任何站点信息。
 */

require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/CacheStore.php';

use Core\CacheStore;

$id = (int) ((isset($_GET['id']) ? $_GET['id'] : 0));
if ($id > 0) {
    CacheStore::bumpClick($id);
}
http_response_code(204);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Length: 0');
exit;
