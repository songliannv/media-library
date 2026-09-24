<?php
use Core\Config;use Core\User;if(!defined('ML_APP')){define('ML_APP',true);}if(!defined('ML_ROOT')){define('ML_ROOT',dirname(__DIR__));}if(!defined('ML_APP_VERSION')){define('ML_APP_VERSION','2.0.4');}header('Content-Type: text/html; charset=utf-8');header('X-Robots-Tag: noindex, nofollow');require_once __DIR__.'/../core/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/../core/Session.php';require_once __DIR__.'/../core/DB.php';require_once __DIR__.'/../core/Config.php';require_once __DIR__.'/../core/User.php';$a=ml_session_boot(User::SESS);function ml_ad_csrf(){if(empty($_SESSION['ml_ad_csrf'])){if(function_exists('openssl_random_pseudo_bytes')){$_SESSION['ml_ad_csrf']=bin2hex(openssl_random_pseudo_bytes(16));}else{$_SESSION['ml_ad_csrf']=md5(uniqid((string)mt_rand(),true));}}return(string)$_SESSION['ml_ad_csrf'];}$b=trim((string)Config::sub('admin','dir','admin'));if($b===''||strpos($b,'admin')!==strlen($b)-5){$b='admin';}$c='/'.$b.'/';$d='';$e='';$f='';$g=isset($_POST['ad_do'])?(string)$_POST['ad_do']:'';if($g===''&&isset($_POST['u'],$_POST['p'])){$g='login';}if($g==='login'){$f='user';if(!$a){$d='服务器无法保存登录会话（session 不可写），所以登不上去。'.'请打开 check.php 看「会话」一栏，或让服务商把 PHP 的 session.save_path 目录设为可写。';}elseif(!isset($_POST['csrf'])||!hash_equals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期（登录会话没保持住），请再提交一次；若反复如此，请打开 check.php 看「会话」一栏。';}elseif(isset($_POST['ml_hp'])&&trim((string)$_POST['ml_hp'])!==''){$d='检测到异常提交，已忽略。';}else{$h=isset($_POST['u'])?(string)$_POST['u']:'';$i=isset($_POST['p'])?(string)$_POST['p']:'';$j=User::login($h,$i);if(!empty($j['ok'])){if(User::isAdmin()){header('Location: '.$c);exit;}User::logout();$d='该账号不是管理员，无法进入后台。';}else{$d=(string)$j['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='forgot'){if(!isset($_POST['csrf'])||!hash_equals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期，请重新提交。';}else{$j=User::requestReset(isset($_POST['account'])?(string)$_POST['account']:'','admin');if(!empty($j['ok'])){$e=(string)$j['msg'];}else{$d=(string)$j['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='reset'){if(!isset($_POST['csrf'])||!hash_equals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期，请重新提交。';}else{$j=User::doReset(isset($_POST['token'])?(string)$_POST['token']:'',isset($_POST['new'])?(string)$_POST['new']:'',isset($_POST['new2'])?(string)$_POST['new2']:'');if(!empty($j['ok'])){$e=(string)$j['msg'].' 请用新密码登录。';}else{$d=(string)$j['msg'];}}}if(isset($_GET['logout'])){User::logout();header('Location: '.$c);exit;}$k=User::me();if(!$k||!User::isAdmin()){$l='login';$m='';if(isset($_GET['reset'])){$l='reset';$m=(string)$_GET['reset'];}elseif(isset($_GET['forgot'])){$l='forgot';}if($e!==''&&$l==='reset'){$l='login';}$n=($l==='reset')?(User::verifyResetToken($m)!==null):false;?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台登录 · 媒资资料库</title>
<meta name="robots" content="noindex,nofollow">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#eef2f7;--card:#fff;--line:#e2e8f0;--txt:#0f172a;--mut:#64748b;
      --pri:#4f46e5;--pri2:#7c3aed;--bad:#dc2626;--shadow:0 10px 34px rgba(15,23,42,.12)}
@media (prefers-color-scheme:dark){
  :root{--bg:#0f1420;--card:#182034;--line:#28324a;--txt:#e6eaf3;--mut:#94a0b8;
        --shadow:0 10px 34px rgba(0,0,0,.5)}
}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;
     background:var(--bg);color:var(--txt);min-height:100vh;display:flex;align-items:center;
     justify-content:center;padding:20px;-webkit-font-smoothing:antialiased}
.box{width:100%;max-width:390px}
.head{text-align:center;margin-bottom:22px}
.logo{width:60px;height:60px;border-radius:17px;margin:0 auto 14px;display:flex;align-items:center;
      justify-content:center;font-size:28px;background:linear-gradient(135deg,var(--pri),var(--pri2));
      box-shadow:0 8px 22px rgba(79,70,229,.35)}
.head h1{font-size:20px;font-weight:700}
.head p{color:var(--mut);font-size:13px;margin-top:6px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow)}
label{display:block;font-size:12.5px;font-weight:600;color:var(--mut);margin-bottom:6px}
input{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px;background:transparent;
      color:var(--txt);font-size:15px;outline:none;margin-bottom:14px;transition:border-color .15s,box-shadow .15s}
input:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(79,70,229,.14)}
button{width:100%;padding:12px;border:none;border-radius:10px;font-size:15px;font-weight:600;color:#fff;
       cursor:pointer;background:linear-gradient(120deg,var(--pri),var(--pri2));transition:filter .15s,transform .1s}
button:hover{filter:brightness(1.07)}
button:active{transform:translateY(1px)}
.err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.28);color:var(--bad);
     border-radius:10px;padding:10px 13px;font-size:13px;margin-bottom:14px;line-height:1.6}
.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.ok{background:rgba(15,157,99,.1);border:1px solid rgba(15,157,99,.28);color:#0f9d63;border-radius:10px;padding:10px 13px;font-size:13px;margin-bottom:14px;line-height:1.6}
.mini{color:var(--mut);font-size:12.5px;margin-top:12px;line-height:1.75}
.mini a{color:var(--pri);text-decoration:none}
.mini a:hover{text-decoration:underline}
.foot{text-align:center;color:var(--mut);font-size:12px;margin-top:18px;line-height:1.8}
@media (max-width:420px){.card{padding:20px 17px;border-radius:14px}}
</style>
</head>
<body>
<div class="box">
  <div class="head">
    <div class="logo">🛡</div>
    <h1>媒资资料库 · 管理后台</h1>
    <p><?php echo($l==='forgot')?'找回密码':(($l==='reset')?'设置新密码':'请用管理员账号登录');?></p>
  </div>
  <div class="card">
    <?php if(!$a):?>
    <div class="err">⚠️ 服务器没有保存住会话（session），登录会一直跳回本页。<br>
      请打开 <code>check.php</code> 看「会话」一栏，或让服务商把 PHP 的 <code>session.save_path</code> 目录设为可写。</div>
    <?php endif;?>
    <?php if($d!==''):?>
    <div class="err"><?php echo htmlspecialchars($d,ENT_QUOTES,'UTF-8');?></div>
    <?php endif;?>
    <?php if($e!==''):?>
    <div class="ok"><?php echo htmlspecialchars($e,ENT_QUOTES,'UTF-8');?></div>
    <?php endif;?>

    <?php if($l==='forgot'):?>
    <!-- 找回密码：输入账号 / 邮箱，发一封带重置链接的邮件 -->
    <form method="post" action="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>?forgot=1" autocomplete="off">
      <input type="hidden" name="ad_do" value="forgot">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <label>管理员账号 / 邮箱</label>
      <input type="text" name="account" value="" placeholder="用户名，或账号绑定的邮箱" required autofocus autocomplete="username">
      <button type="submit">发送重置邮件</button>
    </form>
    <p class="mini">重置链接会发到账号绑定的邮箱，1 小时内有效、只能用一次。<br>
      还没绑定邮箱？请先用密码登录，在右上角「修改密码」里填写邮箱。<br>
      <a href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>">← 返回登录</a></p>

    <?php elseif($l==='reset'):?>
    <?php if(!$n):?>
    <!-- 链接无效 / 已过期 -->
    <div class="err">这个重置链接无效或已过期（可能已被使用，或超过 1 小时）。请重新发起「找回密码」。</div>
    <p class="mini"><a href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>?forgot=1">重新找回密码</a>　·　<a href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>">返回登录</a></p>
    <?php else:?>
    <!-- 设置新密码 -->
    <form method="post" action="<?php echo htmlspecialchars($c.'?reset='.urlencode($m),ENT_QUOTES,'UTF-8');?>" autocomplete="off">
      <input type="hidden" name="ad_do" value="reset">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($m,ENT_QUOTES,'UTF-8');?>">
      <label>新密码</label>
      <input type="password" name="new" value="" placeholder="至少 6 位" required autofocus autocomplete="new-password">
      <label>确认新密码</label>
      <input type="password" name="new2" value="" placeholder="再输一次" required autocomplete="new-password">
      <button type="submit">设置新密码</button>
    </form>
    <p class="mini"><a href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>">← 返回登录</a></p>
    <?php endif;?>

    <?php else:?>
    <!-- 正常登录 -->
    <form method="post" action="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>" autocomplete="off">
      <input type="hidden" name="ad_do" value="login">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input class="hp" type="text" name="ml_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label>管理员账号 / 邮箱</label>
      <input id="u" type="text" name="u" value="" placeholder="安装时填的邮箱或账号" required autofocus autocomplete="username">
      <label>密码</label>
      <input type="password" name="p" value="" placeholder="你的密码" required autocomplete="current-password">
      <button type="submit">登 录</button>
    </form>
    <p class="mini"><a href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>?forgot=1">忘记密码？</a></p>
    <?php endif;?>
  </div>
  <div class="foot">
    这不是给外部调用者的入口。<br>
    外部调用 API 用的是「API 令牌」，在后台「API 调用」页里单独发。
  </div>
</div>
</body>
</html>
<?php
exit;}?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>媒资资料库 · 管理后台</title>
<style>
  :root{ --maxw:1200px; --pad:16px; --thbg:#fafbff; --rowhov:#fafbff;
         --bg:#f4f5fa; --card:#fff; --line:#eceef3; --pri:#5b5bd6; --pri2:#8b5cf6; --txt:#1b1f2a; --mut:#9aa1b1;
         --ok:#0f9d63; --dead:#e5484d; --shadow:0 6px 22px rgba(27,31,42,.06); }
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased}
  header{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;padding:15px 22px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 18px rgba(91,91,214,.28)}
  header h1{font-size:16px;margin:0;font-weight:700;letter-spacing:.3px}
  header .sver{font-size:11px;opacity:.75;font-weight:400;margin-left:6px;padding:2px 7px;background:rgba(255,255,255,.15);border-radius:8px;white-space:nowrap}
  header .sub{font-size:13.5px;opacity:.9;margin-left:auto;line-height:1.5;padding-left:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:680px}
  .wrap{max-width:var(--maxw);margin:20px auto;padding:0 var(--pad);overflow:hidden}
  .token-bar{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 15px;margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:var(--shadow)}
  .token-bar input{flex:1;min-width:220px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;outline:none}
  .tabs{display:flex;gap:8px;margin-bottom:16px}
  .tab{padding:9px 18px;border:1px solid var(--line);background:var(--card);border-radius:22px;cursor:pointer;font-size:14px;color:var(--mut);transition:.15s}
  .tab:hover{color:var(--pri)}
  .tab.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;font-weight:600;box-shadow:0 6px 16px rgba(91,91,214,.3)}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:var(--shadow)}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .stat{background:linear-gradient(160deg,#f7f7ff,#f1f1fb);border:1px solid var(--line);border-radius:11px;padding:15px}
  .stat b{font-size:22px;display:block}
  .stat span{color:var(--mut);font-size:13px}
  .cats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .cat{padding:7px 16px;border:1px solid var(--line);background:var(--card);border-radius:20px;cursor:pointer;font-size:14px;color:var(--mut);transition:.15s}
  .cat:hover{color:var(--pri);border-color:var(--pri)}
  .cat.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;font-weight:600}
  .toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}
  .toolbar input,.toolbar select{padding:9px 11px;border:1px solid var(--line);border-radius:9px;outline:none}
  .toolbar input[type=text]{flex:1;min-width:200px}
  button{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border:0;border-radius:9px;padding:9px 15px;cursor:pointer;font-size:14px;transition:.15s}
  button:hover{filter:brightness(1.06);box-shadow:0 6px 16px rgba(91,91,214,.28)}
  button.ghost{background:var(--card);color:var(--pri);border:1px solid var(--pri)}
  button.ghost:hover{background:#f5f5ff}
  button.danger{background:linear-gradient(120deg,#e5484d,#f0686c)}
  button.sm{padding:6px 11px;font-size:13px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
  th{color:var(--mut);font-weight:600;background:var(--thbg)}
  tbody tr:hover{background:var(--rowhov)}
  .thumb{width:46px;height:66px;object-fit:cover;border-radius:7px;background:#eee}
  .pager{display:flex;gap:8px;align-items:center;justify-content:center;margin-top:12px}
  .log{font-size:13px;line-height:1.7;white-space:pre-wrap;color:#444;overflow-x:auto;word-break:break-word}
  .modal{position:fixed;inset:0;background:rgba(27,31,42,.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:50}
  .modal .box{background:var(--card);border-radius:16px;padding:22px;width:100%;max-width:580px;max-height:88vh;overflow:auto;box-shadow:0 24px 60px rgba(0,0,0,.3)}
  .modal h3{margin-top:0}
  .modal label{display:block;font-size:13px;color:var(--mut);margin:12px 0 5px}
  .modal input,.modal textarea,.modal select{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font-size:14px;outline:none}
  .dl-row{display:flex;gap:8px;margin-bottom:8px;align-items:center}
  .dl-row .dl-st{flex:0 0 auto;min-width:56px;text-align:center}
  .dl-row .dl-type{flex:0 0 112px;width:112px}
  .dl-row .dl-url{flex:1}
  .dl-row button{flex:0 0 auto;padding:9px 12px}
  .modal textarea{min-height:74px;resize:vertical}
  .modal .grid2{display:flex;gap:12px}
  .modal .grid2>div{flex:1}
  .row{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
  .hint{color:var(--mut);font-size:12px}
  .ext-note{background:var(--bg);border-radius:9px;padding:10px 12px;margin-top:8px;font-size:13px;color:var(--mut)}
  .st{display:inline-block;font-size:11.5px;padding:2px 9px;border-radius:20px;white-space:nowrap;font-weight:600}
  .st-ok{background:#e9f9f1;color:var(--ok)}
  .st-dead{background:#feecec;color:var(--dead)}
  .st-none{background:#f0f1f5;color:var(--mut)}
  /* --- 系统设置页 --- */
  .set-sec{border-top:1px solid var(--line);padding:18px 0 10px}
  .set-sec:first-child{border-top:0;padding-top:4px}
  .set-sec h4{margin:0 0 5px;font-size:14.5px;display:flex;align-items:center;flex-wrap:wrap;gap:4px}
  .set-sec .tip{color:var(--mut);font-size:12.5px;line-height:1.7;margin-bottom:9px}
  .set-sec input[type=text]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font-size:14px;outline:none;font-family:inherit}
  .set-sec input[type=text]:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.12)}
  .inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .inline input[type=text]{flex:1;min-width:180px}
  .chk{display:flex;gap:9px;align-items:center;font-size:14px;margin:9px 0;cursor:pointer}
  .chk input{width:16px;height:16px}
  /* --- 数据源行：勾选 + 测试按钮 + 连通说明 --- */
  .src-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:2px 0}
  .src-row .chk{margin:6px 0}
  .src-state{font-size:12px;color:var(--mut);word-break:break-all;line-height:1.7}
  .src-state.src-ok{color:var(--ok)}
  .src-state.src-dead{color:var(--dead)}
  .src-ok{color:var(--ok)}
  .src-dead{color:var(--dead)}
  .src-mut{color:var(--mut)}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 10px;min-height:26px}
  .chip{display:inline-flex;align-items:center;gap:8px;background:#f2f2fd;border:1px solid #e4e4f8;color:#4a4aa8;border-radius:20px;padding:5px 12px;font-size:13px}
  .chip b{cursor:pointer;color:#9a9ac4;font-weight:700;font-size:15px;line-height:1}
  .chip b:hover{color:var(--dead)}
  .set-flag{font-size:11.5px;font-weight:600;border-radius:20px;padding:2px 9px}
  .set-flag.on{background:#eef0ff;color:#4a4aa8}
  .set-flag.off{background:#f0f1f5;color:var(--mut)}
  .set-msg{font-size:13px;margin-top:12px;min-height:18px;font-weight:600}
  .fileval{color:var(--mut);font-size:12px;font-family:Consolas,monospace;word-break:break-all}
  /* --- 安全防护页 --- */
  .sec-row{display:flex;gap:12px;align-items:flex-start;padding:13px 0;border-top:1px solid var(--line)}
  .sec-row:first-child{border-top:0}
  .sec-bd{flex:0 0 76px}
  .sec-badge{display:inline-block;font-size:11.5px;font-weight:700;border-radius:20px;padding:3px 10px;white-space:nowrap}
  .sec-badge.y{background:#e9f9f1;color:var(--ok)}
  .sec-badge.n{background:#fff7e6;color:#b45309}
  .sec-badge.f{background:#feecec;color:var(--dead)}
  .sec-nm{font-size:13.5px;font-weight:600}
  .sec-dt{font-size:12.5px;color:var(--mut);margin-top:3px;word-break:break-all;line-height:1.7}
/* ===== 站点设置（基础设置 / SEO / 跳转扫码 / 网盘链接 / 接口配置）===== */
.subtabs{display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:12px;margin-bottom:18px}
.stab{padding:8px 16px;border-radius:9px;font-size:13.5px;color:var(--mut);cursor:pointer;transition:.15s;border:1px solid transparent;background:none}
.stab:hover{color:var(--pri);background:#f7f7ff}
.stab.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-weight:600}
.site-2col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 22px}
.imgpick{display:flex;gap:10px;align-items:center}
.imgpick img{width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid var(--line);background:#f8fafc;flex:0 0 52px}
.imgpick .ph{width:52px;height:52px;border-radius:10px;border:1px dashed var(--line);display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:11px;flex:0 0 52px}
.pending-row{display:flex;gap:12px;align-items:flex-start;padding:11px 13px;border:1px solid var(--line);border-radius:11px;margin-bottom:8px;background:var(--card)}
.pending-row .t{flex:1;min-width:0}
.pending-row .t b{font-size:13.5px}
.pending-row .t .u{font-size:11.5px;color:var(--mut);word-break:break-all;line-height:1.7}
.score{font-size:11.5px;padding:2px 9px;border-radius:20px;background:#eef2ff;color:var(--pri);white-space:nowrap}
.status-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.status-bar .pill{font-size:12.5px;padding:5px 12px;border-radius:20px;background:var(--bg);color:var(--mut);border:1px solid var(--line)}
.status-bar .pill b{color:var(--txt)}
.seo-box{background:#0f172a;color:#c7d2fe;border-radius:11px;padding:13px 15px;font-size:12.5px;line-height:1.9;word-break:break-all;font-family:ui-monospace,Menlo,Consolas,monospace}
.seo-box em{color:#7dd3fc;font-style:normal}
/* ============================================================
   响应式自适应 + 明暗主题（v1.6.1）
   断点：>=1400 大屏 / <=1024 平板横屏 / <=768 平板竖屏 /
         <=560 手机 / <=380 小屏手机
   另覆盖：触屏去 hover 粘滞 / 刘海安全区 / 手机整屏弹层 /
          表格横滑且首列粘住 / 减少动画 / 打印 / 键盘焦点
   ============================================================ */
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{overflow-wrap:break-word}
:root[data-theme=light]{color-scheme:light}

/* 键盘可达性：仅键盘聚焦时出现描边 */
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,
.tab:focus-visible,.cat:focus-visible,.stab:focus-visible{outline:3px solid rgba(91,91,214,.5);outline-offset:2px}
header :focus-visible{outline-color:#fff}

/* 顶栏明暗切换按钮 */
.themebtn{flex:0 0 auto;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);color:#fff;
  padding:6px 11px;font-size:15px;line-height:1;border-radius:9px;cursor:pointer;transition:.15s}
.themebtn:hover{background:rgba(255,255,255,.3);filter:none;box-shadow:none}
/* 提示条配色统一走类名，便于暗色适配（原先是内联 style，暗色下会瞎眼） */
.note-ok{background:#ecfdf5;color:#065f46}
.note-warn{background:#fff7ed;color:#9a3412}
.note-err{background:#feecec;color:#b91c1c}

/* 表格横向滚动容器（小屏时表格保持可读宽度，滑动查看） */
.tablewrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.tablewrap::-webkit-scrollbar{height:6px}
.tablewrap::-webkit-scrollbar-thumb{background:#d7dae4;border-radius:6px}
table{min-width:auto;max-width:100%}
.pager{flex-wrap:wrap;gap:8px}
.pager span{font-size:13px;color:var(--mut)}

/* --- 大屏 --- */
@media (min-width:1400px){
  :root{--maxw:1320px}
  .stat-grid{grid-template-columns:repeat(auto-fill,minmax(170px,1fr))}
  .site-2col{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px 22px}
}

/* --- 平板横屏及以下 --- */
@media (max-width:1024px){
  header{padding-top:13px;padding-bottom:13px}
  header .sub{font-size:12.5px;line-height:1.5;max-width:none;white-space:normal}
  .stat-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
}

/* --- 平板竖屏及以下：导航类横滑、表格横滚 + 首列粘住 --- */
@media (max-width:768px){
  :root{--pad:13px}
  header{padding:12px 14px;gap:9px}
  header h1{font-size:15px}
  header .sub{display:none}
  .wrap{margin:14px auto}
  .card{padding:14px;border-radius:12px;margin-bottom:14px}
  .tabs,.subtabs,.cats{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch}
  .tabs::-webkit-scrollbar,.subtabs::-webkit-scrollbar,.cats::-webkit-scrollbar{height:0}
  .tabs{gap:7px;padding-bottom:3px}
  .subtabs{gap:6px}
  .cats{gap:7px;padding-bottom:3px}
  .tab,.cat,.stab{flex:0 0 auto;min-height:38px;display:inline-flex;align-items:center;justify-content:center}
  .tab{padding:8px 15px;font-size:13.5px}
  .cat{padding:7px 14px;font-size:13.5px}
  .stab{padding:8px 14px;font-size:13px}
  button{padding:10px 15px;font-size:13.5px}
  button.sm{padding:8px 12px;font-size:12.5px}
  .log{font-size:12.5px}
  .seo-box{overflow-x:auto}
  .set-sec{padding:15px 0 9px}
  .set-sec h4{font-size:14px}
  .tablewrap{border-radius:10px}
  .tablewrap th:first-child,.tablewrap td:first-child{position:sticky;left:0;z-index:2;
    background:var(--card);box-shadow:1px 0 0 var(--line)}
  .tablewrap th:first-child{background:var(--thbg)}
  .tablewrap tbody tr:hover td:first-child{background:var(--rowhov)}
  .tablewrap th,.tablewrap td{padding:8px 9px}
}

/* --- 手机 --- */
@media (max-width:560px){
  header{padding:11px 13px}
  header h1{font-size:14.5px}
  .themebtn{padding:6px 9px;font-size:14px}
  .token-bar{padding:11px 13px;gap:8px}
  .token-bar input{flex:1 1 60px;min-width:0;font-size:16px}
  .stat-grid{grid-template-columns:repeat(2,1fr);gap:9px}
  .stat{padding:12px}
  .stat b{font-size:19px}
  .stat span{font-size:12px}
  .toolbar{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .toolbar input[type=text]{grid-column:1 / -1;min-width:0;font-size:16px}
  .toolbar button{width:100%}
  .pending-row{flex-direction:column;gap:8px;padding:11px 12px}
  .pending-row .t .u{font-size:11px}
  .sec-row{flex-direction:column;gap:7px}
  .sec-bd{flex:0 0 auto}
  .site-2col{grid-template-columns:1fr;gap:12px}
  .inline{gap:7px}
  .inline input[type=text]{min-width:0;flex:1 1 100%;font-size:16px}
  .status-bar{gap:6px}
  .status-bar .pill{font-size:12px;padding:4px 10px;border-radius:16px}
  .seo-box{padding:12px;font-size:12px}
  .chips{gap:6px}
  .chip{padding:5px 10px;font-size:12.5px}
  /* 弹层：手机上整屏显示，避免软键盘把表单挤到看不见 */
  .modal{padding:0;align-items:stretch}
  .modal .box{max-width:none;max-height:none;height:100%;border-radius:0;
    padding:16px 15px max(26px,calc(env(safe-area-inset-bottom) + 18px))}
  .modal h3{font-size:16px}
  .modal input,.modal textarea,.modal select{font-size:16px}
  .modal .grid2{flex-direction:column;gap:0}
  .modal .row{gap:8px;margin-top:14px}
  .modal .row button{flex:1}
  .dl-row{flex-wrap:wrap;gap:7px}
  .dl-row .dl-st{min-width:0;flex:0 0 auto}
  .dl-row .dl-type{flex:1 1 auto;width:auto;min-width:110px}
  .dl-row .dl-url{flex:1 1 100%}
  .dl-row button{width:100%;justify-content:center}
  .ext-note{font-size:12.5px}
}

/* --- 小屏手机 --- */
@media (max-width:380px){
  .stat-grid{gap:8px}
  .stat b{font-size:17px}
  .toolbar{grid-template-columns:1fr}
  .tab,.cat,.stab{font-size:12.5px}
}

/* --- 手机横屏（高度很小）：弹层不再整屏，改回居中卡片 --- */
@media (max-height:520px) and (orientation:landscape){
  header{padding-top:9px;padding-bottom:9px}
  .modal{align-items:center;padding:10px}
  .modal .box{height:auto;max-height:94vh;border-radius:14px;margin:auto}
}

/* --- 触屏：取消 hover 造成的位移与粘滞高亮 --- */
@media (hover:none){
  button:hover{filter:none;box-shadow:none}
  button.ghost:hover{background:var(--card)}
  .tab:hover,.cat:hover{color:var(--mut)}
  .tab.active:hover,.cat.active:hover{color:#fff}
  .stab:hover{color:var(--mut);background:none}
  .stab.active:hover{color:#fff}
  .themebtn:hover{background:rgba(255,255,255,.16)}
  tbody tr:hover{background:transparent}
  .tablewrap tbody tr:hover td:first-child{background:var(--card)}
}

/* --- 系统「减少动态效果」--- */
@media (prefers-reduced-motion:reduce){
  *,*:before,*:after{transition-duration:.01ms!important;animation-duration:.01ms!important}
}

/* --- 打印：只留内容 --- */
@media print{
  header,.token-bar,.tabs,.subtabs,.cats,.toolbar,.row,.pager,.modal,.status-bar{display:none!important}
  body{background:#fff;color:#000}
  .wrap{max-width:none;margin:0;padding:0}
  .card{box-shadow:none;border-color:#ccc;break-inside:avoid;page-break-inside:avoid}
  .tablewrap{overflow:visible}
  table{min-width:0}
  .log{font-size:11px;color:#000}
  .seo-box{background:#f5f5f5;color:#111}
}

/* --- 刘海屏安全区（放最后，兜住前面简写里的左右内边距） --- */
@supports(padding:max(0px)){
  header{padding-left:max(22px,env(safe-area-inset-left));padding-right:max(22px,env(safe-area-inset-right))}
  .wrap{padding-left:max(var(--pad),env(safe-area-inset-left));padding-right:max(var(--pad),env(safe-area-inset-right))}
}

/* ===== 明暗主题：暗色（顶栏按钮切换，或「跟随系统」时由系统偏好决定）===== */
:root[data-theme=dark]{
  color-scheme:dark;
  --bg:#0f1420;--card:#182034;--line:#28324a;--txt:#e6eaf3;--mut:#94a0b8;
  --ok:#4ade80;--dead:#f87171;--thbg:#1d263b;--rowhov:#1d263b;
  --shadow:0 6px 22px rgba(0,0,0,.45);
}
:root[data-theme=dark] header{box-shadow:0 2px 18px rgba(0,0,0,.5)}
:root[data-theme=dark] .stat{background:linear-gradient(160deg,#1e2740,#1a2233)}
:root[data-theme=dark] button.ghost{background:transparent;color:#b3b6f5;border-color:#4a4aa8}
:root[data-theme=dark] button.ghost:hover{background:#232c45}
:root[data-theme=dark] .toolbar input,:root[data-theme=dark] .toolbar select,
:root[data-theme=dark] .token-bar input,:root[data-theme=dark] .modal input,
:root[data-theme=dark] .modal textarea,:root[data-theme=dark] .modal select,
:root[data-theme=dark] .set-sec input[type=text]{background:#141c2e;color:var(--txt);border-color:var(--line)}
:root[data-theme=dark] .modal{background:rgba(0,0,0,.68)}
:root[data-theme=dark] .thumb{background:#232c45}
:root[data-theme=dark] .ext-note{background:#1d263b;color:var(--mut)}
:root[data-theme=dark] .note-ok{background:#10321f;color:#6ee7b7}
:root[data-theme=dark] .note-warn{background:#3a2f12;color:#fbbf24}
:root[data-theme=dark] .note-err{background:#3a1a1c;color:#fca5a5}
:root[data-theme=dark] .st-ok{background:#10321f;color:var(--ok)}
:root[data-theme=dark] .st-dead{background:#3a1a1c;color:var(--dead)}
:root[data-theme=dark] .st-none{background:#232c45}
:root[data-theme=dark] .chip{background:#232c45;border-color:#2f3a56;color:#b9bcf2}
:root[data-theme=dark] .chip b{color:#8b93b8}
:root[data-theme=dark] .set-flag.on{background:#232c45;color:#b9bcf2}
:root[data-theme=dark] .set-flag.off{background:#232c45;color:var(--mut)}
:root[data-theme=dark] .sec-badge.y{background:#10321f;color:var(--ok)}
:root[data-theme=dark] .sec-badge.n{background:#3a2f12;color:#fbbf24}
:root[data-theme=dark] .sec-badge.f{background:#3a1a1c;color:var(--dead)}
:root[data-theme=dark] .imgpick img,:root[data-theme=dark] .imgpick .ph{background:#1d263b}
:root[data-theme=dark] .score{background:#262f4a;color:#b3b6f5}
:root[data-theme=dark] .stab:hover{background:#232c45;color:#b3b6f5}
:root[data-theme=dark] .stab.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff}
:root[data-theme=dark] .log{color:#c3cad8}
:root[data-theme=dark] .status-bar .pill{background:#1d263b}
:root[data-theme=dark] .seo-box{background:#0b1120;color:#c7d2fe;border:1px solid #1e293b}
:root[data-theme=dark] .tablewrap::-webkit-scrollbar-thumb{background:#37415c}
:root[data-theme=dark] .themebtn{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.34)}

/* ================= 资源请求 / 注册用户（v1.6.2） ================= */
.req-row{border:1px solid var(--line);border-radius:12px;padding:13px 15px;margin-bottom:10px;background:var(--card)}
.req-row .hd{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.req-row .tt{font-weight:700;font-size:14.5px;word-break:break-word}
.req-row .mt{color:var(--mut);font-size:12.5px;margin-top:7px;line-height:1.8;word-break:break-word}
.req-row .nt{font-size:13px;margin-top:8px;line-height:1.8;white-space:pre-wrap;word-break:break-word}
.req-row .ops{display:grid;grid-template-columns:140px 1fr auto;gap:9px;align-items:start;margin-top:11px}
.req-row .ops select,.req-row .ops textarea{width:100%}
.req-row .ops textarea{min-height:46px;resize:vertical;font-family:inherit}
.req-row .opbtns{display:flex;gap:8px;flex-wrap:wrap}
.ubadge{font-size:11.5px;font-weight:700;border-radius:999px;padding:3px 10px;white-space:nowrap}
.ubadge.pending{background:#fef3c7;color:#92400e}
.ubadge.found{background:#dcfce7;color:#166534}
.ubadge.notfound{background:#f1f5f9;color:#475569}
.ubadge.replied{background:#dbeafe;color:#1e40af}
.ubadge.closed{background:#f3f4f6;color:#6b7280}
.ubadge.u1{background:#dcfce7;color:#166534}
.ubadge.u0{background:#fee2e2;color:#991b1b}
.ubadge.u2{background:#fef3c7;color:#92400e}
:root[data-theme=dark] .ubadge.pending{background:#3a2f12;color:#fbbf24}
:root[data-theme=dark] .ubadge.found{background:#10321f;color:#6ee7b7}
:root[data-theme=dark] .ubadge.notfound{background:#26304a;color:#c3cad8}
:root[data-theme=dark] .ubadge.replied{background:#152238;color:#93c5fd}
:root[data-theme=dark] .ubadge.closed{background:#232b3c;color:#9aa3b8}
:root[data-theme=dark] .ubadge.u1{background:#10321f;color:#6ee7b7}
:root[data-theme=dark] .ubadge.u0{background:#3a1a1c;color:#fca5a5}
:root[data-theme=dark] .ubadge.u2{background:#3a2f12;color:#fbbf24}
@media(max-width:768px){
  .req-row .ops{grid-template-columns:1fr}
  .req-row .opbtns{justify-content:flex-start}
}
</style>
<script>
/* 明暗主题：进页面先定好，避免亮色闪一下再变暗。
   优先级：上次手动选择(localStorage) > 系统偏好；默认跟随系统。 */
(function(){
  try{
    var v = localStorage.getItem('ml_admin_theme');
    var p = (v === 'light' || v === 'dark' || v === 'auto') ? v : 'auto';
    var dark = (p === 'dark') || (p === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  }catch(e){
    document.documentElement.setAttribute('data-theme', 'light');
  }
})();
</script>
</head>
<body>
<header>
  <h1>媒资资料库 · 管理后台</h1>
  <span class="sver">Ver <?php echo htmlspecialchars((string)ML_APP_VERSION,ENT_QUOTES,'UTF-8');?></span>
  <span class="sub">条目管理 · 采集同步 · 站点设置 · 下载链接检查 · 静态页生成 · 手机 / 平板 / 大屏自适应</span>
  <button type="button" class="themebtn" onclick="toggleTheme()" title="切换明暗主题">🌗</button>
</header>
<div class="wrap">
  <div class="token-bar">
    <span class="hint">当前登录：</span>
    <b style="margin-right:auto"><?php echo htmlspecialchars((string)$k['username'],ENT_QUOTES,'UTF-8');?></b>
    <button type="button" class="ghost sm" onclick="openPwd()">修改密码</button>
    <a class="ghost" href="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>?logout=1"
       style="text-decoration:none;padding:6px 13px;border-radius:8px;font-size:13px">退出登录</a>
  </div>
  <div class="tabs">
    <div class="tab active" data-tab="overview" onclick="switchTab('overview')">概览</div>
    <div class="tab" data-tab="items" onclick="switchTab('items')">条目管理</div>
    <div class="tab" data-tab="sync" onclick="switchTab('sync')">同步采集</div>
    <div class="tab" data-tab="settings" onclick="switchTab('settings')">系统设置</div>
    <div class="tab" data-tab="site" onclick="switchTab('site')">站点设置</div>
    <div class="tab" data-tab="request" onclick="switchTab('request')">资源请求</div>
    <div class="tab" data-tab="apitoken" onclick="switchTab('apitoken')">API 调用</div>
    <div class="tab" data-tab="security" onclick="switchTab('security')">安全防护</div>
    <div class="tab" data-tab="logs" onclick="switchTab('logs')">同步日志</div>
    <div class="tab" data-tab="scrape" onclick="switchTab('scrape')">自动刮削</div>
    <div class="tab" data-tab="users" onclick="switchTab('users')">用户中心</div>
  </div>

  <div id="tab-overview" class="tabpane">
    <div class="card"><div class="stat-grid" id="stats"></div></div>
  </div>

  <div id="tab-items" class="tabpane" style="display:none">
    <div class="card">
      <div class="cats" id="cats">
        <button class="cat active" data-cat="">全部</button>
        <button class="cat" data-cat="short">短剧</button>
        <button class="cat" data-cat="movie">电影</button>
        <button class="cat" data-cat="tv">电视剧</button>
        <button class="cat" data-cat="anime">动漫</button>
        <button class="cat" data-cat="variety">综艺</button>
        <button class="cat" data-cat="game">游戏</button>
        <button class="cat" data-cat="book">书籍</button>
        <button class="cat" data-cat="music">音乐</button>
        <button class="cat" data-cat="other">其他</button>
      </div>
      <div class="toolbar">
        <input type="text" id="q" placeholder="搜索标题…" onkeydown="if(event.key==='Enter')loadItems()">
        <button onclick="loadItems()">查询</button>
        <button class="ghost" onclick="openAdd()">+ 新增项目</button>
        <button class="ghost" id="btnSyncInline" onclick="triggerSync(false, this)">同步热门</button>
        <button class="ghost" onclick="buildStatic()">生成静态页</button>
        <button class="ghost" onclick="checkAll()">检查全部链接</button>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>封面</th><th>标题</th><th>分类</th><th>来源</th><th>年份</th><th>评分</th><th>下载链接</th><th>操作</th></tr></thead>
          <tbody id="itemRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="pageItems(-1)">上一页</button>
        <span id="pageInfo" class="hint"></span>
        <button class="ghost sm" onclick="pageItems(1)">下一页</button>
      </div>
    </div>
  </div>

  <div id="tab-sync" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">采集（同步热门）</h3>
        <span class="hint">只取各源「最热门 / 最多人看」的榜；<b>已采集过的一律跳过</b>；按各源官方要求限流，单次条数与请求数都有上限。</span>
      </div>
      <div class="row" style="justify-content:flex-start;margin-top:12px">
        <button id="btnSync" onclick="triggerSync(false, this)">▶ 立即采集</button>
        <button class="ghost" id="btnSyncForce" onclick="triggerSync(true, this)">强制采集（忽略最小间隔）</button>
        <button class="ghost" id="btnSyncDry" onclick="dryRun(this)">预演（只探测不入库）</button>
        <span id="syncState" class="hint"></span>
      </div>
      <div id="syncReport" style="margin-top:12px"></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">采集参数</h3>
        <span class="hint">保存到数据库 <b>app_settings</b>，立即生效；<b>后台网页与每小时计划任务共用这一套参数</b>。</span>
      </div>
      <div id="syncParams" style="margin-top:12px"><div class="hint">正在加载…</div></div>
      <div class="row">
        <button class="ghost" onclick="resetSyncParams()">恢复默认</button>
        <button onclick="saveSyncParams()">保存采集参数</button>
      </div>
      <div id="syncMsg" class="set-msg"></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">各源「官方要求」对照本次限制</h3>
        <span class="hint">下面每个数字都来自对应站点的接口文档；我们只会比它更保守，不会更激进。</span>
      </div>
      <div id="syncPlan" style="margin-top:12px"><div class="hint">正在加载…</div></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">每小时计划任务（宝塔面板）</h3>
        <span class="hint">让服务器自己每小时采集一次；命令行脚本已加守卫，<b>用网址访问会 404</b>。</span>
      </div>
      <div id="cronBox" style="margin-top:12px"><div class="hint">正在加载…</div></div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px;font-size:15px">最近采集记录</h3>
      <div id="syncRecent"><div class="hint">正在加载…</div></div>
    </div>
  </div>

  <div id="tab-settings" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">系统设置</h3>
        <span class="hint">改动保存到数据库 <b>app_settings</b> 表并立即生效，<b>优先级高于 config/config.php</b>；留空 = 沿用 config.php 里的值。</span>
      </div>
      <div id="setBox" style="margin-top:14px"><div class="hint">正在加载…</div></div>
      <div class="row">
        <button class="ghost" onclick="resetAllSettings()">全部恢复为 config.php 的值</button>
        <button onclick="saveSettings()">保存设置</button>
      </div>
      <div id="setMsg" class="set-msg"></div>
    </div>
  </div>

  <div id="tab-logs" class="tabpane" style="display:none">
    <div class="card"><div class="log" id="logs"></div></div>
  </div>

  <div id="tab-scrape" class="tabpane" style="display:none">
    <div class="card">
      <h2>自动刮削</h2>
      <p class="hint">自动从 TMDB 补充缺失的海报和背景图</p>
      <div class="form-row">
        <label>类型筛选：</label>
        <select id="scrapeType">
          <option value="">全部</option>
          <option value="movie">电影</option>
          <option value="tv">电视剧</option>
          <option value="anime">动漫</option>
          <option value="short">短剧</option>
          <option value="variety">综艺</option>
          <option value="game">游戏</option>
          <option value="book">书籍</option>
          <option value="music">音乐</option>
          <option value="other">其他</option>
        </select>
      </div>
      <div class="form-row">
        <label>每次处理数量：</label>
        <input type="number" id="scrapeLimit" value="50" min="10" max="500" style="width:100px">
      </div>
      <div class="form-row" style="margin-top:16px">
        <button onclick="startScrape()">开始刮削</button>
        <button class="ghost" onclick="clearScrapeResult()">清除结果</button>
      </div>
      <div id="scrapeStatus" style="margin-top:16px"></div>
      <div id="scrapeLog" style="margin-top:8px;max-height:300px;overflow-y:auto;font-family:monospace;font-size:12px;background:#f5f5f5;padding:8px;border-radius:4px;display:none"></div>
    </div>
  </div>
  <div id="tab-users" class="tabpane" style="display:none">
    <div class="card">
      <h2>用户中心</h2>
      <div class="toolbar">
        <input type="text" id="userQ" placeholder="搜索用户名/邮箱..." onkeydown="if(event.key==='Enter')loadUsers()">
        <button onclick="loadUsers()">查询</button>
        <button class="ghost" onclick="loadUsers()">刷新</button>
      </div>
      <div id="userBox"><div class="hint">加载中...</div></div>
      <div class="pager" id="userPager"></div>
    </div>
  </div>

  <div id="tab-site" class="tabpane" style="display:none">
    <div class="card">
      <div class="subtabs" id="siteSubtabs">
        <button class="stab active" data-stab="basic"  onclick="switchSiteTab('basic')">基础设置</button>
        <button class="stab" data-stab="seo"    onclick="switchSiteTab('seo')">SEO 设置</button>
        <button class="stab" data-stab="jump"   onclick="switchSiteTab('jump')">跳转与扫码</button>
        <button class="stab" data-stab="pan"    onclick="switchSiteTab('pan')">网盘链接</button>
        <button class="stab" data-stab="pansou" onclick="switchSiteTab('pansou')">接口配置</button>
        <button class="stab" data-stab="req"    onclick="switchSiteTab('req')">资源请求</button>
        <button class="stab" data-stab="mail"   onclick="switchSiteTab('mail')">邮件服务</button>
      </div>
      <div id="siteBox"><div class="hint">加载中…</div></div>
      <div class="inline" style="margin-top:18px">
        <button id="siteSaveBtn" onclick="saveSite()">保存设置</button>
        <button class="ghost" onclick="resetSite()">恢复为配置文件的值</button>
        <button class="ghost" onclick="loadSeoPreview()">刷新 SEO 预览</button>
        <span id="siteMsg" class="hint"></span>
      </div>
    </div>
  </div>

  <div id="tab-request" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">资源请求</h3>
        <span class="hint">注册用户在前台「资源请求」页填写的求片内容，在这里逐条处理；回复只有提交者本人能看到。</span>
      </div>
      <div class="status-bar" id="reqBar" style="margin-top:12px"><span class="pill">加载中…</span></div>
      <div class="subtabs" id="reqSubtabs">
        <button class="stab active" data-rstab="list"  onclick="switchReqTab('list')">请求列表</button>
        <button class="stab"        data-rstab="users" onclick="switchReqTab('users')">注册用户</button>
      </div>
      <div class="toolbar">
        <input type="text" id="reqQ" placeholder="搜索资源名称 / 说明…" onkeydown="if(event.key==='Enter')loadReqList()">
        <select id="reqStatus" onchange="loadReqList()"></select>
        <button onclick="loadReqList()">查询</button>
        <button class="ghost" onclick="loadReq()">刷新</button>
      </div>
      <div id="reqBox"><div class="hint">加载中…</div></div>
      <div class="pager">
        <button class="ghost sm" onclick="reqPage(-1)">上一页</button>
        <span id="reqPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="reqPage(1)">下一页</button>
      </div>
    </div>
  </div>

  <div id="tab-apitoken" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">API 调用令牌</h3>
        <span class="hint">把资料库 API 开放给外部系统/个人调用时，在这里给每个调用者发一个独立令牌 —— 可分别限制每日调用次数与来源 IP，并查看「谁在什么时候调用了什么」。</span>
      </div>
      <div class="ext-note" style="margin-top:11px">
        调用方式：请求头 <code>X-Api-Token: 你的令牌</code>，或 URL 参数 <code>?token=你的令牌</code><br>
        示例：<code id="apiDemo">GET /api/v1/search?q=庆余年&amp;token=xxxx</code>
        <button class="ghost sm" style="margin-left:8px" onclick="copyApiDemo()">复制示例</button>
      </div>
      <div class="stat-grid" id="tkStats" style="margin-top:13px"></div>
      <div class="toolbar">
        <button onclick="openTokenAdd()">+ 新建令牌</button>
        <button class="ghost" onclick="loadTokens()">刷新</button>
        <button class="ghost" onclick="switchApiSub('tokens')">令牌列表</button>
        <button class="ghost" onclick="switchApiSub('logs')">调用记录</button>
      </div>
    </div>

    <div class="card" id="tkListCard">
      <div class="tablewrap">
        <table>
          <thead><tr><th>名称</th><th>令牌</th><th>状态</th><th>今日/限额</th><th>累计</th><th>最后调用</th><th>操作</th></tr></thead>
          <tbody id="tkRows"></tbody>
        </table>
      </div>
    </div>

    <div class="card" id="tkLogCard" style="display:none">
      <div class="toolbar">
        <input type="text" id="logStart" placeholder="开始日期 2026-09-01">
        <input type="text" id="logEnd" placeholder="结束日期 2026-09-30">
        <select id="logToken" onchange="loadApiLogs()"></select>
        <button onclick="loadApiLogs()">查询</button>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>时间</th><th>令牌</th><th>接口</th><th>方法</th><th>IP</th><th>耗时</th></tr></thead>
          <tbody id="logRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="logPage(-1)">上一页</button>
        <span id="logPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="logPage(1)">下一页</button>
      </div>
    </div>
  </div>

  <div id="tab-security" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">安全防护</h3>
        <span class="hint">安装器已从站点根移除；安装相关路径、敏感目录与内部 PHP 文件被直接请求时统一返回 404。</span>
      </div>
      <div id="secBox" style="margin-top:12px"><div class="hint">正在检测…</div></div>
      <div class="row" style="justify-content:flex-start">
        <button onclick="openSelfCheck()">🔍 打开环境自检报告</button>
        <button class="ghost" onclick="loadSecurity()">重新检测</button>
      </div>
      <div class="hint" style="margin-top:8px">自检页只认管理员登录态，直接打开即可（已登录就无需再做任何事）。</div>
    </div>
  </div>
</div>

<div class="modal" id="modal">
  <div class="box">
    <h3 id="modalTitle" style="margin-top:0">编辑条目</h3>
    <label>分类（决定新增表单字段）</label>
    <select id="e_type"></select>

    <label>标题 *</label><input id="e_title">
    <label>原标题</label><input id="e_original_title">
    <div class="grid2">
      <div><label>年份</label><input id="e_year" type="number"></div>
      <div><label>评分</label><input id="e_rating" type="number" step="0.1"></div>
    </div>
    <label>类型标签（逗号分隔，如 动作,悬疑）</label><input id="e_genres" placeholder="动作, 悬疑">
    <label>海报 URL</label><input id="e_poster">
    <label>背景图 URL</label><input id="e_backdrop">
    <label>下载链接（可添加多种网盘，如 光鸭 / 夸克；直接粘贴地址，可留空）</label>
    <div id="dlRows"></div>
    <button type="button" class="ghost sm" onclick="addDlRow()">+ 添加下载链接</button>
    <label>简介</label><textarea id="e_overview"></textarea>

    <div id="extraFields"></div>

    <div class="row">
      <button class="ghost" onclick="closeModal()">取消</button>
      <button onclick="saveEdit()">保存</button>
    </div>
    <div class="form-row" style="margin-top:16px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" id="e_downloadImages" value="1">
        <span>自动下载海报/背景图到服务器本地</span>
      </label>
    </div>
  </div>
</div>

<!-- 修改密码 / 邮箱（右上角「修改密码」按钮打开） -->
<div class="modal" id="pwdModal">
  <div class="box" style="max-width:470px">
    <h3 style="margin-top:0">修改密码</h3>
    <p class="hint" style="margin:0 0 4px">改完立即生效，下次请用新密码登录。邮箱用于「找回密码」，建议填写。</p>
    <?php
$o=(string)$k['email'];if($o===\Core\User::ADMIN_TAG){$o='';}?>
    <label>账号邮箱（找回密码用，可留空）</label>
    <input id="pw_email" type="email" value="<?php echo htmlspecialchars($o,ENT_QUOTES,'UTF-8');?>" placeholder="you@example.com">
    <label>当前密码（改密码时必填）</label>
    <input id="pw_old" type="password" placeholder="输入当前登录密码" autocomplete="current-password">
    <label>新密码（不改密码就留空）</label>
    <input id="pw_new" type="password" placeholder="至少 6 位" autocomplete="new-password">
    <label>确认新密码</label>
    <input id="pw_new2" type="password" placeholder="再输一次" autocomplete="new-password">
    <div id="pwMsg" class="set-msg"></div>
    <div class="row">
      <button class="ghost" onclick="closePwd()">取消</button>
      <button onclick="savePwd()">保存修改</button>
    </div>
  </div>
</div>

<script>
// 站点根（兼容 域名根目录 与 子目录部署；去掉结尾 /admin）
// 注意：不能写成 (x || '/')，否则根目录部署时会拼出 "//api/v1/..."（协议相对，会打到别的域名）
const API = (function(){
  let p = location.pathname.replace(/\/admin(\/.*)?$/, '');
  return p.replace(/\/+$/, '');
})();
let curPage = 1, editId = null, curCat = '', SCHEMA = null, PAN_LIST = ['夸克','迅雷','光鸭','百度','UC'];
let LINK_STATUS = {}; // url => true(有效)/false(失效)

/**
 * 统一的接口调用：**永不抛异常**。
 * 以前是 fetch(...).then(r=>r.json())——一旦服务端返回的是 HTML
 * （PHP 致命错误页 / 502 / 504 网关超时），r.json() 会 reject，
 * 调用方的 await 直接中断，按钮看起来就是"点了没反应"（只在控制台留红字）。
 * 现在任何异常/非 JSON 一律转成 {success:false, error:"…"}，页面必定给出提示。
 */
async function api(path, opts={}){
  opts.headers = Object.assign({'Content-Type':'application/json'}, opts.headers||{});
  opts.credentials = 'same-origin';   /* 带上登录会话 Cookie */
  let r;
  try {
    r = await fetch(API + path, opts);
  } catch(e) {
    return {success:false, error:'网络请求失败：'+((e&&e.message)?e.message:e)+'（检查站点能否访问、伪静态是否生效）'};
  }
  let txt = '';
  try { txt = await r.text(); } catch(e) { txt = ''; }
  let d = null;
  try { d = txt ? JSON.parse(txt) : {}; } catch(e) { d = null; }
  if(d === null || typeof d !== 'object'){
    let hint = '';
    if(r.status===502 || r.status===504) hint = '（网关超时／上游错误：本次操作耗时过长，或 PHP 报错被面板拦下）';
    else if(r.status===500) hint = '（服务器内部错误：多为 PHP 致命错误，可打开 check.php 看第 7 组语法检查）';
    else if(r.status===404) hint = '（接口不存在：站点需配置 Nginx 伪静态，见部署文档第四节）';
    else if(r.status===403) hint = '（被拒绝：未登录管理员账号，或被面板安全规则拦截）';
    const peek = String(txt||'').replace(/\s+/g,' ').slice(0,180);
    return {success:false, error:'服务器返回了非 JSON 内容（HTTP '+r.status+'）'+hint+(peek?('：'+peek):''), _http:r.status};
  }
  if(d.success===undefined){ d.success = (r.status>=200 && r.status<300); }
  d._http = r.status;
  if(!d.success && !d.error){ d.error = 'HTTP '+r.status; }
  return d;
}
/** 按钮忙碌态：跑长任务时禁用按钮并显示进度，避免"点了没反应"的错觉 */
async function busy(btn, text, fn){
  if(!btn){ return fn(); }
  const old = btn.textContent, was = btn.disabled;
  btn.disabled = true; btn.textContent = text;
  try { return await fn(); }
  finally { btn.disabled = was; btn.textContent = old; }
}

async function loadSchema(){
  const d = await api('/api/v1/categories');
  if(!d.success) return;
  SCHEMA = d.data;
  PAN_LIST = (d.data && d.data.pan_types && d.data.pan_types.length) ? d.data.pan_types : PAN_LIST;
  const sel = document.getElementById('e_type');
  sel.innerHTML = '';
  for(const k in SCHEMA.labels){
    const o=document.createElement('option'); o.value=k; o.textContent=SCHEMA.labels[k]; sel.appendChild(o);
  }
}
function switchTab(t){
  document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active', x.dataset.tab===t));
  document.querySelectorAll('.tabpane').forEach(x=>x.style.display = (x.id==='tab-'+t)?'block':'none');
  if(t==='overview') loadStats();
  if(t==='items') loadItems();
  if(t==='sync') loadSync();
  if(t==='settings') loadSettings();
  if(t==='site') loadSite();
  if(t==='request') loadReq();
  if(t==='apitoken') loadTokens();
  if(t==='logs') loadLogs();
  if(t==='security') loadSecurity();
  if(t==='scrape') renderScrapePanel();
  if(t==='users') loadUsers();
}
async function loadStats(){
  const d = await api('/api/v1/admin/stats');
  if(!d.success){ alert(d.error||'加载失败'); return; }
  const s=d.data; let h='';
  h+=`<div class="stat"><b>${s.total}</b><span>总条目</span></div>`;
  for(const k in s.by_type) h+=`<div class="stat"><b>${s.by_type[k]}</b><span>${SCHEMA?SCHEMA.labels[k]:k}</span></div>`;
  for(const k in s.by_source) h+=`<div class="stat"><b>${s.by_source[k]}</b><span>源:${k}</span></div>`;
  document.getElementById('stats').innerHTML=h;
}

/* ============ API 调用令牌（v1.7.0）============ */
let TK_LIST=[], TK_PAGE=1, LOG_PAGE=1, API_SUB='tokens';
function switchApiSub(which){
  API_SUB=which;
  document.getElementById('tkListCard').style.display = (which==='tokens')?'block':'none';
  document.getElementById('tkLogCard').style.display  = (which==='logs')?'block':'none';
  if(which==='logs') loadApiLogs();
}
function copyApiDemo(){
  const el=document.getElementById('apiDemo');
  const txt=(el?el.innerText:'');
  if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(()=>alert('已复制：'+txt),()=>alert(txt)); }
  else { alert(txt); }
}
async function loadTokens(){
  const st=await api('/api/v1/admin/token_stats');
  if(st.success){
    const s=st.data;
    document.getElementById('tkStats').innerHTML=
      `<div class="stat"><b>${s.total_tokens||0}</b><span>令牌总数</span></div>`+
      `<div class="stat"><b>${s.active_tokens||0}</b><span>启用中</span></div>`+
      `<div class="stat"><b>${s.today_calls||0}</b><span>今日调用</span></div>`+
      `<div class="stat"><b>${s.yesterday_calls||0}</b><span>昨日调用</span></div>`+
      `<div class="stat"><b>${s.total_calls||0}</b><span>累计调用</span></div>`;
  }
  const d=await api(`/api/v1/admin/token_list?page=${TK_PAGE}`);
  const box=document.getElementById('tkRows');
  if(!d.success){ box.innerHTML=`<tr><td colspan="7" class="hint">${esc(d.error||'加载失败')}</td></tr>`; return; }
  TK_LIST=d.data.items||[];
  if(!TK_LIST.length){ box.innerHTML='<tr><td colspan="7" class="hint">还没有令牌。点上方「+ 新建令牌」给调用者发一个。</td></tr>'; refreshLogTokenSel(); return; }
  let h='';
  TK_LIST.forEach(k=>{
    const on=(String(k.status)==='1');
    const quota=(Number(k.daily_limit)>0)?(' / '+k.daily_limit):' / 不限';
    h+=`<tr>
      <td><b>${esc(k.name||'')}</b></td>
      <td><code class="tkv" title="点击复制" onclick="copyTk('${esc(k.token)}')">${esc(String(k.token||'').slice(0,12))}…</code></td>
      <td><span class="ubadge ${on?'approved':'rejected'}">${on?'启用':'已禁用'}</span></td>
      <td>${k.today_calls||0}${quota}</td>
      <td>${k.usage_count||0}</td>
      <td>${esc(String(k.last_used||'—').slice(0,16))}</td>
      <td style="white-space:nowrap">
        <button class="ghost sm" onclick="toggleTk(${k.id},${on?0:1})">${on?'禁用':'启用'}</button>
        <button class="ghost sm" onclick="editTk(${k.id})">编辑</button>
        <button class="ghost sm" onclick="delTk(${k.id})">删除</button>
      </td></tr>`;
  });
  box.innerHTML=h;
  refreshLogTokenSel();
}
function copyTk(v){
  if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(v).then(()=>alert('令牌已复制：'+v),()=>alert(v)); }
  else { alert(v); }
}
function refreshLogTokenSel(){
  const sel=document.getElementById('logToken');
  if(!sel) return;
  let h='<option value="0">全部令牌</option>';
  TK_LIST.forEach(k=>{ h+=`<option value="${k.id}">${esc(k.name||('#'+k.id))}</option>`; });
  sel.innerHTML=h;
}
async function toggleTk(id,st){
  const d=await api('/api/v1/admin/token_save',{method:'POST',body:JSON.stringify({id:id,status:st})});
  if(!d.success){ alert(d.error||'操作失败'); return; }
  loadTokens();
}
function editTk(id){
  const k=TK_LIST.find(x=>Number(x.id)===Number(id)); if(!k) return;
  const name=prompt('令牌名称（用途说明）', k.name||''); if(name===null) return;
  const lim =prompt('每日调用上限（0 = 不限）', String(k.daily_limit||0)); if(lim===null) return;
  const ip  =prompt('IP 白名单（逗号分隔，留空 = 不限）', k.ip_whitelist||''); if(ip===null) return;
  api('/api/v1/admin/token_save',{method:'POST',body:JSON.stringify({id:id,name:name,daily_limit:parseInt(lim)||0,ip_whitelist:ip})})
    .then(d=>{ if(!d.success){alert(d.error||'保存失败');return;} loadTokens(); });
}
async function delTk(id){
  const k=TK_LIST.find(x=>Number(x.id)===Number(id));
  if(!confirm('确定删除令牌「'+((k&&k.name)||id)+'」？该令牌将立即失效，其调用记录也会一并删除。')) return;
  const d=await api('/api/v1/admin/token_del',{method:'POST',body:JSON.stringify({id:id})});
  if(!d.success){ alert(d.error||'删除失败'); return; }
  loadTokens();
}
async function openTokenAdd(){
  const name=prompt('给谁用？（填名称，例如：客户A / 某某App）'); if(!name) return;
  const lim =prompt('每日调用上限（0 = 不限）','0'); if(lim===null) return;
  const ip  =prompt('IP 白名单（逗号分隔，留空 = 不限）',''); if(ip===null) return;
  const d=await api('/api/v1/admin/token_add',{method:'POST',body:JSON.stringify({name:name,daily_limit:parseInt(lim)||0,ip_whitelist:ip})});
  if(!d.success){ alert(d.error||'创建失败'); return; }
  alert('令牌已创建，请复制保存：\n\n'+d.data.token+'\n\n（它只在这里完整显示这一次）');
  loadTokens();
}
async function loadApiLogs(){
  const box=document.getElementById('logRows');
  box.innerHTML='<tr><td colspan="6" class="hint">加载中…</td></tr>';
  const tid=document.getElementById('logToken').value||0;
  const sd=document.getElementById('logStart').value.trim();
  const ed=document.getElementById('logEnd').value.trim();
  const d=await api(`/api/v1/admin/api_logs?page=${LOG_PAGE}&token_id=${tid}&start=${encodeURIComponent(sd)}&end=${encodeURIComponent(ed)}`);
  if(!d.success){ box.innerHTML=`<tr><td colspan="6" class="hint">${esc(d.error||'加载失败')}</td></tr>`; return; }
  const items=d.data.items||[];
  document.getElementById('logPageInfo').innerText='第 '+LOG_PAGE+' 页 / 共 '+(d.data.total||0)+' 条';
  if(!items.length){ box.innerHTML='<tr><td colspan="6" class="hint">这段时间没有调用记录。</td></tr>'; return; }
  let h='';
  items.forEach(r=>{
    h+=`<tr>
      <td>${esc(String(r.created_at||'').slice(0,19))}</td>
      <td>${esc(r.token_name||('#'+r.token_id))}</td>
      <td><code>${esc(r.endpoint||'')}</code></td>
      <td>${esc(r.method||'')}</td>
      <td>${esc(r.ip||'')}</td>
      <td>${r.cost_ms||0} ms</td></tr>`;
  });
  box.innerHTML=h;
}
function logPage(n){
  if(n<0 && LOG_PAGE<=1) return;
  LOG_PAGE+=n; loadApiLogs();
}

// 分类切换
document.getElementById('cats').addEventListener('click', e=>{
  if(e.target.classList.contains('cat')){
    document.querySelectorAll('#cats .cat').forEach(x=>x.classList.toggle('active', x===e.target));
    curCat = e.target.dataset.cat;
    curPage = 1; loadItems();
  }
});
async function loadItems(){
  const q=document.getElementById('q').value;
  const d=await api(`/api/v1/admin/items?page=${curPage}&type=${curCat}&q=${encodeURIComponent(q)}`);
  if(!d.success){ alert(d.error||'加载失败'); return; }
  // 拉取链接状态汇总
  let map={};
  try{ const m=await api('/api/v1/admin/link_status_map'); if(m.success) map=m.data.map||{}; }catch(e){}
  let h='';
  d.data.items.forEach(it=>{
    const lb = SCHEMA ? (SCHEMA.labels[it.type]||it.type) : it.type;
    const st = linkCell(map[it.id]);
    h+=`<tr>
      <td>${it.poster?`<img class="thumb" src="${it.poster}">`:''}</td>
      <td>${esc(it.title||'')}<br><span class="hint">${(it.original_title||'')}</span></td>
      <td>${lb}</td><td>${esc(it.source||'')}</td><td>${it.year||''}</td><td>${it.rating==null?'':it.rating}</td>
      <td>${st}</td>
      <td><button class="ghost sm" onclick="checkOne(${it.id})">查</button>
          <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
          <button class="danger sm" onclick="del(${it.id})">删</button></td></tr>`;
  });
  document.getElementById('itemRows').innerHTML=h;
  document.getElementById('pageInfo').innerText=`第 ${curPage} 页 / 共 ${d.data.total} 条`;
}
function linkCell(s){
  if(!s) return '<span class="st st-none">未检测</span>';
  const dl = s.dead||0;
  if(dl>0) return `<span class="st st-dead">✗ 失效 ${dl}</span>`;
  return '<span class="st st-ok">✓ 有效</span>';
}
function pageItems(d){ if(curPage+d<1) return; curPage+=d; loadItems(); }

// 渲染分类扩展字段
function renderExtra(type, values){
  const box=document.getElementById('extraFields'); box.innerHTML='';
  const fs = (SCHEMA && SCHEMA.extra_fields[type]) ? SCHEMA.extra_fields[type] : [];
  if(!fs.length){ box.innerHTML='<div class="ext-note">该分类暂无额外字段，想加集数/导演等可在 core/Categories.php 配置。</div>'; return; }
  let h='<div class="ext-note">'+ (SCHEMA.labels[type]||type) +'专属字段：</div>';
  fs.forEach(f=>{
    h+=`<label>${f.label}</label><input id="x_${f.k}" placeholder="${f.ph||''}" value="${(values&&values[f.k]!=null)?esc(values[f.k]):''}">`;
  });
  box.innerHTML=h;
}
function openAdd(){
  editId=0; LINK_STATUS={};
  ['e_title','e_original_title','e_year','e_rating','e_genres','e_poster','e_backdrop','e_overview']
    .forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  const sel=document.getElementById('e_type'); sel.value = curCat || 'movie';
  document.getElementById('dlRows').innerHTML='';
  addDlRow(PAN_LIST[0]||'夸克','', null);
  renderExtra(sel.value, {});
  document.getElementById('modalTitle').innerText='新增项目';
  document.getElementById('modal').style.display='flex';
}
async function openEdit(id){
  editId=id; const d=await api('/api/v1/admin/item/'+id);
  if(!d.success){ alert(d.error); return; }
  const it=d.data;
  // 链接状态映射
  LINK_STATUS={};
  (it.link_checks||[]).forEach(c=>{ LINK_STATUS[c.url] = (parseInt(c.status,10)===1); });
  document.getElementById('e_type').value=it.type||'movie';
  document.getElementById('e_title').value=it.title||'';
  document.getElementById('e_original_title').value=it.original_title||'';
  document.getElementById('e_year').value=it.year||'';
  document.getElementById('e_rating').value=it.rating==null?'':it.rating;
  try{ const gs=JSON.parse(it.genres||'[]'); document.getElementById('e_genres').value=Array.isArray(gs)?gs.join(', '):''; }catch(e){ document.getElementById('e_genres').value=''; }
  document.getElementById('e_poster').value=it.poster||'';
  document.getElementById('e_backdrop').value=it.backdrop||'';
  // 下载链接：解析 JSON 数组 [{"label","url"}] 或旧版纯文本
  const links = parseDownloads(it.download_url||'');
  document.getElementById('dlRows').innerHTML='';
  if(links.length){ links.forEach(l=>addDlRow(l.label||(PAN_LIST[0]||'夸克'), l.url||'', statusOf(l.url))); } else { addDlRow(PAN_LIST[0]||'夸克','', null); }
  document.getElementById('e_overview').value=it.overview||'';
  let extra={}; try{ extra=JSON.parse(it.extra||'{}')||{}; }catch(e){ extra={}; }
  renderExtra(it.type||'movie', extra);
  document.getElementById('modalTitle').innerText='编辑 #'+id;
  document.getElementById('modal').style.display='flex';
}
function statusOf(url){ return (url && url in LINK_STATUS) ? LINK_STATUS[url] : null; }
function closeModal(){ document.getElementById('modal').style.display='none'; }

/* ---- 修改密码 / 邮箱（右上角按钮） ---- */
function openPwd(){
  const m=document.getElementById('pwdModal');
  if(!m) return;
  ['pw_old','pw_new','pw_new2'].forEach(function(id){ const el=document.getElementById(id); if(el) el.value=''; });
  const msg=document.getElementById('pwMsg'); if(msg){ msg.innerText=''; msg.style.color=''; }
  m.style.display='flex';
}
function closePwd(){ const m=document.getElementById('pwdModal'); if(m) m.style.display='none'; }
async function savePwd(){
  const msg=document.getElementById('pwMsg');
  const em   = (document.getElementById('pw_email').value||'').trim();
  const oldp = document.getElementById('pw_old').value||'';
  const n1   = document.getElementById('pw_new').value||'';
  const n2   = document.getElementById('pw_new2').value||'';
  const fail = function(t){ if(msg){ msg.style.color='var(--dead)'; msg.innerText=t; } };
  const payload = { email: em };
  if(oldp || n1 || n2){
    if(!oldp){ return fail('请输入当前密码。'); }
    if(n1.length<6){ return fail('新密码至少 6 位。'); }
    if(n1!==n2){ return fail('两次输入的新密码不一致。'); }
    payload.old=oldp; payload.new=n1; payload.new2=n2;
  }
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='保存中…'; }
  const d = await api('/api/v1/admin/password',{method:'POST',body:JSON.stringify(payload)});
  if(!d.success){ return fail(d.error||'修改失败'); }
  if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ '+((d.data&&d.data.msg)?d.data.msg:'已保存'); }
  setTimeout(closePwd, 1200);
}
async function saveEdit(){
  const type=document.getElementById('e_type').value;
  const genres=document.getElementById('e_genres').value.split(',').map(s=>s.trim()).filter(Boolean);
  const extra={};
  const fs=(SCHEMA&&SCHEMA.extra_fields[type])?SCHEMA.extra_fields[type]:[];
  fs.forEach(f=>{ const el=document.getElementById('x_'+f.k); if(el){ const v=el.value.trim(); if(v) extra[f.k]=v; } });
  const body={
    type,
    title:document.getElementById('e_title').value.trim(),
    original_title:document.getElementById('e_original_title').value.trim(),
    year:document.getElementById('e_year').value||null,
    rating:document.getElementById('e_rating').value||null,
    genres:JSON.stringify(genres),
    poster:document.getElementById('e_poster').value.trim(),
    backdrop:document.getElementById('e_backdrop').value.trim(),
    download_images:document.getElementById('e_downloadImages')?.checked ? '1' : '0',
    download_url:collectDownloads(),
    overview:document.getElementById('e_overview').value,
    extra:JSON.stringify(extra),
  };
  if(!body.title){ alert('标题必填'); return; }
  const d = editId
    ? await api('/api/v1/admin/item/'+editId,{method:'PUT',body:JSON.stringify(body)})
    : await api('/api/v1/admin/item',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ alert(d.error); return; }
  const id = editId ? editId : (d.data.id||0);
  if(id){ await api('/api/v1/admin/build',{method:'POST',body:JSON.stringify({id})}).catch(()=>{}); }
  closeModal(); loadItems();
}
async function del(id){ if(!confirm('确认删除 #'+id+' ？')) return; const d=await api('/api/v1/admin/item/'+id,{method:'DELETE'}); if(d.success) loadItems(); else alert(d.error); }
/**
 * 采集入口：网页按钮与计划任务走同一套引擎（core/Sync.php）。
 * force=true 忽略「最小间隔」；dry=true 只探测接口不入库。
 */
async function triggerSync(force, btn){
  const st  = document.getElementById('syncState');
  const box = document.getElementById('syncReport');
  if(btn) btn.disabled = true;
  if(st)  st.textContent = (force ? '正在强制采集' : '正在采集') + '（按各源官方要求限流，可能要十几秒）…';
  if(box) box.innerHTML = '<div class="hint">正在请求上游…请勿关闭页面。</div>';
  const d = await api('/api/v1/admin/sync', {method:'POST', body: JSON.stringify({force: !!force})});
  if(btn) btn.disabled = false;
  if(st) st.textContent = '';
  if(!d.success){
    if(box) box.innerHTML = '<div class="ext-note note-err">✗ 采集失败：'+esc(d.error)+'</div>';
    return;
  }
  renderSyncReport(d.data);
  loadStats(); loadItems(); loadSyncPlan(); loadSyncRecent();
}
async function dryRun(btn){
  const box = document.getElementById('syncReport');
  if(btn) btn.disabled = true;
  const d = await api('/api/v1/admin/sync', {method:'POST', body: JSON.stringify({dry:true, force:true})});
  if(btn) btn.disabled = false;
  if(!d.success){ if(box) box.innerHTML='<div class="ext-note note-err">✗ 预演失败：'+esc(d.error)+'</div>'; return; }
  renderSyncReport(d.data, true);
}
/** 把采集报告画成一张清楚的表（而不是一句 alert） */
function renderSyncReport(r, isDry){
  const box = document.getElementById('syncReport');
  if(!box || !r) return;
  let h = '';
  if(r.skipped){
    h += `<div class="ext-note note-warn">⏸ 本次跳过：${esc(r.reason||'')}</div>`;
  } else if(!r.ok){
    h += `<div class="ext-note note-err">✗ 失败：${esc(r.reason||'未知原因')}</div>`;
  } else {
    h += `<div class="ext-note note-ok">${isDry?'🔍 预演完成（未入库）':'✓ 采集完成'}：
      抓到 <b>${r.fetched}</b> 条 → 新增 <b>${r.inserted}</b> 条，重复跳过 <b>${r.skipped_dup}</b> 条${r.skipped_low?('，低于票数门槛 '+r.skipped_low+' 条'):''}；
      上游请求 <b>${r.requests}</b> 次，静态页 <b>${r.built}</b> 个，耗时 ${((r.ms||0)/1000).toFixed(1)}s。
      ${r.reason?('<br>提示：'+esc(r.reason)):''}</div>`;
  }
  h += '<table style="margin-top:10px"><thead><tr><th>数据源</th><th>接口</th><th>抓到</th><th>新增</th><th>重复跳过</th><th>备注</th></tr></thead><tbody>';
  (r.sources||[]).forEach(s=>{
    const eps = (s.endpoints||[]);
    if(!eps.length){
      h += `<tr><td>${esc(s.label||s.source)}</td><td class="hint">—</td><td>${s.fetched||0}</td><td>${s.inserted||0}</td><td>${s.skipped_dup||0}</td><td class="hint">${esc(s.msg||'')}</td></tr>`;
    } else {
      eps.forEach((ep,i)=>{
        h += `<tr>`+
          (i===0?`<td rowspan="${eps.length}">${esc(s.label||s.source)}</td>`:'')+
          `<td class="hint">${esc(ep.path)}${ep.error?(' <span style="color:#b91c1c">✗ '+esc(ep.error)+'</span>'):''}</td>`+
          (i===0?`<td rowspan="${eps.length}">${s.fetched||0}</td><td rowspan="${eps.length}">${s.inserted||0}</td><td rowspan="${eps.length}">${s.skipped_dup||0}</td>`:'')+
          (i===0?`<td rowspan="${eps.length}" class="hint">${esc(s.msg||'')}</td>`:'')+
        `</tr>`;
      });
    }
  });
  h += '</tbody></table>';
  box.innerHTML = h;
}

/* ===== 同步采集页（参数 / 官方限制对照 / 计划任务 / 最近记录）===== */
let SYNC_PLAN = null, SYNC_META = null, SYNC_DRAFT = null;

function syncGroupKeys(){
  const keys = [];
  const m = SYNC_META || {};
  for(const k in m){ if(m[k] && m[k].group==='sync'){ keys.push(k); } }
  return keys;
}
async function loadSync(){
  await loadSyncPlan();
  await loadSyncRecent();
}
async function loadSyncPlan(){
  const pbox = document.getElementById('syncParams');
  const box  = document.getElementById('syncPlan');
  const cbox = document.getElementById('cronBox');
  const rbox = document.getElementById('syncRecent');
  if(pbox) pbox.innerHTML = '<div class="hint">正在加载…</div>';
  const [plan, set] = await Promise.all([ api('/api/v1/admin/sync_plan'), api('/api/v1/admin/settings') ]);
  if(!plan.success){
    /* ★ v2.1.0 fix：原来只在 pbox 里写错误，box/cbox/rbox 三个卡片永远停在"正在加载…"。
       用户如果没滚到最上面就看不到 pbox 里的报错，只看到三块空白，以为"卡住了"。
       这里把同一个错误文案同时写进所有相关容器，确保无论用户滚动到哪个位置都能看见。 */
    const err = '<div class="hint">⚠ ' + esc(plan.error||'加载失败') + '</div>';
    if(pbox) pbox.innerHTML = err;
    if(box)  box.innerHTML  = err;
    if(cbox) cbox.innerHTML = err;
    if(rbox) rbox.innerHTML = err;
    return;
  }
  SYNC_PLAN = plan.data;
  if(set.success && set.data && set.data.meta){ SYNC_META = set.data.meta; }
  renderSyncParams();
  renderSyncPlanTable();
  renderCronBox();
  /* 顺手把"最近采集记录"也一起填了，免得 loadSync() 里再等一次 */
  loadSyncRecent();
}
function renderSyncParams(){
  const pbox = document.getElementById('syncParams');
  if(!pbox) return;
  const m = SYNC_META || {};
  const keys = syncGroupKeys();
  if(!keys.length){
    pbox.innerHTML = '<div class="hint">未能读到参数定义（请确认站点已上传最新的 core/Settings.php）。</div>';
    return;
  }
  let h = '';
  keys.forEach(k=>{
    const mm = m[k]||{};
    const cur = (SYNC_DRAFT && SYNC_DRAFT[k]!=null) ? SYNC_DRAFT[k] : syncEffective(k, mm);
    let ctrl = '';
    if(mm.type==='select'){
      const opts = mm.options||{};
      ctrl = `<select id="sy_${k}" onchange="onSyncParamChange()">` +
        Object.keys(opts).map(o=>`<option value="${esc(o)}" ${String(o)===String(cur)?'selected':''}>${esc(opts[o])}</option>`).join('') +
      `</select>`;
    } else {
      ctrl = `<input type="number" id="sy_${k}" min="${mm.min!=null?mm.min:0}" max="${mm.max!=null?mm.max:''}" value="${esc(cur)}" onchange="onSyncParamChange()" style="width:110px">`
           + (mm.unit?`<span class="hint" style="margin-left:6px">${esc(mm.unit)}</span>`:'');
    }
    h += `<div class="set-sec">
      <h4>${esc(mm.label||k)}</h4>
      <div class="tip">${esc(mm.tip||'')}</div>
      <div class="inline">${ctrl}</div>
    </div>`;
  });
  pbox.innerHTML = h;
}
/** 取某个采集参数的当前生效值（plan.params 用的是 sync 段子键） */
function syncEffective(settingKey, meta){
  const map = {sync_limit:'limit',sync_max_pages:'max_pages',sync_max_requests:'max_requests',
    sync_window:'window',sync_order:'order',sync_min_votes:'min_votes',sync_dedupe:'dedupe',
    sync_skip_same_title:'skip_same_title',sync_interval:'interval',sync_build:'build',
    sync_hourly_enabled:'hourly_enabled',sync_hourly_limit:'hourly_limit'};
  const child = map[settingKey];
  const p = (SYNC_PLAN && SYNC_PLAN.params) ? SYNC_PLAN.params : {};
  if(child && p[child]!=null) return String(p[child]);
  return '';
}
function onSyncParamChange(){ collectSyncDraft(); }
function collectSyncDraft(){
  const d = {};
  syncGroupKeys().forEach(k=>{ const el=document.getElementById('sy_'+k); if(el) d[k]=el.value; });
  SYNC_DRAFT = d;
}
function renderSyncPlanTable(){
  const box = document.getElementById('syncPlan');
  if(!box || !SYNC_PLAN) return;
  const p = SYNC_PLAN.params||{};
  let h = `<div class="hint" style="margin-bottom:8px">
    本次口径：<b>${p.order==='hot'?'只要最热':(p.order==='popular'?'只要最多人看':'最热 + 最多人看（自动去重）')}</b>
    · 时间窗 <b>${p.window==='day'?'今日':'本周'}</b>
    · 每源最多 <b>${p.limit}</b> 条
    · 单次请求总上限 <b>${p.max_requests}</b> 次
    · 实际将发出 <b>${SYNC_PLAN.requests}</b> 次请求
    · 最小间隔 <b>${p.interval}</b> 分钟
    ${SYNC_PLAN.minutes_since==null ? '（尚未采集过）' : ('（距上次 '+SYNC_PLAN.minutes_since+' 分钟）')}
  </div>`;
  h += '<table><thead><tr><th>数据源</th><th>本次要打的接口（最热 / 最多人看）</th><th>官方限制与我们自设的上限</th><th>本次条数</th><th>请求间隔</th></tr></thead><tbody>';
  (SYNC_PLAN.sources||[]).forEach(s=>{
    const eps = (s.endpoints||[]).map(e=>`<div style="font-family:Consolas,monospace;font-size:12px">${esc(e.path)}</div><div class="hint" style="margin-bottom:4px">${esc(e.label)}</div>`).join('');
    h += `<tr>
      <td><b>${esc(s.label)}</b><div class="hint"><a href="${esc(s.docs)}" target="_blank" rel="noopener" style="color:inherit">接口文档</a></div></td>
      <td>${eps||'<span class="hint">—</span>'}</td>
      <td><div class="hint" style="line-height:1.8">${esc(s.quota)}<br>单页上限：${s.page_size>0?s.page_size:'—'} 条 · 本系统每源上限：<b>${s.limit}</b> 条</div></td>
      <td>${s.limit} 条</td>
      <td>${s.delay_ms} ms / 请求</td>
    </tr>`;
  });
  h += '</tbody></table>';
  h += `<div class="hint" style="margin-top:8px">说明：<b>重复就跳过</b> —— 同源同 ID 已存在直接跳过${p.skip_same_title?('；同名同年（跨源）也跳过'):''}
    ；${p.dedupe==='update'?'已存在的条目会刷新评分/热度字段':'不刷新已存在条目的数据'}。
    低于 <b>${p.min_votes}</b> 票的条目不入库。</div>`;
  box.innerHTML = h;
}
function renderCronBox(){
  const box = document.getElementById('cronBox');
  if(!box || !SYNC_PLAN) return;
  const cmd = SYNC_PLAN.cron_cmd || 'php /www/wwwroot/你的站点/cron/sync_hourly.php';
  let h = `<div class="inline">
      <input id="cronCmd" type="text" readonly value="${esc(cmd)}" style="min-width:420px">
      <button type="button" class="ghost" onclick="copyCron()">复制命令</button>
    </div>
    <ol class="hint" style="margin:10px 0 0 18px;line-height:2">
      <li>宝塔面板 → 左侧「<b>计划任务</b>」→ 添加任务</li>
      <li>任务类型选「<b>Shell 脚本</b>」，任务名称如「媒资库-每小时采集」</li>
      <li>执行周期选「<b>N 小时 → 1 小时</b>」（或「每小时」）</li>
      <li>脚本内容填上面这条命令 → 保存；建议先点一次「执行」看输出是否正常</li>
    </ol>
    <div class="hint" style="margin-top:8px">
      命令里的 php 建议用绝对路径（本机当前检测到：<code>${esc(SYNC_PLAN.php_bin||'php')}</code>），
      这样计划任务不会因为 PATH 不同而找不到命令。<br>
      脚本已做三重限制：① 与上次同步间隔不足 <b>${(SYNC_PLAN.params||{}).interval||55}</b> 分钟会自己跳过（防重复触发）；
      ② 同一时刻只允许一个采集在跑（进程锁）；③ 单次请求总数不超过 <b>${(SYNC_PLAN.params||{}).max_requests||8}</b> 次。
    </div>`;
  box.innerHTML = h;
}
function copyCron(){
  const el = document.getElementById('cronCmd');
  if(!el) return;
  el.removeAttribute('readonly'); el.select(); el.setSelectionRange(0, 9999);
  let ok = false;
  try { ok = document.execCommand('copy'); } catch(e) { ok = false; }
  el.setAttribute('readonly','readonly');
  if(!ok && navigator.clipboard){ navigator.clipboard.writeText(el.value); ok = true; }
  const m = document.getElementById('syncMsg');
  if(m){ m.style.color = ok ? 'var(--ok)' : 'var(--dead)'; m.innerText = ok ? '✓ 命令已复制到剪贴板' : '复制失败，请手动选中复制'; }
}
async function loadSyncRecent(){
  const box = document.getElementById('syncRecent');
  if(!box) return;
  /* ★ v2.1.0 fix：SYNC_PLAN 没准备好（接口失败 / 还没跑完）时，
     原来直接 return 让"最近采集记录"卡在"正在加载…"，现在显式提示。 */
  if(!SYNC_PLAN){ box.innerHTML = '<div class="hint">采集参数加载失败，暂无法显示采集记录（先解决上方参数卡片里的错误）</div>'; return; }
  const rs = SYNC_PLAN.recent || [];
  if(!rs.length){ box.innerHTML = '<div class="hint">还没有采集记录。点上面的「立即采集」或配好计划任务后就会出现在这里。</div>'; return; }
  let h = '<table><thead><tr><th>时间</th><th>触发方式</th><th>新增</th><th>结果</th><th>各源</th></tr></thead><tbody>';
  rs.forEach(r=>{
    const srcs = (r.sources||[]).map(s=>`${esc(s.source)}:${s.inserted}/跳过${s.skipped_dup}`).join(' · ');
    const kind = r.task==='sync:hourly' ? '计划任务' : (r.task==='sync:manual' ? '后台手动' : esc(r.task));
    h += `<tr>
      <td class="hint">${esc(r.created_at)}</td>
      <td>${kind}</td>
      <td>${r.items}</td>
      <td>${r.ok?'<span class="st st-ok">✓</span>':'<span class="st st-dead">✗</span>'}<span class="hint"> ${esc(String(r.reason||'').slice(0,60))}</span></td>
      <td class="hint">${srcs||'—'}</td>
    </tr>`;
  });
  h += '</tbody></table>';
  box.innerHTML = h;
}
async function saveSyncParams(){
  collectSyncDraft();
  const body = Object.assign({}, SYNC_DRAFT||{});
  const msg = document.getElementById('syncMsg');
  msg.style.color = 'var(--mut)'; msg.innerText = '保存中…';
  const d = await api('/api/v1/admin/settings', {method:'POST', body: JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText = d.error||'保存失败'; return; }
  msg.style.color = 'var(--ok)'; msg.innerText = '✓ 已保存，网页采集与每小时计划任务都已按新参数执行';
  SYNC_DRAFT = null;
  await loadSyncPlan();
}
async function resetSyncParams(){
  if(!confirm('把采集参数恢复为默认（每源 20 条 / 今日 / 最热+最多人看 / 最小间隔 55 分钟…）？')) return;
  const body = {};
  syncGroupKeys().forEach(k=>{ body[k] = ''; });
  const msg = document.getElementById('syncMsg');
  const d = await api('/api/v1/admin/settings', {method:'POST', body: JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText = d.error||'操作失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复默认';
  SYNC_DRAFT = null;
  await loadSyncPlan();
}
async function buildStatic(){ const d=await api('/api/v1/admin/build',{method:'POST'}); alert(d.success?('已生成 '+d.data.built+' 个静态页'):(d.error||'失败')); }
async function loadLogs(){ const d=await api('/api/v1/admin/sync_log'); if(!d.success){alert(d.error);return;} document.getElementById('logs').innerText=JSON.stringify(d.data.items,null,2); }
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

// ===== 下载链接（多条网盘，类型来自 config/pan_types.php，可升级扩展）=====
function panOptions(list, sel){
  list = (list && list.length) ? list : PAN_LIST;
  let h=''; list.forEach(t=>{ h+=`<option value="${esc(t)}" ${t===sel?'selected':''}>${esc(t)}</option>`; }); return h;
}
function pill(ok){
  if(ok===true)  return '<span class="st st-ok">✓ 有效</span>';
  if(ok===false) return '<span class="st st-dead">✗ 失效</span>';
  return '<span class="st st-none">未检测</span>';
}
function addDlRow(label, url='', status=null){
  label = label || (PAN_LIST[0]||'夸克');
  const wrap=document.getElementById('dlRows');
  const row=document.createElement('div'); row.className='dl-row';
  row.innerHTML=`<span class="dl-st">${pill(status)}</span>`
    +`<select class="dl-type">${panOptions(PAN_LIST, label)}</select>`
    +`<input class="dl-url" placeholder="粘贴链接，如 https://... 或 ed2k://..." value="${esc(url)}">`
    +`<button type="button" class="ghost" onclick="this.parentNode.remove()">删</button>`;
  wrap.appendChild(row);
}
function collectDownloads(){
  const rows=document.querySelectorAll('#dlRows .dl-row');
  const links=[];
  rows.forEach(r=>{ const url=r.querySelector('.dl-url').value.trim(); if(url) links.push({label:r.querySelector('.dl-type').value, url}); });
  return JSON.stringify(links);
}
function parseDownloads(raw){
  raw=(raw||'').trim(); if(!raw) return [];
  if(raw[0]==='['){ try{ const a=JSON.parse(raw); if(Array.isArray(a)) return a.map(e=> e&&typeof e==='object'? {label:e.label||'下载',url:e.url||''} : {label:'下载',url:String(e)}); }catch(e){} }
  return raw.split(/\r?\n/).map(s=>s.trim()).filter(Boolean).map(u=>({label:'下载',url:u}));
}

// ===== 链接失效检查 =====
async function checkOne(id){
  const btns=document.querySelectorAll('#itemRows button'); btns.forEach(b=>b.disabled=true);
  const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({id})});
  btns.forEach(b=>b.disabled=false);
  if(!d.success){ alert(d.error||'检查失败'); return; }
  const rs=d.data.results||[]; const dead=rs.filter(r=>!r.ok).length;
  alert('检测完成：共 '+rs.length+' 条，有效 '+(rs.length-dead)+' 条，失效 '+dead+' 条');
  loadItems();
}
async function checkAll(){
  if(!confirm('将逐个检测所有已填写下载链接的条目，可能耗时较久，确定继续？')) return;
  let remaining=1, round=0, ok=0, dead=0;
  while(remaining>0 && round<40){
    round++;
    const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({limit:40})});
    if(!d.success){ alert(d.error||'检查失败'); break; }
    ok+=d.data.ok||0; dead+=d.data.dead||0; remaining=d.data.remaining||0;
    if(remaining===0 || (d.data.checked||0)===0) break; // 无进展则停止，避免空转
  }
  alert('全部检测完成：有效 '+ok+' 条，失效 '+dead+' 条');
  loadItems();
}

// ===== 系统设置（API Key / 数据源 / 网盘类型）=====
let SET = null;        // 后端返回的设置视图
let PAN_DRAFT = [];    // 网盘类型草稿（保存时才提交）
let TXT_DRAFT = null;  // 编辑中的文本值（重绘页面时保留，避免刚打的字被清空）
let AD_DRAFT  = null;  // 编辑中的数据源勾选
let SET_TS    = {};    // 数据源连通状态：{tmdb:{ok,label,detail}, hongguoduanju:{…}}
const SRCS = [
  {kind:'tmdb',    name:'TMDB'},
  {kind:'hongguoduanju', name:'红果短剧（国产短剧）'}
];

const TXT_KEYS = ['tmdb_api_key'];
const AD_KEYS  = ['tmdb','hongguoduanju'];

function mask(s){
  s = String(s||'');
  if(!s) return '（未填写）';
  if(s.length<=10) return s.slice(0,2)+'•••';
  return s.slice(0,4)+'••••'+s.slice(-4);
}
function flag(override){
  return override ? '<span class="set-flag on">已在后台覆盖</span>'
                  : '<span class="set-flag off">来自 config.php</span>';
}
/** 把当前表单里的输入收进草稿（重绘前调用） */
function collectDraft(){
  const t = {};
  TXT_KEYS.forEach(k=>{ const el=document.getElementById('s_'+k); if(el) t[k]=el.value; });
  TXT_DRAFT = t;
  const a = [];
  AD_KEYS.forEach(k=>{ const el=document.getElementById('a_'+k); if(el&&el.checked) a.push(k); });
  AD_DRAFT = a;
}
/* ==================================================================
   站点设置：基础设置 / SEO / 跳转与扫码 / 网盘链接 / 接口配置
   数据全部走 app_settings（与「系统设置」同一张表、同一套白名单），
   键的元信息来自后端 Core\Settings::schema()，前端按 group 分组渲染。
   ================================================================== */
let SITE = null;                 // 设置数据（items + meta）
let SITE_TAB = 'basic';          // 当前二级页
let SITE_DRAFT = {};             // 未保存的输入
let PANSOU_STATE = null;         // 接口连通状态（点「测试连接」后填）

const SITE_GROUPS = {
  basic:  ['site'],
  seo:    ['seo'],
  jump:   ['redirect'],
  pan:    ['pan'],
  pansou: ['pansou'],
  req:    ['req'],
  mail:   ['mail']
};
const SITE_HINTS = {
  basic:  '站点名称、LOGO 与页脚 —— 决定前台顶栏和页脚长什么样。',
  seo:    '首页 / 列表页 / 内页的标题、关键词、描述按这里的模板自动生成；下面是实际效果预览。',
  jump:   'PC 访客打开首页时的处理方式（跳转 / 扫码 / 两者），以及前台模板、明暗模式与顶部导航。前台已做手机 / 平板 / 大屏自适应。',
  pan:    '各网盘的 Cookie 与转存目录，供后续转存 / 直链能力使用；本页只负责保存与状态显示。',
  pansou: '对接 PanSou 网盘搜索服务：每次采集入库新资源后，自动搜索并补写网盘下载地址。',
  req:    '注册与「资源请求」页的开关：谁能注册、谁能提交、列表是否公开、每人每天能提几条。改完立即生效，前台顶栏与页面文案跟着变。',
  mail:   '找回密码的发信服务（SMTP）：填邮箱的 SMTP 账号与授权码、打开开关，点「发送测试邮件」验证。QQ / 163 / Gmail 需先在邮箱设置里生成「授权码」当密码用；没配好时「忘记密码」会明确提示，不会静默失败。'
};
const PAN_ACCOUNTS = { quark:'夸克网盘', aliyun:'阿里云盘', baidu:'百度网盘', uc:'UC网盘', xunlei:'迅雷云盘', guangya:'光鸭网盘' };

function switchSiteTab(tab){
  collectSiteDraft();
  SITE_TAB = tab;
  document.querySelectorAll('#siteSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.stab===tab));
  renderSite();
}
function siteKeys(){
  const groups = SITE_GROUPS[SITE_TAB] || [];
  const meta = (SITE && SITE.meta) || {};
  const out = [];
  for(const k in meta){ if(groups.indexOf(meta[k].group||'')>=0) out.push(k); }
  return out;
}
async function loadSite(){
  const box = document.getElementById('siteBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const d = await api('/api/v1/admin/settings');
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  SITE = d.data;
  SITE_DRAFT = {};
  renderSite();
  const m = document.getElementById('siteMsg'); m.innerText=''; m.style.color='';
}
// 图片地址：支持绝对网址，也支持上传后的 uisc-assets/xxx
function imgSrc(v){
  v = (v||'').trim();
  if(!v) return '';
  if(/^(https?:)?\/\//i.test(v) || v.charAt(0)==='/') return v;
  if(v.indexOf('uisc-assets/')===0) return '/'+v;
  return '/uisc-assets/'+v;
}
function fieldRow(k, mm, ii){
  const val = (SITE_DRAFT[k]!==undefined) ? SITE_DRAFT[k] : ii.value;
  const ty  = mm.type || 'text';
  let input = '';
  if(ty==='textarea'){
    input = `<textarea id="S_${k}" rows="3">${esc(val)}</textarea>`;
  } else if(ty==='int'){
    const mn = (mm.min!==undefined?` min="${mm.min}"`:''), mx = (mm.max!==undefined?` max="${mm.max}"`:'');
    input = `<input type="number" id="S_${k}" value="${esc(val)}"${mn}${mx}> ${mm.unit?`<span class="hint">${esc(mm.unit)}</span>`:''}`;
  } else if(ty==='select'){
    const opts = mm.options||{}; let s='';
    for(const v in opts){ s += `<option value="${esc(v)}" ${String(val)===String(v)?'selected':''}>${esc(opts[v])}</option>`; }
    input = `<select id="S_${k}">${s}</select>`;
  } else if(ty==='image'){
    const src = imgSrc(val);
    input = `<div class="imgpick">
      <span>${src?`<img src="${esc(src)}" alt="">`:'<span class="ph">无图</span>'}</span>
      <input type="text" id="S_${k}" value="${esc(val)}" placeholder="图片网址，或点右侧上传">
      <button type="button" class="ghost" onclick="pickImage('${k}')">上传</button>
      <input type="file" id="F_${k}" accept="image/*" style="display:none" onchange="uploadImage('${k}',this)">
    </div>`;
  } else {
    /* SMTP 密码 / 授权码按密码框渲染，避免被人瞟到 */
    const pwd = (k === 'mail_pass');
    input = `<input type="${pwd?'password':'text'}" id="S_${k}" value="${esc(val)}"${pwd?' autocomplete="new-password" placeholder="邮箱授权码或登录密码"':''}>`;
  }
  return `<div class="set-sec">
    <h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
    <div class="tip">${esc(mm.tip||'')}</div>
    ${input}
  </div>`;
}
function renderSite(){
  const box = document.getElementById('siteBox');
  if(!SITE){ box.innerHTML='<div class="hint">加载失败</div>'; return; }
  const meta = SITE.meta||{}, items = SITE.items||{};
  let h = `<div class="ext-note">${esc(SITE_HINTS[SITE_TAB]||'')}</div>`;
  if(SITE.table_ok===false){
    h += `<div class="ext-note note-warn">⚠️ 设置表创建失败：数据库用户可能没有建表权限，请到宝塔 → 数据库 → 该库用户 → 勾选「所有权限」。</div>`;
  }

  // 接口配置页顶部：状态栏
  if(SITE_TAB==='pansou'){
    const st = PANSOU_STATE;
    h += `<div class="status-bar">
      <span class="pill">接口状态：<b>${st ? (st.ok?'连通':'异常') : '未测试'}</b></span>
      <span class="pill">频道：<b>${st?st.channels:'—'}</b></span>
      <span class="pill">插件：<b>${st?st.plugins:'—'}</b></span>
      <span class="pill">延迟：<b>${st?(st.ms+' ms'):'—'}</b></span>
      <span class="pill">待确认：<b id="pendCount">—</b></span>
      <button class="ghost" onclick="testPansou(this)">测试连接</button>
      <button class="ghost" onclick="clearPansouCache()">清空搜索缓存</button>
    </div>`;
    if(st && st.message){ h += `<div class="ext-note ${st.ok?'note-ok':'note-err'}">${esc(st.message)}</div>`; }
  }

  // 邮件服务页顶部：发送测试邮件（可用还没保存的草稿值先测）
  if(SITE_TAB==='mail'){
    h += `<div class="status-bar">
      <span class="pill">发信状态：<b id="mailState">未测试</b></span>
      <span class="pill">收件人</span>
      <input type="text" id="mailTestTo" placeholder="留空 = 发给发件人邮箱自己" style="max-width:230px">
      <button class="ghost" onclick="testMail(this)">发送测试邮件</button>
    </div>
    <div id="mailTestOut" style="margin:0 0 12px"></div>`;
  }

  const keys = siteKeys();
  if(SITE_TAB==='pan'){
    // 网盘页：先给「要管理哪些网盘」，再按网盘分块（Cookie / 默认目录 / 临时目录）
    if(meta['pan_group']) h += fieldRow('pan_group', meta['pan_group'], items['pan_group']||{});
    for(const code in PAN_ACCOUNTS){
      const kc='pan_'+code+'_cookie', kd='pan_'+code+'_dir', kt='pan_'+code+'_tmp';
      if(!meta[kc]) continue;
      const cur = (SITE_DRAFT[kc]!==undefined ? SITE_DRAFT[kc] : ((items[kc]||{}).value||''));
      h += `<div class="set-sec" style="border:1px solid var(--line);border-radius:12px;padding:14px 16px">
        <h4>${esc(PAN_ACCOUNTS[code])} <span class="hint">Cookie ${cur?('已填写（'+cur.length+' 字符）'):'未填写'}</span></h4>`;
      h += fieldRow(kc, meta[kc], items[kc]||{});
      h += fieldRow(kd, meta[kd], items[kd]||{});
      h += fieldRow(kt, meta[kt], items[kt]||{});
      h += `</div>`;
    }
  } else if(SITE_TAB==='seo'){
    const half = Math.ceil(keys.length/2);
    h += `<div class="site-2col">`;
    keys.forEach((k,i)=>{ h += fieldRow(k, meta[k]||{}, items[k]||{}); });
    h += `</div>`;
    h += `<div class="set-sec"><h4>效果预览 <span class="hint">（用站内最新一条数据套模板，点上方「刷新 SEO 预览」重算）</span></h4>
      <div id="seoPreview" class="seo-box">点上方「刷新 SEO 预览」查看实际生成的标题 / 关键词 / 描述 / robots.txt</div></div>`;
  } else {
    const list = keys.filter(k=>((meta[k]||{}).type)!=='list');
    const lists = keys.filter(k=>((meta[k]||{}).type)==='list');
    h += `<div class="site-2col">`;
    list.forEach(k=>{ h += fieldRow(k, meta[k]||{}, items[k]||{}); });
    h += `</div>`;
    lists.forEach(k=>{
      const mm = meta[k]||{}, ii = items[k]||{};
      const cur = (SITE_DRAFT[k]!==undefined) ? SITE_DRAFT[k] : (ii.value||[]);
      const opts = mm.options||{};
      let s='';
      for(const v in opts){
        const on = cur.indexOf(v)>=0;
        s += `<label class="chk"><input type="checkbox" class="sl_${k}" value="${esc(v)}" ${on?'checked':''}>${esc(opts[v])}</label>`;
      }
      h += `<div class="set-sec"><h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
        <div class="tip">${esc(mm.tip||'')}</div><div class="chks">${s}</div></div>`;
    });
  }
  box.innerHTML = h;
  if(SITE_TAB==='pansou'){
    box.innerHTML += `<div class="set-sec"><h4>试搜一次 <span class="hint">（只查不写，用来确认接口与关键词效果）</span></h4>
      <div class="tip">输入一个资源名，看看 PanSou 能搜到什么。搜索结果默认缓存，重复搜不会重复请求。</div>
      <div class="inline">
        <input type="text" id="pansouKw" placeholder="例：某部电影的名字">
        <button type="button" class="ghost" onclick="searchPansou()">试搜</button>
      </div>
      <div id="pansouResult" style="margin-top:10px"></div></div>
    <div class="set-sec"><h4>立即补地址</h4>
      <div class="tip">对「还没有下载地址」的条目立刻跑一次搜索补写。根据「写入策略」决定自动写入还是进待确认队列。</div>
      <div class="inline"><button type="button" onclick="runEnrich()">立即补地址</button></div></div>
    <div class="set-sec"><h4>待确认队列 <span class="hint" id="pendHint"></span></h4>
      <div class="tip">匹配度不够高、按策略暂不自动写入的结果会落到这里。逐条看清楚再「采纳」，比全自动安全。</div>
      <div id="pendingBox"></div>
      <div class="inline"><button type="button" class="ghost" onclick="loadPending()">刷新队列</button></div></div>`;
    loadPendingCount();
  }
}
function collectSiteDraft(){
  if(!SITE) return;
  const meta = SITE.meta||{};
  siteKeys().forEach(k=>{
    const mm = meta[k]||{};
    if(mm.type==='list'){
      const arr=[];
      document.querySelectorAll('.sl_'+k+':checked').forEach(x=>arr.push(x.value));
      SITE_DRAFT[k]=arr;
      return;
    }
    const el = document.getElementById('S_'+k);
    if(el) SITE_DRAFT[k]=el.value;
  });
}
async function saveSite(){
  const msg = document.getElementById('siteMsg');
  collectSiteDraft();
  if(!Object.keys(SITE_DRAFT).length){ msg.style.color='var(--mut)'; msg.innerText='没有需要保存的项'; return; }
  // 单独保存一个网盘的三个字段时，其它网盘的值也要带上，避免被清空
  const body = {};
  for(const k in SITE_DRAFT) body[k] = SITE_DRAFT[k];
  msg.style.color='var(--mut)'; msg.innerText='保存中…';
  const d = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'保存失败'; return; }
  SITE.items = d.data.items || SITE.items;
  SITE_DRAFT = {};
  msg.style.color='var(--ok)'; msg.innerText='✓ 已保存并生效';
  renderSite();
}
async function resetSite(){
  const keys = siteKeys();
  if(!keys.length) return;
  if(!confirm('把「'+SITE_TAB+'」这一页的设置恢复为 config/config.php 里的值？（不影响条目数据）')) return;
  const msg = document.getElementById('siteMsg');
  msg.style.color='var(--mut)'; msg.innerText='恢复中…';
  for(const k of keys){
    const d = await api('/api/v1/admin/settings_reset',{method:'POST',body:JSON.stringify({key:k})});
    if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'恢复失败'; return; }
    SITE.items = d.data.items || SITE.items;
  }
  SITE_DRAFT = {};
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复为 config.php 里的值';
  renderSite();
}
/* ---- 邮件服务：发送测试邮件（先测再存：草稿值一起提交） ---- */
async function testMail(btn){
  collectSiteDraft();
  const body = { to:((document.getElementById('mailTestTo')||{}).value||'').trim() };
  ['mail_enable','mail_host','mail_port','mail_secure','mail_user','mail_pass','mail_from','mail_from_name']
    .forEach(k=>{ if(SITE_DRAFT[k]!==undefined) body[k]=SITE_DRAFT[k]; });
  const st  = document.getElementById('mailState');
  const out = document.getElementById('mailTestOut');
  await busy(btn,'发送中…', async()=>{
    if(st) st.innerText='发送中…';
    if(out) out.innerHTML='<div class="ext-note">正在连接 SMTP 服务器，最长等 1 分钟…</div>';
    const d = await api('/api/v1/admin/mail_test',{method:'POST',body:JSON.stringify(body)});
    if(!d.success){
      if(st) st.innerText='失败';
      if(out) out.innerHTML='<div class="ext-note note-err">'+esc(d.error||'测试失败')+'</div>';
      return;
    }
    const r = d.data||{};
    if(st) st.innerText = r.ok ? '连通 ✓' : '失败';
    let h = '';
    if(r.msg) h += '<div class="ext-note '+(r.ok?'note-ok':'note-err')+'">'+esc(r.msg)+'</div>';
    if(!r.ok && r.log) h += '<div class="ext-note">SMTP 会话日志（排查用）：<pre style="white-space:pre-wrap;margin:4px 0 0;font-size:12px">'+esc(r.log)+'</pre></div>';
    if(out) out.innerHTML = h;
  });
}
function pickImage(k){ const f=document.getElementById('F_'+k); if(f) f.click(); }
async function uploadImage(k, inp){
  const file = inp.files && inp.files[0];
  if(!file) return;
  const msg = document.getElementById('siteMsg');
  if(file.size > 2*1024*1024){ msg.style.color='var(--dead)'; msg.innerText='图片太大了，请压到 2MB 以内'; inp.value=''; return; }
  msg.style.color='var(--mut)'; msg.innerText='上传中…';
  const fd = new FormData(); fd.append('kind', k); fd.append('file', file);
  let d;
  try{
    const r = await fetch(API+'/api/v1/admin/site_upload',{method:'POST',credentials:'same-origin',body:fd});
    const txt = await r.text();
    try{ d = JSON.parse(txt); }catch(e){ d = {success:false, error:'服务器返回了非 JSON（HTTP '+r.status+'）：'+String(txt).slice(0,160)}; }
    if(d && d.success===undefined) d.success = (r.status>=200 && r.status<300);
  }catch(e){ d = {success:false, error:'网络请求失败：'+((e&&e.message)?e.message:e)}; }
  inp.value = '';
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'上传失败'; return; }
  collectSiteDraft();
  SITE_DRAFT[k] = d.data.path;
  msg.style.color='var(--ok)'; msg.innerText='✓ 上传成功，别忘了点「保存设置」';
  renderSite();
}
/* ---- 接口配置：测试 / 试搜 / 补地址 / 缓存 ---- */
/**
 * 测试 PanSou 连通。
 * 之前"点了没反应"的两个原因：① siteMsg 元素在某些渲染路径下取不到，赋值直接抛错、
 * 函数中断（页面上什么都不显示）；② 探测上游可能要十几秒，期间没有任何进度提示，
 * 用户以为按钮坏了。现在：元素兜底 + 中途进度 + 明确的结果/失败提示 + 按钮忙碌态。
 */
async function testPansou(btn){
  const msg = document.getElementById('siteMsg') || setMsgFallback();
  collectSiteDraft();
  const draftLen = Object.keys(SITE_DRAFT).length;
  if(msg){ msg.style.color='var(--mut)'; msg.innerText = draftLen ? '先保存当前改动…' : '正在连接 PanSou（最长可能等十几秒）…'; }
  await busy(btn, '测试中…', async ()=>{
    if(draftLen){
      const d0 = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(SITE_DRAFT)});
      if(!d0.success){ if(msg){ msg.style.color='var(--dead)'; msg.innerText='先保存再测试：'+(d0.error||''); } return; }
      SITE.items = d0.data.items || SITE.items;
      SITE_DRAFT = {};
      renderSite();
    }
    if(msg){ const m2=document.getElementById('siteMsg'); if(m2){ m2.style.color='var(--mut)'; m2.innerText='正在连接 PanSou（最长可能等十几秒）…'; } }
    const d = await api('/api/v1/admin/pansou_test',{method:'POST',body:'{}'});
    const m3 = document.getElementById('siteMsg') || msg;
    if(!d.success){ if(m3){ m3.style.color='var(--dead)'; m3.innerText='✗ '+(d.error||'测试失败'); } return; }
    PANSOU_STATE = d.data;
    if(m3){
      m3.style.color = d.data.ok ? 'var(--ok)' : 'var(--dead)';
      m3.innerText = (d.data.ok?'✓ ':'✗ ') + (d.data.message||(d.data.ok?'可用':'不可用'));
    }
    renderSite();
  });
}
/** 万一页面上找不到 siteMsg（比如标签页结构变了），临时造一个，保证一定给得出提示 */
function setMsgFallback(){
  let box = document.getElementById('siteBox');
  if(!box) return null;
  let el = document.getElementById('siteMsg');
  if(!el){
    el = document.createElement('div');
    el.id = 'siteMsg'; el.className = 'set-msg';
    box.appendChild(el);
  }
  return el;
}
async function searchPansou(){
  const kw = (document.getElementById('pansouKw')||{}).value;
  if(!kw || !kw.trim()){ alert('先填写要试搜的关键词'); return; }
  const out = document.getElementById('pansouResult');
  if(!out){ alert('试搜区域未就绪，请刷新后台页面后重试。'); return; }
  out.innerHTML = '<div class="hint">搜索中…（上游较慢时可能要十几秒）</div>';
  const d = await api('/api/v1/admin/pansou_search',{method:'POST',body:JSON.stringify({kw:kw.trim()})});
  if(!d.success){ out.innerHTML='<div class="hint" style="color:var(--dead)">✗ '+esc(d.error||'搜索失败')+'</div>'; return; }
  const r = d.data || {};
  const types = r.types || [];
  let h = `<div class="hint">共 ${r.total||0} 条${r.cached?'（来自缓存）':''}</div>`;
  if(!types.length){ h += '<div class="hint">没有搜到结果。可以换个关键词，或到 PanSou 里确认频道 / 插件已加载。</div>'; }
  types.forEach(t=>{
    h += `<div class="set-sec"><h4>${esc(t.label)} <span class="hint">${t.count} 条</span></h4>`;
    (t.top||[]).forEach(x=>{ h += `<div class="u" style="font-size:12px;word-break:break-all;color:var(--mut)">${esc(x.note||'（无标题）')}<br>${esc(x.url)}</div>`; });
    h += `</div>`;
  });
  out.innerHTML = h;
}
async function runEnrich(){
  const msg = document.getElementById('siteMsg');
  if(!confirm('对「还没有下载地址」的条目搜一次网盘并补地址？\n\n按当前写入策略处理：仅高度匹配自动写 / 搜到就写 / 一律进待确认。')) return;
  msg.style.color='var(--mut)'; msg.innerText='正在搜索并补地址，条目多时需要一会儿…';
  const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({limit:15})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'补地址失败'; return; }
  const r = d.data;
  msg.style.color='var(--ok)';
  msg.innerText = `✓ 检查 ${r.checked} 条：写入 ${r.written} 条（共 ${r.links} 个链接）、待确认 ${r.queued} 条、无匹配 ${r.nomatch} 条，用时 ${r.ms} ms`;
  loadPending();
}
async function clearPansouCache(){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pansou_cache_clear',{method:'POST',body:'{}'});
  msg.style.color = d.success?'var(--ok)':'var(--dead)';
  msg.innerText = d.success ? ('✓ 已清空搜索缓存（'+d.data.cleared+' 条）') : (d.error||'操作失败');
}
async function loadPendingCount(){
  const d = await api('/api/v1/admin/pending_list');
  const el = document.getElementById('pendCount');
  if(el && d.success) el.innerText = d.data.count;
}
async function loadPending(){
  const box = document.getElementById('pendingBox');
  if(!box) return;
  box.innerHTML = '<div class="hint">加载中…</div>';
  const d = await api('/api/v1/admin/pending_list');
  if(!d.success){ box.innerHTML='<div class="hint" style="color:var(--dead)">'+esc(d.error||'加载失败')+'</div>'; return; }
  const list = d.data.list||[];
  const el = document.getElementById('pendCount'); if(el) el.innerText = d.data.count;
  if(!list.length){ box.innerHTML = '<div class="hint">暂无待确认记录 —— 说明自动补地址这一路很干净。</div>'; return; }
  let h='';
  list.forEach(r=>{
    h += `<div class="pending-row">
      <div class="t"><b>${esc(r.title)}</b> <span class="score">匹配 ${r.score}%</span>
        <div class="u">搜索词：${esc(r.keyword)}　·　${r.links.length} 个网盘链接</div>`;
    r.links.forEach(l=>{ h += `<div class="u">[${esc(l.label)}] ${esc(l.url)}</div>`; });
    h += `</div>
      <div style="display:flex;gap:6px;flex-direction:column">
        <button class="ghost" onclick="applyPending(${r.id})">采纳</button>
        <button class="ghost" onclick="dropPending(${r.id})">忽略</button>
      </div></div>`;
  });
  box.innerHTML = h;
}
async function applyPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_apply',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'采纳失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已写入 '+d.data.added+' 个下载链接（下次生成静态页后前台可见）';
  loadPending();
}
async function dropPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_drop',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'操作失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已忽略该条';
  loadPending();
}
async function loadSeoPreview(){
  const box = document.getElementById('seoPreview');
  if(!box) return;
  box.textContent = '正在生成预览…';
  const d = await api('/api/v1/admin/seo_preview');
  if(!d.success){ box.textContent = d.error||'预览失败'; return; }
  const r = d.data;
  let h = '';
  h += `<em>【首页】</em><br>title：${esc(r.home.title)}<br>keywords：${esc(r.home.keywords||'（未设置）')}<br>description：${esc(r.home.description||'（未设置）')}<br><br>`;
  h += `<em>【列表页 · 电影 + 按热度】</em><br>title：${esc(r.list.title)}<br>keywords：${esc(r.list.keywords||'（未设置）')}<br><br>`;
  h += `<em>【内页 · 拿站内最新一条真实数据】</em><br>title：${esc(r.item.title)}<br>keywords：${esc(r.item.keywords||'（未设置）')}<br>description：${esc(r.item.description||'（未设置）')}<br>canonical：${esc(r.item.canonical||'（未设置网站网址）')}<br><br>`;
  h += `<em>【robots.txt】</em><br>${esc(r.robots).replace(/\n/g,'<br>')}`;
  box.innerHTML = h;
}

/* ================================================================== */
/* 资源请求 / 注册用户（一级 Tab）                                      */
/*   请求列表：改状态 + 写回复 + 删除；注册用户：通过 / 禁用 / 重置密码 / 删除 */
/* ================================================================== */
const REQ_ST  = { pending:'待处理', found:'已找到', notfound:'暂无资源', replied:'已回复', closed:'已关闭' };
const REQ_UST = { '1':'正常', '0':'已禁用', '2':'待审核' };
let REQ_TAB = 'list', REQ_PAGE = 1, REQ_UPAGE = 1, REQ_TOTAL = 0, REQ_UTOTAL = 0;

function switchReqTab(tab){
  REQ_TAB = tab;
  document.querySelectorAll('#reqSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.rstab===tab));
  document.getElementById('reqQ').value = '';
  syncReqToolbar();
  if(tab==='users'){ REQ_UPAGE = 1; loadUserList(); } else { REQ_PAGE = 1; loadReqList(); }
}
/** 同一套工具条，按当前子页切换占位符与状态选项 */
function syncReqToolbar(){
  const users = (REQ_TAB === 'users');
  document.getElementById('reqQ').placeholder = users ? '搜索用户名 / 邮箱…' : '搜索资源名称 / 说明…';
  const map = users ? REQ_UST : REQ_ST;
  let s = '<option value="">全部状态</option>';
  for(const k in map){ s += '<option value="'+k+'">'+esc(map[k])+'</option>'; }
  document.getElementById('reqStatus').innerHTML = s;
}
async function loadReq(){
  const bar = document.getElementById('reqBar');
  const d = await api('/api/v1/admin/req_stats');
  if(!d.success){ bar.innerHTML = '<span class="pill">'+esc(d.error||'加载失败')+'</span>'; return; }
  const s = d.data.stats||{}, c = d.data.counts||{};
  let h = '';
  h += '<span class="pill">注册用户：<b>'+(s.users||0)+'</b></span>';
  h += '<span class="pill">待审核：<b>'+(s.users_wait||0)+'</b></span>';
  h += '<span class="pill">请求总数：<b>'+(c.total||0)+'</b></span>';
  h += '<span class="pill">待处理：<b>'+(c.pending||0)+'</b></span>';
  h += '<span class="pill">今日新增：<b>'+(s.today||0)+'</b></span>';
  if(d.data.tables === false){
    h += '<span class="pill" style="color:var(--dead)">⚠️ 数据表创建失败，请检查数据库用户是否有建表权限</span>';
  }
  h += '<a class="ghost" href="/request" target="_blank" style="text-decoration:none">前台页面 ↗</a>';
  h += '<button class="ghost" onclick="loadReq()">刷新</button>';
  bar.innerHTML = h;
  syncReqToolbar();
  if(REQ_TAB === 'users'){ loadUserList(); } else { loadReqList(); }
}
async function loadReqList(){
  const box = document.getElementById('reqBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const q = document.getElementById('reqQ').value, st = document.getElementById('reqStatus').value;
  const d = await api(`/api/v1/admin/req_list?page=${REQ_PAGE}&status=${encodeURIComponent(st)}&q=${encodeURIComponent(q)}`);
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const items = d.data.items||[];
  REQ_TOTAL = d.data.total||0;
  document.getElementById('reqPageInfo').innerText = '第 '+REQ_PAGE+' 页 / 共 '+REQ_TOTAL+' 条';
  if(!items.length){ box.innerHTML = '<div class="hint">没有符合条件的请求。</div>'; return; }
  let h = '';
  items.forEach(r=>{
    const meta = [];
    if(r.type) meta.push(esc(r.type));
    if(r.year) meta.push(r.year);
    meta.push('由 '+esc(r.username || '（未登录提交）')+' 于 '+esc(String(r.created_at||'').slice(0,16))+' 提交');
    h += `<div class="req-row">
      <div class="hd">
        <span class="tt">#${r.id} ${esc(r.title||'')}</span>
        <span class="ubadge ${esc(r.status||'pending')}">${esc(REQ_ST[r.status]||r.status||'')}</span>
        <span class="hint">${meta.join(' · ')}</span>
      </div>`;
    if(r.note)    h += `<div class="nt">${esc(r.note)}</div>`;
    if(r.contact) h += `<div class="mt">联系方式：<b>${esc(r.contact)}</b></div>`;
    let opts = '';
    for(const k in REQ_ST){ opts += `<option value="${k}" ${k===r.status?'selected':''}>${REQ_ST[k]}</option>`; }
    h += `<div class="ops">
        <select id="rs_${r.id}">${opts}</select>
        <textarea id="rn_${r.id}" placeholder="给用户看的回复（只有提交者本人能看到）">${esc(r.admin_note||'')}</textarea>
        <div class="opbtns">
          <button onclick="saveReq(${r.id},this)">保存</button>
          <button class="danger" onclick="delReq(${r.id},this)">删除</button>
        </div>
      </div></div>`;
  });
  box.innerHTML = h;
}
async function loadUserList(){
  const box = document.getElementById('reqBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const q = document.getElementById('reqQ').value, st = document.getElementById('reqStatus').value;
  const d = await api(`/api/v1/admin/user_list?page=${REQ_UPAGE}&status=${encodeURIComponent(st)}&q=${encodeURIComponent(q)}`);
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const items = d.data.items||[];
  REQ_UTOTAL = d.data.total||0;
  document.getElementById('reqPageInfo').innerText = '第 '+REQ_UPAGE+' 页 / 共 '+REQ_UTOTAL+' 位用户';
  if(!items.length){ box.innerHTML = '<div class="hint">没有符合条件的用户。</div>'; return; }
  let h = '<div class="tablewrap"><table><thead><tr>'
        + '<th>ID</th><th>用户名</th><th>邮箱</th><th>状态</th><th>提交数</th>'
        + '<th>注册时间</th><th>最后登录</th><th>操作</th></tr></thead><tbody>';
  items.forEach(u=>{
    const st = String(u.status);
    let ops = '';
    if(st !== '1') ops += `<button class="ghost sm" onclick="setUser(${u.id},1,this)">通过/启用</button> `;
    if(st !== '0') ops += `<button class="ghost sm" onclick="setUser(${u.id},0,this)">禁用</button> `;
    ops += `<button class="ghost sm" onclick="resetPwd(${u.id},this)">重置密码</button> `;
    ops += `<button class="danger sm" onclick="delUser(${u.id},this)">删</button>`;
    h += `<tr>
      <td>${u.id}</td>
      <td>${esc(u.username||'')}</td>
      <td>${esc(u.email||'—')}</td>
      <td><span class="ubadge u${st}">${esc(REQ_UST[st]||st)}</span></td>
      <td>${u.req_count||0}</td>
      <td>${esc(String(u.created_at||'').slice(0,16))}</td>
      <td>${esc(String(u.last_login||'—').slice(0,16))}</td>
      <td>${ops}</td></tr>`;
  });
  box.innerHTML = h + '</tbody></table></div>';
}
function reqPage(step){
  if(REQ_TAB === 'users'){
    const max = Math.max(1, Math.ceil(REQ_UTOTAL/30));
    REQ_UPAGE = Math.min(max, Math.max(1, REQ_UPAGE + step));
    loadUserList();
  } else {
    const max = Math.max(1, Math.ceil(REQ_TOTAL/30));
    REQ_PAGE = Math.min(max, Math.max(1, REQ_PAGE + step));
    loadReqList();
  }
}
async function saveReq(id, btn){
  const st = document.getElementById('rs_'+id).value;
  const nt = document.getElementById('rn_'+id).value;
  await busy(btn, '保存中…', async ()=>{
    const d = await api('/api/v1/admin/req_save', {method:'POST', body: JSON.stringify({id:id, status:st, admin_note:nt})});
    if(!d.success){ alert(d.error||'保存失败'); return; }
    loadReq();
  });
}
async function delReq(id, btn){
  if(!confirm('删除这条资源请求？删除后不可恢复。')) return;
  await busy(btn, '删除中…', async ()=>{
    const d = await api('/api/v1/admin/req_del', {method:'POST', body: JSON.stringify({id:id})});
    if(!d.success){ alert(d.error||'删除失败'); return; }
    loadReq();
  });
}
async function setUser(id, st, btn){
  await busy(btn, '处理中…', async ()=>{
    const d = await api('/api/v1/admin/user_save', {method:'POST', body: JSON.stringify({id:id, status:st})});
    if(!d.success){ alert(d.error||'操作失败'); return; }
    loadReq();
    loadUserList();
  });
}
async function resetPwd(id, btn){
  const p = prompt('给这位用户设置新密码（至少 6 位）：');
  if(p === null) return;
  if(p.length < 6){ alert('密码至少 6 位'); return; }
  await busy(btn, '处理中…', async ()=>{
    const d = await api('/api/v1/admin/user_save', {method:'POST', body: JSON.stringify({id:id, new_password:p})});
    if(!d.success){ alert(d.error||'重置失败'); return; }
    alert('密码已重置，请把新密码告诉该用户。');
  });
}
async function delUser(id, btn){
  if(!confirm('删除这位用户？他提交过的请求会保留（记录里仍有用户名）。')) return;
  await busy(btn, '删除中…', async ()=>{
    const d = await api('/api/v1/admin/user_del', {method:'POST', body: JSON.stringify({id:id})});
    if(!d.success){ alert(d.error||'删除失败'); return; }
    loadReq();
  });
}

async function loadSettings(){
  const box = document.getElementById('setBox');
  const d = await api('/api/v1/admin/settings');
  if(!d.success){ box.innerHTML='<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  SET = d.data;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  renderSettings();
  const m=document.getElementById('setMsg'); m.innerText=''; m.style.color='';
}
function renderSettings(){
  const m = SET.meta, it = SET.items;
  let h='';

  /* 数据源连通状态栏：一次把 TMDB / 红果短剧 两个源试一遍 */
  h += `<div class="status-bar">
    <span class="pill">TMDB：<b id="st_tmdb">${SET_TS.tmdb?SET_TS.tmdb.label:'未测试'}</b></span>    <span class="pill">红果短剧：<b id="st_hongguoduanju">${SET_TS.hongguoduanju?SET_TS.hongguoduanju.label:'未测试'}</b></span>
    <button type="button" class="ghost" id="btnTestAll" onclick="testAllSources()">一键测试全部数据源</button>
    <span class="hint">红果短剧（动漫）免 Key，可直接测；结果只是「本站服务器能否连上该站点」的探测，不影响已入库数据。</span>
  </div>`;

  if(SET.table_ok === false){
    h += `<div class="ext-note note-warn">⚠️ 设置表 <b>${esc(SET.table||'app_settings')}</b> 创建失败：当前数据库用户可能没有建表权限。
      请到宝塔 → 数据库 → 该库的用户 → 权限勾选「所有权限」，或手动导入 <b>sql/migrate_settings.sql</b> 后重试。</div>`;
  }

  // 文本项：TMDB Key
  TXT_KEYS.forEach(k=>{
    const mm = m[k]||{}, ii = it[k]||{};
    const val = (TXT_DRAFT && TXT_DRAFT[k]!=null) ? TXT_DRAFT[k] : ii.value;
    const kind = (k==='tmdb_api_key') ? 'tmdb' : '';
    h += `<div class="set-sec">
      <h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
      <div class="tip">${esc(mm.tip||'')}</div>
      <div class="inline">
        <input type="text" id="s_${k}" value="${esc(val)}" placeholder="${esc(ii.file)}">
        ${kind ? `<button type="button" class="ghost" onclick="testKey('${kind}', true)">测试此 Key</button>` : ''}
      </div>
      <div class="fileval">config.php 里的值：${esc(mask(ii.file))}</div>
    </div>`;
  });

  // 数据源开关：每个源后面都挂一个「测试连通」（红果短剧 免 key 也能测）
  const ma = m.adapters||{}, ia = it.adapters||{};
  const cur = AD_DRAFT ? AD_DRAFT : (ia.value||[]);
  h += `<div class="set-sec"><h4>${esc(ma.label)} ${flag(ia.override)}</h4>
    <div class="tip">${esc(ma.tip||'')}</div>`;
  const opts = ma.options||{};
  for(const k in opts){
    const on = cur.indexOf(k)>=0;
    const kind = (k==='tmdb') ? 'tmdb' : (k==='hongguoduanju' ? 'hongguoduanju' : '');
    const ts = (kind && SET_TS[kind]) ? SET_TS[kind] : null;
    h += `<div class="src-row">
      <label class="chk"><input type="checkbox" id="a_${k}" value="${esc(k)}" ${on?'checked':''}>${esc(opts[k])}</label>
      ${kind ? `<button type="button" class="ghost sm" onclick="testKey('${kind}')">测试连通</button>
        <span class="src-state" id="ss_${kind}">${ts?esc(ts.detail||ts.label):(kind==='hongguoduanju'?'免 Key，可直接测':'需先填 Key')}</span>` : ''}
    </div>`;
  }
  h += `<div class="fileval">config.php 里的值：${esc((ia.file||[]).join(' / ')||'（空）')}</div></div>`;

  // 网盘类型（chip 增删）
  const mp = m.pan_types||{}, ip = it.pan_types||{};
  const chips = PAN_DRAFT.length
    ? PAN_DRAFT.map((t,i)=>`<span class="chip">${esc(t)}<b title="删除" onclick="rmPan(${i})">×</b></span>`).join('')
    : '<span class="hint">（暂无，点下面「添加」新增）</span>';
  h += `<div class="set-sec"><h4>${esc(mp.label)} ${flag(ip.override)}</h4>
    <div class="tip">${esc(mp.tip||'')}</div>
    <div class="chips" id="panChips">${chips}</div>
    <div class="inline">
      <input type="text" id="s_panNew" placeholder="输入网盘名后回车，如 123网盘" onkeydown="if(event.key==='Enter'){event.preventDefault();addPan();}">
      <button type="button" class="ghost" onclick="addPan()">添加</button>
      <button type="button" class="ghost" onclick="setPanDefault()">恢复默认</button>
    </div>
    <div class="fileval">当前生效：${esc((ip.value||[]).join(' / ')||'（空）')}　·　config/pan_types.php 里：${esc((ip.file||[]).join(' / ')||'（无该文件）')}</div>
  </div>`;

  document.getElementById('setBox').innerHTML = h;
}
function addPan(){
  const el = document.getElementById('s_panNew');
  let v = (el.value||'').trim().replace(/[,，、;；]+/g,' ').trim();
  if(!v) return;
  if(PAN_DRAFT.indexOf(v)>=0){ alert('「'+v+'」已存在'); el.value=''; return; }
  collectDraft();                 // 先保住已输入的 Key
  PAN_DRAFT.push(v);
  renderSettings();
  const box=document.getElementById('s_panNew'); if(box){ box.value=''; box.focus(); }
}
function rmPan(i){ collectDraft(); PAN_DRAFT.splice(i,1); renderSettings(); }
function setPanDefault(){ collectDraft(); PAN_DRAFT = ['夸克','迅雷','光鸭','百度','UC']; renderSettings(); }
async function saveSettings(){
  const msg = document.getElementById('setMsg');
  collectDraft();
  const body = {
    tmdb_api_key: (TXT_DRAFT['tmdb_api_key']||'').trim(),
    adapters: (AD_DRAFT||[]).slice(),
    pan_types: PAN_DRAFT.slice()
  };
  msg.style.color='var(--mut)'; msg.innerText='保存中…';
  const d = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'保存失败'; return; }
  SET.items = d.data.items || SET.items;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  msg.style.color='var(--ok)'; msg.innerText='✓ 已保存并生效';
  renderSettings();
  loadSchema();   // 网盘类型可能变了，刷新「下载链接」下拉
}
async function resetAllSettings(){
  if(!confirm('把所有设置恢复为 config/config.php 里的值？（不影响条目数据）')) return;
  const msg = document.getElementById('setMsg');
  const d = await api('/api/v1/admin/settings_reset',{method:'POST',body:JSON.stringify({all:1})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'操作失败'; return; }
  SET.items = d.data.items || SET.items;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复为 config.php 里的值';
  renderSettings(); loadSchema();
}
/**
 * 测试某个数据源能否连通。
 *  - 点数据源那一行 / 状态栏的按钮：useDraft 省略，直接用「已保存」的配置测；
 *  - 点 Key 输入框旁的「测试此 Key」：useDraft=true，先拿框里正在编辑的值测（不落库）。
 * 之所以要区分，是因为以前无论点哪里都会去读输入框；TMDB 的输入框
 * 初始值就是已保存的值，看似能用；而 红果短剧 从来没有输入框，于是压根没按钮。
 */
async function testKey(kind, useDraft){
  const id = (kind==='tmdb') ? 's_tmdb_api_key' : '';
  const el = id ? document.getElementById(id) : null;
  const key = useDraft ? (el ? el.value.trim() : '') : '';
  setTestState(kind, null, '测试中…', 'mut');
  const d = await api('/api/v1/admin/settings_test',{method:'POST',body:JSON.stringify({kind:kind, key:key})});
  if(!d.success){ setTestState(kind, false, d.error||'测试失败', 'dead'); return false; }
  const r = d.data || {};
  const detail = (r.ok?'✓ 可用':'✗ 不可用') + ' · HTTP ' + (r.code||0) + (r.msg ? (' · '+r.msg) : '');
  setTestState(kind, !!r.ok, detail, r.ok?'ok':'dead');
  return !!r.ok;
}
/** 一键把三个源依次测一遍（红果短剧 免 key 也会测），带进度与总结果 */
async function testAllSources(){
  const btn = document.getElementById('btnTestAll');
  const msg = document.getElementById('setMsg');
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='正在依次测试 TMDB / 红果短剧 …（连不上时每个源最多等 10 秒）'; }
  let ok=0, fail=0;
  await busy(btn, '测试中…', async ()=>{
    for(const s of SRCS){
      const good = await testKey(s.kind, false);
      if(good) ok++; else fail++;
    }
  });
  if(msg){
    msg.style.color = (fail===0) ? 'var(--ok)' : (ok>0 ? 'var(--mut)' : 'var(--dead)');
    msg.innerText += '\n';
    msg.innerText = '测试完成：' + ok + ' 个可用' + (fail ? ('，' + fail + ' 个不可用（看上方各源后面的说明）') : '，全部连通 ✓');
  }
}
/** 把测试结果同时写回：状态栏小标签 + 数据源行内说明 */
function setTestState(kind, ok, detail, level){
  const st = SET_TS[kind] || (SET_TS[kind] = {});
  st.ok = ok; st.detail = detail;
  st.label = (ok===null) ? '测试中…' : (ok ? '连通' : '异常');
  const bar = document.getElementById('st_'+kind);
  if(bar){ bar.textContent = st.label; bar.className = 'src-'+(level||''); }
  const ss = document.getElementById('ss_'+kind);
  if(ss){ ss.textContent = detail || ''; ss.className = 'src-state src-'+(level||''); }
}

// ===== 安全防护（安装器自锁状态 / 敏感文件暴露面）=====
function secBadge(ok, level){
  if(level==='fail') return '<span class="sec-badge f">需处理</span>';
  if(level==='info') return '<span class="sec-badge n">提示</span>';
  return ok ? '<span class="sec-badge y">已防护</span>' : '<span class="sec-badge n">待处理</span>';
}
function secRow(ok, name, detail, hint, level){
  return `<div class="sec-row">
    <div class="sec-bd">${secBadge(ok, level)}</div>
    <div style="flex:1">
      <div class="sec-nm">${esc(name)}</div>
      <div class="sec-dt">${esc(detail||'')}</div>
      ${hint?`<div class="hint" style="display:block;margin-top:5px">${esc(hint)}</div>`:''}
    </div></div>`;
}
async function loadSecurity(){
  const box = document.getElementById('secBox');
  box.innerHTML = '<div class="hint">正在检测…</div>';
  const d = await api('/api/v1/admin/security');
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const g = d.data.guard || {};
  let h = '';
  h += `<div class="ext-note note-ok">
    🔒 <b>后台入口已改为「账号 + 密码」登录</b>：不再是"填个令牌就能进"，管理员账号在安装时创建。
    安装相关路径、敏感目录、内部 PHP 文件被直接请求时统一返回 404。</div>`;

  var insPresent = !!g.install_present, insLocked = !!g.install_locked, insActive = !!g.install_active;
  var insDetail = insActive
      ? '存在，且站点尚未安装 —— 此刻任何人打开它都能看到安装向导（装完会写 install.lock 自动失效）'
      : (insPresent ? '存在，但已写入 install.lock → 对所有人返回 404（与文件不存在无异），无法被用来重装站点'
                    : '未上传（此时安装只能用命令行 php tools/install-cli.php）');
  var insHint = insActive
      ? '按向导装完即可（会写 config/install.lock 锁死本页面）；不打算用它就直接删掉站点根的 install.php'
      : (insPresent ? '' : '需要网页安装时，从发布包把 install.php 传到站点根目录即可');
  h += secRow(!insActive, '网页版安装器 install.php', insDetail, insHint,
      insActive ? 'info' : (insPresent ? '' : 'info'));

  if(g.install_ghosts && g.install_ghosts.length){
    h += secRow(false, '安装类残留文件', g.install_ghosts.join('、'), '建议一并删除', 'fail');
  }

  h += secRow(!!g.cli_installer, '命令行安装器 tools/install-cli.php',
      g.cli_installer ? '就位（仅命令行可执行，公网访问 404；与网页安装器共用同一套内核）' : '未上传（不影响运行；网页安装器 install.php 同样能装）',
      g.cli_installer ? '重装或换服务器时，在宝塔终端执行：php tools/install-cli.php' : '需要时从发布包上传 tools/ 目录',
      g.cli_installer ? '' : 'info');

  h += secRow(!!g.lock, '安装锁 config/install.lock',
      g.lock ? '存在（已标记安装完成）' : '不存在（站点能正常访问可忽略）', '');

  const dg = g.dir_guards || {}; const miss = [];
  for(const k in dg){ if(!dg[k]) miss.push(k); }
  h += secRow(miss.length===0, '敏感目录拒访文件',
      miss.length===0 ? 'config / sql / core / adapters / cron / tools 均已放置拒访 index.php'
                      : ('缺少：'+miss.join('、')),
      miss.length===0 ? '' : '从发布包对应目录补传 index.php');

  const sm = g.shield_missing || [];
  h += secRow(sm.length===0, '内部 PHP 文件反直接访问守卫',
      sm.length===0 ? ((g.shield_total||0)+' 个内部文件均已带守卫（直接用网址请求会返回 404）')
                    : ('未加守卫：'+sm.slice(0,6).join('、')),
      sm.length===0 ? '' : '用发布包覆盖对应文件即可');

  h += secRow(!!g.htaccess, '.htaccess（Apache 用）',
      g.htaccess ? '存在（Apache 自动生效；Nginx 请用宝塔伪静态规则）' : '不存在（Nginx 环境无需本文件）',
      '', g.htaccess ? '' : 'info');

  box.innerHTML = h;
}
function openSelfCheck(){
  window.open(API + '/check.php', '_blank');
}

// ===== 明暗主题：跟随系统 / 亮色 / 暗色 三态循环，选择记在浏览器本地 =====
const THEME_ORDER = ['auto','light','dark'];
function themePref(){
  try{
    const v = localStorage.getItem('ml_admin_theme');
    if(v === 'auto' || v === 'light' || v === 'dark') return v;
  }catch(e){}
  return 'auto';
}
function applyTheme(){
  const p = themePref();
  const sysDark = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches);
  const dark = (p === 'dark') || (p === 'auto' && sysDark);
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  const b = document.querySelector('.themebtn');
  if(b){
    b.textContent = (p === 'auto') ? '🌗' : (dark ? '☀' : '🌙');
    b.title = '明暗：' + (p === 'auto' ? '跟随系统' : (p === 'light' ? '亮色' : '暗色'))
            + '（点击切换到' + (p === 'auto' ? '亮色' : (p === 'light' ? '暗色' : '跟随系统')) + '）';
  }
}
function toggleTheme(){
  const cur = themePref();
  const next = THEME_ORDER[(THEME_ORDER.indexOf(cur) + 1) % THEME_ORDER.length];
  try{ localStorage.setItem('ml_admin_theme', next); }catch(e){}
  applyTheme();
}
applyTheme();
if(window.matchMedia){
  try{
    const mq = window.matchMedia('(prefers-color-scheme:dark)');
    const onSys = function(){ if(themePref() === 'auto') applyTheme(); };
    if(mq.addEventListener) mq.addEventListener('change', onSys);
    else if(mq.addListener) mq.addListener(onSys);
  }catch(e){}
}

(async function(){
  /* 已通过账号登录（PHP 侧已校验），这里只做初始化加载 */
  try { await loadSchema(); } catch(e) {}
  loadStats();
})();

async function loadUsers(page = 1) {
  const box = document.getElementById('userBox');
  const q = document.getElementById('userQ')?.value || '';
  const pager = document.getElementById('userPager');
  box.innerHTML = '<div class="hint">加载中...</div>';
  try {
    const d = await api('/api/v1/admin/user_list?page=' + page + '&q=' + encodeURIComponent(q));
    if (!d.success) { box.innerHTML = '<div class="err">' + esc(d.error || '加载失败') + '</div>'; return; }
    const users = d.data.items || [];
    const total = d.data.total || 0;
    const ustatus = d.data.ustatus || {};
    if (users.length === 0) { box.innerHTML = '<div class="hint">暂无用户</div>'; pager.innerHTML = ''; return; }
    let h = '<table><thead><tr><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead><tbody>';
    users.forEach(u => {
      const statusClass = u.status == 1 ? 'ok' : 'bad';
      const statusText = u.status == 1 ? '正常' : '禁用';
      const isAdm = String(u.is_admin) === '1';
      h += '<tr>'
        + '<td>' + esc(u.username) + '</td>'
        + '<td>' + (isAdm ? '-' : esc(u.email || '-')) + '</td>'
        + '<td>' + (isAdm ? '管理员' : '普通用户') + '</td>'
        + '<td><span class="pill ' + statusClass + '">' + statusText + '</span></td>'
        + '<td>' + (u.created_at || '-') + '</td>'
        + '<td>'
          + '<button class="ghost sm" onclick="toggleUserStatus(' + u.id + ', ' + (u.status == 1 ? 0 : 1) + ')">' + (u.status == 1 ? '禁用' : '启用') + '</button>'
          + '<button class="ghost sm" onclick="editUser(' + u.id + ')">编辑</button>'
          + '<button class="ghost sm bad" onclick="deleteUser(' + u.id + ')">删除</button>'
        + '</td></tr>';
    });
    h += '</tbody></table>';
    box.innerHTML = h;
    /* ★ v1.9.4 fix：这里原来写成 meta.total / meta.per_page，而 meta 根本不存在，
       且与上面的 const total 重复声明 —— 重复 const 会让整段 script 解析失败，
       直接导致后台所有按钮失效。每页条数以接口为准（30）。 */
    const totalPages = Math.ceil((d.data.total || 0) / 30);
    if (totalPages > 1) {
      let pH = '<nav class="pagination">';
      if (page > 1) pH += '<button onclick="loadUsers(' + (page - 1) + ')">上一页</button>';
      for (let i = 1; i <= totalPages; i++) pH += '<button class="' + (i === page ? 'active' : '') + '" onclick="loadUsers(' + i + ')">' + i + '</button>';
      if (page < totalPages) pH += '<button onclick="loadUsers(' + (page + 1) + ')">下一页</button>';
      pH += '</nav>';
      pager.innerHTML = pH;
    } else { pager.innerHTML = ''; }
  } catch (e) { box.innerHTML = '<div class="err">加载失败: ' + esc(e.message) + '</div>'; }
}
async function toggleUserStatus(id, status) {
  if (!confirm('确定要' + (status == 1 ? '启用' : '禁用') + '该用户吗？')) return;
  const d = await api('/api/v1/admin/user_save', 'POST', { id, status });
  if (!d.success) { alert(d.error || '操作失败'); return; }
  loadUsers();
}
async function editUser(id) {
  const d = await api('/api/v1/admin/user_list');
  if (!d.success) { alert('加载失败'); return; }
  /* ★ v1.9.4 fix：接口返回的字段是 items（原来写成 users，永远找不到人 →「用户不存在」） */
  const user = (d.data.items || []).find(u => u.id == id);
  if (!user) { alert('用户不存在'); return; }
  const isAdm = String(user.is_admin) === '1';
  const newUsername = prompt('用户名:', user.username);
  if (newUsername === null) return;
  /* 管理员的 email 字段存的是系统标记，不当作真实邮箱来改 */
  let newEmail = '';
  if (!isAdm) {
    newEmail = prompt('邮箱:', user.email || '');
    if (newEmail === null) return;
  }
  const isAdmin = confirm('设为管理员？\n（当前：' + (isAdm ? '管理员' : '普通用户') + '）');
  const payload = { id: id, username: newUsername, is_admin: isAdmin ? 1 : 0 };
  if (!isAdm) { payload.email = newEmail; }
  const saveD = await api('/api/v1/admin/user_save', 'POST', payload);
  if (!saveD.success) { alert(saveD.error || '保存失败'); return; }
  alert('用户已更新');
  loadUsers();
}
async function deleteUser(id) {
  if (!confirm('确定要删除该用户吗？此操作不可恢复！')) return;
  const d = await api('/api/v1/admin/user_del', 'POST', { id });
  if (!d.success) { alert(d.error || '删除失败'); return; }
  alert('用户已删除');
  loadUsers();
}

// ★ v1.9.0: 自动刮削功能
function renderScrapePanel() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="hint">就绪，可点击"开始刮削"</div>';
}

async function startScrape() {
  const status = document.getElementById('scrapeStatus');
  const log = document.getElementById('scrapeLog');
  const type = document.getElementById('scrapeType').value;
  const limit = parseInt(document.getElementById('scrapeLimit').value) || 50;

  status.innerHTML = '<div class="hint">正在刮削... 处理 ' + limit + ' 条</div>';
  log.style.display = 'block';
  log.innerHTML = '[开始] 类型=' + (type || '全部') + ' 数量=' + limit + '<br>';

  try {
    const d = await api('/api/v1/item', 'POST', { auto_scrape: true, type: type, limit: limit });
    if (!d.success) {
      status.innerHTML = '<div class="err">刮削失败: ' + esc(d.error || '未知错误') + '</div>';
      log.innerHTML += '[失败] ' + esc(d.error || '未知错误') + '<br>';
      return;
    }

    const scraped = d.data.scraped || 0;
    status.innerHTML = '<div class="ok">✓ 刮削完成！成功补充 ' + scraped + ' 条图片</div>';
    log.innerHTML += '[完成] 成功 ' + scraped + ' 条<br>';

    if (document.getElementById('tab-items').style.display !== 'none') {
      loadItems();
    }
  } catch (e) {
    status.innerHTML = '<div class="err">请求失败: ' + esc(e.message) + '</div>';
    log.innerHTML += '[错误] ' + esc(e.message) + '<br>';
  }
}

function clearScrapeResult() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="hint">就绪</div>';
  document.getElementById('scrapeLog').style.display = 'none';
  document.getElementById('scrapeLog').innerHTML = '';
}
</script>
</body>
</html>
