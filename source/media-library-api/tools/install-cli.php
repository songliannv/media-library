<?php
/**
 * 媒资资料库 · 命令行安装器（tools/install-cli.php）
 * ==================================================================
 * 与网页安装器的关系（v1.6.3 起）：
 *   网页安装器 install.php 已恢复，但它是**带自锁**的 —— 只要站点已安装
 *   （config/install.lock 存在，或 config/config.php 的 db 段已填真值），
 *   它对所有人返回 404，没人能借此重装站点。本脚本则是"只能命令行执行"
 *   的入口，公网访问一律 404，适合重装 / 迁移 / 无 web 环境。
 *   两者共用同一套安装内核 core/Installer.php（建表、生成配置、写安装锁），
 *   行为完全一致，不再是两份实现。
 *
 * 用法（宝塔 → 终端，先 cd 到站点根目录）：
 *   php tools/install-cli.php
 *       交互式，逐步输入数据库信息（推荐）
 *
 *   php tools/install-cli.php --db=media_library --user=media_lib --pass=xxxx
 *       参数式，适合脚本/批量部署
 *
 *   常用附加参数：
 *     --host=127.0.0.1   数据库地址（默认 127.0.0.1）
 *     --port=3306        端口（默认 3306）
 *     --admin=xxx        管理员账号（登录后台用，必填）
 *     --adminpass=xxx    管理员密码（至少 6 位，必填）
 *     --tmdb=xxx         TMDB API Key（可留空，装完也能在后台改）
 *     --rawg=xxx         RAWG API Key（可留空）
 *     --force            已安装过也强制重装（会先备份原 config.php）
 *     --yes              全部用默认值/参数值，不交互
 *
 * 本脚本会：测试数据库 → 自动建表（已存在则跳过）→ 生成 config/config.php
 *          与 config/pan_types.php（写前自动备份）→ 写 config/install.lock。
 *
 * 兼容 PHP 5.6 ~ 8.2。公网不可访问：非命令行运行会直接返回 404。
 */

/* ---------------- 只允许命令行运行 ---------------- */
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../core/Guard.php';
    ml_guard_deny();
}

require_once __DIR__ . '/../core/Guard.php';
require_once __DIR__ . '/../core/InstallGuard.php';
require_once __DIR__ . '/../core/Installer.php';

$ROOT      = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$CFG_FILE  = $ROOT . '/config/config.php';
$PAN_FILE  = $ROOT . '/config/pan_types.php';
$LOCK_FILE = $ROOT . '/config/install.lock';

/* ================================================================== */
/* 小工具                                                             */
/* ================================================================== */

function cli_out($s = '')
{
    fwrite(STDOUT, $s . "\n");
}

function cli_err($s)
{
    fwrite(STDERR, $s . "\n");
}

function cli_ask($label, $default = '')
{
    $tip = $default === '' ? ' : ' : ' [' . $default . '] : ';
    fwrite(STDOUT, $label . $tip);
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $line = trim($line);
    return $line === '' ? $default : $line;
}

/* 以下小工具、建表 SQL、生成配置、写文件全部复用 core/Installer.php，
   保证网页安装器与命令行安装器的行为完全一致（不再各写一份实现）。 */

function cli_rand_token()
{
    return mlinst_rand_token();
}

function cli_q($v)
{
    return mlinst_q($v);
}

function cli_sql()
{
    return mlinst_tables();
}

function cli_config_content($db, $tmdbKey, $rawgKey, $adminToken, $adapters)
{
    return mlinst_config_content($db, $tmdbKey, $rawgKey, $adminToken, $adapters);
}

function cli_pan_content($list)
{
    return mlinst_pan_content($list);
}

function cli_write($file, $content)
{
    return mlinst_write($file, $content);
}

/* 解析参数                                                           */
/* ================================================================== */

$ARGS = array();
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--') === 0) {
        $p  = substr($a, 2);
        $eq = strpos($p, '=');
        if ($eq === false) {
            $ARGS[$p] = true;
        } else {
            $ARGS[substr($p, 0, $eq)] = substr($p, $eq + 1);
        }
    }
}
$FORCE = !empty($ARGS['force']);
$YES   = !empty($ARGS['yes']);

function cli_arg($ARGS, $k, $d = '')
{
    return (isset($ARGS[$k]) && $ARGS[$k] !== true) ? (string) $ARGS[$k] : $d;
}

/* ================================================================== */
/* 开场                                                               */
/* ================================================================== */

cli_out('');
cli_out('=====================================================');
cli_out('  媒资资料库 · 命令行安装器  (tools/install-cli.php)');
cli_out('=====================================================');
cli_out('');

/* ---------------- 环境检查 ---------------- */
$phpOk = version_compare(PHP_VERSION, '5.6.0', '>=');
if (!$phpOk) {
    cli_err('[×] PHP 版本过低：' . PHP_VERSION . '，需要 5.6 及以上。');
    exit(1);
}
foreach (array('pdo_mysql', 'curl', 'json') as $ext) {
    if (!extension_loaded($ext)) {
        cli_err('[×] 缺少 PHP 扩展：' . $ext . '（宝塔 → 网站 → 设置 → PHP → 安装扩展）');
        exit(1);
    }
}
cli_out('[√] PHP ' . PHP_VERSION . '，pdo_mysql / curl / json 扩展齐全');

$cfgDir = $ROOT . '/config';
if (!is_dir($cfgDir)) {
    @mkdir($cfgDir, 0755, true);
}
if (!is_writable($cfgDir)) {
    cli_err('[×] 目录不可写：' . $cfgDir . '（宝塔里给站点目录 755 属主 www）');
    exit(1);
}
cli_out('[√] config/ 目录可写');

/* ---------------- 已安装判断 ---------------- */
$state = ml_install_state($ROOT);
if (!empty($state['installed']) && !$FORCE) {
    cli_out('');
    cli_out('[!] 检测到本站**已经安装过**（原因：' . $state['reason'] . '）。');
    cli_out('    如果只是想改数据库或密钥，直接改 config/config.php 或在后台「系统设置」里改即可。');
    cli_out('    确实要重装（会覆盖配置，原文件自动备份为 config.php.bak-日期）：');
    cli_out('        php tools/install-cli.php --force');
    cli_out('');
    exit(0);
}

/* ---------------- 采集参数 ---------------- */
cli_out('');
if ($YES) {
    $in = array(
        'host'  => cli_arg($ARGS, 'host', '127.0.0.1'),
        'port'  => cli_arg($ARGS, 'port', '3306'),
        'db'    => cli_arg($ARGS, 'db', ''),
        'user'  => cli_arg($ARGS, 'user', ''),
        'pass'  => cli_arg($ARGS, 'pass', ''),
        'admin_user' => cli_arg($ARGS, 'admin', ''),
        'admin_pass' => cli_arg($ARGS, 'adminpass', ''),
        'tmdb'  => cli_arg($ARGS, 'tmdb', ''),
        'rawg'  => cli_arg($ARGS, 'rawg', ''),
    );
    if ($in['db'] === '' || $in['user'] === '') {
        cli_err('[×] --yes 模式下必须提供 --db= 与 --user=');
        exit(1);
    }
    if ($in['admin_user'] === '' || strlen($in['admin_pass']) < 6) {
        cli_err('[×] --yes 模式下必须提供 --admin=账号 与 --adminpass=密码（密码至少 6 位）');
        exit(1);
    }
} else {
    cli_out('请填写数据库信息与管理员账号（直接回车用括号里的默认值）：');
    cli_out('');
    $in = array(
        'host'  => cli_ask('数据库地址        ', cli_arg($ARGS, 'host', '127.0.0.1')),
        'port'  => cli_ask('数据库端口        ', cli_arg($ARGS, 'port', '3306')),
        'db'    => cli_ask('数据库名 dbname   ', cli_arg($ARGS, 'db', '')),
        'user'  => cli_ask('数据库用户名      ', cli_arg($ARGS, 'user', '')),
        'pass'  => cli_ask('数据库密码        ', cli_arg($ARGS, 'pass', '')),
    );
    cli_out('');
    cli_out('管理员账号（登录后台用，就是你自己的账号）：');
    $in['admin_user'] = cli_ask('  账号            ', cli_arg($ARGS, 'admin', ''));
    $in['admin_pass'] = cli_ask('  密码(至少6位)   ', cli_arg($ARGS, 'adminpass', ''));
    cli_out('');
    $in['tmdb'] = cli_ask('TMDB Key (可空)   ', cli_arg($ARGS, 'tmdb', ''));
    $in['rawg'] = cli_ask('RAWG Key (可空)   ', cli_arg($ARGS, 'rawg', ''));
}
cli_out('');

if ($in['db'] === '' || $in['user'] === '') {
    cli_err('[×] 数据库名与用户名不能为空。');
    exit(1);
}
if ($in['admin_user'] === '') {
    cli_err('[×] 管理员账号不能为空（进后台靠它）。');
    exit(1);
}
if (strpos($in['admin_user'], '@') !== false) {
    /* 带 @ 的按邮箱校验（v1.8.3 起推荐管理员直接用邮箱） */
    if (strlen($in['admin_user']) > 128 || !filter_var($in['admin_user'], FILTER_VALIDATE_EMAIL)) {
        cli_err('[×] 管理员账号邮箱格式不正确（示例：admin@example.com）。');
        exit(1);
    }
} elseif (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $in['admin_user'])) {
    cli_err('[×] 管理员账号只能含字母/数字/下划线/点/短横线，长度 3~32 位；或直接填邮箱。');
    exit(1);
}
if (strlen((string) $in['admin_pass']) < 6) {
    cli_err('[×] 管理员密码至少 6 位。');
    exit(1);
}

/* ---------------- 测试数据库 ---------------- */
cli_out('[..] 正在连接数据库…');
$dsn = 'mysql:host=' . $in['host'] . ';port=' . (int) $in['port'] . ';dbname=' . $in['db'] . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $in['user'], $in['pass'], array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 6,
    ));
} catch (Exception $e) {
    cli_err('[×] 连接失败：' . $e->getMessage());
    cli_err('');
    cli_err('    常见原因：');
    cli_err('      · 数据库名/用户名/密码写错（宝塔 → 数据库 里可改密码）');
    cli_err('      · 数据库还没建（先在宝塔建库）');
    cli_err('      · MySQL 8 + PHP 5.6 的 caching_sha2_password 认证不兼容：');
    cli_err('        在宝塔 → 数据库 → 该库用户 → 改密码时选 mysql_native_password');
    exit(1);
}
$ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
cli_out('[√] 数据库连接成功，MySQL ' . $ver);

/* ---------------- 建表 ---------------- */
cli_out('[..] 正在建表…');
try {
    foreach (cli_sql() as $sql) {
        $pdo->exec($sql);
    }
} catch (Exception $e) {
    cli_err('[×] 建表失败：' . $e->getMessage());
    exit(1);
}
cli_out('[√] 表结构就绪：media_items / sync_log / link_checks / app_settings / app_users / app_requests / app_api_tokens / app_api_logs');

/* ---------------- 创建管理员账号 ---------------- */
cli_out('[..] 正在创建管理员账号…');
try {
    $hash = password_hash((string) $in['admin_pass'], PASSWORD_DEFAULT);
    if ($hash === false || $hash === null) {
        $hash = '$sha256$' . hash('sha256', 'ml-admin|' . $in['admin_pass']);
    }
    $now  = date('Y-m-d H:i:s');
    $stm  = $pdo->prepare(
        'INSERT INTO `app_users` (`username`,`email`,`pass_hash`,`status`,`created_at`,`last_login`)
         VALUES (:u, :e, :p, 1, :c, :c)'
    );
    $stm->execute(array(
        ':u' => $in['admin_user'],
        ':e' => (strpos($in['admin_user'], '@') !== false) ? $in['admin_user'] : 'ml-admin@local',
        ':p' => $hash, ':c' => $now,
    ));
} catch (Exception $e) {
    cli_err('[×] 创建管理员账号失败：' . $e->getMessage());
    exit(1);
}
cli_out('[√] 管理员账号「' . $in['admin_user'] . '」已创建（进后台就靠它）');

/* ---------------- 生成配置 ---------------- */
$dbCfg = array(
    'host'   => $in['host'],
    'port'   => (int) $in['port'],
    'dbname' => $in['db'],
    'user'   => $in['user'],
    'pass'   => $in['pass'],
);

$adapters = array('tmdb', 'rawg', 'hongguoduanju');
$panList  = array('夸克', '迅雷', '光鸭', '百度', 'UC');

/* 第 4 个参数（后台令牌）v1.7.0 起已废弃，传空 */
if (!cli_write($CFG_FILE, cli_config_content($dbCfg, $in['tmdb'], $in['rawg'], '', $adapters))) {
    cli_err('[×] 写入 config/config.php 失败（检查 config/ 目录权限）');
    exit(1);
}
cli_out('[√] 已生成 ' . $CFG_FILE);

if (!file_exists($PAN_FILE)) {
    @cli_write($PAN_FILE, cli_pan_content($panList));
    cli_out('[√] 已生成 ' . $PAN_FILE . '（默认：' . implode(' / ', $panList) . '）');
} else {
    cli_out('[i] ' . $PAN_FILE . ' 已存在，保持不变');
}

if (!file_exists($LOCK_FILE)) {
    @file_put_contents($LOCK_FILE, "installed at " . date('c') . "\n");
}
@file_put_contents($ROOT . '/config/install-info.txt',
    "安装时间: " . date('Y-m-d H:i:s') . "\n"
    . "站点目录: " . $ROOT . "\n"
    . "数据库  : " . $in['user'] . '@' . $in['host'] . ':' . $in['port'] . '/' . $in['db'] . "\n"
    . "管理员账号: " . $in['admin_user'] . "\n"
    . "※ 密码不写在这里。本文件含账号信息；放在 config/ 下公网访问不到，记下后建议删除。\n");
cli_out('[√] 已写 config/install.lock（锁定安装）');

/* ---------------- 收尾 ---------------- */
cli_out('');
cli_out('=====================================================');
cli_out('  安装完成 ✅');
cli_out('=====================================================');
cli_out('  后台入口：  https://你的域名/admin');
cli_out('  登录账号：  ' . $in['admin_user'] . '（密码是你刚才填的那个）');
cli_out('');
cli_out('  自检报告：  https://你的域名/check.php（已登录管理员即可查看）');
cli_out('');
cli_out('  下一步建议：');
cli_out('   1) 打开后台 → 系统设置，填 TMDB / RAWG Key 与要用的网盘类型；');
cli_out('   2) 后台 → 同步热门，采集一批数据；');
cli_out('   3) 后台 → API 调用，给外部调用者逐个发令牌（每个调用者可单独限额）；');
cli_out('   4) 宝塔 → 网站 → 伪静态，确认已填入（必须）：');
cli_out('        location / { try_files $uri $uri/ /index.php?$query_string; }');
cli_out('     并按 DEPLOY.md「安全加固」一节加上目录保护规则；');
cli_out('   5) 确认自检全部正常后，可删掉 check.php（或改名）与 install-info.txt。');
cli_out('');
cli_out('  提示：也可用网页安装器 /install.php 安装（仅未安装时可用；装完写入 install.lock 后自动 404）。');
cli_out('');
exit(0);
