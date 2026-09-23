<?php
/**
 * 紧急重置管理员密码 —— 一次性脚本（v1.8.6）
 * ==================================================================
 * 用途：后台登不进去时，把某个账号的密码改成你指定的，并**顺带解除锁定**
 *      （连续输错次数过多会被临时锁定，此时就算密码对也登不上）。
 *
 * 用法：
 *   1. 把本文件放到站点根目录
 *   2. 浏览器打开 http://你的域名/__emergency_reset.php
 *   3. 填「账号或邮箱」+ 新密码（至少 6 位）
 *   4. 点「重置」→ 看到成功提示后用 账号 + 新密码 登录后台
 *   5. ★ 重置成功后本文件会**自动删除自己**（如需再用请重新上传）
 *
 * 找不到账号时，页面会列出库里现有的账号（ID / 账号 / 邮箱），照着填即可。
 *
 * 安全提示：本文件能让任何人重置管理员密码，请只在排障时上传，用完即删。
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

$root = __DIR__;
$config_path = $root . '/config/config.php';

if (!file_exists($config_path)) {
    die('<b style="color:red">错误</b>：找不到 config/config.php，站点未安装。<br>请先 /install.php 完成安装。');
}

$c  = include $config_path;
$db = isset($c['db']) && is_array($c['db']) ? $c['db'] : array();
if (empty($db['host']) || empty($db['dbname']) || empty($db['user'])) {
    die('<b style="color:red">错误</b>：config.php 数据库段不完整。');
}

$dsn = 'mysql:host=' . $db['host'] . ';port=' . (int) (isset($db['port']) ? $db['port'] : 3306)
     . ';dbname=' . $db['dbname'] . ';charset=utf8mb4';

$error    = '';
$success  = '';
$accounts = array();
$selfGone = false;

$email    = '';
$newpass  = '';
$newpass2 = '';

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
} catch (Exception $e) {
    die('<b style="color:red">连不上数据库</b>：' . htmlspecialchars($e->getMessage()));
}

/* 列出账号（供找不到时对照；不输出密码散列） */
try {
    $st = $pdo->query("SELECT id, username, email, status, fail_count FROM app_users ORDER BY id ASC LIMIT 50");
    $accounts = $st->fetchAll();
} catch (Exception $e) {
    $accounts = array();
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim(isset($_POST['email']) ? (string) $_POST['email'] : '');
    $newpass  = (string) (isset($_POST['newpass']) ? $_POST['newpass'] : '');
    $newpass2 = (string) (isset($_POST['newpass2']) ? $_POST['newpass2'] : '');

    if ($email === '' || strlen($newpass) < 6 || $newpass !== $newpass2) {
        $error = '账号不能为空；新密码至少 6 位，且两次输入必须一致。';
    } else {
        try {
            /* 先按邮箱找，再按账号（username）找 —— 覆盖各种历史情况 */
            $st = $pdo->prepare("SELECT id, username, email FROM app_users WHERE email = ? OR username = ? ORDER BY id ASC LIMIT 1");
            $st->execute(array($email, $email));
            $row = $st->fetch();

            if (!$row) {
                $error = '找不到这个账号 / 邮箱。下面是库里现有的账号，照抄一处再试：';
            } else {
                $hash = password_hash($newpass, PASSWORD_DEFAULT);
                if ($hash === false || $hash === '') {
                    $error = '密码散列失败（极端环境），请换台机器或联系我们。';
                } else {
                    /* 改密码 + 解除锁定 + 恢复为「正常」状态（否则可能仍登不上） */
                    $upd = $pdo->prepare(
                        "UPDATE app_users
                            SET pass_hash = ?, fail_count = 0, lock_until = NULL, status = 1
                          WHERE id = ?"
                    );
                    $upd->execute(array($hash, $row['id']));

                    $success = '已把账号「' . htmlspecialchars((string) $row['username']) . '」'
                             . '（ID=' . (int) $row['id'] . '）的密码重置为：<b>' . htmlspecialchars($newpass) . '</b>'
                             . '<br>同时已解除登录锁定、把账号状态恢复为「正常」。'
                             . '<br><br>请立即用该账号 + 新密码登录后台。';

                    /* 一次性：成功后自删，避免留下后门 */
                    if (@unlink(__FILE__)) {
                        $selfGone = true;
                    }
                }
            }
        } catch (Exception $e) {
            $error = '数据库错误：' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>紧急重置管理员密码</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;max-width:560px;margin:40px auto;padding:0 20px;color:#1b1f2a}
input{width:100%;padding:9px;margin:4px 0 12px;box-sizing:border-box;border:1px solid #d8dce4;border-radius:6px}
button{padding:10px 24px;background:#4f46e5;color:#fff;border:none;cursor:pointer;border-radius:6px;font-size:15px}
.ok{color:#15803d;background:#f0fdf4;padding:12px;border-radius:6px;border:1px solid #bbf7d0;line-height:1.7}
.err{color:#b91c1c;background:#fef2f2;padding:12px;border-radius:6px;border:1px solid #fecaca;line-height:1.7}
label{font-weight:600;display:block;margin-top:12px}
.warning{color:#b45309;background:#fffbeb;padding:10px;border-radius:6px;border:1px solid #fde68a;margin-bottom:16px;line-height:1.7}
table{border-collapse:collapse;width:100%;margin-top:10px;font-size:13px}
td,th{border:1px solid #e2e8f0;padding:5px 8px;text-align:left}
code{background:#f1f5f9;padding:1px 5px;border-radius:4px}
.note{color:#64748b;font-size:13px;line-height:1.8;margin-top:22px}
</style>
</head>
<body>
<h2>🚨 紧急重置管理员密码（v1.8.6）</h2>

<?php if ($selfGone): ?>
<div class="ok"><?php echo $success; ?>
  <br><br>✅ 本脚本已<b>自动删除</b>，无需再手动处理。</div>
<?php else: ?>
<div class="warning">⚠️ 这是临时排障脚本，<b>重置成功后会自动删除自己</b>；若没触发，请手动删掉 <code>__emergency_reset.php</code>。</div>

<?php if ($error !== ''): ?><div class="err"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="ok"><?php echo $success; ?></div><?php endif; ?>

<form method="post">
  <label>账号 或 邮箱（安装时创建的那个）</label>
  <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="如 15505239925@163.com">

  <label>新密码（至少 6 位）</label>
  <input type="password" name="newpass" required minlength="6">

  <label>确认新密码</label>
  <input type="password" name="newpass2" required minlength="6">

  <button type="submit">重置密码并解锁</button>
</form>

<?php if (!empty($accounts)): ?>
<h3 style="margin-top:26px;font-size:15px">库里现有的账号（供对照）</h3>
<table>
  <tr><th>ID</th><th>账号</th><th>邮箱</th><th>状态</th></tr>
  <?php foreach ($accounts as $a): ?>
  <tr>
    <td><?php echo (int) $a['id']; ?></td>
    <td><?php echo htmlspecialchars((string) $a['username']); ?></td>
    <td><?php echo htmlspecialchars((string) $a['email']); ?></td>
    <td><?php echo (int) $a['status']; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p class="note">
后记：若表里<b>一条记录都没有</b>，说明安装没成功，请重新跑一遍 <code>/install.php</code>。<br>
登录问题排查：打开 <code>/check.php</code>，看「会话（登录状态）」一栏是否为 ok。
</p>
<?php endif; ?>
</body>
</html>
