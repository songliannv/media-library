<?php
/**
 * 媒资资料库 · 环境自检 / 一键体检（check.php）
 * ------------------------------------------------------------------
 * 用途：部署到宝塔后，浏览器打开 https://你的域名/check.php 即可一次性检查
 *       环境 → 权限 → 配置 → 数据库 → 表结构 → 数据 → 路由/伪静态 →
 *       静态页 → 上游源连通 → 链接健康 → 代码语法。
 *
 * 访问方式：
 *   /check.php                 查看 HTML 报告（推荐）
 *   /check.php?format=json     输出机器可读 JSON（方便脚本/CI 调用）
 *   /check.php?token=你的后台令牌   带鉴权访问
 *   /check.php?nohttp=1        跳过「路由自测 / 上游连通」的网络请求（更快）
 *   /check.php?write=1         额外做一次真实写库测试（插入后立即删除）
 *
 * 安全：v1.4.0 起**未通过令牌校验一律返回 404**（不显示"需要令牌"，避免告诉扫描器本页存在）；
 *       令牌取「后台 → 系统设置」里设置的值，或 config.php 的 admin.token，两者都认；
 *       站点尚未设置任何令牌时（刚装完），只放行 127.0.0.1 / 内网地址。
 *       报告中所有密钥/口令均已打码，仅显示首尾字符。
 *       ★ 确认无误后请删除本文件（或重命名），彻底消除暴露面。
 */

/* 先记下服务器**原本**的 display_errors —— 本页自己会临时打开它方便排错，
   若不先记录，下面第 1 组就会把自己打开的值误报成「服务器配置有问题」。 */
$ORIG_DISPLAY_ERRORS = (string) ini_get('display_errors');
@ini_set('display_errors', '1');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$T0        = microtime(true);
$IS_JSON   = (((isset($_GET['format']) ? $_GET['format'] : '')) === 'json');
$NO_HTTP   = !empty($_GET['nohttp']);
$SELF      = basename(__FILE__);
$ROOT      = __DIR__;

/* 安装状态判定器（与 index.php / tools/install-cli.php 共用同一套逻辑） */
$__guardFile = $ROOT . '/core/InstallGuard.php';
if (file_exists($__guardFile)) {
    require_once $__guardFile;
}

/* ============================ 工具函数 ============================ */

function ml_mask($s,$keep = 2)
{
    $s = (string) $s;
    if ($s === '') { return '<em>（空）</em>'; }
    $hasMb = function_exists('mb_strlen') && function_exists('mb_substr');
    $len   = $hasMb ? mb_strlen($s) : strlen($s);
    if ($len <= $keep * 2) { return str_repeat('•', $len); }
    $head = $hasMb ? mb_substr($s, 0, $keep) : substr($s, 0, $keep);
    $tail = $hasMb ? mb_substr($s, -$keep) : substr($s, -$keep);
    return htmlspecialchars($head . '••••' . $tail);
}

function ml_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
}

/** 从 fetch() 结果里安全取字段（兼容 PHP 5.6，无 ?? 语法） */
function ml_pick($row, $key, $def = '?')
{
    return (is_array($row) && isset($row[$key])) ? $row[$key] : $def;
}

/** 组装一条检查项 */
function ml_item($name,$status,$detail = '',$hint = '')
{
    return ['name' => $name, 'status' => $status, 'detail' => $detail, 'hint' => $hint];
}

/** 布尔 → ok/fail */
function ml_bool($ok,$okText = '正常',$failText = '异常')
{
    return $ok ? 'ok' : 'fail';
}

/** HTTP 请求（优先 curl，回退流式） */
function ml_http($url,$timeout = 6)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'ML-SelfCheck/1.1',
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => (string) $body, 'err' => $err, 'ok' => $code > 0];
    }

    $ctx  = stream_context_create(['http' => [
        'method'        => 'GET',
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'user_agent'    => 'ML-SelfCheck/1.1',
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return ['code' => $code, 'body' => (string) $body, 'err' => $body === false ? 'request failed' : '', 'ok' => $code > 0];
}

/** 目录/文件可写检测（用真实写入验证，而非只看权限位） */
function ml_writable($path)
{
    if (!file_exists($path)) { return [false, '不存在']; }
    if (!is_dir($path))      { return [is_writable($path), '文件']; }
    $probe = rtrim($path, '/\\') . '/.ml_probe_' . substr(md5((string) mt_rand()), 0, 8) . '.tmp';
    $ok = @file_put_contents($probe, 'x') !== false;
    if ($ok) { @unlink($probe); }
    return [$ok, $ok ? '可写' : '不可写'];
}

/** PHP 语法检查（依赖 PHP CLI） */
function ml_lint($file)
{
    static $bin = null;
    if ($bin === null) {
        $bin = (defined('PHP_BINARY') && PHP_BINARY && @is_executable(PHP_BINARY)) ? PHP_BINARY : '';
    }
    if ($bin === '' || !function_exists('exec')) { return null; }
    $out = []; $rc = 0;
    @exec(escapeshellarg($bin) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
    return ['rc' => (int) $rc, 'msg' => trim(implode(' | ', $out))];
}

/**
 * 读后台「系统设置」保存的覆盖值（app_settings 表）。
 * 表不存在 / 连不上库 / 无权限 → 返回空数组，自检照样能跑完。
 */
function ml_settings_override($cfg)
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = [];
    if (!is_array($cfg) || empty($cfg['db']) || !class_exists('PDO')) { return $cache; }
    $db = $cfg['db'];
    if (empty($db['host']) || empty($db['dbname']) || empty($db['user'])) { return $cache; }
    try {
        $dsn = 'mysql:host=' . $db['host'] . ';port=' . (isset($db['port']) ? $db['port'] : 3306)
             . ';dbname=' . $db['dbname'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $db['user'], (isset($db['pass']) ? $db['pass'] : ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 4,
        ]);
        foreach ($pdo->query('SELECT `k`,`v` FROM app_settings')->fetchAll() as $r) {
            $cache[(string) $r['k']] = (string) $r['v'];
        }
    } catch (\Exception $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * 本机 / 内网请求判断。
 * 仅用于「站点还没设置任何令牌」时放行自检（例如刚装完在服务器上先看一眼），
 * 一旦设置了后台令牌，就一律要求令牌，本函数不再起作用。
 */
function ml_is_local_request()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ($ip === '') { return false; }
    if (in_array($ip, array('127.0.0.1', '::1', '0.0.0.0'), true)) { return true; }
    // 私有网段：10.x / 172.16-31.x / 192.168.x / 169.254.x
    if (preg_match('/^10\./', $ip)) { return true; }
    if (preg_match('/^192\.168\./', $ip)) { return true; }
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $ip)) { return true; }
    if (preg_match('/^169\.254\./', $ip)) { return true; }
    return false;
}

/* ============================ 读取配置 ============================ */

$CFG_FILE = $ROOT . '/config/config.php';
$CFG      = null;
$CFG_ERR  = '';
if (is_file($CFG_FILE)) {
    try { $tmp = require $CFG_FILE; if (is_array($tmp)) { $CFG = $tmp; } else { $CFG_ERR = 'config.php 未返回数组'; } }
    catch (\Exception $e) { $CFG_ERR = 'config.php 载入失败：' . $e->getMessage(); }
} else {
    $CFG_ERR = 'config/config.php 不存在';
}

/* 后台「系统设置」可能覆盖了 config.php 里的令牌：这里取真正生效的那个 */
$OVR          = ml_settings_override($CFG);
$FILE_TOKEN   = (string) ((isset($CFG['admin']['token']) ? $CFG['admin']['token'] : ''));
$ADMIN_TOKEN  = (!empty($OVR['admin_token'])) ? (string) $OVR['admin_token'] : $FILE_TOKEN;
$NEED_AUTH    = ($ADMIN_TOKEN !== '' && !in_array($ADMIN_TOKEN, ['CHANGE_ADMIN_TOKEN', 'CHANGE_ME', 'admin'], true));
$PROVIDED     = (string) ((isset($_GET['token']) ? $_GET['token'] : ((isset($_SERVER['HTTP_X_ADMIN_TOKEN']) ? $_SERVER['HTTP_X_ADMIN_TOKEN'] : ((isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : ''))))));
$PROVIDED     = trim(str_ireplace('bearer', '', $PROVIDED));

/* 配置文件里的旧令牌也一并放行，避免改过令牌后一时想不起新值被锁在外面 */
$TOKEN_PASS   = ($PROVIDED !== '' && ($PROVIDED === $ADMIN_TOKEN || ($FILE_TOKEN !== '' && $PROVIDED === $FILE_TOKEN)));

/* 站点还没设置任何令牌（刚装完 / 未安装）时：只放行本机与内网地址，方便先在服务器上自检 */
$LOCAL_BYPASS = (!$NEED_AUTH && ml_is_local_request());

if (!$TOKEN_PASS && !$LOCAL_BYPASS) {
    /* 一律以 404 收场：不出现「需要令牌 / 令牌错误」等字样，
       避免被扫描器确认本页存在、也避免被拿来爆破令牌。
       要看自检报告请带 ?token=你的后台令牌（后台 →「安全防护」页有一键入口）。 */
    ml_guard_deny();
}

/* ============================ 站点地址 ============================ */

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '')) === 'https')) ? 'https' : 'http';
$host   = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1');
$script = (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/check.php');
$basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
$BASE   = $scheme . '://' . $host . $basePath;

/* ============================ 各项检查 ============================ */

$S = [];   // sections
$SUM = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];

/* ---------- 1. 运行环境 ---------- */
$env = [];
$phpOk = version_compare(PHP_VERSION, '5.6.0', '>=');
if (!$phpOk) {
    $phpHint = '本项目最低要求 PHP 5.6（5.6 / 7.x / 8.x 都能跑）；当前版本过低，去 宝塔 → 网站 → 设置 → PHP 版本 切换即可';
} elseif (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $phpHint = '可用（本项目支持 PHP 5.6 ~ 8.2，7.2 不是问题）。有条件可升到 8.0 / 8.1，性能更好、安全更新更长；不升也不影响运行';
} else {
    $phpHint = '';
}
$env[] = ml_item('PHP 版本', $phpOk ? 'ok' : 'fail', PHP_VERSION, $phpHint);

$exts = [
    'pdo_mysql' => ['PDO MySQL 驱动', true, '数据库必需，宝塔 PHP 设置里勾选安装'],
    'curl'      => ['cURL',            true, '采集/链接检测必需'],
    'json'      => ['JSON',            true, 'API 输出必需'],
    'mbstring'  => ['mbstring',        false, '中文截断/编码更稳（强烈建议）'],
    'openssl'   => ['OpenSSL',         false, '访问 HTTPS 上游必需'],
    'fileinfo'  => ['fileinfo',        false, '一般用途'],
];
foreach ($exts as $ext => $row) {
    $label = $row[0]; $must = $row[1]; $hint = $row[2];
    $has = extension_loaded($ext);
    $env[] = ml_item('扩展 · ' . $label, $has ? 'ok' : ($must ? 'fail' : 'warn'), $has ? '已安装' : '未安装', $has ? '' : $hint);
}

$env[] = ml_item('allow_url_fopen', ini_get('allow_url_fopen') ? 'ok' : 'warn', ini_get('allow_url_fopen') ? 'On' : 'Off', '关闭时若 cURL 也不可用则无法采集');
$deOn = ($ORIG_DISPLAY_ERRORS !== '' && $ORIG_DISPLAY_ERRORS !== '0' && strtolower($ORIG_DISPLAY_ERRORS) !== 'off');
$env[] = ml_item('display_errors（生产）', $deOn ? 'warn' : 'ok',
    ($deOn ? 'On' : 'Off') . '（服务器原值' . ($ORIG_DISPLAY_ERRORS === '' ? '：未设置' : '）'),
    $deOn ? '开启后，任何 PHP 报错都会把服务器绝对路径 / 代码片段直接打到浏览器上。关闭：宝塔 → 软件商店 → PHP-x.x → 设置 → 配置修改 → display_errors = Off → 重载。本页自身为便于排错会临时打开它，这里显示的是服务器原始值' : '');
$env[] = ml_item('expose_php', ini_get('expose_php') ? 'warn' : 'ok', ini_get('expose_php') ? 'On' : 'Off', ini_get('expose_php') ? '暴露 PHP 版本到响应头，建议关闭' : '');
$env[] = ml_item('时区', 'info', (string) (ini_get('date.timezone') ?: '（未设置，默认 UTC）'), '宝塔 → PHP 设置 → 时区选 Asia/Shanghai，避免日期显示偏差');
$env[] = ml_item('memory_limit', 'info', (string) ini_get('memory_limit'), '批量生成静态页建议 ≥ 256M');
$env[] = ml_item('max_execution_time', 'info', ini_get('max_execution_time') . ' 秒', '批量采集/生成建议 ≥ 300 秒');
$env[] = ml_item('exec 函数', 'info', function_exists('exec') ? '可用' : '被禁用（更安全）',
    function_exists('exec') ? '「代码语法检查」将逐个文件跑 php -l' : '宝塔默认禁用，属正常且更安全的配置；仅影响本页的 php -l 语法自检，不影响站点运行');
$env[] = ml_item('服务器 / SAPI', 'info', ((isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '未知')) . ' / ' . PHP_SAPI);
$env[] = ml_item('磁盘剩余空间', (($free = @disk_free_space($ROOT)) !== false && $free > 200 * 1024 * 1024) ? 'ok' : 'warn',
    ($free !== false ? round($free / 1048576, 1) . ' MB' : '无法读取'), '静态页会写入磁盘，建议保留 ≥ 200MB');

$S[] = ['title' => '1 · 运行环境', 'icon' => '🖥', 'items' => $env];

/* ---------- 2. 文件与目录权限 ---------- */
$perm = [];
$dirs = [
    '/uisc'          => ['静态内页目录（生成 /uisc/{id}.html）', true],
    '/config'        => ['配置目录', true],
    '/'              => ['站点根目录', true],
];
foreach ($dirs as $d => $row) {
    $desc = $row[0]; $must = $row[1];
    $p = $ROOT . $d;
    list($ok, $note) = ml_writable($p);
    $perm[] = ml_item('可写 · ' . ($d === '/' ? '站点根' : $d), $ok ? 'ok' : ($must ? 'fail' : 'warn'), "{$desc}：{$note}", $ok ? '' : '宝塔 → 文件 → 选中目录 → 权限设为 755、属主 www，或右键「权限」勾选写入');
}

$files = [
    '/index.php', '/home.php', '/track.php', '/check.php',
    '/core/Config.php', '/core/DB.php', '/core/Http.php', '/core/Json.php', '/core/Settings.php',
    '/core/CacheStore.php', '/core/Categories.php', '/core/Views.php',
    '/core/StaticGen.php', '/core/LinkChecker.php', '/core/InstallGuard.php', '/core/Guard.php',
    '/api/tmdb_proxy.php', '/api/unified.php',
    '/adapters/Adapter.php', '/adapters/Tmdb.php', '/adapters/Rawg.php', '/adapters/Bangumi.php',
    '/admin/index.php', '/config/pan_types.php',
];
$missing = [];
foreach ($files as $f) {
    $p = $ROOT . $f;
    if (!is_file($p) || filesize($p) < 20) { $missing[] = $f; }
}
$perm[] = ml_item('核心文件完整性', $missing ? 'fail' : 'ok',
    $missing ? '缺失/异常：' . implode('、', $missing) : count($files) . ' 个核心文件齐全',
    $missing ? '重新上传完整发布包（勿单独上传部分文件）' : '');

$uisc = $ROOT . '/uisc';
$htmlCount = 0;
if (is_dir($uisc)) {
    foreach (scandir($uisc) ?: [] as $f) { if (substr($f, -5) === '.html') { $htmlCount++; } }
}
$perm[] = ml_item('静态页已生成', $htmlCount > 0 ? 'ok' : 'info', $htmlCount . ' 个 .html 文件',
    $htmlCount ? '静态内页 /uisc/{id}.html 由 Nginx 直接吐出，不消耗 PHP' : '站点还没有内容，属正常；有资料后在后台点「生成静态页」（第 4 组会给覆盖率）');

$S[] = ['title' => '2 · 文件与目录权限', 'icon' => '📁', 'items' => $perm];

/* ---------- 3. 配置项 ---------- */
$conf = [];
$db = (isset($CFG['db']) ? $CFG['db'] : []);

/* 安装状态：看 db 是否填了真值（不是看文件在不在，否则自带示例配置会误判成已安装） */
$__st = function_exists('ml_install_state')
    ? ml_install_state($ROOT)
    : array('installed' => (bool) $CFG, 'reason' => 'unknown', 'lock' => false, 'db_ready' => false, 'cfg_exists' => false);
$__reasonTxt = array(
    'lock'   => '已写入 config/install.lock',
    'db'     => 'config/config.php 的数据库信息已填写',
    'sample' => 'config/config.php 仍是示例模板（数据库信息未填）',
    'none'   => '尚未生成 config/config.php',
);
$__why = isset($__reasonTxt[$__st['reason']]) ? $__reasonTxt[$__st['reason']] : (string) $__st['reason'];
$conf[] = ml_item('安装状态', $__st['installed'] ? 'ok' : 'fail',
    $__st['installed'] ? ('已完成安装（' . $__why . '）') : ('尚未安装（' . $__why . '）'),
    $__st['installed'] ? '' : '两种装法：浏览器打开 /install.php 按向导装（推荐），或在终端执行 php tools/install-cli.php');

$conf[] = ml_item('config/config.php', $CFG ? 'ok' : 'fail', $CFG ? '已载入' : $CFG_ERR,
    $CFG ? '' : '运行 php tools/install-cli.php 自动生成，或把 config/config.sample.php 复制为 config.php 后手填');

$passDefault = in_array((string) ((isset($db['pass']) ? $db['pass'] : '')), ['', 'CHANGE_ME', 'password', '123456'], true);
$conf[] = ml_item('数据库口令', $passDefault ? 'fail' : 'ok',
    ml_mask((isset($db['pass']) ? $db['pass'] : '')), $passDefault ? '仍是默认值，请改成宝塔新建库时设的真实密码' : '');

$conf[] = ml_item('数据库地址', 'info', ((isset($db['host']) ? $db['host'] : '-')) . ':' . ((isset($db['port']) ? $db['port'] : '-')) . ' / 库名 ' . ((isset($db['dbname']) ? $db['dbname'] : '-')) . ' / 用户 ' . ((isset($db['user']) ? $db['user'] : '-')));

$tokenDefault = in_array($ADMIN_TOKEN, ['', 'CHANGE_ADMIN_TOKEN', 'admin'], true);
$tokenSrc     = !empty($OVR['admin_token']) ? '（来自后台「系统设置」，已覆盖 config.php）' : '（来自 config/config.php）';
$conf[] = ml_item('后台令牌 admin.token', $tokenDefault ? 'fail' : 'ok',
    $tokenDefault ? '默认占位值' : (ml_mask($ADMIN_TOKEN) . ' ' . $tokenSrc),
    $tokenDefault ? '必须改成你自己的随机字符串，否则后台与自检页都能被任何人访问' : '');

$tmdbKey = (string) ((isset($OVR['tmdb_api_key']) && $OVR['tmdb_api_key'] !== '') ? $OVR['tmdb_api_key'] : ((isset($CFG['tmdb']['api_key']) ? $CFG['tmdb']['api_key'] : '')));
$conf[] = ml_item('TMDB key（影视）', (($tmdbKey === '' || strpos($tmdbKey, 'YOUR_') === 0) ? 'warn' : 'ok'),
    (($tmdbKey === '' || strpos($tmdbKey, 'YOUR_') === 0) ? '未填写' : (ml_mask($tmdbKey) . (!empty($OVR['tmdb_api_key']) ? ' （后台已覆盖）' : ''))),
    ($tmdbKey === '' || strpos($tmdbKey, 'YOUR_') === 0) ? '影视/电视剧/短剧采集需要，去 themoviedb.org 申请；也可在后台「系统设置」里填' : '');

$rawgKey = (string) ((isset($OVR['rawg_api_key']) && $OVR['rawg_api_key'] !== '') ? $OVR['rawg_api_key'] : ((isset($CFG['rawg']['api_key']) ? $CFG['rawg']['api_key'] : '')));
$conf[] = ml_item('RAWG key（游戏）', (($rawgKey === '' || strpos($rawgKey, 'YOUR_') === 0) ? 'warn' : 'ok'),
    (($rawgKey === '' || strpos($rawgKey, 'YOUR_') === 0) ? '未填写' : (ml_mask($rawgKey) . (!empty($OVR['rawg_api_key']) ? ' （后台已覆盖）' : ''))),
    ($rawgKey === '' || strpos($rawgKey, 'YOUR_') === 0) ? '游戏采集需要，rawg.io 免费申请；也可在后台「系统设置」里填' : '');

$adapters = [];
if (!empty($OVR['adapters'])) {
    $adapters = array_filter(array_map('trim', explode(',', $OVR['adapters'])));
} elseif (isset($CFG['adapters']) && is_array($CFG['adapters'])) {
    $adapters = $CFG['adapters'];
}
$conf[] = ml_item('启用的数据源', $adapters ? 'ok' : 'warn',
    $adapters ? implode(' / ', $adapters) : '未配置',
    $adapters ? (!empty($OVR['adapters']) ? '已在后台「系统设置」里调整（覆盖 config.php）' : '') : 'config.php → adapters 至少填 tmdb');

$panFile = $ROOT . '/config/pan_types.php';
$panList = [];
if (is_file($panFile)) { $t = require $panFile; if (is_array($t)) { $panList = $t; } }
$panOvr  = (!empty($OVR['pan_types'])) ? array_map('trim', explode(',', $OVR['pan_types'])) : [];
$panEff  = $panOvr ? $panOvr : $panList;
$conf[] = ml_item('网盘类型（下载下拉）', $panEff ? 'ok' : 'warn',
    $panEff ? (implode('、', $panEff) . ($panOvr ? ' （后台已覆盖，共 ' . count($panEff) . ' 种）' : '')) : '未读取到，使用兜底默认',
    $panEff ? '前台加网盘：后台 →「系统设置」直接增删，无需改文件、升级也不会丢' : '');

/* 后台设置覆盖情况（一眼看出哪些项被后台改过） */
$ovrKeys = ['admin_token' => '后台令牌', 'tmdb_api_key' => 'TMDB key', 'rawg_api_key' => 'RAWG key', 'adapters' => '数据源', 'pan_types' => '网盘类型'];
$ovrOn   = [];
foreach ($ovrKeys as $k => $cn) { if (!empty($OVR[$k])) { $ovrOn[] = $cn; } }
$conf[] = ml_item('后台设置覆盖（app_settings）',
    $ovrOn ? 'ok' : 'info',
    $ovrOn ? ('已覆盖 ' . count($ovrOn) . ' 项：' . implode('、', $ovrOn)) : '无覆盖，全部沿用 config/config.php',
    '后台 →「系统设置」里改；保存的项优先级高于 config.php，升级覆盖文件也不会被冲掉');

$S[] = ['title' => '3 · 配置项', 'icon' => '⚙️', 'items' => $conf];

/* ---------- 4. 数据库连接 ---------- */
$pdo = null;
$dtabs = [];
$dbinfo = [];
if ($CFG && $db) {
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=" . ((isset($db['charset']) ? $db['charset'] : 'utf8mb4'));
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $ver = ml_pick($pdo->query('SELECT VERSION() v')->fetch(), 'v', '?');
        $dbinfo[] = ml_item('数据库连接', 'ok', '成功 · MySQL ' . $ver);
        $dbinfo[] = ml_item('字符集', 'info', ml_pick($pdo->query('SELECT @@character_set_database c')->fetch(), 'c', '?'));
        $dbinfo[] = ml_item('表前缀/引擎', 'info', ml_pick($pdo->query("SELECT ENGINE e FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='media_items'")->fetch(), 'e', '无 media_items 表'));

        // 可选：真实写库测试（?write=1）——插入后立即删除，不残留数据
        if (!empty($_GET['write'])) {
            if ($pdo->query("SHOW TABLES LIKE 'media_items'")->fetchAll()) {
                try {
                    $probe   = '__selftest_' . substr(md5((string) mt_rand()), 0, 10);
                    $st      = $pdo->prepare("INSERT INTO media_items (source,source_id,type,title,updated_at) VALUES ('__selftest',?,?,?,NOW())");
                    $st->execute([$probe, 'movie', '自检临时数据']);
                    $newId   = (int) $pdo->lastInsertId();
                    $pdo->prepare('DELETE FROM media_items WHERE id=?')->execute([$newId]);
                    $dbinfo[] = ml_item('写入权限（INSERT/DELETE）', 'ok', '通过：插入 #' . $newId . ' 并已删除，未残留数据');
                } catch (\Exception $e2) {
                    $dbinfo[] = ml_item('写入权限（INSERT/DELETE）', 'fail', '失败：' . $e2->getMessage(),
                        '宝塔 → 数据库 → 该库的授权用户需有 INSERT/UPDATE/DELETE 权限（建议用「所有权限」重建用户）');
                }
            } else {
                $dbinfo[] = ml_item('写入权限（INSERT/DELETE）', 'fail', 'media_items 表不存在，无法测试', '先导入 sql/install.sql');
            }
        } else {
            $dbinfo[] = ml_item('写入权限（INSERT/DELETE）', 'info', '未测试',
                '带参数 ?write=1 可做一次真实写库测试（插入后立即删除，不留数据）；用于确认后台能否正常保存条目');
        }
    } catch (\Exception $e) {
        $dbinfo[] = ml_item('数据库连接', 'fail', '失败：' . $e->getMessage(), '核对 config.php 的 db.host/port/dbname/user/pass；宝塔新建库时用户要授权给该库');
    }
} else {
    $dbinfo[] = ml_item('数据库连接', 'fail', $CFG ? '配置不完整' : '未载入配置', '先修好 config/config.php');
}

/* ---------- 5. 表结构 ---------- */
$tabs = [];
if ($pdo) {
    $required = [
        'media_items' => [
            'id', 'source', 'source_id', 'type', 'title', 'original_title', 'year', 'rating',
            'vote_count', 'api_calls', 'clicks', 'genres', 'poster', 'backdrop', 'overview',
            'download_url', 'extra', 'payload', 'cached_at', 'refresh_at', 'updated_at',
        ],
        'sync_log'    => ['id', 'task', 'detail', 'items', 'created_at'],
        'link_checks' => ['id', 'item_id', 'label', 'url', 'status', 'http_code', 'checked_at'],
        'app_settings' => ['k', 'v', 'updated_at'],
    ];
    $missHint = [
        'link_checks'  => '导入 sql/migrate_link_checks.sql（新装用 install.sql）',
        'app_settings' => '导入 sql/migrate_settings.sql，或打开后台「系统设置」页会自动建表（不影响使用）',
    ];
    $alters = [];
    foreach ($required as $t => $cols) {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchAll();
            if (!$exists) {
                $tabs[] = ml_item("表 {$t}", 'fail', '不存在',
                    isset($missHint[$t]) ? $missHint[$t] : '导入 sql/install.sql');
                continue;
            }
            $have = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll() as $c) { $have[] = strtolower($c['Field']); }
            $miss = array_values(array_diff($cols, $have));
            if ($miss) {
                $tabs[] = ml_item("表 {$t}", 'warn', '缺列：' . implode('、', $miss), '执行下面的补救 SQL');
                if ($t === 'media_items') {
                    foreach ($miss as $mc) {
                        if ($mc === 'api_calls')    { $alters[] = 'ALTER TABLE media_items ADD COLUMN api_calls INT UNSIGNED NOT NULL DEFAULT 0;'; }
                        if ($mc === 'clicks')       { $alters[] = 'ALTER TABLE media_items ADD COLUMN clicks INT UNSIGNED NOT NULL DEFAULT 0;'; }
                        if ($mc === 'extra')        { $alters[] = 'ALTER TABLE media_items ADD COLUMN extra TEXT;'; }
                        if ($mc === 'download_url') { $alters[] = 'ALTER TABLE media_items MODIFY download_url MEDIUMTEXT NULL;'; }
                    }
                }
            } else {
                $tabs[] = ml_item("表 {$t}", 'ok', count($cols) . ' 个关键字段齐全');
            }
        } catch (\Exception $e) {
            $tabs[] = ml_item("表 {$t}", 'fail', $e->getMessage());
        }
    }
    $tabs[] = ml_item('补救 SQL（如有缺列/缺表）', $alters ? 'warn' : 'ok',
        $alters ? '见下方代码块' : '无需补救',
        $alters ? implode("\n", $alters) : '');
}

/* ---------- 6. 数据概览 ---------- */
$data = [];
if ($pdo) {
    try {
        $total = (int) $pdo->query('SELECT COUNT(*) c FROM media_items')->fetch()['c'];
        $data[] = ml_item('资料总条数', $total > 0 ? 'ok' : 'info', $total . ' 条', $total ? '' : '内容还是空的，属正常（不是部署问题）：后台「立即同步」或手动新增一条即可');

        $cat = ['short' => '短剧', 'movie' => '电影', 'tv' => '电视剧', 'anime' => '动漫', 'variety' => '综艺', 'game' => '游戏', 'person' => '人物'];
        $rows = $pdo->query("SELECT type, COUNT(*) c FROM media_items GROUP BY type ORDER BY c DESC")->fetchAll();
        $parts = [];
        foreach ($rows as $r) { $parts[] = ((isset($cat[$r['type']]) ? $cat[$r['type']] : ($r['type'] ?: '未分类'))) . ' ' . $r['c']; }
        $data[] = ml_item('分类分布', 'info', $parts ? implode(' · ', $parts) : '无数据（还没内容）');

        $withDl = (int) $pdo->query("SELECT COUNT(*) c FROM media_items WHERE download_url IS NOT NULL AND download_url<>'' AND download_url<>'[]'")->fetch()['c'];
        $data[] = ml_item('已填下载链接', $withDl > 0 ? 'ok' : 'info', $withDl . ' 条', $withDl ? '仅这些条目会在前台显示下载区（符合「有链接才显示」）' : '还没填下载链接，前台不会出现下载块（符合「有链接才显示」的设计）');

        if ($total > 0) {
            $cover = round($htmlCount / $total * 100, 1);
            $data[] = ml_item('静态页覆盖率', $cover >= 99 ? 'ok' : 'warn', "{$htmlCount} / {$total}（{$cover}%）", $cover >= 99 ? '' : '后台点「生成静态页」补全，或跑 cron/build_static.php');
        }

        if ($pdo->query("SHOW TABLES LIKE 'link_checks'")->fetchAll()) {
            $lc = $pdo->query("SELECT COUNT(*) total, SUM(status=1) okc, SUM(status=0) deadc, MAX(checked_at) last FROM link_checks")->fetch();
            $data[] = ml_item('链接检测记录', ((int) ((isset($lc['total']) ? $lc['total'] : 0))) ? 'ok' : 'info',
                '共 ' . (int) ((isset($lc['total']) ? $lc['total'] : 0)) . ' 条：有效 ' . (int) ((isset($lc['okc']) ? $lc['okc'] : 0)) . ' / 失效 ' . (int) ((isset($lc['deadc']) ? $lc['deadc'] : 0)) . '，最近 ' . ($lc['last'] ?: '从未检测'),
                ((int) ((isset($lc['deadc']) ? $lc['deadc'] : 0))) > 0 ? '存在失效链接，去后台点「检查全部链接」并清理' : '');
        }

        $lastSync = $pdo->query("SELECT task, items, created_at FROM sync_log ORDER BY id DESC LIMIT 1")->fetch();
        $data[] = ml_item('最近一次采集', 'info',
            $lastSync ? "{$lastSync['task']}：{$lastSync['items']} 条 @ {$lastSync['created_at']}" : '无记录',
            $lastSync ? '' : '宝塔 → 计划任务，定时跑 cron/sync_trending.php');
    } catch (\Exception $e) {
        $data[] = ml_item('数据统计', 'fail', $e->getMessage());
    }
}

$S[] = ['title' => '4 · 数据库与数据', 'icon' => '🗄', 'items' => array_merge($dbinfo, $tabs, $data)];

/* ---------- 7. 路由 / 伪静态自测 ---------- */
$route = [];
if (!$NO_HTTP) {
    $tests = [
        ['/api/v1/categories',        '统一 API · 分类结构',  'JSON 且含 types / pan_types'],
        ['/3/configuration',          'TMDB 兼容代理',        'JSON 且含 images'],
        ['/',                         '主页',                  'HTML 含资料卡片'],
        ['/' . $SELF,                 '自检页自身',            'HTTP 200'],
    ];
    foreach ($tests as $t) {
        $p = $t[0]; $label = $t[1]; $expect = $t[2];
        $r = ml_http($BASE . $p, 8);
        $ok = $r['ok'] && $r['code'] === 200;
        $body = trim((string) $r['body']);
        $isJson = ($body !== '' && ($body[0] === '{' || $body[0] === '['));
        $detail = $r['ok'] ? ('HTTP ' . $r['code'] . ' · ' . ($isJson ? 'JSON' : 'HTML') . ' · ' . strlen($body) . ' 字节') : ('请求失败：' . ($r['err'] ?: '无响应'));
        $hint = '';
        if (!$ok) {
            $hint = '若浏览器能直接打开该地址但这里失败，说明服务器禁止回环/自请求（可忽略本项，改用浏览器验证）';
        }
        $route[] = ml_item('路由 · ' . $label . '  ' . $p, $ok ? 'ok' : 'warn', $detail . '（期望：' . $expect . '）', $hint);
    }

    // 伪静态识别：请求一个不存在的路径，正常情况下应由 index.php 接手并返回 JSON 404
    $r = ml_http($BASE . '/__ml_rewrite_probe__', 8);
    $probe = (string) $r['body'];
    $isRoute404 = (strpos($probe, '"success":false') !== false && strpos($probe, 'Not Found') !== false);
    $route[] = ml_item('伪静态（rewrite）', $isRoute404 ? 'ok' : 'fail',
        $isRoute404 ? '生效：未匹配路径已交给 PHP 路由（返回 JSON 404）' : ('HTTP ' . $r['code'] . ' · 未进入 PHP 路由（返回的是服务器自带 404 页）'),
        $isRoute404 ? '' : '宝塔 → 网站 → 设置 → 伪静态，填入：location / { try_files $uri $uri/ /index.php?$query_string; }');

    // 敏感目录暴露检测
    $r = ml_http($BASE . '/config/config.php', 8);
    $leaked = (strpos((string) $r['body'], '=>') !== false && strpos((string) $r['body'], 'db') !== false) || strpos((string) $r['body'], '<?php') !== false;
    $route[] = ml_item('config 目录是否可被下载', $leaked ? 'fail' : 'ok',
        $leaked ? '危险：config/config.php 内容可被直接读取！' : '已正确执行，未泄露源码（HTTP ' . $r['code'] . '）',
        $leaked ? '立即在 Nginx 加：location ~* /(config|sql|cron|core|adapters)/ { deny all; }' : '');

    $r = ml_http($BASE . '/sql/install.sql', 8);
    $sqlLeak = (strpos((string) $r['body'], 'CREATE TABLE') !== false);
    $route[] = ml_item('sql 目录是否可被下载', $sqlLeak ? 'fail' : 'ok',
        $sqlLeak ? '危险：sql/install.sql 可被直接下载' : '未泄露（HTTP ' . $r['code'] . '）',
        $sqlLeak ? '同上，用 Nginx 规则屏蔽 /sql 目录' : '');
} else {
    $route[] = ml_item('路由自测', 'info', '已按 ?nohttp=1 跳过', '去掉 URL 中的 nohttp=1 可执行网络自测');
}

$S[] = ['title' => '5 · 路由 / 伪静态 / 安全', 'icon' => '🛡', 'items' => $route];

/* ---------- 8. 上游数据源连通 ---------- */
$up = [];
if (!$NO_HTTP) {
    $sources = [
        ['TMDB',     'https://api.themoviedb.org/3/configuration?api_key=' . urlencode((string) ((isset($CFG['tmdb']['api_key']) ? $CFG['tmdb']['api_key'] : ''))), ($tmdbKey === '' || strpos($tmdbKey, 'YOUR_') === 0)],
        ['RAWG',     'https://api.rawg.io/api/games?page_size=1&key=' . urlencode((string) ((isset($CFG['rawg']['api_key']) ? $CFG['rawg']['api_key'] : ''))), ($rawgKey === '' || strpos($rawgKey, 'YOUR_') === 0)],
        ['Bangumi',  'https://api.bgm.tv/v0/subjects/1', false],
        ['图片 CDN', 'https://image.tmdb.org/t/p/w92/wwemzKWzjKYJFfCeiBssw6xDBc3.jpg', false],
    ];
    foreach ($sources as $src) {
        $label = $src[0]; $url = $src[1]; $skip = $src[2];
        if ($skip) { $up[] = ml_item('上游 · ' . $label, 'warn', '未配置 key，跳过', '填好 config.php 里的 key 后再测'); continue; }
        $r = ml_http($url, 8);
        $ok = $r['ok'] && $r['code'] >= 200 && $r['code'] < 400;
        $hint = '';
        if (!$ok) { $hint = '服务器可能无法访问境外网络（宝塔常见），可配置代理或在 config.php 里换用镜像地址'; }
        if ($label === 'TMDB' && $r['code'] === 401) { $hint = 'HTTP 401：TMDB key 无效或未填写'; $ok = false; }
        $up[] = ml_item('上游 · ' . $label, $ok ? 'ok' : 'warn', $r['ok'] ? ('HTTP ' . $r['code']) : ('失败：' . ($r['err'] ?: '超时')), $hint);
    }
} else {
    $up[] = ml_item('上游连通性', 'info', '已按 ?nohttp=1 跳过');
}
$S[] = ['title' => '6 · 上游数据源连通性', 'icon' => '🌐', 'items' => $up];

/* ---------- 9. 代码语法检查（php -l） ---------- */
$lint = [];
$lintFiles = [];
foreach ($files as $f) {
    if (strpos($f, '/config/config.php') !== false || strpos($f, '/config/config.sample.php') !== false) { continue; }
    $lintFiles[] = $ROOT . $f;
}
$lintSupported = (function_exists('exec') && defined('PHP_BINARY') && PHP_BINARY && @is_executable(PHP_BINARY));
if ($lintSupported) {
    $bad = 0; $checked = 0; $msgs = [];
    foreach ($lintFiles as $f) {
        if (!is_file($f)) { continue; }
        $res = ml_lint($f);
        if ($res === null) { break; }
        $checked++;
        if ($res['rc'] !== 0) { $bad++; $msgs[] = basename($f) . '：' . $res['msg']; }
    }
    $lint[] = ml_item('PHP 语法（php -l）', $bad === 0 ? 'ok' : 'fail',
        $bad === 0 ? "已检查 {$checked} 个文件，全部通过" : "{$checked} 个文件中 {$bad} 个有语法错误",
        $bad ? implode("\n", array_slice($msgs, 0, 8)) : '语法层面没有问题，可继续部署');
} else {
    $lint[] = ml_item('PHP 语法（php -l）', 'info', '当前环境禁用 exec 或找不到 PHP CLI，已跳过',
        '本机无法校验；请在宝塔终端执行：find . -name "*.php" -exec php -l {} \\;');
}
$S[] = ['title' => '7 · 代码语法检查', 'icon' => '🧾', 'items' => $lint];

/* ---------- 8. 安全防护（安装器 / 敏感文件暴露面） ---------- */
$sec8 = [];
$gs   = function_exists('ml_guard_status') ? ml_guard_status($ROOT) : [];

if (!empty($gs)) {
    /* 网页版安装器：v1.6.3 起改为「带自锁」—— 已安装即 404，与文件不存在无异。
       真正要盯的是 install_active（文件存在 **且** 尚未安装）＝ 此刻真的能被重装。 */
    $insPresent = !empty($gs['install_present']);
    $insLocked  = !empty($gs['install_locked']);
    $insActive  = !empty($gs['install_active']);
    if ($insActive) {
        $sec8[] = ml_item('网页版安装器 install.php', 'warn',
            '文件存在，且站点**尚未安装** —— 此刻任何人访问它都能看到安装向导（这正是它的用途，但装完必须失效）',
            '按向导装完会写入 config/install.lock，之后它自动返回 404；若你并不打算用它，删掉站点根的 install.php 也一样安全');
    } elseif ($insPresent) {
        $sec8[] = ml_item('网页版安装器 install.php', 'ok',
            '存在，但已写入 install.lock → 对所有人返回 404（与文件不存在无异），无法被用来重装站点');
    } else {
        $sec8[] = ml_item('网页版安装器 install.php', 'ok',
            '未上传（此时安装只能用命令行 php tools/install-cli.php）');
    }

    if (!empty($gs['install_ghosts'])) {
        $sec8[] = ml_item('安装类残留文件', 'warn', implode('、', $gs['install_ghosts']),
            '这些文件可能被用来重装站点或覆盖配置，建议一并删除');
    }

    $hasCli = !empty($gs['cli_installer']);
    $sec8[] = ml_item('命令行安装器 tools/install-cli.php', $hasCli ? 'ok' : 'info',
        $hasCli ? '就位（仅命令行可执行，公网访问返回 404）' : '未上传（不影响运行，仅在重装 / 迁移服务器时需要）',
        $hasCli ? '' : '需要时从发布包上传 tools/ 目录，或按 DEPLOY.md「手动安装」操作');

    $hasLock = !empty($gs['lock']);
    $sec8[] = ml_item('安装锁 config/install.lock', $hasLock ? 'ok' : 'warn',
        $hasLock ? '存在（已标记安装完成）' : '不存在',
        $hasLock ? '' : '站点能正常访问可忽略；运行一次 CLI 安装器会自动补上');

    /* 敏感目录拒访文件 */
    $missDirs = [];
    foreach ($gs['dir_guards'] as $d => $okd) {
        if (!$okd) { $missDirs[] = $d; }
    }
    $sec8[] = ml_item('敏感目录拒访文件', empty($missDirs) ? 'ok' : 'warn',
        empty($missDirs) ? 'config / sql / core / adapters / cron / tools 均已放置拒访 index.php'
                         : ('缺少：' . implode('、', $missDirs)),
        empty($missDirs) ? '' : '从发布包对应目录补传 index.php（内容只有一行 404）');

    /* 内部文件守卫覆盖率 */
    $shieldMissing = isset($gs['shield_missing']) ? $gs['shield_missing'] : [];
    $sec8[] = ml_item('内部 PHP 文件反直接访问守卫', empty($shieldMissing) ? 'ok' : 'warn',
        empty($shieldMissing) ? ((int) $gs['shield_total'] . ' 个内部文件均已带守卫（直接请求会返回 404）')
                              : ('未加守卫：' . implode('、', array_slice($shieldMissing, 0, 6))),
        empty($shieldMissing) ? '' : '用发布包覆盖对应文件即可（v1.4.0 起所有内部文件都带守卫）');

    $sec8[] = ml_item('.htaccess（Apache 用）', !empty($gs['htaccess']) ? 'ok' : 'info',
        !empty($gs['htaccess']) ? '存在（Apache 环境自动生效；Nginx 请用宝塔伪静态规则）' : '不存在（Nginx 环境无需本文件）');
}

/* ---------- 8b. 定时脚本守卫（不联网、无双副作用地判断能否被公网触发） ---------- */
$cronFiles = glob($ROOT . '/cron/*.php');
$cronUnguarded = [];
if ($cronFiles) {
    foreach ($cronFiles as $cf) {
        if (basename($cf) === 'index.php') { continue; }
        $src = (string) @file_get_contents($cf);
        if (strpos($src, 'ml_guard_shield') === false) { $cronUnguarded[] = 'cron/' . basename($cf); }
    }
}
if (!$cronFiles) {
    $sec8[] = ml_item('定时脚本 cron/*.php', 'info', '目录不存在或为空（不影响运行，仅说明没配定时采集）');
} else {
    $sec8[] = ml_item('定时脚本防公网触发', empty($cronUnguarded) ? 'ok' : 'fail',
        empty($cronUnguarded) ? (count($cronFiles) . ' 个脚本均已加守卫（被网址请求会 404）')
                              : ('未加守卫：' . implode('、', $cronUnguarded)),
        empty($cronUnguarded) ? '' : '这些文件只要能被网址直接打开，任何人都能反复触发采集 / 重建静态页（浪费服务器与上游配额）。'
            . '修法：上传 v1.4.0 起的 cron/ 目录（每个脚本都带 ml_guard_shield），并在宝塔伪静态加 location ^~ /cron/ { return 404; }');
}

/* ---------- 8c. 公网可达性实测（只探测"只读"路径，避免误触发采集） ---------- */
if (!$NO_HTTP) {
    $probes = [
        ['/install.php',              '网页版安装器（已安装后必须 404）',   'danger'],
        ['/config/config.php',        '数据库配置（内部文件）',     'danger'],
        ['/core/Config.php',          '核心类库（内部文件）',       'warn'],
        ['/home.php',                 '主页渲染文件（内部文件）',   'danger'],
        ['/sql/install.sql',          '建表脚本',                   'danger'],
        ['/config/config.sample.php', '配置示例',                   'warn'],
        ['/DEPLOY.md',                '部署文档',                   'warn'],
    ];
    $badPaths = [];
    foreach ($probes as $pb) {
        $path = $pb[0]; $label = $pb[1]; $lv = $pb[2];
        $r    = ml_http($BASE . $path, 8);
        $code = (int) $r['code'];
        $body = (string) $r['body'];
        $name = '公网可达 · ' . $label . '  ' . $path;

        /* /install.php：尚未安装时返回 200（安装向导）属正常，不算漏洞；
           已安装却还是 200，才是真正要命的情况（下面照常判 fail）。 */
        if ($path === '/install.php' && empty($__st['installed'])) {
            $sec8[] = ml_item($name, 'info',
                'HTTP ' . $code . ' · 站点尚未安装，安装向导可访问属正常（装完写 install.lock 后自动 404）');
            continue;
        }

        /* 建表脚本能读到内容 = 最严重的源码泄露 */
        if (strpos($body, 'CREATE TABLE') !== false) {
            $sec8[] = ml_item($name, 'fail', '危险：该文件内容可被直接下载！',
                'Nginx 必须用「^~ 前缀匹配」保护目录（普通前缀 location /sql/ 会被宝塔自带的 location ~ \\.php$ 正则抢先匹配）：location ^~ /sql/ { return 404; }');
            $badPaths[] = $path;
            continue;
        }

        /* 404 / 403 / 30x 都算安全：404=文件不在或被守卫吞掉，403=被拒绝，30x=跳转 */
        if ($code === 404 || $code === 403 || $code === 301 || $code === 302) {
            $sec8[] = ml_item($name, 'ok', 'HTTP ' . $code . '（已不可访问）');
            continue;
        }

        /* HTTP 200：说明这个文件真的被 PHP 执行了 */
        $leak = '';
        if (stripos($body, 'Fatal error') !== false) {
            $leak = '；且直接把 PHP Fatal error 回显出来（连带服务器绝对路径 / 类名结构一起泄露）';
        } elseif (stripos($body, 'Warning') !== false || stripos($body, 'Notice') !== false) {
            $leak = '；且直接回显了 PHP Warning / Notice';
        } elseif (trim($body) !== '') {
            $leak = '；返回了 ' . strlen($body) . ' 字节内容';
        }

        /* config/ 下的文件是安装器"生成"的，发布包不会覆盖它们，修法要单独说明 */
        if (strpos($path, '/config/') === 0) {
            $fix = '这是安装器生成的配置（发布包不覆盖它，避免冲掉你的数据库口令）。两种修法：'
                 . '① 宝塔伪静态加 location ^~ /config/ { return 404; }（最省事，推荐）；'
                 . '② 在本文件最顶部照抄 config/config.sample.php 开头那 4 行"反直接访问守卫"'
                 . '（v1.4.1 起安装器新生成的 config.php / pan_types.php 已自带这段）';
        } else {
            $fix = '两层修法：① 上传 v1.4.0 起的 core/Guard.php + 带 ml_guard_shield() 的内部文件（被直接请求即 404）；'
                 . '② 宝塔伪静态用「^~ 前缀」保护目录：location ^~ /config/ { return 404; }、location ^~ /core/ { return 404; }、'
                 . 'location ^~ /cron/ { return 404; }、location ^~ /sql/ { return 404; }、location ^~ /adapters/ { return 404; }、'
                 . 'location ^~ /tools/ { return 404; }';
        }
        $sec8[] = ml_item($name, ($lv === 'danger' ? 'fail' : 'warn'), 'HTTP 200 · 仍可访问' . $leak, $fix);
        if ($lv === 'danger') { $badPaths[] = $path; }
    }

    $sec8[] = ml_item('内部文件暴露面汇总', empty($badPaths) ? 'ok' : 'fail',
        empty($badPaths) ? '安装器 / 数据库配置 / 核心类库 / 内部渲染文件均无法从公网访问'
                         : (count($badPaths) . ' 个高危文件仍可从公网访问：' . implode('、', $badPaths)),
        empty($badPaths) ? '' : '只要这些地址在浏览器里能打开，等于把站点结构和内部脚本交给任何人；按上面提示加 Nginx 规则，或直接上传 v1.4.0 整包覆盖');
} else {
    $sec8[] = ml_item('公网可达性探测', 'info', '已按 ?nohttp=1 跳过 —— 建议去掉 nohttp=1 再跑一次',
        '安装器 / 内部文件 / 数据库配置能不能被公网直接打开，只有联网实测才能确认（如需最快，可只跑一次不带 nohttp 的）');
}


$S[] = ['title' => '8 · 安全防护', 'icon' => '🔒', 'items' => $sec8];

/* ---------- 9. 采集与计划任务（同步能不能跑出数据 / 每小时任务配了吗） ---------- */
$sync9 = [];

/* 9.1 启用的数据源 与 对应 Key 是否齐全（采集没数据最常见的两个原因） */
$enabledSrc = [];
if (!empty($OVR['adapters'])) {
    foreach (explode(',', $OVR['adapters']) as $x) { $x = trim($x); if ($x !== '') { $enabledSrc[] = $x; } }
} elseif (isset($CFG['adapters']) && is_array($CFG['adapters'])) {
    $enabledSrc = $CFG['adapters'];
}
$srcLabel = ['tmdb' => 'TMDB（影视）', 'rawg' => 'RAWG（游戏）', 'bangumi' => 'Bangumi（动漫）'];
$srcName  = [];
foreach ($enabledSrc as $s9) { $srcName[] = isset($srcLabel[$s9]) ? $srcLabel[$s9] : $s9; }
$sync9[] = ml_item('启用的数据源', $enabledSrc ? 'ok' : 'fail',
    $enabledSrc ? (implode('、', $srcName) . '（共 ' . count($enabledSrc) . ' 个）') : '一个都没启用',
    $enabledSrc ? '' : '后台 →「系统设置 → 启用哪些数据源」至少勾一个，否则采集跑不出任何数据');

$keyProblems = [];
if (in_array('tmdb', $enabledSrc, true) && ($tmdbKey === '' || strpos($tmdbKey, 'YOUR_') === 0)) {
    $keyProblems[] = 'TMDB Key 未填';
}
if (in_array('rawg', $enabledSrc, true) && ($rawgKey === '' || strpos($rawgKey, 'YOUR_') === 0)) {
    $keyProblems[] = 'RAWG Key 未填';
}
$sync9[] = ml_item('数据源配套密钥', $keyProblems ? 'fail' : 'ok',
    $keyProblems ? ('已启用但缺少：' . implode('、', $keyProblems)) : '已启用的数据源密钥都齐了',
    $keyProblems ? '缺 Key 的源调用上游会返回 401，采集结果就是 0 条。去「系统设置」填 Key 并点「测试连通」' : '');

/* 9.2 采集参数（后台覆盖值 → 默认值） */
$syncDef = [
    'sync_limit' => '20', 'sync_max_pages' => '1', 'sync_max_requests' => '8', 'sync_window' => 'week',
    'sync_order' => 'both', 'sync_min_votes' => '0', 'sync_dedupe' => 'skip',
    'sync_skip_same_title' => '1', 'sync_interval' => '55', 'sync_build' => '1',
];
$ordTxt = ['hot' => '只要最热', 'popular' => '只要最多人看', 'both' => '最热+最多人看'];
$eff = [];
foreach ($syncDef as $k => $dv) {
    $eff[$k] = (isset($OVR[$k]) && $OVR[$k] !== '') ? (string) $OVR[$k] : $dv;
}
$ovrCount = 0;
foreach ($syncDef as $k => $dv) { if (isset($OVR[$k]) && $OVR[$k] !== '') { $ovrCount++; } }
$sync9[] = ml_item('采集参数', 'info',
    '每源 ' . $eff['sync_limit'] . ' 条 · 时间窗 ' . ($eff['sync_window'] === 'day' ? '今日' : '本周')
    . ' · 口径 ' . (isset($ordTxt[$eff['sync_order']]) ? $ordTxt[$eff['sync_order']] : $eff['sync_order'])
    . ' · 最低票数 ' . $eff['sync_min_votes'] . ' · 重复项 ' . ($eff['sync_dedupe'] === 'update' ? '刷新' : '跳过')
    . ' · 最小间隔 ' . $eff['sync_interval'] . ' 分钟 · 单次请求上限 ' . $eff['sync_max_requests'] . ' 次'
    . ($ovrCount ? ('（' . $ovrCount . ' 项来自后台设置）') : '（全部为默认值）'),
    '后台 →「同步采集」页可逐项调整；网页按钮与每小时计划任务共用这套参数');

/* 9.3 计划任务脚本是否就位 */
$cronHourly = file_exists($ROOT . '/cron/sync_hourly.php');
$cronTrend  = file_exists($ROOT . '/cron/sync_trending.php');
$sync9[] = ml_item('计划任务脚本', ($cronHourly ? 'ok' : 'warn'),
    'cron/sync_hourly.php ' . ($cronHourly ? '已就位' : '缺失') . ' · cron/sync_trending.php ' . ($cronTrend ? '已就位' : '缺失'),
    $cronHourly ? '' : '从发布包上传 cron/ 目录（v1.5.0 起新增 sync_hourly.php），否则没法配每小时采集');

/* 注意：PHP_BINARY 在 FPM 下指向 php-fpm（不能用来跑脚本），
   所以优先找宝塔的 /www/server/php/<版本>/bin/php，找不到再退回字面量 php。 */
$phpBin = 'php';
$__ver  = str_replace('.', '', substr(PHP_VERSION, 0, 3));
if (is_dir('/www/server/php')) {
    $__c = glob('/www/server/php/' . $__ver . '/bin/php');
    if (!$__c) { $__c = glob('/www/server/php/*/bin/php'); }
    if ($__c) { sort($__c); $phpBin = $__c[count($__c) - 1]; }
} elseif (defined('PHP_BINARY') && PHP_BINARY
          && strpos(PHP_BINARY, 'fpm') === false && strpos(PHP_BINARY, 'cgi') === false) {
    $phpBin = PHP_BINARY;
}
$cronCmd = $phpBin . ' ' . $ROOT . '/cron/sync_hourly.php';
$sync9[] = ml_item('每小时计划任务（宝塔）', $cronHourly ? 'info' : 'warn',
    '命令：' . $cronCmd,
    '宝塔 →「计划任务」→ 添加任务 → 类型选 Shell 脚本 → 周期选「1 小时」→ 脚本内容填上面这条命令保存。'
    . '（后台「同步采集」页有可一键复制的命令）');

/* 9.4 最近一次采集记录 */
if ($pdo) {
    try {
        $sync9Last = $pdo->query("SELECT task,detail,items,created_at FROM sync_log WHERE task LIKE 'sync%' ORDER BY id DESC LIMIT 1")->fetch();
        if (!$sync9Last) {
            $sync9[] = ml_item('最近一次采集', 'info', '还没有采集记录',
                '点后台「同步采集 → 立即采集」，或配好计划任务后等它自己跑一次');
        } else {
            $ld   = json_decode((string) ml_pick($sync9Last, 'detail', ''), true);
            $lok  = (is_array($ld) && array_key_exists('ok', $ld)) ? (bool) $ld['ok'] : true;
            $ins  = (is_array($ld) && isset($ld['inserted'])) ? (int) $ld['inserted'] : (int) ml_pick($sync9Last, 'items', 0);
            $sync9Skip = (is_array($ld) && isset($ld['skipped_dup'])) ? (int) $ld['skipped_dup'] : 0;
            $rsn  = (is_array($ld) && isset($ld['reason'])) ? (string) $ld['reason'] : '';
            $ago  = (int) floor((time() - strtotime((string) ml_pick($sync9Last, 'created_at', 'now'))) / 60);
            $sync9[] = ml_item('最近一次采集', $lok ? 'ok' : 'warn',
                ml_pick($sync9Last, 'created_at', '?') . '（' . $ago . ' 分钟前 · ' . ml_pick($sync9Last, 'task', '-') . '）'
                . ' · 新增 ' . $ins . ' 条 · 重复跳过 ' . $sync9Skip . ' 条',
                $rsn !== '' ? ('备注：' . $rsn) : ($lok ? '' : '上次采集有异常，看后台「同步采集 → 最近采集记录」里的各源明细'));
        }
    } catch (\Exception $e) {
        $sync9[] = ml_item('最近一次采集', 'info', 'sync_log 表不可用：' . $e->getMessage(), '导入 sql/install.sql 建表');
    }
} else {
    $sync9[] = ml_item('最近一次采集', 'info', '数据库未连接，跳过');
}

/* 9.5 同步锁（正常跑完会自动删除；长期存在说明上次崩溃或被中断） */
$lockFile = $ROOT . '/config/sync.lock';
if (is_file($lockFile)) {
    $lockRaw = json_decode((string) @file_get_contents($lockFile), true);
    $lockAt  = (is_array($lockRaw) && isset($lockRaw['at'])) ? (int) $lockRaw['at'] : (int) @filemtime($lockFile);
    $lockAge = (int) floor((time() - $lockAt) / 60);
    $sync9[] = ml_item('同步进程锁 config/sync.lock', ($lockAge > 10) ? 'warn' : 'info',
        '存在（' . $lockAge . ' 分钟前写入）',
        ($lockAge > 10) ? '超过 10 分钟的锁会被自动忽略（视为上次中断的残留），也可手动删掉该文件' : '正在采集或刚刚结束，属正常');
} else {
    $sync9[] = ml_item('同步进程锁 config/sync.lock', 'ok', '无残留锁', '同一时刻只允许一个采集在跑，避免计划任务撞车重复请求上游');
}

$S[] = ['title' => '9 · 采集与计划任务', 'icon' => '⏱', 'items' => $sync9];

/* ---------- 10. 站点设置 / SEO / 网盘接口（v1.6.0 新增） ---------- */
$site10 = [];

/* 取生效值：后台覆盖 > config.php > 内置默认。
   ★ 0 / "0" 都是合法值，所以一律用 `!== ''` 判断，绝不能用 empty()。 */
if (!function_exists('ml_eff')) {
    function ml_eff($OVR, $seg, $key, $sub, $def = '')
    {
        if (isset($OVR[$key]) && $OVR[$key] !== '') { return (string) $OVR[$key]; }
        if (is_array($seg) && isset($seg[$sub]) && $seg[$sub] !== '') { return (string) $seg[$sub]; }
        return (string) $def;
    }
}
if (!function_exists('ml_len')) {
    /** 按「字」算长度（中文逐字，无 mbstring 时退回字节数） */
    function ml_len($s)
    {
        $s = (string) $s;
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }
}
$SITE = (is_array($CFG) && isset($CFG['site'])     && is_array($CFG['site']))     ? $CFG['site']     : array();
$SEOC = (is_array($CFG) && isset($CFG['seo'])      && is_array($CFG['seo']))      ? $CFG['seo']      : array();
$REDC = (is_array($CFG) && isset($CFG['redirect']) && is_array($CFG['redirect'])) ? $CFG['redirect'] : array();
$PANC = (is_array($CFG) && isset($CFG['pan'])      && is_array($CFG['pan']))      ? $CFG['pan']      : array();
$PSC  = (is_array($CFG) && isset($CFG['pansou'])   && is_array($CFG['pansou']))   ? $CFG['pansou']   : array();

/* 10.1 基础设置（网站名 / LOGO / 网址 / 页脚） */
$sName   = ml_eff($OVR, $SITE, 'site_name', 'name', '媒资资料库');
$sSlogan = ml_eff($OVR, $SITE, 'site_slogan', 'slogan', '');
$sUrl    = ml_eff($OVR, $SITE, 'site_url', 'url', '');
$sLogo   = ml_eff($OVR, $SITE, 'site_logo', 'logo', '');
$sIcon   = ml_eff($OVR, $SITE, 'site_favicon', 'favicon', '');
$sHide   = ml_eff($OVR, $SITE, 'site_name_hide', 'name_hide', '0');
$fIntro  = ml_eff($OVR, $SITE, 'site_footer_intro', 'footer_intro', '');
$fDec    = ml_eff($OVR, $SITE, 'site_declare', 'declare', '');
$fCopy   = ml_eff($OVR, $SITE, 'site_copyright', 'copyright', '');

$site10[] = ml_item('网站名称 / 宣传语', ($sName !== '') ? 'ok' : 'warn',
    ml_h($sName) . ($sSlogan !== '' ? (' · ' . ml_h($sSlogan)) : '（未设宣传语）')
    . ($sHide === '1' ? ' · 顶栏已隐藏名称' : ''),
    '后台 →「站点设置 → 基础设置」。LOGO 图片里已含文字时，可勾「隐藏网站名称」');
$site10[] = ml_item('网站网址', ($sUrl !== '') ? 'ok' : 'warn',
    ($sUrl !== '') ? ml_h($sUrl) : '未填写（canonical / robots.txt / sitemap.xml 将按请求 Host 自动推断）',
    ($sUrl === '') ? ('建议填上，例：' . ml_h($BASE) . '（结尾不要带 /）。填了才能输出稳定的绝对地址，对收录很关键') : '');
$site10[] = ml_item('LOGO / 浏览器 icon', (($sLogo !== '' || $sIcon !== '')) ? 'ok' : 'info',
    'LOGO ' . ($sLogo !== '' ? '已设置' : '未设置') . ' · icon ' . ($sIcon !== '' ? '已设置' : '未设置'),
    ($sLogo === '' && $sIcon === '') ? '可在「基础设置」直接上传（自动存到 /uisc-assets/ 并随机改名，单张 2MB 以内）' : '');
$site10[] = ml_item('页脚三段（介绍 / 声明 / 版权）',
    (($fIntro !== '' || $fDec !== '' || $fCopy !== '')) ? 'ok' : 'info',
    '介绍 ' . ($fIntro !== '' ? '有' : '无') . ' · 声明 ' . ($fDec !== '' ? '有' : '无') . ' · 版权 ' . ($fCopy !== '' ? '有' : '无'),
    '页脚为空不影响功能；建议至少写上「本站仅提供索引，不存储任何资源」之类的免责声明');

/* 10.2 SEO（标题 / 关键词 / 描述 / 模板 / 统计代码 / robots / sitemap） */
$seoTitle  = ml_eff($OVR, $SEOC, 'seo_title', 'title', '');
$seoKw     = ml_eff($OVR, $SEOC, 'seo_keywords', 'keywords', '');
$seoDesc   = ml_eff($OVR, $SEOC, 'seo_description', 'description', '');
$seoStats  = ml_eff($OVR, $SEOC, 'seo_stats_code', 'stats_code', '');
$tplType   = ml_eff($OVR, $SEOC, 'seo_pattern_type', 'pattern_type', '{type} - 第{page}页 - {site}');
$tplItem   = ml_eff($OVR, $SEOC, 'seo_pattern_item', 'pattern_item', '{title}({year}) - {type} - {site}');
$itemLen   = ml_eff($OVR, $SEOC, 'seo_item_desc_len', 'item_desc_len', '0');
$robotsRaw = ml_eff($OVR, $SEOC, 'seo_robots', 'robots', '');
$smOn      = ml_eff($OVR, $SEOC, 'seo_sitemap', 'sitemap', '1');
$smSize    = ml_eff($OVR, $SEOC, 'seo_sitemap_size', 'sitemap_size', '5000');

$seoMiss = array();
if ($seoTitle === '') { $seoMiss[] = 'SEO 标题'; }
if ($seoKw === '')    { $seoMiss[] = 'SEO 关键词'; }
if ($seoDesc === '')  { $seoMiss[] = 'SEO 描述'; }
$site10[] = ml_item('SEO 三件套（标题 / 关键词 / 描述）', ($seoMiss ? 'warn' : 'ok'),
    ($seoMiss ? ('未填：' . implode('、', $seoMiss) . '，前台会按站点名自动生成')
              : ('已填 · 标题 ' . ml_len($seoTitle) . ' 字 · 关键词 ' . ml_len($seoKw) . ' 字 · 描述 ' . ml_len($seoDesc) . ' 字')),
    ($seoMiss ? '后台 →「站点设置 → SEO」补齐即可；描述建议 80~160 字' : ''));
$site10[] = ml_item('SEO 模板（列表页 / 内页）', 'info',
    '列表页：' . ml_h($tplType) . '<br>内页：' . ml_h($tplItem)
    . '<br>内页描述：' . (($itemLen === '0') ? '不截取简介，直接用 SEO 描述' : ('取简介前 ' . (int) $itemLen . ' 字')),
    '占位符：列表页 {site} {type} {sort} {page}；内页 {title} {year} {type} {site} {region}');
$site10[] = ml_item('统计代码', ($seoStats !== '') ? 'ok' : 'info',
    ($seoStats !== '') ? ('已配置（' . ml_len($seoStats) . ' 字，原样插到每个页面的 </head> 前）') : '未配置',
    '支持 51.la / 百度统计等的整段代码，直接粘贴即可');
$site10[] = ml_item('robots.txt / sitemap.xml 内容', 'info',
    'robots.txt：' . ($robotsRaw !== '' ? '自定义（后台已填）' : '默认（允许首页与内页，屏蔽后台/接口）')
    . ' · sitemap：' . ($smOn === '0' ? '不生成' : ('生成，最多 ' . (int) $smSize . ' 条')),
    '访问 /robots.txt 与 /sitemap.xml 即可查看实际输出');

/* 10.3 新功能代码是否就位 + 真实 HTTP 探测 */
$idxTxt = is_file($ROOT . '/index.php') ? (string) @file_get_contents($ROOT . '/index.php') : '';
$hasRobotsRoute  = ($idxTxt !== '' && strpos($idxTxt, 'Site::robotsTxt()') !== false);
$hasSitemapRoute = ($idxTxt !== '' && strpos($idxTxt, 'Site::sitemapXml()') !== false);
$hasJumpRoute    = ($idxTxt !== '' && strpos($idxTxt, 'Site::interceptHtml()') !== false);
$siteLibOk       = is_file($ROOT . '/core/Site.php');
$panLibOk        = is_file($ROOT . '/core/PanSou.php');
$site10[] = ml_item('新功能代码是否就位（v1.6.0 起）',
    (($siteLibOk && $panLibOk && $hasRobotsRoute && $hasSitemapRoute && $hasJumpRoute) ? 'ok' : 'fail'),
    'core/Site.php ' . ($siteLibOk ? '有' : '缺失') . ' · core/PanSou.php ' . ($panLibOk ? '有' : '缺失')
    . ' · robots 路由 ' . ($hasRobotsRoute ? '有' : '缺') . ' · sitemap 路由 ' . ($hasSitemapRoute ? '有' : '缺')
    . ' · 跳转/扫码路由 ' . ($hasJumpRoute ? '有' : '缺'),
    '任一项「缺失/缺」都说明站点根 index.php 还是旧版、或 core/ 下的新文件没上传。用最新增量升级包（或整包）覆盖 index.php 与 core/ 即可');
if (!$NO_HTTP) {
    $rb = ml_http($BASE . '/robots.txt', 6);
    $sm = ml_http($BASE . '/sitemap.xml', 8);
    $rbBody = trim(str_replace(array("\r", "\n"), ' ', substr((string) $rb['body'], 0, 110)));
    $site10[] = ml_item('robots.txt 实际可访问', ($rb['code'] === 200) ? 'ok' : 'warn',
        'HTTP ' . ($rb['code'] > 0 ? $rb['code'] : '无响应') . ' · ' . ml_h($rbBody),
        ($rb['code'] === 200) ? '' : '若这里不是 200：确认伪静态 location / { try_files $uri $uri/ /index.php?$query_string; } 已生效，且站点根没有放别的 robots.txt 抢路由');
    $smIsXml = (strpos((string) $sm['body'], '<urlset') !== false);
    $site10[] = ml_item('sitemap.xml 实际可访问',
        ($sm['code'] === 200 && $smIsXml) ? 'ok' : (($smOn === '0') ? 'info' : 'warn'),
        'HTTP ' . ($sm['code'] > 0 ? $sm['code'] : '无响应') . ' · ' . ($smIsXml ? '是合法 sitemap' : '不是 sitemap 内容'),
        ($smOn === '0') ? '后台把「生成 sitemap.xml」关掉了，返回非 sitemap 属正常'
                        : (($sm['code'] === 200 && $smIsXml) ? '' : '检查伪静态规则；并确认站点根没有物理 sitemap.xml 文件'));
} else {
    $site10[] = ml_item('robots.txt / sitemap.xml 实访问', 'info', '已跳过（?nohttp=1）', '去掉 nohttp 参数即可做真实探测');
}

/* 10.4 图片上传目录 uisc-assets（基础设置 / 跳转扫码 都要用） */
$asDir = $ROOT . '/uisc-assets';
if (is_dir($asDir)) {
    $asWr = ml_writable($asDir);
    $asCnt = 0;
    $dh = @opendir($asDir);
    if ($dh) {
        while (($asF = readdir($dh)) !== false) {
            if ($asF === '.' || $asF === '..' || $asF === 'index.php' || $asF === '.htaccess') { continue; }
            $asCnt++;
        }
        closedir($dh);
    }
    $site10[] = ml_item('图片目录 /uisc-assets', ($asWr[0] ? 'ok' : 'fail'),
        ($asWr[0] ? '可写' : '不可写') . ' · 已有文件 ' . $asCnt . ' 个 · 拒访 index.php ' . (is_file($asDir . '/index.php') ? '有' : '无（首次上传时自动生成）'),
        ($asWr[0] ? '' : '在宝塔里把 uisc-assets 目录权限设为 755、属主设为 www，否则后台上传图片会失败'));
} else {
    $rootWr = ml_writable($ROOT);
    $site10[] = ml_item('图片目录 /uisc-assets', ($rootWr[0] ? 'info' : 'warn'),
        '目录尚未创建（第一次上传图片时会自动创建）· 站点根目录' . ($rootWr[0] ? '可写' : '不可写'),
        ($rootWr[0] ? '' : '站点根目录不可写，请先在宝塔里手动新建 uisc-assets 目录并设 755 / www 属主'));
}

/* 10.5 跳转 / 扫码（后台「站点设置 → 扫描+跳转」） */
$rMode   = ml_eff($OVR, $REDC, 'redirect_mode', 'mode', 'jump_scan');
$rTarget = ml_eff($OVR, $REDC, 'redirect_target', 'target', '');
$rMobile = ml_eff($OVR, $REDC, 'redirect_mobile', 'mobile', '0');
$rQr     = ml_eff($OVR, $REDC, 'redirect_qr', 'qr', '');
$rQrTip  = ml_eff($OVR, $REDC, 'redirect_qr_tip', 'qr_tip', '');
$rModeTx = array('jump_scan' => '跳转 + 扫码', 'jump_only' => '仅跳转', 'scan_only' => '仅扫码');
$rActive = ($rTarget !== '' || $rQr !== '');
if (!$rActive) {
    $site10[] = ml_item('PC 端跳转 / 扫码', 'ok', '未启用（访客直接进站）',
        '想启用：后台 →「站点设置 → 扫描+跳转」填「跳转目标地址」，或上传「群 / 公众号二维码」');
} else {
    $rWarn = array();
    if ($rTarget === '' && $rMode === 'jump_scan') { $rWarn[] = '没填跳转地址，实际会降级成「仅扫码」'; }
    if ($rQr === '' && $rMode !== 'jump_only')     { $rWarn[] = '没传二维码，扫码模式下无法展示图片'; }
    if ($rQr === '' && $rMode === 'scan_only')     { $rWarn[] = '仅扫码却没有二维码 —— 此时不会拦截任何人，等于关闭'; }
    $site10[] = ml_item('PC 端跳转 / 扫码', ($rWarn ? 'warn' : 'ok'),
        '模式：' . (isset($rModeTx[$rMode]) ? $rModeTx[$rMode] : $rMode)
        . ' · 跳转地址：' . ($rTarget !== '' ? ml_h($rTarget) : '未填')
        . ' · 二维码：' . ($rQr !== '' ? '已上传' : '未上传')
        . ' · 手机端：' . ($rMobile === '1' ? '同样处理' : '直接进站')
        . ' · 提示语：' . ml_h($rQrTip !== '' ? $rQrTip : '（默认）请用手机扫码访问本站'),
        ($rWarn ? implode('；', $rWarn) : '只拦首页入口，内页 /uisc/*.html 不会被拦，不影响搜索引擎收录'));
}

/* 10.6 前台模板 / 顶部导航 / 榜单样式 / 最新列表 */
$tpl      = ml_eff($OVR, $REDC, 'tpl_switch', 'tpl', 'classic');
$tplMd    = ml_eff($OVR, $REDC, 'tpl_simple_mode', 'simple_mode', 'auto');
$navRaw   = ml_eff($OVR, $REDC, 'nav_show', 'nav_show', 'home,latest');
$navExt   = ml_eff($OVR, $REDC, 'nav_external', 'nav_external', '');
$rankSt   = ml_eff($OVR, $REDC, 'list_rank_style', 'rank_style', 'grid');
$latestOn = ml_eff($OVR, $REDC, 'list_latest', 'latest', '1');
$navCnt = 0;
foreach (preg_split('/[,，、;；]+/u', $navRaw) as $nav1) { if (trim($nav1) !== '') { $navCnt++; } }
$extCnt = 0;
foreach (preg_split('/[\r\n]+/', $navExt) as $nav2) {
    $nav2 = trim($nav2);
    if ($nav2 !== '' && strpos($nav2, '|') !== false) { $extCnt++; }
}
$site10[] = ml_item('前台模板 / 明暗 / 顶部导航', 'info',
    '模板：' . ($tpl === 'simple' ? '简约' : '经典')
    . ' · 明暗：' . ($tplMd === 'auto' ? '跟随系统' : ($tplMd === 'dark' ? '暗色' : '亮色'))
    . ' · 导航项 ' . ($navCnt ? ($navCnt . ' 个') : '无（顶栏不显示导航）')
    . ($extCnt ? (' · 自定义外链 ' . $extCnt . ' 条') : '')
    . ' · 榜单样式：' . ($rankSt === 'list' ? '无图（纯文字列表）' : '有图（卡片网格）')
    . ' · 首页最新列表：' . ($latestOn === '1' ? '开启' : '关闭'),
    '后台 →「站点设置 → 扫描+跳转」下半部分；全部留空都会走内置默认，不会白屏');

/* 10.6b 响应式自适应（手机 / 平板 / 大屏 / 深色 / 打印） */
$vMedia = 0; $aMedia = 0; $respNote = array();
$vFile = __DIR__ . '/core/Views.php';
if (is_file($vFile)) {
    $vSrc = @file_get_contents($vFile);
    if ($vSrc !== false && $vSrc !== '') {
        $vMedia = substr_count($vSrc, '@media');
        if (strpos($vSrc, 'viewport-fit=cover') !== false)        { $respNote[] = '刘海安全区'; }
        if (strpos($vSrc, 'safe-area-inset-bottom') !== false)    { $respNote[] = '底部横条避让'; }
        if (strpos($vSrc, 'prefers-color-scheme:dark') !== false) { $respNote[] = '跟随系统深色'; }
        if (strpos($vSrc, 'prefers-reduced-motion') !== false)    { $respNote[] = '减少动画'; }
        if (strpos($vSrc, '@media print') !== false)              { $respNote[] = '打印排版'; }
    }
}
$aFile = __DIR__ . '/admin/index.php';
if (is_file($aFile)) {
    $aSrc = @file_get_contents($aFile);
    if ($aSrc !== false && $aSrc !== '') { $aMedia = substr_count($aSrc, '@media'); }
}
$site10[] = ml_item('响应式自适应（手机 / 平板 / 大屏）',
    ($vMedia > 0 && $aMedia > 0) ? 'ok' : 'warn',
    '前台断点 ' . $vMedia . ' 个 · 后台断点 ' . $aMedia . ' 个'
    . ($respNote ? (' · ' . implode(' · ', $respNote)) : ''),
    '前台覆盖 380 / 560 / 768 / 1024 / 1400 / 1800px 与手机横屏；后台覆盖 380 / 560 / 768 / 1024px。数量为 0 说明样式文件被改动过，建议用升级包覆盖回官方版本');

/* 10.7 网盘账号（Cookie / 默认转存目录 / 临时资源目录） */
$panAll = array('quark' => '夸克', 'aliyun' => '阿里', 'baidu' => '百度', 'uc' => 'UC', 'xunlei' => '迅雷', 'guangya' => '光鸭');
$panGrpRaw = ml_eff($OVR, $PANC, 'pan_group', 'group', '');
$panSel = array();
foreach (preg_split('/[,，、;；\s]+/u', $panGrpRaw) as $pg) {
    $pg = trim($pg);
    if ($pg !== '' && isset($panAll[$pg])) { $panSel[] = $pg; }
}
if (!$panSel) { $panSel = array_keys($panAll); }
$panNames = array();
$panCk = array();
foreach ($panSel as $pc) {
    $panNames[] = $panAll[$pc];
    $ck = ml_eff($OVR, $PANC, 'pan_' . $pc . '_cookie', $pc . '_cookie', '');
    if ($ck !== '') { $panCk[] = $panAll[$pc]; }
}
$site10[] = ml_item('网盘账号（网盘链接页）', 'info',
    '管理 ' . count($panSel) . ' 种：' . implode('、', $panNames)
    . ' · 已填 Cookie：' . ($panCk ? implode('、', $panCk) : '无'),
    '后台 →「站点设置 → 网盘链接」按网盘分组填 Cookie / 默认转存目录 / 临时资源目录。本系统只保存与检测，不会主动登录你的网盘账号');

/* 10.8 接口配置 PanSou（采集后自动补网盘下载地址） */
$psEnable = ml_eff($OVR, $PSC, 'pansou_enable', 'enable', '0');
$psMode   = ml_eff($OVR, $PSC, 'pansou_mode', 'mode', 'local');
$psUrl    = ml_eff($OVR, $PSC, 'pansou_url', 'url', 'http://localhost:8888');
$psLine   = ml_eff($OVR, $PSC, 'pansou_line', 'line', '');
$psTypes  = ml_eff($OVR, $PSC, 'pansou_types', 'types', '');
$psAuto   = ml_eff($OVR, $PSC, 'pansou_auto', 'auto', '1');
$psPolicy = ml_eff($OVR, $PSC, 'pansou_policy', 'policy', 'exact');
$psMatch  = ml_eff($OVR, $PSC, 'pansou_match', 'match', '80');
$psMax    = ml_eff($OVR, $PSC, 'pansou_max', 'max', '5');
$psDelay  = ml_eff($OVR, $PSC, 'pansou_delay', 'delay', '1000');
$psTtl    = ml_eff($OVR, $PSC, 'pansou_ttl', 'ttl', '720');
$psPolTx  = array('exact' => '仅标题高度匹配才自动写入', 'write' => '只要搜到就自动写入', 'pending' => '一律只进待确认队列');
$psTypeCnt = 0;
foreach (preg_split('/[,，、;；]+/u', $psTypes) as $pt) { if (trim($pt) !== '') { $psTypeCnt++; } }
$site10[] = ml_item('PanSou 接口配置', ($psEnable === '1') ? 'ok' : 'info',
    ($psEnable === '1' ? '已启用' : '未启用')
    . ' · ' . ($psMode === 'remote' ? '远程部署' : '本机部署') . ' · ' . ml_h($psUrl)
    . ($psLine !== '' ? (' · 线路名 ' . ml_h($psLine)) : '')
    . ' · 网盘类型 ' . ($psTypeCnt ? ($psTypeCnt . ' 种') : '全部'),
    ($psEnable === '1') ? '地址只填服务根（不要带 /api/search）。与站点同机部署一般填 http://127.0.0.1:8888'
                        : '要「采集后自动补网盘下载地址」必须先启用并填好接口地址');

$psTest = null;
$psPend = null;
$psCalc = false;
if ($psEnable === '1' && $panLibOk && is_file($CFG_FILE)) {
    try {
        require_once $ROOT . '/core/PanSou.php';
        if ($NO_HTTP) {
            $site10[] = ml_item('PanSou 连通性', 'info', '已跳过（?nohttp=1）', '去掉 nohttp 参数即可真实探测 /api/health');
        } else {
            $psTest = \Core\PanSou::test();
            $site10[] = ml_item('PanSou 连通性', ($psTest['ok'] ? 'ok' : 'fail'),
                'HTTP ' . ($psTest['http'] > 0 ? $psTest['http'] : '无响应') . ' · ' . ml_h($psTest['message'])
                . ' · 耗时 ' . (int) $psTest['ms'] . ' ms' . ($psTest['version'] !== '' ? (' · 版本 ' . ml_h($psTest['version'])) : ''),
                ($psTest['ok'] ? '采集入库后会自动拿标题去搜网盘链接并写入下载地址'
                               : '接口不通：确认 PanSou 已在服务器跑起来（默认 8888 端口）、安全组/防火墙已放行、地址不要带 /api/search'));
        }
        $psPend = \Core\PanSou::pendingCount();
        $psCalc = true;
    } catch (\Exception $e) {
        $site10[] = ml_item('PanSou 连通性', 'warn', '调用失败：' . ml_h($e->getMessage()),
            'core/PanSou.php 可能没上传，或服务器 PHP 缺少 curl 扩展');
    }
} else {
    $site10[] = ml_item('PanSou 连通性', 'info', ($psEnable === '1' ? 'core/PanSou.php 缺失，无法测试' : '未启用，跳过'),
        '启用 PanSou 后这里会真实请求一次 /api/health');
}

$site10[] = ml_item('采集后自动补地址', (($psEnable === '1' && $psAuto === '1') ? 'ok' : 'info'),
    '自动补地址：' . ($psAuto === '1' ? '开启' : '关闭')
    . ' · 写入策略：' . (isset($psPolTx[$psPolicy]) ? $psPolTx[$psPolicy] : $psPolicy)
    . ' · 最低相似度 ' . (int) $psMatch . '% · 每条最多 ' . (int) $psMax . ' 个链接'
    . ' · 两次搜索间隔 ' . (int) $psDelay . ' ms · 缓存 ' . ($psTtl === '0' ? '关闭' : ((int) $psTtl . ' 分钟')),
    '开启后「同步采集」跑完会接着去搜网盘链接；相似度不够的会进「待确认」队列，人工过一遍再写入，避免写错链接');
if ($psCalc) {
    $site10[] = ml_item('待确认队列', ($psPend > 0 ? 'info' : 'ok'),
        ($psPend > 0 ? ((int) $psPend . ' 条待人工确认，可在后台「站点设置 → 接口配置」里一键写入或丢弃') : '队列为空'),
        ($psPend > 0 ? '这些是搜到但相似度没到阈值的候选，确认无误再写入' : ''));
} else {
    $site10[] = ml_item('待确认队列', 'info', '未启用 PanSou，跳过');
}

$S[] = ['title' => '10 · 站点设置 / SEO / 网盘接口', 'icon' => '🎛', 'items' => $site10];

/* ---------- 11. 注册与资源请求 ---------- */
/* 说明：本组只读 app_settings 的手算生效值（$OVR 叠 $CFG），
   不 require core/User.php —— 保持自检页「不触发任何业务逻辑」的约定。 */
$req11 = [];

$req11Keys = ['req_open', 'req_logon', 'req_audit', 'req_verify', 'req_guest_view',
              'req_public', 'req_contact', 'req_nav', 'req_daily', 'req_mintitle',
              'req_page_title', 'req_page_intro', 'req_notice'];
$req11Def = [
    'req_open' => '1', 'req_logon' => '1', 'req_audit' => '0', 'req_verify' => '1',
    'req_guest_view' => '1', 'req_public' => '1', 'req_contact' => '1', 'req_nav' => '1',
    'req_daily' => '5', 'req_mintitle' => '2',
    'req_page_title' => '资源请求', 'req_page_intro' => '', 'req_notice' => '',
];
$req11Eff = [];
foreach ($req11Keys as $req11K) {
    if (isset($OVR[$req11K]) && $OVR[$req11K] !== '') { $req11Eff[$req11K] = (string) $OVR[$req11K]; continue; }
    $req11C = substr($req11K, 4);   // req_open → open（与 Core\Settings::reqMap() 一一对应）
    $req11F = (isset($CFG['req'][$req11C]) ? $CFG['req'][$req11C] : '');
    $req11Eff[$req11K] = ($req11F === '' || $req11F === null) ? $req11Def[$req11K] : (string) $req11F;
}
$req11IsOn = function ($k) use ($req11Eff) { return (isset($req11Eff[$k]) && $req11Eff[$k] === '1'); };

$req11[] = ml_item('注册功能', $req11IsOn('req_open') ? 'ok' : 'info',
    $req11IsOn('req_open') ? '已开放注册' : '注册已关闭（只有已有账号能登录）',
    $req11IsOn('req_open') ? '' : '想开放：后台「站点设置 → 资源请求」→「是否开放注册」选“开放注册”');
$req11[] = ml_item('提交是否要求登录', $req11IsOn('req_logon') ? 'ok' : 'info',
    $req11IsOn('req_logon') ? '必须注册并登录后才能提交' : '游客也能提交资源请求', '');
$req11[] = ml_item('防风控设置', 'info',
    '算术验证码：' . ($req11IsOn('req_verify') ? '开' : '关')
    . ' · 注册审核：' . ($req11IsOn('req_audit') ? '需管理员审核' : '免审核')
    . ' · 每人每天上限：' . ($req11Eff['req_daily'] === '0' ? '不限' : ((int) $req11Eff['req_daily'] . ' 条'))
    . ' · 名称最少 ' . (int) $req11Eff['req_mintitle'] . ' 字',
    '除以上设置外，还固定生效两道闸：同 IP 一小时最多注册 3 个账号、同 IP 十分钟最多提交 15 条请求');
$req11[] = ml_item('可见性与文案', 'info',
    '游客浏览列表：' . ($req11IsOn('req_guest_view') ? '允许' : '仅登录可见')
    . ' · 展示「大家都在找」：' . ($req11IsOn('req_public') ? '是' : '否')
    . ' · 收集联系方式：' . ($req11IsOn('req_contact') ? '是' : '否')
    . ' · 导航入口：' . ($req11IsOn('req_nav') ? '显示' : '隐藏')
    . ' · 页面名：' . ($req11Eff['req_page_title'] !== '' ? $req11Eff['req_page_title'] : '资源请求'), '');

/* 数据表与数据概况 */
if ($pdo) {
    try {
        $req11HasU = (bool) $pdo->query("SHOW TABLES LIKE 'app_users'")->fetchAll();
        $req11HasR = (bool) $pdo->query("SHOW TABLES LIKE 'app_requests'")->fetchAll();
        if (!$req11HasU && !$req11HasR) {
            $req11[] = ml_item('用户 / 请求数据表', 'info', '尚未创建（属正常）',
                '首次打开 /user/register 或保存一次「资源请求」设置时自动建表；也可以手动导入 sql/migrate_users.sql');
        } else {
            $req11Need = [
                'app_users'    => ['username', 'pass_hash', 'status', 'fail_count', 'lock_until', 'last_login', 'req_count'],
                'app_requests' => ['user_id', 'title', 'status', 'admin_note', 'contact', 'item_id'],
            ];
            foreach ($req11Need as $req11Tb => $req11Cols) {
                if (!$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($req11Tb))->fetchAll()) {
                    $req11[] = ml_item('表 ' . $req11Tb, 'fail', '不存在', '导入 sql/migrate_users.sql，或打开一次 /user/register 让它自动建表');
                    continue;
                }
                $req11Have = [];
                foreach ($pdo->query("SHOW COLUMNS FROM `{$req11Tb}`")->fetchAll() as $req11Cc) {
                    $req11Have[] = strtolower($req11Cc['Field']);
                }
                $req11Miss = array_values(array_diff($req11Cols, $req11Have));
                $req11[] = ml_item('表 ' . $req11Tb, $req11Miss ? 'warn' : 'ok',
                    $req11Miss ? ('缺列：' . implode('、', $req11Miss)) : (count($req11Cols) . ' 个关键字段齐全'),
                    $req11Miss ? '覆盖升级后缺列会由程序自动补上；若一直补不上，请检查数据库用户是否有 ALTER 权限' : '');
            }
            $req11Users = $req11Wait = $req11Reqs = $req11Pend = $req11Today = 0;
            if ($req11HasU) {
                $req11Users = (int) $pdo->query('SELECT COUNT(*) c FROM app_users')->fetch()['c'];
                $req11Wait  = (int) $pdo->query('SELECT COUNT(*) c FROM app_users WHERE status=2')->fetch()['c'];
            }
            if ($req11HasR) {
                $req11Reqs = (int) $pdo->query('SELECT COUNT(*) c FROM app_requests')->fetch()['c'];
                $req11Pend = (int) $pdo->query("SELECT COUNT(*) c FROM app_requests WHERE status='pending'")->fetch()['c'];
                $req11St = $pdo->prepare('SELECT COUNT(*) c FROM app_requests WHERE created_at >= ?');
                $req11St->execute([date('Y-m-d 00:00:00')]);
                $req11Today = (int) $req11St->fetch()['c'];
            }
            $req11Hint = '';
            if ($req11Wait > 0)      { $req11Hint = '有 ' . $req11Wait . ' 个账号在等审核：后台「资源请求 → 注册用户」点「通过/启用」'; }
            elseif ($req11Pend > 0)  { $req11Hint = '有 ' . $req11Pend . ' 条待处理请求：后台「资源请求 → 请求列表」'; }
            $req11[] = ml_item('用户 / 请求数据', ($req11Wait > 0 ? 'warn' : 'ok'),
                '注册用户 ' . $req11Users . ' 位（待审核 ' . $req11Wait . '）'
                . ' · 资源请求 ' . $req11Reqs . ' 条（待处理 ' . $req11Pend . '，今日新增 ' . $req11Today . '）',
                $req11Hint);
        }
    } catch (\Exception $e) {
        $req11[] = ml_item('注册数据', 'warn', $e->getMessage());
    }
} else {
    $req11[] = ml_item('注册数据', 'info', '数据库未连接，跳过统计', '先解决第 4 组的数据库问题');
}

/* 密码散列：账号安全的地基 */
$req11HasPw = (function_exists('password_hash') && function_exists('password_verify'));
$req11[] = ml_item('密码加密', $req11HasPw ? 'ok' : 'fail',
    $req11HasPw ? ('已启用（password_hash，算法标识 ' . (defined('PASSWORD_DEFAULT') ? PASSWORD_DEFAULT : '') . '）')
                : ('PHP 缺少 password_hash（需要 5.5+，当前 ' . PHP_VERSION . '）'),
    $req11HasPw ? '用户密码只存散列、绝不存明文；即使数据库被拖走也无法直接还原密码'
                : 'PHP 版本过低，请切换到 5.6 以上的 PHP');

/* 会话目录：宝塔上最常见的「登录后又变回未登录」原因 */
$req11Sp = (string) ini_get('session.save_path');
if (strpos($req11Sp, ';') !== false) { $req11SpA = explode(';', $req11Sp); $req11Sp = trim((string) end($req11SpA)); }
$req11SpOk = ($req11Sp === '') ? true : (is_dir($req11Sp) && is_writable($req11Sp));
$req11[] = ml_item('会话（登录状态）', $req11SpOk ? 'ok' : 'fail',
    '会话目录：' . ($req11Sp !== '' ? $req11Sp : '（未设置，用系统临时目录）') . ' · 自动开启 = ' . (ini_get('session.auto_start') ? '是' : '否'),
    $req11SpOk ? '' : '会话目录不可写 → 用户会「登录成功后又变回未登录」。改成可写目录或 chown 给 www 用户');

/* 代码是否就位 */
$req11Files = ['core/User.php', 'core/UserViews.php', 'user.php'];
$req11Lack  = [];
foreach ($req11Files as $req11Fn) { if (!is_file($ROOT . '/' . $req11Fn)) { $req11Lack[] = $req11Fn; } }
$req11Idx   = (string) @file_get_contents($ROOT . '/index.php');
$req11Route = (strpos($req11Idx, "require __DIR__ . '/user.php';") !== false);
$req11[] = ml_item('前台代码是否就位（v1.6.2 起）', ($req11Lack || !$req11Route) ? 'fail' : 'ok',
    $req11Lack ? ('缺少文件：' . implode('、', $req11Lack))
               : ('core/User.php · core/UserViews.php · user.php 齐备' . ($req11Route ? ' · 路由已挂载' : ' · index.php 未挂载 /user 与 /request')),
    $req11Lack ? '用最新整包（或增量升级包）覆盖站点文件即可' : '');

/* 真实 HTTP 探测（nohttp=1 跳过） */
if (!$NO_HTTP) {
    $req11Probe = [
        ['/user/register', '注册页',     '200 且渲染出注册表单'],
        ['/request',       '资源请求页', '200（未登录时给出「请先登录」引导）'],
    ];
    foreach ($req11Probe as $req11Pb) {
        $req11R  = ml_http($BASE . $req11Pb[0], 8);
        $req11Ok = ($req11R['ok'] && $req11R['code'] === 200);
        $req11B  = (string) $req11R['body'];
        $req11Mark = (strpos($req11B, 'u-card') !== false);
        $req11[] = ml_item('前台页面 · ' . $req11Pb[1] . '  ' . $req11Pb[0], ($req11Ok && $req11Mark) ? 'ok' : 'warn',
            $req11Ok ? ('HTTP 200 · ' . strlen($req11B) . ' 字节' . ($req11Mark ? '' : '（未识别到页面特征，可能被别的内容占用）'))
                     : ('请求失败：' . ($req11R['err'] ?: '无响应')),
            $req11Ok ? '' : ('服务器若禁止回环/自请求，本项可忽略；但请确认浏览器能打开 ' . $req11Pb[0] . '（期望：' . $req11Pb[2] . '）'));
    }
} else {
    $req11[] = ml_item('前台页面探测', 'info', '已跳过（nohttp=1）');
}

$S[] = ['title' => '11 · 注册与资源请求', 'icon' => '🙋', 'items' => $req11];

/* ============================ 汇总 ============================ */
foreach ($S as $sec) {
    foreach ($sec['items'] as $it) { $SUM[$it['status']] = ((isset($SUM[$it['status']]) ? $SUM[$it['status']] : 0)) + 1; }
}
$TOTAL = array_sum($SUM);
$score = $TOTAL ? (int) round(($SUM['ok'] + $SUM['info'] * 0.5) / $TOTAL * 100) : 0;
$verdict = ($SUM['fail'] > 0) ? 'fail' : ($SUM['warn'] > 0 ? 'warn' : 'ok');
$elapsed = round((microtime(true) - $T0) * 1000);

if ($IS_JSON) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'    => $SUM['fail'] === 0,
        'verdict'    => $verdict,
        'score'      => $score,
        'summary'    => $SUM,
        'base'       => $BASE,
        'php'        => PHP_VERSION,
        'elapsed_ms' => $elapsed,
        'checked_at' => date('Y-m-d H:i:s'),
        'sections'   => $S,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$theme = ['ok' => '#16a34a', 'warn' => '#f59e0b', 'fail' => '#ef4444', 'info' => '#3b82f6'];
$themeBg = ['ok' => '#dcfce7', 'warn' => '#fef3c7', 'fail' => '#fee2e2', 'info' => '#dbeafe'];
$themeTx = ['ok' => '通过', 'warn' => '注意', 'fail' => '失败', 'info' => '信息'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>媒资资料库 · 环境自检</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#f1f5f9;color:#0f172a;line-height:1.6;padding-bottom:60px}
.wrap{max-width:1080px;margin:0 auto;padding:22px}
.hero{background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777);border-radius:20px;padding:28px 30px;color:#fff;box-shadow:0 18px 44px rgba(79,70,229,.32);position:relative;overflow:hidden}
.hero:after{content:"";position:absolute;right:-40px;top:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.13)}
.hero h1{font-size:23px;letter-spacing:.4px;margin-bottom:6px}
.hero p{font-size:13px;opacity:.9;word-break:break-all}
.chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;position:relative;z-index:1}
.chip{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:999px;padding:7px 15px;font-size:13px;font-weight:600;backdrop-filter:blur(4px)}
.chip b{font-size:15px}
.bar{height:7px;border-radius:99px;background:rgba(255,255,255,.25);margin-top:16px;overflow:hidden;position:relative;z-index:1}
.bar i{display:block;height:100%;background:linear-gradient(90deg,#4ade80,#facc15);border-radius:99px;transition:width .5s}
.sec{background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-top:18px;box-shadow:0 4px 18px rgba(15,23,42,.05);overflow:hidden}
.sec>h2{font-size:15px;padding:15px 20px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fbfcfe,#f8fafc);display:flex;align-items:center;gap:9px;color:#1e293b}
.sec>h2 span.n{background:#eef2ff;color:#4f46e5;border-radius:8px;padding:2px 9px;font-size:12px;font-weight:700}
.row{display:grid;grid-template-columns:88px 260px 1fr;gap:12px;padding:12px 20px;border-bottom:1px solid #f4f6fa;align-items:start}
.row:last-child{border-bottom:0}
.row:hover{background:#fafbfd}
.badge{display:inline-block;text-align:center;font-size:12px;font-weight:700;padding:3px 0;width:58px;border-radius:7px}
.nm{font-size:13.5px;font-weight:600;color:#334155;word-break:break-all}
.dt{font-size:13px;color:#475569;word-break:break-word}
.dt pre{background:#0f172a;color:#a5f3fc;padding:10px 12px;border-radius:9px;font-size:12px;overflow-x:auto;margin-top:6px;font-family:Consolas,Monaco,monospace;white-space:pre-wrap}
.hint{display:inline-block;margin-top:6px;font-size:12.5px;color:#b45309;background:#fffbeb;border-left:3px solid #f59e0b;padding:6px 10px;border-radius:0 8px 8px 0}
code{background:#f1f5f9;padding:1px 6px;border-radius:5px;font-family:Consolas,Monaco,monospace;font-size:12.5px;color:#be123c}
.foot{text-align:center;color:#94a3b8;font-size:12.5px;margin-top:22px;line-height:2}
.foot a{color:#6366f1;text-decoration:none}
.acts{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.btn{display:inline-block;padding:9px 16px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;color:#334155;font-size:13px;text-decoration:none;font-weight:600;box-shadow:0 2px 8px rgba(15,23,42,.05)}
.btn.p{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:0}
@media(max-width:720px){.row{grid-template-columns:76px 1fr;}.row .dt{grid-column:2}}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <h1>媒资资料库 · 环境自检</h1>
    <p><?= ml_h($BASE) ?>　·　PHP <?= ml_h(PHP_VERSION) ?>　·　<?= ml_h(date('Y-m-d H:i:s')) ?></p>
    <div class="chips">
      <div class="chip">✅ 通过 <b><?= $SUM['ok'] ?></b></div>
      <div class="chip">⚠️ 注意 <b><?= $SUM['warn'] ?></b></div>
      <div class="chip">❌ 失败 <b><?= $SUM['fail'] ?></b></div>
      <div class="chip">ℹ️ 信息 <b><?= $SUM['info'] ?></b></div>
      <div class="chip">健康度 <b><?= $score ?></b>/100</div>
      <div class="chip"><?= $verdict === 'ok' ? '🎉 一切正常' : ($verdict === 'warn' ? '⚠️ 有注意事项' : '⛔ 存在待修复项') ?></div>
    </div>
    <div class="bar"><i style="width:<?= $score ?>%"></i></div>
  </div>

  <div class="acts">
    <a class="btn p" href="?<?= $NEED_AUTH ? 'token=' . urlencode($PROVIDED) . '&' : '' ?>r=<?= time() ?>">🔄 重新检测</a>
    <a class="btn" href="?<?= $NEED_AUTH ? 'token=' . urlencode($PROVIDED) . '&' : '' ?>format=json" target="_blank">{} 查看 JSON</a>
    <a class="btn" href="?<?= $NEED_AUTH ? 'token=' . urlencode($PROVIDED) . '&' : '' ?>nohttp=1">⚡ 快速模式（跳过网络）</a>
    <a class="btn" href="?<?= $NEED_AUTH ? 'token=' . urlencode($PROVIDED) . '&' : '' ?>write=1">🧪 写入测试（写库后自动删）</a>
    <a class="btn" href="<?= ml_h($BASE) ?>/admin" target="_blank">🛠 打开后台</a>
    <a class="btn" href="<?= ml_h($BASE) ?>/" target="_blank">🏠 打开主页</a>
  </div>

<?php foreach ($S as $sec): ?>
  <div class="sec">
    <h2><?= $sec['icon'] ?> <?= ml_h($sec['title']) ?> <span class="n"><?= count($sec['items']) ?></span></h2>
    <?php foreach ($sec['items'] as $it):
        $st = $it['status']; ?>
      <div class="row">
        <div><span class="badge" style="background:<?= $themeBg[$st] ?>;color:<?= $theme[$st] ?>"><?= $themeTx[$st] ?></span></div>
        <div class="nm"><?= ml_h($it['name']) ?></div>
        <div class="dt">
          <?php if (strpos($it['detail'], "\n") !== false || strpos($it['hint'], "\n") !== false): ?>
            <?= ml_h($it['detail']) ?>
            <?php if ($it['hint']): ?><pre><?= ml_h($it['hint']) ?></pre><?php endif; ?>
          <?php else: ?>
            <?= $it['detail'] ?>
            <?php if ($it['hint']): ?><div class="hint"><?= ml_h($it['hint']) ?></div><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

  <div class="foot">
    检测耗时 <?= $elapsed ?> ms　·　共 <?= $TOTAL ?> 项　·　<a href="?<?= $NEED_AUTH ? 'token=' . urlencode($PROVIDED) . '&' : '' ?>format=json">JSON 接口</a><br>
    🔒 本页已启用「未授权即 404」保护，扫描器无法确认它存在。全部确认无误后，仍建议删除或改名 <b>check.php</b>，彻底消除暴露面。
  </div>

</div>
</body>
</html>
