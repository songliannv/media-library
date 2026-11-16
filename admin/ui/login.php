<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台登录 · 媒资资料库</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="<?php echo ml_admin_asset('css/admin-login.css');?>?v=<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'1';?>">
</head>
<body>
<div class="box">
  <div class="head">
    <div class="logo">🛡</div>
    <h1>媒资资料库 · 管理后台</h1>
    <p><?php echo($MODE==='forgot')?'找回密码':(($MODE==='reset')?'设置新密码':'请用管理员账号登录');?></p>
  </div>
  <div class="card">
    <?php if(!$SESSION_OK):?>
    <div class="err">⚠️ 服务器没有保存住会话（session），登录会一直跳回本页。<br>
      请打开 <code>check.php</code> 看「会话」一栏，或让服务商把 PHP 的 <code>session.save_path</code> 目录设为可写。</div>
    <?php endif;?>
    <?php if($LOGIN_ERR!==''):?>
    <div class="err"><?php echo htmlspecialchars($LOGIN_ERR,ENT_QUOTES,'UTF-8');?></div>
    <?php endif;?>
    <?php if($LOGIN_OK!==''):?>
    <div class="ok"><?php echo htmlspecialchars($LOGIN_OK,ENT_QUOTES,'UTF-8');?></div>
    <?php endif;?>

    <?php if($MODE==='forgot'):?>
    <!-- 找回密码：输入账号 / 邮箱，发一封带重置链接的邮件 -->
    <form method="post" action="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>?forgot=1" autocomplete="off">
      <input type="hidden" name="ad_do" value="forgot">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <label>管理员账号 / 邮箱</label>
      <input type="text" name="account" value="" placeholder="用户名，或账号绑定的邮箱" required autofocus autocomplete="username">
      <button type="submit">发送重置邮件</button>
    </form>
    <p class="mini">重置链接会发到账号绑定的邮箱，1 小时内有效、只能用一次。<br>
      还没绑定邮箱？请先用密码登录，在右上角「修改密码」里填写邮箱。<br>
      <a href="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>">← 返回登录</a></p>

    <?php elseif($MODE==='reset'):?>
    <?php if(!$resetValid):?>
    <!-- 链接无效 / 已过期 -->
    <div class="err">这个重置链接无效或已过期（可能已被使用，或超过 1 小时）。请重新发起「找回密码」。</div>
    <p class="mini"><a href="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>?forgot=1">重新找回密码</a>　·　<a href="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>">返回登录</a></p>
    <?php else:?>
    <!-- 设置新密码 -->
    <form method="post" action="<?php echo htmlspecialchars($ADMIN_URL.'?reset='.urlencode($RESET_TOKEN),ENT_QUOTES,'UTF-8');?>" autocomplete="off">
      <input type="hidden" name="ad_do" value="reset">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($RESET_TOKEN,ENT_QUOTES,'UTF-8');?>">
      <label>新密码</label>
      <input type="password" name="new" value="" placeholder="至少 6 位" required autofocus autocomplete="new-password">
      <label>确认新密码</label>
      <input type="password" name="new2" value="" placeholder="再输一次" required autocomplete="new-password">
      <button type="submit">设置新密码</button>
    </form>
    <p class="mini"><a href="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>">← 返回登录</a></p>
    <?php endif;?>

    <?php else:?>
    <!-- 正常登录 -->
    <form method="post" action="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>" autocomplete="off">
      <input type="hidden" name="ad_do" value="login">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input class="hp" type="text" name="ml_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label>管理员账号 / 邮箱</label>
      <input id="u" type="text" name="u" value="" placeholder="安装时填的邮箱或账号" required autofocus autocomplete="username">
      <label>密码</label>
      <input type="password" name="p" value="" placeholder="你的密码" required autocomplete="current-password">
      <button type="submit">登 录</button>
    </form>
    <p class="mini"><a href="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>?forgot=1">忘记密码？</a></p>
    <?php endif;?>
  </div>
  <div class="foot">
    这不是给外部调用者的入口。<br>
    外部调用 API 用的是「API 令牌」，在后台「API 调用」页里单独发。
  </div>
</div>
</body>
</html>
