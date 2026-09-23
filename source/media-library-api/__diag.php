<?php
/**
 * 排障页 __diag.php（v1.8.6）—— 一次性，看完请删掉
 * ==================================================================
 * 专门用来定位「后台登录点了没反应 / 登录后跳回登录页」。
 * 打开 http://你的域名/__diag.php 即可，不需要输入任何东西。
 *
 * 会逐项检查并给出结论：
 *   ① 会话是否真的存得住（最关键）
 *   ② 站点是否经根入口 index.php（决定 /admin/ 会不会 404）
 *   ③ 数据库里有哪些账号、密码散列是否正常
 *   ④ 你给的「账号 + 密码」能不能通过校验、是否被判定为管理员
 *
 * 安全：不输出任何密码散列；但仍可用于枚举管理员邮箱，排障完请立即删除本文件。
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

$root = __DIR__;

/* 载入站内会话模块（存在则用，缺失则退回原生） */
if (file_exists($root . '/core/Session.php')) {
    require_once $root . '/core/Session.php';
}
$booted = function_exists('ml_session_boot') ? ml_session_boot('__ml_diag', 3600) : false;

/* ---- 会话往返测试：第一次来写入一个随机数，第二次来检查还在不在 ---- */
$nonce = bin2hex(defined('PHP_VERSION') ? (string) mt_rand() : '0');
if (!isset($_SESSION) || !is_array($_SESSION)) { $_SESSION = array(); }
$prev = isset($_SESSION['ml_diag_nonce']) ? (string) $_SESSION['ml_diag_nonce'] : '';
$roundTrip = ($prev !== '');
$_SESSION['ml_diag_nonce'] = $nonce;

/* ---- 配置 / 数据库 ---- */
$cfgPath = $root . '/config/config.php';
$dbRow   = array();
$dbErr   = '';
$pdo     = null;
if (file_exists($cfgPath)) {
    $c  = include $cfgPath;
    $db = isset($c['db']) && is_array($c['db']) ? $c['db'] : array();
    $dbRow = array(
        'host' => isset($db['host']) ? $db['host'] : '',
        'port' => isset($db['port']) ? $db['port'] : 3306,
        'name' => isset($db['dbname']) ? $db['dbname'] : '',
        'user' => isset($db['user']) ? $db['user'] : '',
    );
    if ($dbRow['host'] !== '' && $dbRow['name'] !== '') {
        try {
            $pdo = new PDO(
                'mysql:host=' . $dbRow['host'] . ';port=' . (int) $dbRow['port'] . ';dbname=' . $dbRow['name'] . ';charset=utf8mb4',
                $dbRow['user'],
                isset($db['pass']) ? $db['pass'] : '',
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
            );
        } catch (Exception $e) {
            $dbErr = $e->getMessage();
        }
    }
}

$users = array();
if ($pdo) {
    try {
        $st = $pdo->query("SELECT id, username, email, status, fail_count, lock_until,
                                  LENGTH(pass_hash) AS hash_len, created_at
                             FROM app_users ORDER BY id ASC LIMIT 50");
        $users = $st->fetchAll();
    } catch (Exception $e) {
        $dbErr = $e->getMessage();
    }
}

/* ---- 可选的登录校验：?email=...&password=... ---- */
$tEmail = isset($_GET['email']) ? trim((string) $_GET['email']) : '';
$tPass  = isset($_GET['password']) ? (string) $_GET['password'] : '';
$tRow   = null;
$tVerify = null;
$tAdmin  = null;
if ($pdo && $tEmail !== '') {
    try {
        $st = $pdo->prepare("SELECT * FROM app_users WHERE email = ? OR username = ? ORDER BY id ASC LIMIT 1");
        $st->execute(array($tEmail, $tEmail));
        $tRow = $st->fetch();
        if ($tRow && $tPass !== '') {
            $tVerify = password_verify($tPass, (string) $tRow['pass_hash']);
            $st2 = $pdo->query("SELECT MIN(id) AS n FROM app_users WHERE status = 1");
            $r2  = $st2 ? $st2->fetch() : null;
            $tAdmin = ($r2 && (int) $r2['n'] === (int) $tRow['id']);
        }
    } catch (Exception $e) {
        $dbErr = $e->getMessage();
    }
}

function d_ok($b) { return $b ? '<span style="color:#15803d;font-weight:700">✅</span>' : '<span style="color:#b91c1c;font-weight:700">❌</span>'; }
function d_h($s)  { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function d_row($k, $v, $ok = null) {
    echo '<tr><td style="white-space:nowrap">' . d_h($k) . '</td><td>' . ($ok === null ? '' : d_ok($ok) . ' ') . $v . '</td></tr>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>排障 · 媒资资料库</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;max-width:820px;margin:28px auto;padding:0 18px;color:#1b1f2a;line-height:1.65}
h2{font-size:19px} h3{font-size:15px;margin-top:24px;border-left:4px solid #4f46e5;padding-left:9px}
table{border-collapse:collapse;width:100%;font-size:13.5px;margin-top:8px}
td,th{border:1px solid #e2e8f0;padding:6px 9px;text-align:left;vertical-align:top}
th{background:#f8fafc}
.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:8px;padding:11px 13px;margin:14px 0;font-size:13.5px}
.bad{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;padding:11px 13px;margin:14px 0;font-size:13.5px}
.good{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:8px;padding:11px 13px;margin:14px 0;font-size:13.5px}
code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-family:Consolas,monospace}
a.btn{display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:8px 16px;border-radius:7px;font-size:13.5px;margin-top:8px}
</style></head>
<body>
<h2>🔧 排障报告 · 媒资资料库</h2>
<div class="warn">看完请立即删除 <code>__diag.php</code>（它能看到管理员邮箱，不能长期放在线上）。刷新本页一次可让下方「会话往返」更准确。</div>

<h3>① 会话（登录状态）——「登录后跳回登录页」的头号原因</h3>
<table>
<?php
d_row('会话已开启', $booted ? '是' : '否', $booted);
d_row('会话往返（刷新后仍在）', $roundTrip ? '是（会话能持久保存）' : '本次首次写入，<b>刷新一次本页再看这行</b>', $roundTrip);
d_row('会话名', '<code>' . d_h(function_exists('session_name') ? session_name() : '-') . '</code>');
d_row('当前 save_path', '<code>' . d_h((string) ini_get('session.save_path')) . '</code>');
if (function_exists('ml_session_health')) {
    $h = ml_session_health();
    d_row('save_path 可用', d_h($h['real_path']), $h['save_path_ok']);
    d_row('实际使用目录', '<code>' . d_h($h['real_path']) . '</code>');
    d_row('save_handler', d_h($h['handler']));
    d_row('识别为 HTTPS', $h['https'] ? '是' : '否', null);
    d_row('浏览器带回了 cookie', $h['cookie_set'] ? '是' : '否（正常：第一次访问本来就没有）', null);
}
d_row('响应头是否已发出', headers_sent() ? '已发出（会导致会话无法开启）' : '否', !headers_sent());
?>
</table>

<h3>② 入口分发（决定 /admin/ 会不会被 404）</h3>
<table>
<?php
d_row('是否经根入口 index.php', defined('ML_APP') ? '否（本页是独立入口，正常）' : '未知', null);
d_row('index.php 版本', defined('ML_APP_VERSION') ? '<code>' . d_h(ML_APP_VERSION) . '</code>' : '（本页未加载 index.php，属正常）');
d_row('admin/index.php 存在', file_exists($root . '/admin/index.php') ? '是' : '否', file_exists($root . '/admin/index.php'));
d_row('根 index.php 存在', file_exists($root . '/index.php') ? '是' : '否', file_exists($root . '/index.php'));
?>
</table>

<h3>③ 数据库与账号</h3>
<table>
<?php
d_row('config/config.php', file_exists($cfgPath) ? '存在' : '不存在', file_exists($cfgPath));
d_row('数据库连接', $pdo ? '成功（' . d_h($dbRow['name']) . '）' : '失败：' . d_h($dbErr), $pdo !== null);
d_row('app_users 记录数', count($users) > 0 ? (string) count($users) : '0（没有任何账号 → 需要重装）', count($users) > 0);
?>
</table>

<?php if (!empty($users)): ?>
<table>
  <tr><th>ID</th><th>账号</th><th>邮箱</th><th>状态</th><th>失败次数</th><th>锁定至</th><th>散列长度</th><th>创建时间</th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?php echo (int) $u['id']; ?></td>
    <td><?php echo d_h($u['username']); ?></td>
    <td><?php echo d_h($u['email']); ?></td>
    <td><?php echo (int) $u['status']; ?></td>
    <td><?php echo (int) $u['fail_count']; ?></td>
    <td><?php echo d_h($u['lock_until']); ?></td>
    <td><?php echo (int) $u['hash_len']; ?><?php echo ((int) $u['hash_len'] >= 55) ? ' ✅' : ' ❌（散列异常）'; ?></td>
    <td><?php echo d_h($u['created_at']); ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if ($tEmail === ''): ?>
<p>想顺便测一下「账号 + 密码」，把下面链接里的值改成你的再打开：</p>
<a class="btn" href="?email=<?php echo d_h(isset($users[0]['email']) ? $users[0]['email'] : 'ADMIN@example.com'); ?>&password=你的密码">测试账号与密码</a>
<?php else: ?>
<h3>④ 账号密码校验结果</h3>
<?php if (!$tRow): ?>
<div class="bad">❌ 找不到账号「<?php echo d_h($tEmail); ?>」。请照上面表格里的「账号 / 邮箱」原样输入（注意大小写与空格）。</div>
<?php else: ?>
<table>
<?php
d_row('匹配到的账号', d_h($tRow['username']) . ' / ' . d_h($tRow['email']));
d_row('账号状态', (int) $tRow['status'] === 1 ? '正常' : '非正常（' . (int) $tRow['status'] . '）', (int) $tRow['status'] === 1);
d_row('是否被锁定', (!empty($tRow['lock_until']) && strtotime((string) $tRow['lock_until']) > time()) ? '锁到 ' . d_h($tRow['lock_until']) : '未锁定', !(!empty($tRow['lock_until']) && strtotime((string) $tRow['lock_until']) > time()));
d_row('散列是否合法', (strlen((string) $tRow['pass_hash']) >= 55) ? '是（' . strlen((string) $tRow['pass_hash']) . ' 位）' : '否（长度 ' . strlen((string) $tRow['pass_hash']) . '）', strlen((string) $tRow['pass_hash']) >= 55);
if ($tPass !== '') {
    d_row('密码校验', $tVerify ? '通过（密码正确）' : '不通过（密码不对）', $tVerify);
    d_row('管理员判定', $tAdmin ? '是（id 最小的正常账号）' : '否（不是安装时创建的第一个账号）', $tAdmin);
} else {
    d_row('密码校验', '未提供 password 参数');
}
?>
</table>
<?php if ($tVerify === true && $tAdmin === true): ?>
<div class="good">✅ 账号、密码、管理员身份全部正常。若仍登不进去，问题在<b>会话保存</b>或<b>浏览器 cookie</b>——请看第 ① 节的结论，并在浏览器里清除本站 cookie 后重试。</div>
<?php elseif ($tVerify === true && !$tAdmin): ?>
<div class="bad">密码对，但该账号<b>不是管理员</b>。后台只认「安装时创建的第一个账号」（id 最小且状态正常）。请用那条账号登录，或改用 <code>__emergency_reset.php</code> 重置第一个账号的密码。</div>
<?php elseif ($tVerify === false): ?>
<div class="bad">密码不正确。用 <code>__emergency_reset.php</code>（填写上面的账号或邮箱）重置一次即可。</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<h3>结论速查</h3>
<table>
  <tr><th>现象</th><th>对应原因 / 处理</th></tr>
  <tr><td>① 会话往返 = 否</td><td>会话存不住。本版本已内置目录兜底；仍失败请让服务商把 PHP <code>session.save_path</code> 目录 chown 给 www 并给 755/777。</td></tr>
  <tr><td>③ 记录数 = 0</td><td>安装没建成账号 → 删 <code>config/install.lock</code>、<code>config/config.php</code> 后重跑 <code>/install.php</code>。</td></tr>
  <tr><td>④ 账号存在但状态≠1 / 被锁定</td><td>用 <code>__emergency_reset.php</code> 重置（它会一并解锁并恢复状态）。</td></tr>
  <tr><td>④ 密码对但「非管理员」</td><td>用 id 最小的那条账号登录。</td></tr>
</table>

</body>
</html>
