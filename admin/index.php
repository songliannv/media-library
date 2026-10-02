<?php
use Core\Config;use Core\User;if(!defined('ML_APP')){define('ML_APP',true);}if(!defined('ML_ROOT')){define('ML_ROOT',dirname(__DIR__));}if(!defined('ML_APP_VERSION')&&is_file(__DIR__.'/../core/Version.php')){require_once __DIR__.'/../core/Version.php';}if(!defined('ML_APP_VERSION')){define('ML_APP_VERSION','未同步');}header('Content-Type: text/html; charset=utf-8');header('X-Robots-Tag: noindex, nofollow');require_once __DIR__.'/../core/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/../core/Session.php';require_once __DIR__.'/../core/DB.php';require_once __DIR__.'/../core/Config.php';require_once __DIR__.'/../core/User.php';$a=ml_session_boot(User::SESS);function ml_ad_csrf(){if(empty($_SESSION['ml_ad_csrf'])){$_SESSION['ml_ad_csrf']=bin2hex(random_bytes(32));}return(string)$_SESSION['ml_ad_csrf'];}$b=trim((string)Config::sub('admin','dir','admin'),'/');if($b===''||!preg_match('/^[A-Za-z0-9_-]+$/',$b)||substr($b,-5)!=='admin'){$b='admin';}$c='/'.$b.'/';$d='';$e='';$f='';$g=isset($_POST['ad_do'])?(string)$_POST['ad_do']:'';if($g===''&&isset($_POST['u'],$_POST['p'])){$g='login';}if($g==='login'){$f='user';if(!$a){$d='服务器无法保存登录会话（session 不可写），所以登不上去。'.'请打开 check.php 看「会话」一栏，或让服务商把 PHP 的 session.save_path 目录设为可写。';}elseif(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期（登录会话没保持住），请再提交一次；若反复如此，请打开 check.php 看「会话」一栏。';}elseif(isset($_POST['ml_hp'])&&trim((string)$_POST['ml_hp'])!==''){$d='检测到异常提交，已忽略。';}else{$h=isset($_POST['u'])?(string)$_POST['u']:'';$i=isset($_POST['p'])?(string)$_POST['p']:'';$j=User::login($h,$i);if(!empty($j['ok'])){if(User::isAdmin()){header('Location: '.$c);exit;}User::logout();$d='该账号不是管理员，无法进入后台。';}else{$d=(string)$j['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='forgot'){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期，请重新提交。';}else{$j=User::requestReset(isset($_POST['account'])?(string)$_POST['account']:'','admin');if(!empty($j['ok'])){$e=(string)$j['msg'];}else{$d=(string)$j['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='reset'){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期，请重新提交。';}else{$j=User::doReset(isset($_POST['token'])?(string)$_POST['token']:'',isset($_POST['new'])?(string)$_POST['new']:'',isset($_POST['new2'])?(string)$_POST['new2']:'');if(!empty($j['ok'])){$e=(string)$j['msg'].' 请用新密码登录。';}else{$d=(string)$j['msg'];}}}if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['logout'])){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$d='页面已过期，请重新提交。';}else{User::logout();header('Location: '.$c);exit;}}$k=User::me();if(!$k||!User::isAdmin()){$l='login';$m='';if(isset($_GET['reset'])){$l='reset';$m=(string)$_GET['reset'];}elseif(isset($_GET['forgot'])){$l='forgot';}if($e!==''&&$l==='reset'){$l='login';}$n=($l==='reset')?(User::verifyResetToken($m)!==null):false;?><!DOCTYPE html>
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
  /* 标签项目页：全部标签 + 条目数 + 点击跳转条目筛选 */
  .tag-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
  .tag-card{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px;background:var(--card);border:1px solid var(--line);border-radius:12px;cursor:pointer;transition:.15s;text-decoration:none;color:var(--txt)}
  .tag-card:hover{border-color:var(--pri);background:linear-gradient(135deg,#f7f7ff,#f1f1fb);transform:translateY(-1px);box-shadow:0 4px 12px rgba(91,91,214,.15)}
  .tag-card .tag-name{font-size:14px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0}
  .tag-card .tag-count{font-size:13px;font-weight:700;color:var(--pri);background:linear-gradient(135deg,var(--pri),var(--pri2));color:#fff;padding:2px 10px;border-radius:20px;flex:0 0 auto}
  .tag-hint{padding:14px 16px;background:linear-gradient(135deg,#eef2ff,#f7f7ff);border:1px solid var(--line);border-radius:12px;margin-bottom:14px;font-size:13px;color:var(--mut);line-height:1.7}
  .tag-hint b{color:var(--txt)}
  .tag-hint .tag-stat{font-size:15px;color:var(--pri);font-weight:700}
/* ★ v3.6.6：标签页三个项目 —— 常规标签（剧情类单独分组）/ 热门导演 / 热门演员，卡片风格统一 */
.tag-group{margin-bottom:14px}
.tag-group-title{font-size:14px;font-weight:700;color:var(--txt);margin:0 0 10px;display:flex;align-items:center;gap:6px}
.people-cloud{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}
.people-row{padding:12px 14px;background:var(--card);border:1px solid var(--line);border-radius:12px}
.people-row .tag-group-title{margin-bottom:10px}
.people-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
:root[data-theme=dark] .people-row{background:#1b2438;border-color:#2f3a56}
  .tag-list{max-height:520px;overflow-y:auto;margin-top:12px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;align-content:start}
  @media(max-width:1180px){.tag-list{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:760px){.tag-list{grid-template-columns:1fr}}
  .tag-item{display:flex;gap:10px;padding:10px;border:1px solid var(--line);border-radius:10px;align-items:center;min-width:0;transition:.12s;cursor:pointer}
  .tag-item:hover{border-color:var(--pri);background:linear-gradient(135deg,#f7f7ff,#f1f1fb)}
  .tag-item .ti-txt{flex:1;min-width:0}
  .tag-item .ti-txt b{display:block;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tag-item .ti-txt .hint{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  :root[data-theme=dark] .tag-item:hover{background:linear-gradient(135deg,rgba(91,91,214,.12),rgba(91,91,214,.06))}
  .tag-list-empty{text-align:center;padding:32px;color:var(--mut);font-size:13px;grid-column:1/-1}
  :root[data-theme=dark] .tag-card{background:var(--card)}
  :root[data-theme=dark] .tag-card:hover{background:linear-gradient(135deg,rgba(91,91,214,.15),rgba(91,91,214,.08))}
  :root[data-theme=dark] .tag-hint{background:linear-gradient(135deg,rgba(91,91,214,.15),rgba(91,91,214,.08))}

  /* ★ v3.2.5：标签页强制刷新按钮 */
  .tag-hint .tag-refresh{float:right;margin-left:12px;padding:5px 12px;font-size:12px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--mut);cursor:pointer;transition:.12s}
  .tag-hint .tag-refresh:hover{border-color:var(--pri);color:var(--pri);background:rgba(91,91,214,.06)}
  .tag-hint .tag-refresh.loading{opacity:.5;cursor:wait}
  :root[data-theme=dark] .tag-hint .tag-refresh:hover{background:rgba(91,91,214,.12)}

  /* ★ v3.2.5：用户中心 —— 4 卡一排 + 右侧滑出详情面板 */
  .user-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:14px}
  @media(max-width:1100px){.user-grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:820px){.user-grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:520px){.user-grid{grid-template-columns:1fr}}
  .user-card{background:linear-gradient(160deg,#f7f7ff,#f1f1fb);border:1px solid var(--line);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px;transition:.15s;position:relative;overflow:hidden}
  .user-card:hover{border-color:var(--pri);transform:translateY(-2px);box-shadow:0 8px 20px rgba(91,91,214,.15)}
  :root[data-theme=dark] .user-card{background:linear-gradient(160deg,rgba(91,91,214,.12),rgba(124,58,237,.06))}
  :root[data-theme=dark] .user-card:hover{border-color:var(--pri);box-shadow:0 8px 20px rgba(91,91,214,.25)}
  .user-card .uc-head{display:flex;align-items:center;gap:12px}
  .user-card .uc-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--pri),var(--pri2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex:0 0 44px;box-shadow:0 2px 8px rgba(91,91,214,.35)}
  .user-card .uc-name{flex:1;min-width:0}
  .user-card .uc-name b{display:block;font-size:15px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .user-card .uc-name .uc-role{display:block;font-size:11.5px;color:var(--mut);margin-top:2px}
  .user-card .uc-role.admin{color:#f59e0b;font-weight:600}
  .user-card .uc-role.normal{color:var(--mut)}
  .user-card .uc-meta{display:flex;flex-direction:column;gap:5px;padding:10px 12px;background:rgba(0,0,0,.02);border:1px dashed var(--line);border-radius:9px}
  :root[data-theme=dark] .user-card .uc-meta{background:rgba(255,255,255,.03)}
  .user-card .uc-meta .row{display:flex;justify-content:space-between;gap:8px;font-size:12px}
  .user-card .uc-meta .row .k{color:var(--mut);flex:0 0 auto}
  .user-card .uc-meta .row .v{color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right;min-width:0}
  .user-card .uc-actions{display:flex;gap:6px;flex-wrap:wrap}
  .user-card .uc-actions button{flex:1;padding:7px 8px;font-size:12px;border-radius:8px}
  .user-card .uc-actions button.detail{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;font-weight:600;flex:2 0 auto;box-shadow:0 2px 6px rgba(91,91,214,.25)}
  .user-card .uc-actions button.detail:hover{filter:brightness(1.08)}

  /* 用户详情面板（右侧滑出） */
  .user-modal-mask{position:fixed;inset:0;background:rgba(15,20,32,.55);backdrop-filter:blur(3px);z-index:9999;display:none;animation:fadein .18s}
  @keyframes fadein{from{opacity:0}to{opacity:1}}
  .user-modal{position:fixed;top:0;right:0;width:min(560px,100%);height:100vh;background:var(--card);border-left:1px solid var(--line);box-shadow:-10px 0 40px rgba(0,0,0,.2);z-index:10000;display:none;flex-direction:column;animation:slidein .25s cubic-bezier(.4,0,.2,1)}
  @keyframes slidein{from{transform:translateX(100%)}to{transform:translateX(0)}}
  .user-modal .um-head{padding:20px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:14px}
  .user-modal .um-head .uc-avatar{width:52px;height:52px;font-size:22px}
  .user-modal .um-head .um-title{flex:1;min-width:0}
  .user-modal .um-head .um-title b{display:block;font-size:18px}
  .user-modal .um-head .um-title .sub{display:block;font-size:12.5px;color:var(--mut);margin-top:3px}
  .user-modal .um-head .um-close{width:34px;height:34px;border-radius:8px;border:1px solid var(--line);background:var(--card);color:var(--mut);cursor:pointer;font-size:16px;flex:0 0 34px}
  .user-modal .um-head .um-close:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2}
  :root[data-theme=dark] .user-modal .um-head .um-close:hover{background:#2a0a0a;border-color:#7f1d1d;color:#fca5a5}
  .user-modal .um-body{flex:1;overflow-y:auto;padding:16px 22px}
  .user-modal .um-section{margin-bottom:18px}
  .user-modal .um-section h4{margin:0 0 10px;font-size:13.5px;color:var(--mut);display:flex;align-items:center;gap:6px}
  .user-modal .um-section h4::before{content:'';width:3px;height:14px;background:linear-gradient(180deg,var(--pri),var(--pri2));border-radius:2px}
  .user-modal .um-kv{display:grid;grid-template-columns:auto 1fr;gap:8px 14px;font-size:13px}
  .user-modal .um-kv .k{color:var(--mut)}
  .user-modal .um-kv .v{color:var(--txt);word-break:break-all}
  .user-modal .um-kv .v .pill{font-size:11.5px}
  .user-modal .um-logs{display:flex;flex-direction:column;gap:8px}
  .user-modal .um-log{padding:10px 12px;background:rgba(0,0,0,.02);border:1px solid var(--line);border-radius:9px;font-size:12.5px}
  :root[data-theme=dark] .user-modal .um-log{background:rgba(255,255,255,.03)}
  .user-modal .um-log .ln1{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px}
  .user-modal .um-log .ln1 .ep{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--pri);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0}
  .user-modal .um-log .ln1 .code{font-size:11px;padding:1px 8px;border-radius:10px;background:#ecfdf5;color:#10b981;font-weight:600;flex:0 0 auto}
  .user-modal .um-log .ln1 .code.err{background:#fef2f2;color:#ef4444}
  :root[data-theme=dark] .user-modal .um-log .ln1 .code{background:#0a2818;color:#6ee7b7}
  :root[data-theme=dark] .user-modal .um-log .ln1 .code.err{background:#2a0a0a;color:#fca5a5}
  .user-modal .um-log .ln2{display:flex;justify-content:space-between;color:var(--mut);font-size:11.5px;gap:8px;flex-wrap:wrap}
  .user-modal .um-empty{text-align:center;padding:24px;color:var(--mut);font-size:13px}
  /* v3.5.0：排行榜 4 列 × 10 行（共 40 条），带排名序号 */
  /* v3.5.2：排行榜 5 列 × 10 行（共 50 条） */
  .top-list{max-height:620px;overflow-y:auto;margin-top:10px;display:grid;grid-template-columns:repeat(5,1fr);gap:8px 10px;align-content:start}
  @media(max-width:1500px){.top-list{grid-template-columns:repeat(4,1fr)}}
  @media(max-width:1240px){.top-list{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:980px){.top-list{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:640px){.top-list{grid-template-columns:1fr}}
  .top-item{padding:9px 12px;border:1px solid var(--line);border-radius:9px;transition:.12s;cursor:pointer;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:8px}
  .top-item:hover{border-color:var(--pri);background:linear-gradient(135deg,#f7f7ff,#f1f1fb);transform:translateX(2px)}
  /* v3.5.1：序号徽章统一尺寸/字号（前 3 名只换配色不放大），显式锁字体栈避免 emoji 回退导致字形大小不一 */
  .top-rank,.top-rank-1,.top-rank-2,.top-rank-3{flex:0 0 auto;box-sizing:border-box;width:22px;height:22px;min-width:22px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Microsoft YaHei","PingFang SC",sans-serif;font-size:12px;font-weight:700;line-height:1;letter-spacing:0;text-shadow:none;font-variant-numeric:tabular-nums;background:#eef0f5;color:#5a6270}
  .top-rank-1{background:linear-gradient(135deg,#ffd95e,#f5a623);color:#7a3f00;box-shadow:inset 0 0 0 1px rgba(255,255,255,.45),0 1px 3px rgba(245,166,35,.35)}
  .top-rank-2{background:linear-gradient(135deg,#e6eaee,#b3bac4);color:#2c333d;box-shadow:inset 0 0 0 1px rgba(255,255,255,.55)}
  .top-rank-3{background:linear-gradient(135deg,#eab28a,#c07845);color:#fff;box-shadow:inset 0 0 0 1px rgba(255,255,255,.35)}
  .top-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  :root[data-theme=dark] .top-rank{background:rgba(255,255,255,.06);color:#c9d1dd}
  .top-empty{text-align:center;padding:40px;color:var(--mut);font-size:13px;grid-column:1/-1}
  :root[data-theme=dark] .top-item:hover{background:linear-gradient(135deg,rgba(91,91,214,.12),rgba(91,91,214,.06))}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .stat{background:linear-gradient(160deg,#f7f7ff,#f1f1fb);border:1px solid var(--line);border-radius:11px;padding:15px}
  .stat b{font-size:22px;display:block}
  .stat span{color:var(--mut);font-size:13px}
  .cats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .cat{padding:7px 16px;border:1px solid var(--line);background:var(--card);border-radius:20px;cursor:pointer;font-size:14px;color:var(--mut);transition:.15s}
  .cat:hover{color:var(--pri);border-color:var(--pri)}
  .cat.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;font-weight:600}
  .toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}
  .stat-grid + .toolbar{margin-top:14px}
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
  .thumb-ph{width:46px;height:66px;background:#eef0f5;border-radius:7px;font-size:10px;color:var(--hint);display:flex;align-items:center;justify-content:center}
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
  /* 勾选项集合：一排 4 个（窄屏自适应降列），比竖着堆 1 列省纵向空间 */
  .chks{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:6px 10px;margin-top:4px}
  .chks .chk{margin:2px 0}
  .chks .chk input{flex:0 0 16px}
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
/* 网盘卡片：v3.4.4 收紧尺寸 → 宽屏 3 列一排；窄屏依次回退 2 列 / 1 列 */
.pan-2col{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:start}
.pan-card{display:flex;flex-direction:column;gap:0;border:2px solid var(--pri);border-radius:11px;padding:12px 14px;background:var(--card)}
.pan-card-title{font-size:14px;font-weight:700;margin-bottom:8px;padding-bottom:6px;border-bottom:0}
.pan-card .set-sec{padding:6px 0 4px}
.pan-card .set-sec h4{font-size:13px}
.pan-card .set-sec .tip{font-size:11.5px;margin:2px 0 5px}
.pan-cookie-info{display:flex;gap:6px;align-items:center;margin:1px 0 6px;padding-left:1px;flex-wrap:wrap}
/* --- 本地资源导入页（v3.1.5 美化：统一表单控件 / 内嵌卡片 / 结果预览） --- */
#tab-local .local-head{position:relative;display:flex;align-items:center;min-height:38px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--line)}
#tab-local .local-head h2{margin:0;font-size:18px;font-weight:700;white-space:nowrap}
#tab-local .local-head .subtabs{position:absolute;left:50%;transform:translateX(-50%);margin:0;padding:0;border-bottom:0;display:flex;justify-content:center;gap:6px;white-space:nowrap}
/* v3.3.1：本地导入弹窗居中窗口 */
.local-overlay{position:fixed;inset:0;background:rgba(27,31,42,.5);display:none;align-items:center;justify-content:center;padding:24px;z-index:9999}
.local-overlay .local-box{background:var(--card);border-radius:16px;padding:22px;width:100%;max-width:720px;max-height:88vh;overflow:auto;box-shadow:0 24px 60px rgba(0,0,0,.3);margin:0 auto;box-sizing:border-box}
.local-box .local-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line)}
.local-box .local-sub{background:transparent;border:none;padding:0;max-width:none}
.local-box .local-sub .local-actions{justify-content:center}
.local-sub{background:var(--bg);border:1px solid var(--line);border-radius:16px;padding:18px 20px 20px;max-width:880px}
#lpPlaceholder:hover{border-color:var(--pri2);background:linear-gradient(135deg,#f1f1fb,#ebebf7);transform:scale(1.01);box-shadow:0 4px 16px rgba(91,91,214,.15)}
.local-sub>label,.local-sub .form-row>label{display:block;font-size:13px;font-weight:600;color:var(--txt);margin:0 0 7px}
.local-sub .form-row{margin:0 0 14px}
#tab-local textarea{width:100%;box-sizing:border-box;padding:13px 15px;border:1px solid var(--line);border-radius:12px;background:var(--card);font:13px/1.8 ui-monospace,SFMono-Regular,Consolas,Menlo,monospace;color:var(--txt);outline:none;transition:.15s;resize:vertical}
#tab-local textarea:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.13)}
#tab-local select{padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--txt);font-size:14px;outline:none;cursor:pointer;transition:.15s}
#tab-local select:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.13)}
#tab-local input[type=text],#tab-local input:not([type]){width:100%;box-sizing:border-box;padding:11px 13px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--txt);font-size:14px;outline:none;transition:.15s;font-family:inherit}
#tab-local input:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.13)}
#tab-local input[type=file]{width:100%;box-sizing:border-box;padding:16px 14px;border:1.5px dashed var(--line);border-radius:14px;background:var(--card);color:var(--mut);font-size:13px;cursor:pointer;transition:.15s}
#tab-local input[type=file]:hover{border-color:var(--pri);color:var(--pri)}
#tab-local input[type=file]::file-selector-button{margin-right:12px;padding:8px 16px;border:0;border-radius:9px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-weight:600;font-size:13px;cursor:pointer}
.local-type-row{display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap}
.local-type-row>label{margin:0!important}
.local-2col{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.local-actions{display:flex;gap:10px;margin-top:16px}
#tab-local .lpv{margin-top:16px}
#tab-local .lpv:has(table){background:var(--card);border:1px solid var(--line);border-radius:14px;padding:2px 14px 8px;overflow-x:auto}
#tab-local .lpv .lpv-title{font-size:12.5px;font-weight:700;color:var(--pri);padding:10px 2px 4px}
#tab-local .lpv table{margin:0 0 8px}
/* --- v3.2.9：本地导入结果卡片列表（名称 + 网盘链接 + 匹配状态） --- */
#tab-local .lpv .lpv-cards{display:flex;flex-direction:column;gap:8px;margin-top:8px}
#tab-local .lpv .lpv-card{display:flex;align-items:stretch;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden;transition:.15s}
#tab-local .lpv .lpv-card:hover{border-color:var(--pri);box-shadow:0 4px 14px rgba(91,91,214,.12)}
#tab-local .lpv .lpv-card-head{display:flex;align-items:center;gap:12px;padding:10px 14px;flex:1;min-width:0}
#tab-local .lpv .lpv-card-idx{flex-shrink:0;width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--pri),var(--pri2));color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(91,91,214,.25)}
#tab-local .lpv .lpv-card-title{flex:1;min-width:0;font-size:13.5px;font-weight:600;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#tab-local .lpv .lpv-card .lpv-card-url{display:block;min-width:0;margin-top:3px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mut);font-size:12px;font-family:ui-monospace,SFMono-Regular,Consolas,Menlo,monospace}
#tab-local .lpv .lpv-card .st-lc{flex-shrink:0}
#tab-local .lpv .lpv-card .ghost{flex-shrink:0}
/* --- v3.2.4：本地资源导入预览表格增强（网盘筛选 + 失效/已用状态） --- */
#tab-local .subtabs{justify-content:center}
.local-filter{display:flex;flex-wrap:wrap;gap:6px;margin:14px 0 10px;padding:10px 12px;background:linear-gradient(135deg,rgba(91,91,214,.04),rgba(124,58,237,.02));border:1px dashed var(--line);border-radius:12px;align-items:center}
.local-filter .fg{display:inline-flex;align-items:center;gap:8px;font-size:12px;color:var(--mut);font-weight:600;margin-right:2px}
.local-filter .fg:not(:first-child){border-left:1px solid var(--line);padding-left:12px}
.local-filter button{padding:4px 10px;font-size:12px;border:1px solid var(--line);background:var(--card);color:var(--txt);border-radius:14px;cursor:pointer;transition:.15s;font-weight:500}
.local-filter button:hover{border-color:var(--pri);color:var(--pri)}
.local-filter button.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;box-shadow:0 2px 6px rgba(91,91,214,.25);font-weight:600}
.local-filter button .cnt{display:inline-block;background:rgba(0,0,0,.12);padding:1px 6px;border-radius:10px;font-size:10.5px;margin-left:3px;font-weight:700}
.local-filter button.active .cnt{background:rgba(255,255,255,.28)}
.st-lc{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;font-size:11.5px;font-weight:600;border:1px solid var(--line);background:var(--bg)}
.st-lc.unchecked{color:var(--mut)}
.st-lc.ok{color:#10b981;border-color:#6ee7b7;background:#ecfdf5}
.st-lc.dead{color:#ef4444;border-color:#fca5a5;background:#fef2f2}
.st-lc.used{color:#f59e0b;border-color:#fcd34d;background:#fffbeb}
.st-lc.fresh{color:var(--pri);border-color:rgba(91,91,214,.3);background:rgba(91,91,214,.08)}
:root[data-theme=dark] .st-lc.ok{background:#0a2818;border-color:#065f46}
:root[data-theme=dark] .st-lc.dead{background:#2a0a0a;border-color:#7f1d1d}
:root[data-theme=dark] .st-lc.used{background:#2a2010;border-color:#78350f}
:root[data-theme=dark] .st-lc.fresh{background:rgba(91,91,214,.12)}
:root[data-theme=dark] .local-filter{background:rgba(91,91,214,.05);border-color:var(--line)}
:root[data-theme=dark] .local-filter button .cnt{background:rgba(255,255,255,.12)}
.local-url{max-width:280px;word-break:break-all;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;color:var(--mut)}
.local-url:hover{color:var(--pri)}
.lpv-pan{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;padding:2px 8px;border-radius:10px;background:rgba(91,91,214,.08);color:var(--pri)}
/* --- 自动刮削页（v3.1.6 美化：复用本地导入页控件风格 + 终端风日志） --- */
#tab-scrape select{padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--txt);font-size:14px;outline:none;cursor:pointer;transition:.15s;width:100%;box-sizing:border-box}
#tab-scrape input[type=number]{width:100%;box-sizing:border-box;padding:11px 13px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--txt);font-size:14px;outline:none;transition:.15s;font-family:inherit}
#tab-scrape select:focus,#tab-scrape input[type=number]:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.13)}
.scrape-log{margin-top:14px;max-height:300px;overflow-y:auto;font:12.5px/1.9 ui-monospace,SFMono-Regular,Consolas,Menlo,monospace;background:#0f172a;color:#cbd5e1;padding:14px 16px;border-radius:12px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)}
.scrape-log b{color:#7dd3fc;font-weight:600}
:root[data-theme=dark] .scrape-log{background:#0b1120;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08)}
/* ★ v3.5.7：自动刮削页三列布局 + 失败数量状态框（0=灰，>0=红） */
.local-3col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
/* v3.7.4：#tab-scrape 面板撑满卡宽（默认 .local-sub 有 max-width:880px，导致右侧留白） */
#tab-scrape .local-sub{max-width:none}
#tab-scrape .local-3col{grid-template-columns:1.2fr 1.2fr 1fr;gap:16px}
.scrape-stat{min-height:42px;box-sizing:border-box;display:flex;align-items:center;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--card);font-size:15px;font-weight:700;font-variant-numeric:tabular-nums}
.scrape-stat.zero{color:var(--mut);font-weight:400}
.scrape-stat.bad{color:var(--dead);border-color:var(--dead)}
@media (max-width:760px){.local-3col{grid-template-columns:1fr;gap:12px}}
/* ★ v3.7.7：#tab-sync 采集参数页 —— 8 个字段两列布局，整页纵向缩短 ~40%
   默认 .set-sec 是垂直堆叠（border-top 分隔线），两列布局后第一列与第二列首项
   都去掉上边框；行间距用 row-gap 控制，视觉更紧凑。窄屏 (<960px) 回退一列。 */
#tab-sync #syncParams{display:grid;grid-template-columns:1fr 1fr;column-gap:32px;row-gap:0;align-items:start}
#tab-sync #syncParams .set-sec{border-top:1px solid var(--line);padding:14px 0 8px}
#tab-sync #syncParams .set-sec:first-child,#tab-sync #syncParams .set-sec:nth-child(2){border-top:0;padding-top:2px}
@media (max-width:960px){
  #tab-sync #syncParams{grid-template-columns:1fr}
  #tab-sync #syncParams .set-sec:first-child{border-top:0;padding-top:2px}
}
.pan-card-link{color:var(--pri);font-size:11.5px;text-decoration:none;padding:3px 8px;border-radius:6px;border:1px solid var(--line);background:var(--bg);transition:.15s;display:inline-flex;align-items:center;gap:4px}
.pan-card-link:hover{border-color:var(--pri);background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff}
:root[data-theme=dark] .pan-card-link{background:rgba(91,91,214,.08)}
:root[data-theme=dark] .pan-card-link:hover{background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff}
.imgpick{display:flex;gap:10px;align-items:center}
.imgpick img{width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid var(--line);background:#f8fafc;flex:0 0 52px}
.imgpick .ph{width:52px;height:52px;border-radius:10px;border:1px dashed var(--line);display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:11px;flex:0 0 52px}

/* --- 条目匹配页（v3.2.3 新增） --- */
#tab-matching .mc-stats{display:flex;gap:12px;flex-wrap:wrap}
#tab-matching .mc-stat{flex:1 1 140px;min-width:140px;background:linear-gradient(135deg,rgba(91,91,214,.06),rgba(124,58,237,.03));border:1px solid var(--line);border-radius:14px;padding:14px 18px;display:flex;flex-direction:column;gap:4px}
#tab-matching .mc-stat b{font-size:26px;font-weight:700;color:var(--pri);line-height:1}
#tab-matching .mc-stat span{font-size:12px;color:var(--mut)}
#tab-matching .mc-unmatched b{color:#f59e0b}
#tab-matching .mc-matched b{color:#10b981}
#tab-matching .mc-dead b{color:#ef4444}
#tab-matching .mc-tab{padding:9px 16px;border:1px solid var(--line);background:var(--card);color:var(--txt);border-radius:10px;cursor:pointer;font-size:13.5px;font-weight:600;transition:.15s;display:inline-flex;align-items:center;gap:8px}
#tab-matching .mc-tab:hover{border-color:var(--pri)}
#tab-matching .mc-tab.active{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(91,91,214,.25)}
#tab-matching .mc-auto-btn{margin-left:auto;white-space:nowrap}
/* v3.4.6：操作列按钮容器 —— 三个按钮横向排列 + 12px 间距，避免挤在一起 */
#tab-matching .btn-row{display:flex;flex-wrap:wrap;gap:8px 10px;align-items:center}
#tab-matching .btn-row .mc-match-one{border-color:#ffb36b;color:#e67e22;font-weight:600}
#tab-matching .btn-row .mc-match-one:hover{background:linear-gradient(120deg,#ffb36b,#f39c12);color:#fff;border-color:transparent}
#tab-matching .mc-tab .cnt{font-size:11px;background:rgba(0,0,0,.15);padding:2px 7px;border-radius:20px;font-weight:700}
#tab-matching .mc-tab.active .cnt{background:rgba(255,255,255,.28)}
#tab-matching .st-mc{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--line);background:var(--bg)}
#tab-matching .st-mc.unmatched{color:#f59e0b;border-color:#fcd34d;background:#fffbeb}
#tab-matching .st-mc.ok{color:#10b981;border-color:#6ee7b7;background:#ecfdf5}
#tab-matching .st-mc.dead{color:#ef4444;border-color:#fca5a5;background:#fef2f2}
:root[data-theme=dark] #tab-matching .st-mc.unmatched{background:#2a2010;border-color:#78350f}
:root[data-theme=dark] #tab-matching .st-mc.ok{background:#0a2818;border-color:#065f46}
:root[data-theme=dark] #tab-matching .st-mc.dead{background:#2a0a0a;border-color:#7f1d1d}
#tab-matching .mc-rows-empty{padding:40px 20px;text-align:center;color:var(--mut);font-size:13.5px}
#tab-matching .mc-rows-empty b{display:block;font-size:36px;margin-bottom:8px;opacity:.5}
/* v3.5.2：条目列表改为 4 列卡片网格（窄屏逐级回落，卡片同步紧凑化） */
#tab-matching .mc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px;align-items:start}
@media(max-width:1500px){#tab-matching .mc-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:1180px){#tab-matching .mc-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){#tab-matching .mc-grid{grid-template-columns:1fr}}
#tab-matching .mc-card{display:flex;gap:10px;padding:10px;border:1px solid var(--line);border-radius:11px;background:var(--card);transition:.15s;min-width:0}
#tab-matching .mc-card:hover{border-color:var(--pri);box-shadow:0 4px 14px rgba(91,91,214,.12)}
#tab-matching .mc-card .thumb{width:42px;height:60px;flex:0 0 42px;object-fit:cover;border-radius:6px}
#tab-matching .mc-thumb-ph{display:inline-block;background:#eef0f5;border-radius:7px}
#tab-matching .mc-main{flex:1;min-width:0;display:flex;flex-direction:column;gap:5px}
#tab-matching .mc-title{font-size:13px;font-weight:600;line-height:1.35;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#tab-matching .mc-sub{font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-height:15px}
#tab-matching .mc-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px}
#tab-matching .btn-row{gap:6px 8px}
#tab-matching .btn-row .sm{font-size:12px;padding:4px 9px}
#tab-matching .mc-cat{font-size:12px;padding:2px 9px;border-radius:20px;background:#eef2ff;color:var(--pri);white-space:nowrap}
#tab-matching .mc-acts{margin-top:auto}
:root[data-theme=dark] #tab-matching .mc-cat{background:rgba(91,91,214,.18)}
:root[data-theme=dark] #tab-matching .mc-thumb-ph{background:#232c45}
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
  /* v3.4.4：中间档，网盘卡片回退到 2 列 */
  .pan-2col{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .pan-card{padding:11px 13px}
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
  #tab-local .local-head{display:block;min-height:0}
  #tab-local .local-head h2{margin-bottom:10px}
  #tab-local .local-head .subtabs{position:static;transform:none;overflow-x:auto;justify-content:flex-start;white-space:normal}
  #tab-matching .mc-auto-btn{margin-left:0}
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
  .pan-2col{grid-template-columns:1fr;gap:12px}
  .local-2col{grid-template-columns:1fr;gap:12px}
  .local-sub{padding:14px 14px 16px}
  .chks{grid-template-columns:repeat(2,minmax(0,1fr));gap:5px 10px}
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
  .chks{grid-template-columns:1fr;gap:4px 8px}
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
:root[data-theme=dark] .thumb-ph{background:#232c45}
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
/* --- 本地导入弹窗统一美化 --- */
.local-overlay{background:rgba(20,24,38,.66);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);padding:16px}
.local-overlay .local-box{width:min(760px,calc(100vw - 32px));max-width:none;max-height:min(88vh,760px);padding:0;background:var(--card);border:1px solid rgba(255,255,255,.58);border-radius:20px;overflow:auto;box-shadow:0 28px 80px rgba(15,23,42,.34)}
.local-box .local-modal-head{position:sticky;top:0;z-index:2;margin:0;padding:17px 22px 15px;background:linear-gradient(135deg,rgba(91,91,214,.12),rgba(124,58,237,.06) 55%,var(--card));border-bottom:1px solid var(--line)}
.local-box .local-modal-head h3{margin:0!important;color:var(--txt);font-size:16px;line-height:1.3;font-weight:800;letter-spacing:.1px}
.local-box .local-modal-head button{width:32px;height:32px;padding:0;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:17px;line-height:1;color:var(--mut);border-color:var(--line);background:var(--card)}
.local-box .local-modal-head button:hover{color:var(--pri);border-color:var(--pri);background:rgba(91,91,214,.07)}
.local-box .local-sub{padding:22px 24px 24px;background:var(--card);border:0;border-radius:0;max-width:none}
.local-box .local-sub>label,.local-box .local-sub .form-row>label{font-size:12.5px;font-weight:700;line-height:1.5;margin:0 0 8px;color:var(--txt)}
.local-box .local-sub .form-row{margin:0 0 15px}
.local-box textarea,.local-box select,.local-box input[type=text],.local-box input:not([type]){box-sizing:border-box;width:100%;border:1px solid var(--line);border-radius:13px;background:var(--bg);color:var(--txt);font-family:inherit;outline:none;transition:.16s}
.local-box textarea{min-height:168px;padding:14px 15px;font:13px/1.8 ui-monospace,SFMono-Regular,Consolas,Menlo,monospace;resize:vertical}
.local-box input[type=text],.local-box input:not([type]){padding:11px 13px;font-size:14px}
.local-box select{width:auto;min-width:92px;padding:9px 12px;font-size:13.5px;cursor:pointer}
.local-box textarea:focus,.local-box select:focus,.local-box input:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.13);background:var(--card)}
.local-box input[type=file]{width:100%;box-sizing:border-box;padding:15px 14px;border:1.5px dashed var(--line);border-radius:13px;background:var(--bg);color:var(--mut);font-size:13px;cursor:pointer;transition:.16s}
.local-box input[type=file]:hover{border-color:var(--pri);color:var(--pri)}
.local-box input[type=file]::file-selector-button{margin-right:12px;padding:8px 15px;border:0;border-radius:9px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-weight:700;font-size:12.5px;cursor:pointer}
.local-box .local-type-row{margin-top:13px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--bg);gap:9px}
.local-box .local-type-row>label{font-size:12.5px;font-weight:700;color:var(--txt)}
.local-box .local-type-row .hint{font-size:11.5px;line-height:1.5}
.local-box .local-2col{gap:15px}
.local-box .local-actions{justify-content:flex-end;align-items:center;gap:9px;margin-top:18px;padding-top:3px}
.local-box .local-actions button{min-width:112px}
.local-box #lpImportRow,.local-box #leImportRow{margin-top:20px;padding-top:16px;border-top:1px solid var(--line)}
.local-box .local-filter{margin-top:18px}
.local-box .lpv{margin-top:18px}
:root[data-theme=dark] .local-overlay{background:rgba(0,0,0,.72)}
:root[data-theme=dark] .local-overlay .local-box{border-color:rgba(255,255,255,.1);box-shadow:0 28px 80px rgba(0,0,0,.55)}
:root[data-theme=dark] .local-box .local-modal-head{background:linear-gradient(135deg,rgba(91,91,214,.2),rgba(124,58,237,.08) 55%,var(--card))}
:root[data-theme=dark] .local-box textarea,:root[data-theme=dark] .local-box select,
:root[data-theme=dark] .local-box input[type=text],:root[data-theme=dark] .local-box input:not([type]),
:root[data-theme=dark] .local-box input[type=file],:root[data-theme=dark] .local-box .local-type-row{background:#141c2e;color:var(--txt);border-color:var(--line)}
@media (max-width:560px){
  .local-overlay{padding:10px}
  .local-overlay .local-box{width:calc(100vw - 20px);max-height:calc(100vh - 20px);border-radius:16px}
  .local-box .local-modal-head{padding:15px 16px 13px}
  .local-box .local-sub{padding:17px 16px 18px}
  .local-box textarea{min-height:150px}
  .local-box .local-2col{grid-template-columns:1fr}
  .local-box .local-actions{justify-content:stretch;flex-wrap:wrap}
  .local-box .local-actions button{flex:1 1 100%;min-width:0}
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
<meta name="csrf-token" content="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
</head>
<body data-admin-dir="<?php echo htmlspecialchars((string)$b,ENT_QUOTES,'UTF-8');?>">
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
    <!-- ★ v4.4.4：一键清除全部缓存（标签/人物/接口级文件缓存 + 多级缓存），长任务改数据后点一下立即生效 -->
    <button type="button" class="ghost sm" onclick="clearAllCache(this)" title="清除标签/人物/接口等全部文件缓存与多级缓存，下次加载自动重建">清除缓存</button>
    <button type="button" class="ghost sm" onclick="openPwd()">修改密码</button>
    <form method="post" style="display:inline" action="<?php echo htmlspecialchars($c,ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="logout" value="1">
      <button type="submit" class="ghost" style="text-decoration:none;padding:6px 13px;border-radius:8px;font-size:13px;cursor:pointer">退出登录</button>
    </form>
  </div>
  <div class="tabs">
    <div class="tab active" data-tab="overview" onclick="switchTab('overview')">概览</div>
    <div class="tab" data-tab="items" onclick="switchTab('items')">条目管理</div>
    <div class="tab" data-tab="pending" onclick="switchTab('pending')">条目暂存</div>
    <div class="tab" data-tab="sync" onclick="switchTab('sync')">同步采集</div>
    <div class="tab" data-tab="tags" onclick="switchTab('tags')">标签项目</div>
    <div class="tab" data-tab="settings" onclick="switchTab('settings')">系统设置</div>
    <div class="tab" data-tab="site" onclick="switchTab('site')">站点设置</div>
    <div class="tab" data-tab="request" onclick="switchTab('request')">资源请求</div>
    <div class="tab" data-tab="apitoken" onclick="switchTab('apitoken')">API 调用</div>
    <div class="tab" data-tab="scrape" onclick="switchTab('scrape')">自动刮削</div>
    <div class="tab" data-tab="matching" onclick="switchTab('matching')">条目匹配</div>
    <div class="tab" data-tab="local" onclick="switchTab('local')">本地资源</div>
    <div class="tab" data-tab="users" onclick="switchTab('users')">用户中心</div>
  </div>

  <div id="tab-overview" class="tabpane">
    <div class="card"><div class="stat-grid" id="stats"></div></div>
    <div class="card">
      <h3 style="margin:0 0 4px;font-size:15px">🔥 点击最多的 50 个条目</h3>
      <div class="cats" id="topCats" style="margin-top:8px;margin-bottom:8px">
        <button class="cat active" data-cat="" onclick="filterTopItems('')">全部</button>
        <button class="cat" data-cat="short" onclick="filterTopItems('short')">短剧</button>
        <button class="cat" data-cat="movie" onclick="filterTopItems('movie')">电影</button>
        <button class="cat" data-cat="tv" onclick="filterTopItems('tv')">电视剧</button>
        <button class="cat" data-cat="anime" onclick="filterTopItems('anime')">动漫</button>
        <button class="cat" data-cat="variety" onclick="filterTopItems('variety')">综艺</button>
        <button class="cat" data-cat="game" onclick="filterTopItems('game')">游戏</button>
        <button class="cat" data-cat="book" onclick="filterTopItems('book')">书籍</button>
        <button class="cat" data-cat="music" onclick="filterTopItems('music')">音乐</button>
        <button class="cat" data-cat="other" onclick="filterTopItems('other')">其他</button>
      </div>
      <div class="top-list" id="topItemsList"></div>
    </div>
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
        <!-- ★ v4.3.11：条目管理工具栏「同步热门」按钮下线（用户要求）；采集功能仍在「同步采集」标签页 -->
        <button class="ghost" onclick="runPanTransfer(this)">转存网盘链接</button>
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

  <div id="tab-pending" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">条目暂存</h3>
        <span class="hint">PanSou 搜索入库的条目先进暂存，刮削成功后才能进入条目管理对外展示。</span>
      </div>
      <div class="toolbar">
        <input type="text" id="pq" placeholder="搜索暂存条目…" onkeydown="if(event.key==='Enter')loadPending()">
        <button onclick="loadPending()">查询</button>
        <span id="pendingCount" class="hint"></span>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>封面</th><th>标题</th><th>来源</th><th>网盘类型</th><th>入库时间</th><th>操作</th></tr></thead>
          <tbody id="pendingRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="pagePending(-1)">上一页</button>
        <span id="pendingPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="pagePending(1)">下一页</button>
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

  <div id="tab-tags" class="tabpane" style="display:none">
    <div class="tag-hint">
      <button class="tag-refresh" id="tagRefreshBtn" onclick="renderTags(true)" title="绕过文件缓存，直查数据库重新统计">🔄 强制刷新</button>
      <b>🏷️ 标签项目</b> — 这里列出全部标签及其对应条目数。点击标签可查看该标签下的所有条目，或在条目管理中按标签筛选。<b>标签列表有 300 秒文件缓存</b>，若与条目实际不符，点右上角「🔄 强制刷新」直查数据库。<br>
      <span class="tag-stat" id="tagStatLine">正在加载…</span>
    </div>
    <!-- ★ v3.6.8：三个项目顺序 —— 常规标签（人名已被过滤）→ 热门导演 → 热门演员 -->
    <!-- ★ v4.4.1：导演/演员区块保留（用户确认「进标签时挡住它们」正是要求的行为）；本轮只修两点：
         ① 统计行「共 N 个标签」改为 标签+导演+演员 全量计数，与页面卡片数一致；
         ② 点导演/演员不再跳条目管理，改为与标签一致的「就地展开作品列表」 -->
    <div class="tag-group" id="tagGroupWrap">
      <div class="tag-group-title">🏷️ 常规标签</div>
      <div class="tag-grid" id="tagGrid"></div>
    </div>
    <!-- 热门导演 / 热门演员：卡片样式与标签网格一致（名字 + 数量徽章） -->
    <div class="people-cloud" id="peopleCloud">
      <div class="people-row" id="peopleRowDir" style="display:none">
        <div class="tag-group-title">🎬 热门导演</div>
        <div class="people-list" id="peopleListDir"></div>
      </div>
      <div class="people-row" id="peopleRowAct" style="display:none">
        <div class="tag-group-title">⭐ 热门演员</div>
        <div class="people-list" id="peopleListAct"></div>
      </div>
      <div class="hint" id="peopleEmpty" style="display:none">暂无演职员数据 —— 同步 / 拉详情后自动充实，老数据可先跑 tools/backfill_people_tags.php 回填。</div>
    </div>
    <div class="card" id="tagDetailCard" style="display:none">
      <h3 style="margin:0 0 4px;font-size:15px" id="tagDetailTitle"></h3>
      <span class="hint" id="tagDetailHint"></span>
      <div class="toolbar" style="margin-top:10px">
        <button class="ghost sm" onclick="closeTagDetail()">← 返回标签列表</button>
        <button class="ghost sm" onclick="goItemsWithTag()">在条目管理中查看 →</button>
      </div>
      <div class="tag-list" id="tagItemList"></div>
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

  <div id="tab-scrape" class="tabpane" style="display:none">
    <div class="card">
      <h2>🎬 自动刮削</h2>
      <div class="local-sub">
        <div class="local-3col">
          <div class="form-row" style="margin:0">
            <label>类型筛选</label>
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
          <div class="form-row" style="margin:0">
            <label>每次处理数量（10 ~ 500）</label>
            <input type="number" id="scrapeLimit" value="50" min="10" max="500">
          </div>
          <div class="form-row" style="margin:0">
            <label>失败数量（最近一次）</label>
            <div id="scrapeFailed" class="scrape-stat zero">0</div>
          </div>
        </div>
        <div class="local-actions">
          <button id="scrapeGo" onclick="startScrape()">▶ 开始刮削</button>
          <button class="ghost" onclick="clearScrapeResult()">清除结果</button>
        </div>
        <div id="scrapeStatus" class="scrape-status"></div>
      </div>
      <div id="scrapeLog" class="scrape-log" style="display:none"></div>
    </div>
  </div>
  <div id="tab-matching" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <h2 style="margin:0;font-size:17px">🔗 条目匹配</h2>
        <span class="hint">按下载链接分两类：未匹配的需要人工补链接；已匹配的自动检测网盘是否失效。</span>
      </div>
      <div class="mc-stats" id="mcStats">
        <div class="mc-stat mc-unmatched">
          <b id="mcUnmatched">-</b>
          <span>待匹配</span>
        </div>
        <div class="mc-stat mc-matched">
          <b id="mcMatched">-</b>
          <span>已匹配</span>
        </div>
        <div class="mc-stat mc-dead" title="已匹配条目中存在失效链接">
          <b id="mcDead">-</b>
          <span>含失效链接</span>
        </div>
      </div>
      <div class="subtabs" id="mcSubtabs" style="margin-top:14px">
        <button class="mc-tab active" data-mctab="unmatched" onclick="switchMatchTab('unmatched')">
          🕓 待匹配 <span class="cnt" id="mcCntUnmatched">0</span>
        </button>
        <button class="mc-tab" data-mctab="matched" onclick="switchMatchTab('matched')">
          ✅ 已匹配 <span class="cnt" id="mcCntMatched">0</span>
        </button>
        <button class="ghost sm mc-auto-btn" id="mcAutoBtn" onclick="runMatchAuto()">🔗 自动匹配</button>
      </div>
      <div class="toolbar" style="margin-top:12px">
        <input type="text" id="mcQ" placeholder="搜索标题…" onkeydown="if(event.key==='Enter'){mcPage=1;loadMatching();}">
        <button onclick="mcPage=1;loadMatching()">查询</button>
        <!-- ★ v4.3.12：按「功能回对应标签页」原则清理重复按钮 ——
             「去同步采集」跨页跳转按钮、「+ 新增项目」重复入口（条目管理页才有）均已下线 -->
        <button class="ghost" id="mcCheckBtn" onclick="matchCheckAll()">🔍 立即检测全部链接</button>
      </div>
      <div class="mc-grid" id="mcRows"></div>
      <div class="pager">
        <button class="ghost sm" onclick="mcPageItems(-1)">上一页</button>
        <span id="mcPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="mcPageItems(1)">下一页</button>
      </div>
    </div>
  </div>
  <div id="tab-local" class="tabpane" style="display:none">
    <div class="card">
      <div class="local-head">
        <h2>📥 本地资源导入</h2>
        <div class="subtabs" id="localSubtabs">
          <button class="stab active" data-stab="paste" onclick="switchLocalTab('paste')">📋 粘贴解析</button>
          <button class="stab" data-stab="manual" onclick="switchLocalTab('manual')">✏️ 手动输入</button>
          <button class="stab" data-stab="excel" onclick="switchLocalTab('excel')">📊 Excel 导入</button>
        </div>
      </div>
    </div>
  </div>

  <div id="tab-users" class="tabpane" style="display:none">
    <div class="card">
      <h2>用户中心</h2>
      <div class="toolbar">
        <input type="text" id="userQ" placeholder="搜索用户名/邮箱..." onkeydown="if(event.key==='Enter')loadUsers()">
        <button onclick="loadUsers()">查询</button>
        <button class="ghost" onclick="document.getElementById('userQ').value='';loadUsers()">清空</button>
      </div>
      <div id="userBox"><div class="hint">加载中...</div></div>
      <div class="pager" id="userPager"></div>
    </div>
  </div>

  <!-- ★ v3.2.5：用户详情滑出面板 -->
  <div class="user-modal-mask" id="userModalMask" onclick="closeUserDetail()"></div>
  <div class="user-modal" id="userModal">
    <div class="um-head">
      <div class="uc-avatar" id="umAvatar">U</div>
      <div class="um-title">
        <b id="umUsername">用户</b>
        <span class="sub" id="umSub"></span>
      </div>
      <button class="um-close" onclick="closeUserDetail()" title="关闭">×</button>
    </div>
    <div class="um-body" id="umBody"><div class="hint">加载中...</div></div>
  </div>

  <div id="tab-site" class="tabpane" style="display:none">
    <div class="card">
      <div class="subtabs" id="siteSubtabs">
        <button class="stab active" data-stab="basic"  onclick="switchSiteTab('basic')">基础设置</button>
        <button class="stab" data-stab="seo"    onclick="switchSiteTab('seo')">SEO 设置</button>
        <button class="stab" data-stab="jump"   onclick="switchSiteTab('jump')">前台显示</button>
        <button class="stab" data-stab="pan"    onclick="switchSiteTab('pan')">添加网盘 · Cookie获取地址</button>
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
</div>

<div class="modal" id="panQrModal">
  <div class="box" style="max-width:380px;text-align:center">
    <h3 id="panQrTitle" style="margin-top:0">扫码登录</h3>
    <div id="panQrImg" style="display:flex;justify-content:center;align-items:center;min-height:216px;margin:10px 0"></div>
    <div id="panQrStatus" style="font-weight:600;min-height:22px"></div>
    <div class="hint" id="panQrHint" style="margin-top:6px"></div>
    <div class="row" style="margin-top:16px">
      <button type="button" class="ghost" onclick="panQrClose()">关闭</button>
      <button type="button" class="ghost" onclick="panQrRefresh()">刷新二维码</button>
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

<!-- 本地导入弹窗（v3.3.1：点击子标签弹出居中窗口） -->
<div class="local-overlay" id="localOverlay">
  <div class="local-box">
    <div class="local-modal-head">
      <h3 id="localModalTitle" style="margin:0">本地资源导入</h3>
      <button class="ghost sm" onclick="closeLocalModal()">✕</button>
    </div>
    <!-- 粘贴解析 -->
    <div id="local-paste" class="local-sub">
      <label>粘贴分享内容（夸克 / 百度 / 迅雷 / 磁力等网盘分享文本）</label>
      <textarea id="lpText" rows="8" placeholder="例如：&#10;「电影名」https://pan.quark.cn/s/xxx&#10;资源名 https://pan.baidu.com/s/xxx?pwd=abcd"></textarea>
      <div class="local-type-row">
        <label>默认分类：</label>
        <select id="lpType"></select>
        <span class="hint">无法识别分类的资源会落到这里</span>
      </div>
      <div class="local-actions">
        <button onclick="localParse()">🔍 识别解析</button>
      </div>
      <div id="lpFilterRow" class="local-filter" style="display:none"></div>
      <div id="lpResult" class="lpv"></div>
      <div id="lpImportRow" class="local-actions" style="display:none">
        <button id="lpCheckBtn" class="ghost" onclick="localCheckLinks('LP_ITEMS')">🔎 检测失效</button>
        <button onclick="localImportParsed()">📥 导入 <span id="lpCount">0</span> 条</button>
        <button class="ghost" onclick="localParseClear()">清空重来</button>
      </div>
    </div>
    <!-- 手动输入 -->
    <div id="local-manual" class="local-sub" style="display:none">
      <div class="form-row"><label>资源名称 *</label><input id="lmTitle" placeholder="例：顶楼 第五季 4K 全集"></div>
      <div class="form-row"><label>资源地址 *</label><input id="lmUrl" placeholder="网盘 / 磁力 / 任意链接，例：https://pan.quark.cn/s/xxx"></div>
      <div class="local-2col">
        <div class="form-row"><label>分类</label><select id="lmType"></select></div>
        <div class="form-row"><label>封面图片 URL</label><input id="lmPoster" placeholder="可选，封面图地址"></div>
      </div>
      <div class="form-row"><label>资源介绍</label><textarea id="lmOverview" rows="3" placeholder="可选，一两句话介绍这个资源"></textarea></div>
      <div class="form-row"><label>关键字</label><input id="lmGenres" placeholder="可选，逗号分隔，如 电影, 高清"></div>
      <div class="local-actions">
        <button onclick="localManualSubmit()">📥 提交入库</button>
      </div>
    </div>
    <!-- Excel 导入 -->
    <div id="local-excel" class="local-sub" style="display:none">
      <div class="ext-note" style="margin:0 0 14px">📋 <b>表格列顺序</b>：第 1 列 资源名称 · 第 2 列 分享链接 · 第 3 列 分类(可空) · 第 4 列 账号(可空) · 第 5 列 简介(可空)。<br>优先使用 .xlsx（也支持 .csv）；旧版 .xls 二进制格式请先在 Excel 里「另存为 .xlsx」。</div>
      <div class="form-row"><label>选择文件</label><input type="file" id="leFile" accept=".xlsx,.xls,.csv"></div>
      <div class="local-type-row" style="margin-top:0">
        <label>默认分类：</label>
        <select id="leType"></select>
        <span class="hint">表格第 3 列为空时使用</span>
      </div>
      <div class="local-actions">
        <button onclick="localExcelParse()">🔍 解析文件</button>
      </div>
      <div id="leFilterRow" class="local-filter" style="display:none"></div>
      <div id="leResult" class="lpv"></div>
      <div id="leImportRow" class="local-actions" style="display:none">
        <button id="leCheckBtn" class="ghost" onclick="localCheckLinks('LE_ITEMS')">🔎 检测失效</button>
        <button onclick="localExcelImport()">📥 导入 <span id="leCount">0</span> 条</button>
        <button class="ghost" onclick="localExcelClear()">清空重来</button>
      </div>
    </div>
  </div>
</div>

<script>
/* qrcode.min.js 1.0.0 (davidshimjs/qrcodejs, MIT) —— 本地内嵌，扫码登录用；不依赖任何外部 CDN */
var QRCode;!function(){function a(a){this.mode=c.MODE_8BIT_BYTE,this.data=a,this.parsedData=[];for(var b=[],d=0,e=this.data.length;e>d;d++){var f=this.data.charCodeAt(d);f>65536?(b[0]=240|(1835008&f)>>>18,b[1]=128|(258048&f)>>>12,b[2]=128|(4032&f)>>>6,b[3]=128|63&f):f>2048?(b[0]=224|(61440&f)>>>12,b[1]=128|(4032&f)>>>6,b[2]=128|63&f):f>128?(b[0]=192|(1984&f)>>>6,b[1]=128|63&f):b[0]=f,this.parsedData=this.parsedData.concat(b)}this.parsedData.length!=this.data.length&&(this.parsedData.unshift(191),this.parsedData.unshift(187),this.parsedData.unshift(239))}function b(a,b){this.typeNumber=a,this.errorCorrectLevel=b,this.modules=null,this.moduleCount=0,this.dataCache=null,this.dataList=[]}function i(a,b){if(void 0==a.length)throw new Error(a.length+"/"+b);for(var c=0;c<a.length&&0==a[c];)c++;this.num=new Array(a.length-c+b);for(var d=0;d<a.length-c;d++)this.num[d]=a[d+c]}function j(a,b){this.totalCount=a,this.dataCount=b}function k(){this.buffer=[],this.length=0}function m(){return"undefined"!=typeof CanvasRenderingContext2D}function n(){var a=!1,b=navigator.userAgent;return/android/i.test(b)&&(a=!0,aMat=b.toString().match(/android ([0-9]\.[0-9])/i),aMat&&aMat[1]&&(a=parseFloat(aMat[1]))),a}function r(a,b){for(var c=1,e=s(a),f=0,g=l.length;g>=f;f++){var h=0;switch(b){case d.L:h=l[f][0];break;case d.M:h=l[f][1];break;case d.Q:h=l[f][2];break;case d.H:h=l[f][3]}if(h>=e)break;c++}if(c>l.length)throw new Error("Too long data");return c}function s(a){var b=encodeURI(a).toString().replace(/\%[0-9a-fA-F]{2}/g,"a");return b.length+(b.length!=a?3:0)}a.prototype={getLength:function(){return this.parsedData.length},write:function(a){for(var b=0,c=this.parsedData.length;c>b;b++)a.put(this.parsedData[b],8)}},b.prototype={addData:function(b){var c=new a(b);this.dataList.push(c),this.dataCache=null},isDark:function(a,b){if(0>a||this.moduleCount<=a||0>b||this.moduleCount<=b)throw new Error(a+","+b);return this.modules[a][b]},getModuleCount:function(){return this.moduleCount},make:function(){this.makeImpl(!1,this.getBestMaskPattern())},makeImpl:function(a,c){this.moduleCount=4*this.typeNumber+17,this.modules=new Array(this.moduleCount);for(var d=0;d<this.moduleCount;d++){this.modules[d]=new Array(this.moduleCount);for(var e=0;e<this.moduleCount;e++)this.modules[d][e]=null}this.setupPositionProbePattern(0,0),this.setupPositionProbePattern(this.moduleCount-7,0),this.setupPositionProbePattern(0,this.moduleCount-7),this.setupPositionAdjustPattern(),this.setupTimingPattern(),this.setupTypeInfo(a,c),this.typeNumber>=7&&this.setupTypeNumber(a),null==this.dataCache&&(this.dataCache=b.createData(this.typeNumber,this.errorCorrectLevel,this.dataList)),this.mapData(this.dataCache,c)},setupPositionProbePattern:function(a,b){for(var c=-1;7>=c;c++)if(!(-1>=a+c||this.moduleCount<=a+c))for(var d=-1;7>=d;d++)-1>=b+d||this.moduleCount<=b+d||(this.modules[a+c][b+d]=c>=0&&6>=c&&(0==d||6==d)||d>=0&&6>=d&&(0==c||6==c)||c>=2&&4>=c&&d>=2&&4>=d?!0:!1)},getBestMaskPattern:function(){for(var a=0,b=0,c=0;8>c;c++){this.makeImpl(!0,c);var d=f.getLostPoint(this);(0==c||a>d)&&(a=d,b=c)}return b},createMovieClip:function(a,b,c){var d=a.createEmptyMovieClip(b,c),e=1;this.make();for(var f=0;f<this.modules.length;f++)for(var g=f*e,h=0;h<this.modules[f].length;h++){var i=h*e,j=this.modules[f][h];j&&(d.beginFill(0,100),d.moveTo(i,g),d.lineTo(i+e,g),d.lineTo(i+e,g+e),d.lineTo(i,g+e),d.endFill())}return d},setupTimingPattern:function(){for(var a=8;a<this.moduleCount-8;a++)null==this.modules[a][6]&&(this.modules[a][6]=0==a%2);for(var b=8;b<this.moduleCount-8;b++)null==this.modules[6][b]&&(this.modules[6][b]=0==b%2)},setupPositionAdjustPattern:function(){for(var a=f.getPatternPosition(this.typeNumber),b=0;b<a.length;b++)for(var c=0;c<a.length;c++){var d=a[b],e=a[c];if(null==this.modules[d][e])for(var g=-2;2>=g;g++)for(var h=-2;2>=h;h++)this.modules[d+g][e+h]=-2==g||2==g||-2==h||2==h||0==g&&0==h?!0:!1}},setupTypeNumber:function(a){for(var b=f.getBCHTypeNumber(this.typeNumber),c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[Math.floor(c/3)][c%3+this.moduleCount-8-3]=d}for(var c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[c%3+this.moduleCount-8-3][Math.floor(c/3)]=d}},setupTypeInfo:function(a,b){for(var c=this.errorCorrectLevel<<3|b,d=f.getBCHTypeInfo(c),e=0;15>e;e++){var g=!a&&1==(1&d>>e);6>e?this.modules[e][8]=g:8>e?this.modules[e+1][8]=g:this.modules[this.moduleCount-15+e][8]=g}for(var e=0;15>e;e++){var g=!a&&1==(1&d>>e);8>e?this.modules[8][this.moduleCount-e-1]=g:9>e?this.modules[8][15-e-1+1]=g:this.modules[8][15-e-1]=g}this.modules[this.moduleCount-8][8]=!a},mapData:function(a,b){for(var c=-1,d=this.moduleCount-1,e=7,g=0,h=this.moduleCount-1;h>0;h-=2)for(6==h&&h--;;){for(var i=0;2>i;i++)if(null==this.modules[d][h-i]){var j=!1;g<a.length&&(j=1==(1&a[g]>>>e));var k=f.getMask(b,d,h-i);k&&(j=!j),this.modules[d][h-i]=j,e--,-1==e&&(g++,e=7)}if(d+=c,0>d||this.moduleCount<=d){d-=c,c=-c;break}}}},b.PAD0=236,b.PAD1=17,b.createData=function(a,c,d){for(var e=j.getRSBlocks(a,c),g=new k,h=0;h<d.length;h++){var i=d[h];g.put(i.mode,4),g.put(i.getLength(),f.getLengthInBits(i.mode,a)),i.write(g)}for(var l=0,h=0;h<e.length;h++)l+=e[h].dataCount;if(g.getLengthInBits()>8*l)throw new Error("code length overflow. ("+g.getLengthInBits()+">"+8*l+")");for(g.getLengthInBits()+4<=8*l&&g.put(0,4);0!=g.getLengthInBits()%8;)g.putBit(!1);for(;;){if(g.getLengthInBits()>=8*l)break;if(g.put(b.PAD0,8),g.getLengthInBits()>=8*l)break;g.put(b.PAD1,8)}return b.createBytes(g,e)},b.createBytes=function(a,b){for(var c=0,d=0,e=0,g=new Array(b.length),h=new Array(b.length),j=0;j<b.length;j++){var k=b[j].dataCount,l=b[j].totalCount-k;d=Math.max(d,k),e=Math.max(e,l),g[j]=new Array(k);for(var m=0;m<g[j].length;m++)g[j][m]=255&a.buffer[m+c];c+=k;var n=f.getErrorCorrectPolynomial(l),o=new i(g[j],n.getLength()-1),p=o.mod(n);h[j]=new Array(n.getLength()-1);for(var m=0;m<h[j].length;m++){var q=m+p.getLength()-h[j].length;h[j][m]=q>=0?p.get(q):0}}for(var r=0,m=0;m<b.length;m++)r+=b[m].totalCount;for(var s=new Array(r),t=0,m=0;d>m;m++)for(var j=0;j<b.length;j++)m<g[j].length&&(s[t++]=g[j][m]);for(var m=0;e>m;m++)for(var j=0;j<b.length;j++)m<h[j].length&&(s[t++]=h[j][m]);return s};for(var c={MODE_NUMBER:1,MODE_ALPHA_NUM:2,MODE_8BIT_BYTE:4,MODE_KANJI:8},d={L:1,M:0,Q:3,H:2},e={PATTERN000:0,PATTERN001:1,PATTERN010:2,PATTERN011:3,PATTERN100:4,PATTERN101:5,PATTERN110:6,PATTERN111:7},f={PATTERN_POSITION_TABLE:[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90],[6,28,50,72,94],[6,26,50,74,98],[6,30,54,78,102],[6,28,54,80,106],[6,32,58,84,110],[6,30,58,86,114],[6,34,62,90,118],[6,26,50,74,98,122],[6,30,54,78,102,126],[6,26,52,78,104,130],[6,30,56,82,108,134],[6,34,60,86,112,138],[6,30,58,86,114,142],[6,34,62,90,118,146],[6,30,54,78,102,126,150],[6,24,50,76,102,128,154],[6,28,54,80,106,132,158],[6,32,58,84,110,136,162],[6,26,54,82,110,138,166],[6,30,58,86,114,142,170]],G15:1335,G18:7973,G15_MASK:21522,getBCHTypeInfo:function(a){for(var b=a<<10;f.getBCHDigit(b)-f.getBCHDigit(f.G15)>=0;)b^=f.G15<<f.getBCHDigit(b)-f.getBCHDigit(f.G15);return(a<<10|b)^f.G15_MASK},getBCHTypeNumber:function(a){for(var b=a<<12;f.getBCHDigit(b)-f.getBCHDigit(f.G18)>=0;)b^=f.G18<<f.getBCHDigit(b)-f.getBCHDigit(f.G18);return a<<12|b},getBCHDigit:function(a){for(var b=0;0!=a;)b++,a>>>=1;return b},getPatternPosition:function(a){return f.PATTERN_POSITION_TABLE[a-1]},getMask:function(a,b,c){switch(a){case e.PATTERN000:return 0==(b+c)%2;case e.PATTERN001:return 0==b%2;case e.PATTERN010:return 0==c%3;case e.PATTERN011:return 0==(b+c)%3;case e.PATTERN100:return 0==(Math.floor(b/2)+Math.floor(c/3))%2;case e.PATTERN101:return 0==b*c%2+b*c%3;case e.PATTERN110:return 0==(b*c%2+b*c%3)%2;case e.PATTERN111:return 0==(b*c%3+(b+c)%2)%2;default:throw new Error("bad maskPattern:"+a)}},getErrorCorrectPolynomial:function(a){for(var b=new i([1],0),c=0;a>c;c++)b=b.multiply(new i([1,g.gexp(c)],0));return b},getLengthInBits:function(a,b){if(b>=1&&10>b)switch(a){case c.MODE_NUMBER:return 10;case c.MODE_ALPHA_NUM:return 9;case c.MODE_8BIT_BYTE:return 8;case c.MODE_KANJI:return 8;default:throw new Error("mode:"+a)}else if(27>b)switch(a){case c.MODE_NUMBER:return 12;case c.MODE_ALPHA_NUM:return 11;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 10;default:throw new Error("mode:"+a)}else{if(!(41>b))throw new Error("type:"+b);switch(a){case c.MODE_NUMBER:return 14;case c.MODE_ALPHA_NUM:return 13;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 12;default:throw new Error("mode:"+a)}}},getLostPoint:function(a){for(var b=a.getModuleCount(),c=0,d=0;b>d;d++)for(var e=0;b>e;e++){for(var f=0,g=a.isDark(d,e),h=-1;1>=h;h++)if(!(0>d+h||d+h>=b))for(var i=-1;1>=i;i++)0>e+i||e+i>=b||(0!=h||0!=i)&&g==a.isDark(d+h,e+i)&&f++;f>5&&(c+=3+f-5)}for(var d=0;b-1>d;d++)for(var e=0;b-1>e;e++){var j=0;a.isDark(d,e)&&j++,a.isDark(d+1,e)&&j++,a.isDark(d,e+1)&&j++,a.isDark(d+1,e+1)&&j++,(0==j||4==j)&&(c+=3)}for(var d=0;b>d;d++)for(var e=0;b-6>e;e++)a.isDark(d,e)&&!a.isDark(d,e+1)&&a.isDark(d,e+2)&&a.isDark(d,e+3)&&a.isDark(d,e+4)&&!a.isDark(d,e+5)&&a.isDark(d,e+6)&&(c+=40);for(var e=0;b>e;e++)for(var d=0;b-6>d;d++)a.isDark(d,e)&&!a.isDark(d+1,e)&&a.isDark(d+2,e)&&a.isDark(d+3,e)&&a.isDark(d+4,e)&&!a.isDark(d+5,e)&&a.isDark(d+6,e)&&(c+=40);for(var k=0,e=0;b>e;e++)for(var d=0;b>d;d++)a.isDark(d,e)&&k++;var l=Math.abs(100*k/b/b-50)/5;return c+=10*l}},g={glog:function(a){if(1>a)throw new Error("glog("+a+")");return g.LOG_TABLE[a]},gexp:function(a){for(;0>a;)a+=255;for(;a>=256;)a-=255;return g.EXP_TABLE[a]},EXP_TABLE:new Array(256),LOG_TABLE:new Array(256)},h=0;8>h;h++)g.EXP_TABLE[h]=1<<h;for(var h=8;256>h;h++)g.EXP_TABLE[h]=g.EXP_TABLE[h-4]^g.EXP_TABLE[h-5]^g.EXP_TABLE[h-6]^g.EXP_TABLE[h-8];for(var h=0;255>h;h++)g.LOG_TABLE[g.EXP_TABLE[h]]=h;i.prototype={get:function(a){return this.num[a]},getLength:function(){return this.num.length},multiply:function(a){for(var b=new Array(this.getLength()+a.getLength()-1),c=0;c<this.getLength();c++)for(var d=0;d<a.getLength();d++)b[c+d]^=g.gexp(g.glog(this.get(c))+g.glog(a.get(d)));return new i(b,0)},mod:function(a){if(this.getLength()-a.getLength()<0)return this;for(var b=g.glog(this.get(0))-g.glog(a.get(0)),c=new Array(this.getLength()),d=0;d<this.getLength();d++)c[d]=this.get(d);for(var d=0;d<a.getLength();d++)c[d]^=g.gexp(g.glog(a.get(d))+b);return new i(c,0).mod(a)}},j.RS_BLOCK_TABLE=[[1,26,19],[1,26,16],[1,26,13],[1,26,9],[1,44,34],[1,44,28],[1,44,22],[1,44,16],[1,70,55],[1,70,44],[2,35,17],[2,35,13],[1,100,80],[2,50,32],[2,50,24],[4,25,9],[1,134,108],[2,67,43],[2,33,15,2,34,16],[2,33,11,2,34,12],[2,86,68],[4,43,27],[4,43,19],[4,43,15],[2,98,78],[4,49,31],[2,32,14,4,33,15],[4,39,13,1,40,14],[2,121,97],[2,60,38,2,61,39],[4,40,18,2,41,19],[4,40,14,2,41,15],[2,146,116],[3,58,36,2,59,37],[4,36,16,4,37,17],[4,36,12,4,37,13],[2,86,68,2,87,69],[4,69,43,1,70,44],[6,43,19,2,44,20],[6,43,15,2,44,16],[4,101,81],[1,80,50,4,81,51],[4,50,22,4,51,23],[3,36,12,8,37,13],[2,116,92,2,117,93],[6,58,36,2,59,37],[4,46,20,6,47,21],[7,42,14,4,43,15],[4,133,107],[8,59,37,1,60,38],[8,44,20,4,45,21],[12,33,11,4,34,12],[3,145,115,1,146,116],[4,64,40,5,65,41],[11,36,16,5,37,17],[11,36,12,5,37,13],[5,109,87,1,110,88],[5,65,41,5,66,42],[5,54,24,7,55,25],[11,36,12],[5,122,98,1,123,99],[7,73,45,3,74,46],[15,43,19,2,44,20],[3,45,15,13,46,16],[1,135,107,5,136,108],[10,74,46,1,75,47],[1,50,22,15,51,23],[2,42,14,17,43,15],[5,150,120,1,151,121],[9,69,43,4,70,44],[17,50,22,1,51,23],[2,42,14,19,43,15],[3,141,113,4,142,114],[3,70,44,11,71,45],[17,47,21,4,48,22],[9,39,13,16,40,14],[3,135,107,5,136,108],[3,67,41,13,68,42],[15,54,24,5,55,25],[15,43,15,10,44,16],[4,144,116,4,145,117],[17,68,42],[17,50,22,6,51,23],[19,46,16,6,47,17],[2,139,111,7,140,112],[17,74,46],[7,54,24,16,55,25],[34,37,13],[4,151,121,5,152,122],[4,75,47,14,76,48],[11,54,24,14,55,25],[16,45,15,14,46,16],[6,147,117,4,148,118],[6,73,45,14,74,46],[11,54,24,16,55,25],[30,46,16,2,47,17],[8,132,106,4,133,107],[8,75,47,13,76,48],[7,54,24,22,55,25],[22,45,15,13,46,16],[10,142,114,2,143,115],[19,74,46,4,75,47],[28,50,22,6,51,23],[33,46,16,4,47,17],[8,152,122,4,153,123],[22,73,45,3,74,46],[8,53,23,26,54,24],[12,45,15,28,46,16],[3,147,117,10,148,118],[3,73,45,23,74,46],[4,54,24,31,55,25],[11,45,15,31,46,16],[7,146,116,7,147,117],[21,73,45,7,74,46],[1,53,23,37,54,24],[19,45,15,26,46,16],[5,145,115,10,146,116],[19,75,47,10,76,48],[15,54,24,25,55,25],[23,45,15,25,46,16],[13,145,115,3,146,116],[2,74,46,29,75,47],[42,54,24,1,55,25],[23,45,15,28,46,16],[17,145,115],[10,74,46,23,75,47],[10,54,24,35,55,25],[19,45,15,35,46,16],[17,145,115,1,146,116],[14,74,46,21,75,47],[29,54,24,19,55,25],[11,45,15,46,46,16],[13,145,115,6,146,116],[14,74,46,23,75,47],[44,54,24,7,55,25],[59,46,16,1,47,17],[12,151,121,7,152,122],[12,75,47,26,76,48],[39,54,24,14,55,25],[22,45,15,41,46,16],[6,151,121,14,152,122],[6,75,47,34,76,48],[46,54,24,10,55,25],[2,45,15,64,46,16],[17,152,122,4,153,123],[29,74,46,14,75,47],[49,54,24,10,55,25],[24,45,15,46,46,16],[4,152,122,18,153,123],[13,74,46,32,75,47],[48,54,24,14,55,25],[42,45,15,32,46,16],[20,147,117,4,148,118],[40,75,47,7,76,48],[43,54,24,22,55,25],[10,45,15,67,46,16],[19,148,118,6,149,119],[18,75,47,31,76,48],[34,54,24,34,55,25],[20,45,15,61,46,16]],j.getRSBlocks=function(a,b){var c=j.getRsBlockTable(a,b);if(void 0==c)throw new Error("bad rs block @ typeNumber:"+a+"/errorCorrectLevel:"+b);for(var d=c.length/3,e=[],f=0;d>f;f++)for(var g=c[3*f+0],h=c[3*f+1],i=c[3*f+2],k=0;g>k;k++)e.push(new j(h,i));return e},j.getRsBlockTable=function(a,b){switch(b){case d.L:return j.RS_BLOCK_TABLE[4*(a-1)+0];case d.M:return j.RS_BLOCK_TABLE[4*(a-1)+1];case d.Q:return j.RS_BLOCK_TABLE[4*(a-1)+2];case d.H:return j.RS_BLOCK_TABLE[4*(a-1)+3];default:return void 0}},k.prototype={get:function(a){var b=Math.floor(a/8);return 1==(1&this.buffer[b]>>>7-a%8)},put:function(a,b){for(var c=0;b>c;c++)this.putBit(1==(1&a>>>b-c-1))},getLengthInBits:function(){return this.length},putBit:function(a){var b=Math.floor(this.length/8);this.buffer.length<=b&&this.buffer.push(0),a&&(this.buffer[b]|=128>>>this.length%8),this.length++}};var l=[[17,14,11,7],[32,26,20,14],[53,42,32,24],[78,62,46,34],[106,84,60,44],[134,106,74,58],[154,122,86,64],[192,152,108,84],[230,180,130,98],[271,213,151,119],[321,251,177,137],[367,287,203,155],[425,331,241,177],[458,362,258,194],[520,412,292,220],[586,450,322,250],[644,504,364,280],[718,560,394,310],[792,624,442,338],[858,666,482,382],[929,711,509,403],[1003,779,565,439],[1091,857,611,461],[1171,911,661,511],[1273,997,715,535],[1367,1059,751,593],[1465,1125,805,625],[1528,1190,868,658],[1628,1264,908,698],[1732,1370,982,742],[1840,1452,1030,790],[1952,1538,1112,842],[2068,1628,1168,898],[2188,1722,1228,958],[2303,1809,1283,983],[2431,1911,1351,1051],[2563,1989,1423,1093],[2699,2099,1499,1139],[2809,2213,1579,1219],[2953,2331,1663,1273]],o=function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){function g(a,b){var c=document.createElementNS("http://www.w3.org/2000/svg",a);for(var d in b)b.hasOwnProperty(d)&&c.setAttribute(d,b[d]);return c}var b=this._htOption,c=this._el,d=a.getModuleCount();Math.floor(b.width/d),Math.floor(b.height/d),this.clear();var h=g("svg",{viewBox:"0 0 "+String(d)+" "+String(d),width:"100%",height:"100%",fill:b.colorLight});h.setAttributeNS("http://www.w3.org/2000/xmlns/","xmlns:xlink","http://www.w3.org/1999/xlink"),c.appendChild(h),h.appendChild(g("rect",{fill:b.colorDark,width:"1",height:"1",id:"template"}));for(var i=0;d>i;i++)for(var j=0;d>j;j++)if(a.isDark(i,j)){var k=g("use",{x:String(i),y:String(j)});k.setAttributeNS("http://www.w3.org/1999/xlink","href","#template"),h.appendChild(k)}},a.prototype.clear=function(){for(;this._el.hasChildNodes();)this._el.removeChild(this._el.lastChild)},a}(),p="svg"===document.documentElement.tagName.toLowerCase(),q=p?o:m()?function(){function a(){this._elImage.src=this._elCanvas.toDataURL("image/png"),this._elImage.style.display="block",this._elCanvas.style.display="none"}function d(a,b){var c=this;if(c._fFail=b,c._fSuccess=a,null===c._bSupportDataURI){var d=document.createElement("img"),e=function(){c._bSupportDataURI=!1,c._fFail&&_fFail.call(c)},f=function(){c._bSupportDataURI=!0,c._fSuccess&&c._fSuccess.call(c)};return d.onabort=e,d.onerror=e,d.onload=f,d.src="data:image/gif;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==",void 0}c._bSupportDataURI===!0&&c._fSuccess?c._fSuccess.call(c):c._bSupportDataURI===!1&&c._fFail&&c._fFail.call(c)}if(this._android&&this._android<=2.1){var b=1/window.devicePixelRatio,c=CanvasRenderingContext2D.prototype.drawImage;CanvasRenderingContext2D.prototype.drawImage=function(a,d,e,f,g,h,i,j){if("nodeName"in a&&/img/i.test(a.nodeName))for(var l=arguments.length-1;l>=1;l--)arguments[l]=arguments[l]*b;else"undefined"==typeof j&&(arguments[1]*=b,arguments[2]*=b,arguments[3]*=b,arguments[4]*=b);c.apply(this,arguments)}}var e=function(a,b){this._bIsPainted=!1,this._android=n(),this._htOption=b,this._elCanvas=document.createElement("canvas"),this._elCanvas.width=b.width,this._elCanvas.height=b.height,a.appendChild(this._elCanvas),this._el=a,this._oContext=this._elCanvas.getContext("2d"),this._bIsPainted=!1,this._elImage=document.createElement("img"),this._elImage.style.display="none",this._el.appendChild(this._elImage),this._bSupportDataURI=null};return e.prototype.draw=function(a){var b=this._elImage,c=this._oContext,d=this._htOption,e=a.getModuleCount(),f=d.width/e,g=d.height/e,h=Math.round(f),i=Math.round(g);b.style.display="none",this.clear();for(var j=0;e>j;j++)for(var k=0;e>k;k++){var l=a.isDark(j,k),m=k*f,n=j*g;c.strokeStyle=l?d.colorDark:d.colorLight,c.lineWidth=1,c.fillStyle=l?d.colorDark:d.colorLight,c.fillRect(m,n,f,g),c.strokeRect(Math.floor(m)+.5,Math.floor(n)+.5,h,i),c.strokeRect(Math.ceil(m)-.5,Math.ceil(n)-.5,h,i)}this._bIsPainted=!0},e.prototype.makeImage=function(){this._bIsPainted&&d.call(this,a)},e.prototype.isPainted=function(){return this._bIsPainted},e.prototype.clear=function(){this._oContext.clearRect(0,0,this._elCanvas.width,this._elCanvas.height),this._bIsPainted=!1},e.prototype.round=function(a){return a?Math.floor(1e3*a)/1e3:a},e}():function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){for(var b=this._htOption,c=this._el,d=a.getModuleCount(),e=Math.floor(b.width/d),f=Math.floor(b.height/d),g=['<table style="border:0;border-collapse:collapse;">'],h=0;d>h;h++){g.push("<tr>");for(var i=0;d>i;i++)g.push('<td style="border:0;border-collapse:collapse;padding:0;margin:0;width:'+e+"px;height:"+f+"px;background-color:"+(a.isDark(h,i)?b.colorDark:b.colorLight)+';"></td>');g.push("</tr>")}g.push("</table>"),c.innerHTML=g.join("");var j=c.childNodes[0],k=(b.width-j.offsetWidth)/2,l=(b.height-j.offsetHeight)/2;k>0&&l>0&&(j.style.margin=l+"px "+k+"px")},a.prototype.clear=function(){this._el.innerHTML=""},a}();QRCode=function(a,b){if(this._htOption={width:256,height:256,typeNumber:4,colorDark:"#000000",colorLight:"#ffffff",correctLevel:d.H},"string"==typeof b&&(b={text:b}),b)for(var c in b)this._htOption[c]=b[c];"string"==typeof a&&(a=document.getElementById(a)),this._android=n(),this._el=a,this._oQRCode=null,this._oDrawing=new q(this._el,this._htOption),this._htOption.text&&this.makeCode(this._htOption.text)},QRCode.prototype.makeCode=function(a){this._oQRCode=new b(r(a,this._htOption.correctLevel),this._htOption.correctLevel),this._oQRCode.addData(a),this._oQRCode.make(),this._el.title=a,this._oDrawing.draw(this._oQRCode),this.makeImage()},QRCode.prototype.makeImage=function(){"function"==typeof this._oDrawing.makeImage&&(!this._android||this._android>=3)&&this._oDrawing.makeImage()},QRCode.prototype.clear=function(){this._oDrawing.clear()},QRCode.CorrectLevel=d}();
</script>

<script>
// 站点根（兼容 域名根目录 与 子目录部署；去掉结尾 /admin）
// 注意：不能写成 (x || '/')，否则根目录部署时会拼出 "//api/v1/..."（协议相对，会打到别的域名）
const API = (function(){
  let p = location.pathname;
  const adminSeg = '/' + ((document.body && document.body.getAttribute('data-admin-dir')) || 'admin');
  const at = p.lastIndexOf(adminSeg);
  if (at >= 0) p = p.slice(0, at);
  return p.replace(/\/+$/, '');
})();
let curPage = 1, editId = null, curCat = '', curTag = '', SCHEMA = null, PAN_LIST = ['夸克','迅雷','光鸭','百度','UC'];
let PAN_TYPES = {1:'夸克',2:'百度',3:'迅雷',4:'阿里',5:'123网盘',6:'115',7:'UC',8:'蓝奏',9:'磁力',10:'天翼',11:'和彩云',12:'光鸭',baidu:'百度',aliyun:'阿里',quark:'夸克',guangya:'光鸭',tianyi:'天翼',uc:'UC',mobile:'移动','115':'115',pikpak:'PikPak',xunlei:'迅雷','123':'123网盘',magnet:'磁力',ed2k:'电驴',other:'其他'};
let LINK_STATUS = {}; // url => {ok:bool, msg:string, code:int} | null（未检测）
let editExtraBase = {}; // 编辑前 extra 的完整快照 —— saveEdit 用它保留 cast/trailer/videos 等非 schema key

/**
 * 统一的接口调用：**永不抛异常**。
 * 以前是 fetch(...).then(r=>r.json())——一旦服务端返回的是 HTML
 * （PHP 致命错误页 / 502 / 504 网关超时），r.json() 会 reject，
 * 调用方的 await 直接中断，按钮看起来就是"点了没反应"（只在控制台留红字）。
 * 现在任何异常/非 JSON 一律转成 {success:false, error:"…"}，页面必定给出提示。
 */
async function api(path, opts={}){
  var method = (opts.method || 'GET').toUpperCase();
  var headers = Object.assign({}, opts.headers||{});
  if(method !== 'GET'){
    var isForm = (typeof FormData !== 'undefined' && opts.body instanceof FormData);
    if(!isForm){
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      /* 非字符串（对象）body 自动 JSON 化；已传字符串 / FormData 则不处理 */
      if(opts.body !== undefined && opts.body !== null && typeof opts.body !== 'string'){
        opts.body = JSON.stringify(opts.body);
      }
    }
    /* 写操作自动注入 CSRF 令牌（X-CSRF-Token），后端 api/unified.php 强制校验 */
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta){ headers['X-CSRF-Token'] = csrfMeta.getAttribute('content') || ''; }
  }
  opts.headers = headers;
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
let TAG_ACTIVE='';
async function renderTags(force){
  const btn = document.getElementById('tagRefreshBtn');
  if(force && btn){ btn.classList.add('loading'); btn.disabled = true; }
  try {
    const d = await api('/api/v1/admin/tags' + (force ? '?force=1' : ''));
    if(!d.success){ alert(d.error||'加载失败'); return; }
    const tags = d.data.items||[];
    /* ★ v4.4.1：统计行 = 标签 + 导演 + 演员 全量（与页面卡片总数一致，用户反馈「不止 N 个」） */
    TAG_COUNTS.tags = tags.length; updateTagStatLine(force);
    /* v3.6.8：剧情不再单独分组；人名标签已由后端过滤（只在热门导演/演员区块出现） */
    let h='';
    for(const t of tags){
      h+='<div class="tag-card" onclick="openTagDetail(\''+t.tag.replace(/'/g,"\\'")+'\')">'
       +'<span class="tag-name">'+esc(t.tag)+'</span>'
       +'<span class="tag-count">'+t.items+'</span>'
       +'</div>';
    }
    if(!tags.length) h='<div class="tag-list-empty">暂无常规标签，请先在条目中添加 genres 标签</div>';
    document.getElementById('tagGrid').innerHTML=h;
  } finally {
    if(btn){ btn.classList.remove('loading'); btn.disabled = false; }
  }
}
/* ★ v4.4.1：标签页统计行 —— 三类卡片数量合计 */
const TAG_COUNTS = { tags: 0, dir: 0, act: 0 };
function updateTagStatLine(force){
  const el = document.getElementById('tagStatLine');
  if(!el) return;
  el.textContent = '共 '+(TAG_COUNTS.tags + TAG_COUNTS.dir + TAG_COUNTS.act)+' 项'
    +'（标签 '+TAG_COUNTS.tags+' · 导演 '+TAG_COUNTS.dir+' · 演员 '+TAG_COUNTS.act+'）'
    + (force ? ' · 已强制刷新' : '');
}
/* ★ v3.6.6：热门导演/演员 —— 与标签网格同款卡片（名字 + 数量徽章）
   ★ v4.4.1：恢复本区块（用户确认保留）；点人名改为与标签一致的就地展开（不再跳条目管理） */
async function renderPeople(){
  try {
    const d = await api('/api/v1/admin/hot-people');
    if(!d.success){ return; }
    const hp = d.data || {directors:[],actors:[]};
    const fill = (listId, rowId, arr, kind) => {
      const list = document.getElementById(listId), row = document.getElementById(rowId);
      if(!list || !row){ return; }
      if(!arr || !arr.length){ row.style.display='none'; list.innerHTML=''; return; }
      let h='';
      for(const p of arr){
        const n = (p.name||'').replace(/'/g,"\\'");
        h+='<div class="tag-card" style="cursor:pointer" title="'+esc(n)+'：'+(p.items||0)+' 部 · 累计点击 '+(p.clicks||0)+' 次"'
         +' onclick="openPersonItems(\''+n+'\',\''+kind+'\')">'
         +'<span class="tag-name">'+esc(n)+'</span>'
         +'<span class="tag-count">'+(p.items||0)+'</span>'
         +'</div>';
      }
      list.innerHTML=h;
      row.style.display='block';
    };
    fill('peopleListDir','peopleRowDir', hp.directors, 'director');
    fill('peopleListAct','peopleRowAct', hp.actors, 'actor');
    TAG_COUNTS.dir = (hp.directors||[]).length;
    TAG_COUNTS.act = (hp.actors||[]).length;
    updateTagStatLine();
    const e=document.getElementById('peopleEmpty');
    if(e){ e.style.display = ((hp.directors||[]).length || (hp.actors||[]).length) ? 'none' : 'block'; }
  } catch(_e){ /* 人物区块加载失败不影响标签页主体 */ }
}
/* ★ v4.4.1：点导演/演员 → 与标签一致的就地展开作品列表（此前跳条目管理，用户判定为错误行为） */
function openPersonItems(name, kind){
  openTagDetail(name, kind || 'actor');
}
async function openTagDetail(tag, kind){
  TAG_ACTIVE=tag;
  /* ★ v4.3.13：进入某个标签只看该标签 —— 常规标签整组、热门导演/演员一并隐藏，返回时恢复 */
  document.getElementById('tagGroupWrap').style.display='none';
  document.getElementById('peopleCloud').style.display='none';
  document.getElementById('tagGrid').style.display='none';
  document.querySelector('.tag-hint').style.display='none';
  document.getElementById('tagDetailCard').style.display='block';
  /* ★ v4.4.1：标题区分标签 / 导演 / 演员 */
  document.getElementById('tagDetailTitle').textContent =
    kind === 'director' ? ('导演「'+tag+'」的作品')
    : kind === 'actor'  ? ('演员「'+tag+'」的作品')
    : ('标签「'+tag+'」下的条目');
  document.querySelector('.tag-hint').style.display='none';
  document.getElementById('tagDetailCard').style.display='block';
  document.getElementById('tagDetailTitle').textContent='标签「'+tag+'」下的条目';
  const d=await api('/api/v1/admin/items?page=1&type=&q=&tag='+encodeURIComponent(tag));
  if(!d.success){ alert(d.error||'加载失败'); return; }
  document.getElementById('tagDetailHint').textContent='共 '+d.data.total+' 条';
  let h='';
  const items=d.data.items||[];
  if(!items.length){ h='<div class="tag-list-empty">该标签下暂无条目</div>'; }
  else{
    for(const it of items){
      const lb=SCHEMA?(SCHEMA.labels[it.type]||it.type):it.type;
      const sub=[esc(it.original_title||''),lb,it.year?String(it.year):''].filter(Boolean).join(' · ');
      h+='<div class="tag-item" onclick="window.open(\'/uisc/'+it.id+'.html\',\'_blank\')" title="'+esc(it.title||'')+'">'
       +(it.poster?'<img class="thumb" src="'+it.poster+'">':'')
       +'<div class="ti-txt"><b>'+esc(it.title||'')+'</b><span class="hint">'+sub+'</span></div>'
       +'</div>';
    }
  }
  document.getElementById('tagItemList').innerHTML=h;
}
function closeTagDetail(){
  TAG_ACTIVE='';
  document.getElementById('tagGroupWrap').style.display='';
  document.getElementById('peopleCloud').style.display='';
  document.getElementById('tagGrid').style.display='grid';
  document.querySelector('.tag-hint').style.display='block';
  document.getElementById('tagDetailCard').style.display='none';
}
function goItemsWithTag(){
  if(!TAG_ACTIVE) return;
  curTag=TAG_ACTIVE;
  curCat='';
  curPage=1;
  document.getElementById('q').value='';
  document.querySelectorAll('#cats .cat').forEach(x=>x.classList.remove('active'));
  switchTab('items');
}
function switchTab(t){
  document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active', x.dataset.tab===t));
  document.querySelectorAll('.tabpane').forEach(x=>x.style.display = (x.id==='tab-'+t)?'block':'none');
  if(t==='overview'){ loadStats(); loadTopItems(); }
  if(t==='items') loadItems();
  if(t==='pending') loadPending();
  if(t==='sync') loadSync();
  if(t==='settings') loadSettings();
  if(t==='site') loadSite();
  if(t==='request') loadReq();
  if(t==='apitoken') loadTokens();
  if(t==='scrape') renderScrapePanel();
  if(t==='matching') loadMatching();
  if(t==='users') loadUsers();
  if(t==='tags'){ renderTags(); renderPeople(); }
  if(t==='local') loadLocal();
}
async function loadStats(){
  const d = await api('/api/v1/admin/stats');
  if(!d.success){ alert(d.error||'加载失败'); return; }
  const s=d.data; let h='';
  h+=`<div class="stat"><b>${s.total}</b><span>总条目</span></div>`;
  if(s.pending>0) h+=`<div class="stat"><b>${s.pending}</b><span>暂存</span></div>`;
  for(const k in s.by_type) h+=`<div class="stat"><b>${s.by_type[k]}</b><span>${SCHEMA?SCHEMA.labels[k]:k}</span></div>`;
  for(const k in s.by_source) h+=`<div class="stat"><b>${s.by_source[k]}</b><span>源:${k}</span></div>`;
  document.getElementById('stats').innerHTML=h;
}
let TOP_CAT='';
function filterTopItems(cat){
  TOP_CAT=cat;
  document.querySelectorAll('#topCats .cat').forEach(x=>x.classList.toggle('active', x.dataset.cat===cat));
  loadTopItems();
}
async function loadTopItems(){
  const url='/api/v1/admin/items?page=1&sort=clicks&per=50&type='+encodeURIComponent(TOP_CAT);
  const d=await api(url);
  if(!d.success){ document.getElementById('topItemsList').innerHTML='<div class="top-empty">加载失败</div>'; return; }
  const items=(d.data.items||[]).slice(0,50);
  if(!items.length){ document.getElementById('topItemsList').innerHTML='<div class="top-empty">暂无点击数据</div>'; return; }
  let h='';
  items.forEach((it,i)=>{
    const rk=i+1;
    const rkCls='top-rank'+(rk<=3?(' top-rank-'+rk):'');
    h+='<div class="top-item" onclick="window.open(\'/uisc/'+it.id+'.html\',\'_blank\')" title="'+esc(it.title||'')+'"><span class="'+rkCls+'">'+rk+'</span><span class="top-name">'+esc(it.title||'')+'</span></div>';
  });
  document.getElementById('topItemsList').innerHTML=h;
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
      `<div class="stat"><b>${s.total_calls||0}</b><span>累计调用</span></div>`+
      `<div class="stat"><b>${s.success_rate||0}%</b><span>成功率</span></div>`;
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
      <td><code class="tkv" title="令牌仅在创建时显示" >${esc(String(k.token_preview||''))}</code></td>
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
    curTag = '';
    curPage = 1; loadItems();
  }
});
async function loadItems(){
  const q=document.getElementById('q').value;
  const d=await api(`/api/v1/admin/items?page=${curPage}&type=${curCat}&q=${encodeURIComponent(q)}&tag=${encodeURIComponent(curTag)}`);
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
  let info=`第 ${curPage} 页 / 共 ${d.data.total} 条`;
  if(curTag) info=`标签「${curTag}」· ${info}`;
  document.getElementById('pageInfo').innerText=info;
}
function linkCell(s){
  if(!s) return '<span class="st st-none">未检测</span>';
  const dl = s.dead||0;
  if(dl>0) return `<span class="st st-dead">✗ 失效 ${dl}</span>`;
  return '<span class="st st-ok">✓ 有效</span>';
}
function pageItems(d){ if(curPage+d<1) return; curPage+=d; loadItems(); }

/* ===== v3.2.3 条目匹配页 ===== */
let mcPage = 1;
let mcTab = 'unmatched';   // unmatched | matched
let mcAllCheckedDead = 0; // 全局失效条目数（含本页之外的估算，来自 /api/v1/admin/link_status_map）

function switchMatchTab(t){
  mcTab = (t === 'matched') ? 'matched' : 'unmatched';
  mcPage = 1;
  document.querySelectorAll('#mcSubtabs .mc-tab').forEach(b=>b.classList.toggle('active', b.dataset.mctab===mcTab));
  loadMatching();
}

async function runMatchAuto(){
  const btn = document.getElementById('mcAutoBtn');
  if(!btn) return;
  if(!confirm('将使用当前 PanSou 配置，自动为待匹配条目搜索并补入下载地址？\n\n每次最多处理 5 条，完成后可继续点击。')) return;
  const old = btn.innerText;
  btn.disabled = true;
  btn.innerText = '匹配中…';
  try {
    const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({limit:5})});
    if(!d.success){ alert(d.error||'自动匹配失败'); return; }
    const r = d.data || {};
    alert('匹配完成：检查 '+(r.checked||0)+' 条，写入 '+(r.written||0)+' 条，待确认 '+(r.queued||0)+' 条，无匹配 '+(r.nomatch||0)+' 条');
    await loadMatching();
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  } finally {
    btn.disabled = false;
    btn.innerText = old;
  }
}

/* v3.4.6：单条重匹配（自动匹配失败后可用于补单条） */
async function matchOne(id){
  const btn = event && event.currentTarget;
  if(!btn) return;
  if(!confirm('使用当前 PanSou 配置，为 #'+id+' 单独搜索并补入下载地址？\n\n若已有链接，将覆盖。')) return;
  const old = btn.innerText;
  btn.disabled = true;
  btn.innerText = '匹配中…';
  try {
    const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({ids:[id],limit:1,refresh:true})});
    if(!d.success){ alert(d.error||'匹配失败'); return; }
    const r = d.data || {};
    alert('匹配完成：检查 '+(r.checked||0)+' 条，写入 '+(r.written||0)+' 条，待确认 '+(r.queued||0)+' 条，无匹配 '+(r.nomatch||0)+' 条');
    await loadMatching();
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  } finally {
    btn.disabled = false;
    btn.innerText = old;
  }
}

async function loadMatching(){
  const q=document.getElementById('mcQ').value;
  const url=`/api/v1/admin/matching?tab=${mcTab}&page=${mcPage}&per=20&q=${encodeURIComponent(q)}`;
  const d=await api(url);
  if(!d.success){ alert(d.error||'加载失败'); return; }
  // 顶部三个统计卡片
  const c = d.data.counts||{};
  const unmatched = (c.unmatched|0);
  const matched   = (c.matched|0);
  const deadItems = (d.data.tab==='matched') ? countDeadInPage(d.data.items) : estimateDeadFromMap();
  document.getElementById('mcUnmatched').innerText = unmatched;
  document.getElementById('mcMatched').innerText   = matched;
  document.getElementById('mcDead').innerText      = deadItems;
  document.getElementById('mcCntUnmatched').innerText = unmatched;
  document.getElementById('mcCntMatched').innerText   = matched;

  const tb = document.getElementById('mcRows');
  if(!d.data.items.length){
    const msg = (mcTab==='unmatched')
      ? '<b>🎉</b>太棒了！所有条目都已匹配上网盘链接，去「已匹配」页查看失效情况'
      : '<b>🕓</b>还没有匹配网盘链接的条目；用「+ 新增项目」或从「同步采集」拉热门后人工补链接';
    tb.innerHTML = `<div class="mc-rows-empty">${msg}</div>`;
  } else {
    let h='';
    d.data.items.forEach(it=>{
      const lb = SCHEMA ? (SCHEMA.labels[it.type]||it.type) : it.type;
      const st = (mcTab==='unmatched')
        ? '<span class="st-mc unmatched">🕓 待匹配</span>'
        : matchStatusCell(it);
      const btns = mcTab==='unmatched'
        ? `<div class="btn-row">
             <button class="ghost sm mc-match-one" onclick="matchOne(${it.id})" title="用 PanSou 配置单条重匹配（自动匹配失败后可用于补单条）">🔗 匹配</button>
             <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
           </div>`
        : `<div class="btn-row">
             <button class="ghost sm" onclick="checkOne(${it.id})">检测</button>
             <button class="ghost sm mc-match-one" onclick="matchOne(${it.id})" title="用 PanSou 配置单条重匹配（可覆盖已有链接）">🔗 匹配</button>
             <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
           </div>`;
      h += `<div class="mc-card">
        ${it.poster?`<img class="thumb" src="${it.poster}">`:`<span class="thumb mc-thumb-ph"></span>`}
        <div class="mc-main">
          <div class="mc-title" title="${esc(it.title||'')}">${esc(it.title||'')}</div>
          <div class="hint mc-sub">${esc(it.original_title||'')}</div>
          <div class="mc-meta"><span class="mc-cat">${lb}</span>${st}</div>
          <div class="mc-acts">${btns}</div>
        </div>
      </div>`;
    });
    tb.innerHTML = h;
  }
  document.getElementById('mcPageInfo').innerText = `第 ${d.data.page} 页 · 共 ${d.data.total} 条`;
}

function countDeadInPage(items){
  let n=0;
  (items||[]).forEach(it=>{ if((it.links_dead|0) > 0) n++; });
  return n;
}

// 未匹配页时：从 link_status_map 估算失效条目总数（本页看不到失效分布，只能给全局值）
function estimateDeadFromMap(){
  if(mcAllCheckedDead){ return mcAllCheckedDead; }
  // 惰性拉一次，本页不需要就跳过
  try{
    api('/api/v1/admin/link_status_map').then(m=>{
      if(!m.success) return;
      const map = m.data.map||{};
      let n=0;
      Object.keys(map).forEach(k=>{ if((map[k].dead|0)>0) n++; });
      mcAllCheckedDead = n;
      document.getElementById('mcDead').innerText = n;
    }).catch(()=>{});
  }catch(e){}
  return '-';
}

// 已匹配页的状态徽章：显示 有效 X / 失效 Y / 检测时间
function matchStatusCell(it){
  const ok = (it.links_ok|0);
  const dead = (it.links_dead|0);
  const total = (it.links_total|0);
  const last = it.last_checked || '';
  let cls = 'unmatched', txt = '🕓 未检测';
  if(total>0 && dead>0){ cls='dead'; txt=`✗ 失效 ${dead}/${total}`; }
  else if(total>0 && dead===0){ cls='ok'; txt=`✓ 有效 ${ok}/${total}`; }
  const tip = last ? `title="上次检测：${esc(last)}"` : '';
  return `<span class="st-mc ${cls}" ${tip}>${txt}</span>`;
}

function mcPageItems(d){ if(mcPage+d<1) return; mcPage+=d; loadMatching(); }

// 手动触发一次全量链接检测（走批量 check_links）
async function matchCheckAll(){
  const btn = document.getElementById('mcCheckBtn');
  if(!confirm('将逐个检测所有已匹配条目的下载链接，可能耗时较久，确定继续？')) return;
  btn.disabled = true; const old = btn.innerText; btn.innerText = '检测中…';
  let remaining=1, round=0, ok=0, dead=0;
  try {
    while(remaining>0 && round<40){
      round++;
      const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({limit:40})});
      if(!d.success){ alert(d.error||'检查失败'); break; }
      ok+=d.data.ok|0; dead+=d.data.dead|0; remaining=d.data.remaining|0;
      if(remaining===0 || (d.data.checked|0)===0) break;
    }
    alert('检测完成：有效 '+ok+' 条，失效 '+dead+' 条');
  } catch(e){ alert(e&&e.message?e.message:String(e)); }
  btn.disabled = false; btn.innerText = old;
  loadMatching();
}

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
  editId=0; LINK_STATUS={}; editExtraBase={};
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
  (it.link_checks||[]).forEach(c=>{ LINK_STATUS[c.url] = { ok:(parseInt(c.status,10)===1), msg:(c.msg||''), code:(c.http_code|0) }; });
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
  editExtraBase=extra; // v3.3.7：保存编辑前快照，saveEdit 用它保留 cast/trailer/videos 等非 schema key
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
  // v3.3.7：以 editExtraBase 为底，保留 cast/trailer/videos/stills/popularity 等非 schema key
  //   —— 否则用户随便改个字段保存，TMDB 采集的演员头像/预告片数据会被全部擦掉
  const extra={...(editExtraBase||{})};
  const fs=(SCHEMA&&SCHEMA.extra_fields[type])?SCHEMA.extra_fields[type]:[];
  fs.forEach(f=>{
    const el=document.getElementById('x_'+f.k);
    if(el){
      const v=el.value.trim();
      if(v){ extra[f.k]=v; } else { delete extra[f.k]; }   // 用户清空则移除
    }
  });
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

/* ============ v3.8.6：条目暂存管理 ============ */
let PENDING_PAGE=1, PENDING_PER=20;
async function loadPending(){
  const q=document.getElementById('pq').value||'';
  const d=await api('/api/v1/admin/pending?page='+PENDING_PAGE+'&per='+PENDING_PER+'&q='+encodeURIComponent(q));
  if(!d.success){ alert(d.error||'加载失败'); return; }
  const items=d.data.items||[];
  const total=d.data.total||0;
  document.getElementById('pendingCount').textContent='共 '+total+' 条暂存';
  document.getElementById('pendingPageInfo').textContent='第 '+PENDING_PAGE+' 页';
  let h='';
  if(!items.length){
    h='<tr><td colspan="6" style="text-align:center;color:var(--hint);padding:30px">暂无暂存条目</td></tr>';
  } else {
    for(const it of items){
      const lb=SCHEMA?(SCHEMA.labels[it.type]||it.type):it.type;
      const src=it.source||'';
      let panType='', panName='—';
      try{
        const ex=JSON.parse(it.extra||'{}');
        panType=ex.pan_type||'';
        panName=ex.pan_name||(panType?(PAN_TYPES[panType]||panType):'—');
        /* v3.9.7：如果 panType 是 99 或 'other'，尝试从 download_url 推断网盘类型 */
        if((panType==99||panType==99||panType=='other'||panName=='其他')&&it.download_url){
          const url=it.download_url.toLowerCase();
          if(url.includes('guangyapan.com')||url.includes('guangya.com')){ panType=12; panName='光鸭'; }
          else if(url.includes('quark.cn')){ panType=1; panName='夸克'; }
          else if(url.includes('baidu.com')){ panType=2; panName='百度'; }
          else if(url.includes('xunlei.com')){ panType=3; panName='迅雷'; }
          else if(url.includes('aliyun.com')||url.includes('alipan.com')){ panType=4; panName='阿里'; }
          else if(url.includes('123pan.com')){ panType=5; panName='123网盘'; }
          else if(url.includes('115.com')||url.includes('115cdn.com')){ panType=6; panName='115'; }
          else if(url.includes('uc.cn')){ panType=7; panName='UC'; }
          else if(url.includes('lanzou')||url.includes('lanzoux')){ panType=8; panName='蓝奏'; }
          else if(url.includes('189.cn')||url.includes('caiyun.139.com')){ panType=10; panName='天翼'; }
          else if(url.includes('magnet:')){ panType=9; panName='磁力'; }
        }
      }catch(e){}
      const srcLabel=(lb?lb+' · ':'')+(src==='manual'?'本地导入':(src==='pansou'?'PanSou':src));
      const t=it.updated_at?it.updated_at.substring(0,16).replace('T',' '):'';
      h+='<tr>'
        +'<td style="width:60px">'+(it.poster?'<img class="thumb" src="'+it.poster+'">':'<div class="thumb-ph">无图</div>')+'</td>'
        +'<td><b>'+esc(it.title||'')+'</b><br><span class="hint">'+esc(it.original_title||'')+'</span></td>'
        +'<td>'+esc(srcLabel)+'</td>'
        +'<td>'+esc(panName)+'</td>'
        +'<td>'+esc(t)+'</td>'
        +'<td style="white-space:nowrap">'
        +'<button class="sm" onclick="promoteItem('+it.id+',this)">✓ 转为正式</button> '
        +'<button class="ghost sm" onclick="delPending('+it.id+',this)">删除</button>'
        +'</td></tr>';
    }
  }
  document.getElementById('pendingRows').innerHTML=h;
}
function pagePending(d){ PENDING_PAGE+=d; if(PENDING_PAGE<1)PENDING_PAGE=1; loadPending(); }
async function promoteItem(id,btn){
  btn.disabled=true;
  const d=await api('/api/v1/admin/pending_promote',{method:'POST',body:JSON.stringify({id:id})});
  if(d.success){ btn.textContent='✓ 已转化'; setTimeout(loadPending,400); }
  else { alert(d.error||'操作失败'); btn.disabled=false; }
}
async function delPending(id,btn){
  if(!confirm('确定删除这条暂存条目？')) return;
  btn.disabled=true;
  const d=await api('/api/v1/admin/pending_delete',{method:'POST',body:JSON.stringify({id:id})});
  if(d.success){ btn.textContent='✓ 已删除'; setTimeout(loadPending,400); }
  else { alert(d.error||'操作失败'); btn.disabled=false; }
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
      ctrl = `<select id="sy_${k}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" onchange="onSyncParamChange()">` +
        Object.keys(opts).map(o=>`<option value="${esc(o)}" ${String(o)===String(cur)?'selected':''}>${esc(opts[o])}</option>`).join('') +
      `</select>`;
    } else {
      ctrl = `<input type="number" id="sy_${k}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" min="${mm.min!=null?mm.min:0}" max="${mm.max!=null?mm.max:''}" value="${esc(cur)}" onchange="onSyncParamChange()" style="width:110px">`
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
/* ★ v4.4.4：一键清除全部缓存（CacheStore 文件缓存 + Cache 多级缓存），数据改动后立即生效 */
async function clearAllCache(btn){
  if(btn){ btn.disabled=true; btn.textContent='清除中…'; }
  const d = await api('/api/v1/admin/cache_clear',{method:'POST'});
  if(btn){ btn.disabled=false; btn.textContent='清除缓存'; }
  if(!d.success){ alert(d.error||'清除失败'); return; }
  alert('已清除 '+d.data.files+' 个文件缓存'+(d.data.multi?'，多级缓存已同步清空':'')+'。各页面数据将在下次加载时自动重建。');
}
/* ★ v4.3.11：网盘自动转存 —— 把条目里「别人的」夸克/UC 分享转存到站长网盘并改写为自己的链接。
   每次最多处理 3 个链接（上游 API 往返多，防超时）；大批量请配宝塔计划任务 cron/pan_transfer.php。 */
async function runPanTransfer(btn){
  if(btn){ btn.disabled=true; btn.textContent='转存中…'; }
  const d = await api('/api/v1/admin/pan_transfer_run',{method:'POST',body:{limit:3}});
  if(btn){ btn.disabled=false; btn.textContent='转存网盘链接'; }
  if(!d.success){ alert(d.error||'转存失败'); return; }
  if(d.data.msg){ alert(d.data.msg); return; }
  const lines=(d.data.details||[]).map(x=>'#'+x.item+' ['+x.pan+'] '+(x.error?('失败：'+x.error):('→ '+x.new)));
  alert('本次转存完成：成功 '+d.data.ok+' · 失败 '+d.data.fail+' · 跳过 '+d.data.skip
    +(lines.length?('\n'+lines.join('\n')):'')
    +'\n（每次最多处理 3 个；剩余的会自动继续，或配计划任务 cron/pan_transfer.php 批量跑）');
  loadItems();
}
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

// ===== 下载链接（多条网盘，类型来自 config/pan_types.php，可升级扩展）=====
function panOptions(list, sel){
  list = (list && list.length) ? list : PAN_LIST;
  let h=''; list.forEach(t=>{ h+=`<option value="${esc(t)}" ${t===sel?'selected':''}>${esc(t)}</option>`; }); return h;
}
function pill(st){
  // st 兼容三种形态：null/true/false（旧） 与 {ok,msg,code}（新）
  let ok=null, msg='';
  if(st && typeof st==='object'){ ok = !!st.ok; msg = st.msg||''; }
  else if(st===true || st===false){ ok = st; }
  if(ok===true){
    const tip = msg ? ' title="'+esc(msg)+'"' : '';
    return '<span class="st st-ok">✓ 有效'+tip+'</span>';
  }
  if(ok===false){
    // 失效时把检测原因（如"分享已失效"/"提取码错误"）显示为标题提示，鼠标悬停可见
    const tip = msg ? ' title="'+esc(msg)+'"' : '';
    return '<span class="st st-dead">✗ 失效'+tip+'</span>';
  }
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
  // 逐条列出检测结果，把网盘接口返回的中文原因（如"分享已失效""提取码错误""内容违规"）直接透出，
  // 免得用户看到"失效"却不知是啥情况。
  let lines=['检测完成：共 '+rs.length+' 条，有效 '+(rs.length-dead)+' 条，失效 '+dead+' 条',''];
  rs.forEach((r,i)=>{
    const tag = r.ok ? '✓ 有效' : '✗ 失效';
    const why = (r.msg||'').trim();
    lines.push((i+1)+'. ['+tag+'] '+(r.label||'下载')+(why?(' — '+why):''));
  });
  alert(lines.join('\n'));
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
  jump:   '前台模板、明暗模式与顶部导航（v4.3.11 起「跳转 / 扫码」拦截已下线：下载链接点击即直达各网盘分享页，无需再拦首页）。前台已做手机 / 平板 / 大屏自适应。',
  pan:    '各网盘的 Cookie 与转存目录：夸克 / UC 填好 Cookie（可扫码登录）后，系统会把采集到的他人分享自动转存到你的网盘并改写为你自己的分享链接（v4.3.11）。',
  pansou: '对接 PanSou 网盘搜索服务：每次采集入库新资源后，自动搜索并补写网盘下载地址。',
  req:    '注册与「资源请求」页的开关：谁能注册、谁能提交、列表是否公开、每人每天能提几条。改完立即生效，前台顶栏与页面文案跟着变。',
  mail:   '找回密码的发信服务（SMTP）：填邮箱的 SMTP 账号与授权码、打开开关，点「发送测试邮件」验证。QQ / 163 / Gmail 需先在邮箱设置里生成「授权码」当密码用；没配好时「忘记密码」会明确提示，不会静默失败。'
};
const PAN_ACCOUNTS = {
  quark:  {name:'夸克网盘', url:'https://pan.quark.cn/'},
  aliyun: {name:'阿里云盘', url:'https://www.alipan.com/'},
  baidu:  {name:'百度网盘', url:'https://pan.baidu.com/'},
  uc:     {name:'UC网盘',   url:'https://pan.uc.cn/'},
  xunlei: {name:'迅雷云盘', url:'https://cloud.xunlei.com/'},
  guangya:{name:'光鸭网盘', url:'https://www.guangya.com/'}
};
/* v3.1.4：支持服务端扫码登录的网盘（协议见 core/PanQr.php）；不在表内的继续用「🔗 获取Cookie」手动粘贴 */
/* v4.2.0 曾按"参考心悦源码"回退为 4 家（移除 xunlei/guangya），实际核对心悦源码 extend/netdisk/pan/ 发现：
   迅雷走 XunleiOAuth（xluser-ssl.xunlei.com OAuth Device Flow，RFC 8628 设备码）；
   光鸭走 guangyaQrcode（account.guangyapan.com/v1/auth/device/code），也是 OAuth Device Flow。
   v4.2.1 把它们从白名单移除是错的——它们有真扫码能力，只是拿 access_token 而不是 Cookie。
   v4.2.2 参考心悦源码真实实现补回扫码登录入口：
     - 扫码成功后，PanQr 返回 access_token JSON 到"Cookie 框"（沿用阿里 aliyunPoll 的 JSON 落 Cookie 框模式）；
     - 用户点底部「保存设置」持久化；后续如需对接网盘 API 直接读这个 JSON 的 access_token。
*/
const PAN_QR = {
  quark:  {app:'夸克 App'},
  uc:     {app:'UC 浏览器 / UC 网盘 App'},
  baidu:  {app:'百度网盘 App'},
  aliyun: {app:'阿里云盘 App'},
  xunlei: {app:'迅雷云盘 App（Android）'},
  guangya:{app:'光鸭网盘 App'}
};

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

    if(meta['pan_group']) h += fieldRow('pan_group', meta['pan_group'], items['pan_group']||{});
    h += `<div class="pan-2col">`;
    for(const code in PAN_ACCOUNTS){
      const kc='pan_'+code+'_cookie', kd='pan_'+code+'_dir', kt='pan_'+code+'_tmp';
      if(!meta[kc]) continue;
      const pan = PAN_ACCOUNTS[code];
      const cur = (SITE_DRAFT[kc]!==undefined ? SITE_DRAFT[kc] : ((items[kc]||{}).value||''));
      h += `<div class="pan-card">`;
      h += fieldRow(kc, meta[kc], items[kc]||{});
      h += `<div class="pan-cookie-info">
        <span class="hint" id="pci_${code}">Cookie ${cur?('已填写（'+cur.length+' 字符）'):'未填写'}</span>
        ${PAN_QR[code]?`<button type="button" class="ghost sm" onclick="panQrStart('${code}')">📱 扫码登录</button>`:''}
        <a class="pan-card-link" href="${pan.url}" target="_blank" rel="noopener noreferrer">🔗 获取Cookie</a>
      </div>`;
      h += fieldRow(kd, meta[kd], items[kd]||{});
      h += fieldRow(kt, meta[kt], items[kt]||{});
      h += `</div>`;
    }
    h += `</div>`;
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
    h += `<div class="set-sec"><h4>立即补地址</h4>
      <div class="tip">对「还没有下载地址」的条目立刻跑一次搜索补写。根据「写入策略」决定自动写入还是进待确认队列。</div>
      <div class="inline"><button type="button" onclick="runEnrich()">立即补地址</button></div></div>
    <div class="set-sec"><h4>待确认队列 <span class="hint" id="pendHint"></span></h4>
      <div class="tip">匹配度不够高、按策略暂不自动写入的结果会落到这里。逐条看清楚再「采纳」，比全自动安全。</div>
      <div id="pendingBox"></div>
      <div class="inline"><button type="button" class="ghost" onclick="loadPanSouPending()">刷新队列</button></div></div>`;
    loadPanSouPendingCount();
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
  const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({limit:5})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'补地址失败'; return; }
  const r = d.data;
  msg.style.color='var(--ok)';
  msg.innerText = `✓ 检查 ${r.checked} 条：写入 ${r.written} 条（共 ${r.links} 个链接）、待确认 ${r.queued} 条、无匹配 ${r.nomatch} 条，用时 ${r.ms} ms`;
  loadPanSouPending();
}
async function clearPansouCache(){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pansou_cache_clear',{method:'POST',body:'{}'});
  msg.style.color = d.success?'var(--ok)':'var(--dead)';
  msg.innerText = d.success ? ('✓ 已清空搜索缓存（'+d.data.cleared+' 条）') : (d.error||'操作失败');
}
async function loadPanSouPendingCount(){
  const d = await api('/api/v1/admin/pending_list');
  const el = document.getElementById('pendCount');
  if(el && d.success) el.innerText = d.data.count;
}
async function loadPanSouPending(){
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
        <button class="ghost" onclick="applyPanSouPending(${r.id})">采纳</button>
        <button class="ghost" onclick="dropPanSouPending(${r.id})">忽略</button>
      </div></div>`;
  });
  box.innerHTML = h;
}
async function applyPanSouPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_apply',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'采纳失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已写入 '+d.data.added+' 个下载链接（下次生成静态页后前台可见）';
  loadPanSouPending();
}
async function dropPanSouPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_drop',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'操作失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已忽略该条';
  loadPanSouPending();
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
      请到宝塔 → 数据库 → 该库的用户 → 权限勾选「所有权限」，或手动导入 <b>sql/install.sql</b> 后重试。</div>`;
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

  /* v3.5.7：按站长要求移除「网页版安装器 install.php」提示行 —— 安装锁状态
     已由下方 config/install.lock 行体现，残留文件告警（install_ghosts）保留。 */
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
    if (users.length === 0) {
      const qStr = q.trim();
      box.innerHTML = total === 0
        ? '<div class="hint">系统中还没有用户注册。用户通过前台「注册」页面创建账号。</div>'
        : '<div class="hint">搜索「' + esc(qStr) + '」未找到用户（共 ' + total + ' 个用户）。点「清空」查看所有用户。</div>';
      pager.innerHTML = ''; return;
    }
    /* ★ v3.2.5：改为 4 卡一排 + 详情弹窗；v3.2.7：用户名即邮箱，去掉冗余邮箱行 */
    let h = '<div class="user-grid">';
    users.forEach(u => {
      const isAdm = String(u.is_admin) === '1';
      const statusClass = u.status == 1 ? 'ok' : 'bad';
      const statusText = u.status == 1 ? '正常' : '禁用';
      const roleClass = isAdm ? 'admin' : 'normal';
      const roleText = isAdm ? '⭐ 管理员' : '普通用户';
      const initial = (u.username || '?').slice(0, 1).toUpperCase();
      h += '<div class="user-card">'
        + '<div class="uc-head">'
        + '<div class="uc-avatar">' + esc(initial) + '</div>'
        + '<div class="uc-name">'
        + '<b>' + esc(u.username || '') + '</b>'
        + '<span class="uc-role ' + roleClass + '">' + roleText + '</span>'
        + '</div>'
        + '</div>'
        + '<div class="uc-meta">'
        + '<div class="row"><span class="k">状态</span><span class="v"><span class="pill ' + statusClass + '">' + statusText + '</span></span></div>'
        + '<div class="row"><span class="k">注册时间</span><span class="v">' + esc(u.created_at || '-') + '</span></div>'
        + '<div class="row"><span class="k">最后登录</span><span class="v">' + esc(u.last_login || '-') + '</span></div>'
        + '</div>'
        + '<div class="uc-actions">'
        + '<button class="detail" onclick="openUserDetail(' + u.id + ')">📋 详细</button>'
        + '<button class="ghost sm" onclick="toggleUserStatus(' + u.id + ', ' + (u.status == 1 ? 0 : 1) + ')">' + (u.status == 1 ? '禁用' : '启用') + '</button>'
        + '<button class="ghost sm" onclick="editUser(' + u.id + ')">编辑</button>'
        + '<button class="ghost sm bad" onclick="deleteUser(' + u.id + ')">删除</button>'
        + '</div>'
        + '</div>';
    });
    h += '</div>';
    box.innerHTML = h;
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

/* ★ v3.2.5：用户详情面板；v3.2.7：用户名即邮箱，去掉冗余邮箱行 */
async function openUserDetail(id) {
  const mask = document.getElementById('userModalMask');
  const modal = document.getElementById('userModal');
  const body = document.getElementById('umBody');
  mask.style.display = 'block';
  modal.style.display = 'flex';
  document.getElementById('umUsername').textContent = '加载中...';
  document.getElementById('umSub').textContent = '';
  document.getElementById('umAvatar').textContent = 'U';
  body.innerHTML = '<div class="hint">加载中...</div>';
  try {
    const d = await api('/api/v1/admin/user_history?id=' + id);
    if (!d.success) { body.innerHTML = '<div class="err">' + esc(d.error || '加载失败') + '</div>'; return; }
    const u = d.data.user || {};
    const tokens = d.data.tokens || [];
    const logs = d.data.logs || [];
    const isAdm = String(u.is_admin) === '1';
    const statusClass = u.status == 1 ? 'ok' : 'bad';
    const statusText = u.status == 1 ? '正常' : '禁用';
    const roleText = isAdm ? '⭐ 管理员' : '普通用户';
    const initial = (u.username || '?').slice(0, 1).toUpperCase();
    document.getElementById('umAvatar').textContent = esc(initial);
    document.getElementById('umUsername').textContent = u.username || '未知用户';
    document.getElementById('umSub').textContent = roleText + ' · 状态：' + statusText;
    let h = '';
    /* 基本信息（v3.2.7：用户名即邮箱，合并显示） */
    h += '<div class="um-section"><h4>👤 用户信息</h4><div class="um-kv">'
      + '<span class="k">用户名/邮箱</span><span class="v">' + esc(u.username || '-') + '</span>'
      + '<span class="k">角色</span><span class="v">' + esc(roleText) + '</span>'
      + '<span class="k">状态</span><span class="v"><span class="pill ' + statusClass + '">' + statusText + '</span></span>'
      + '<span class="k">注册时间</span><span class="v">' + esc(u.created_at || '-') + '</span>'
      + '<span class="k">最后登录</span><span class="v">' + esc(u.last_login || '-') + '</span>'
      + '<span class="k">最后 IP</span><span class="v">' + esc(u.ip || '-') + '</span>'
      + '<span class="k">资源请求数</span><span class="v">' + (u.req_count || 0) + '</span>'
      + '</div></div>';
    /* 令牌概览 */
    h += '<div class="um-section"><h4>🔑 关联 API 令牌（' + tokens.length + '）</h4>';
    if (tokens.length === 0) {
      h += '<div class="um-empty">该用户尚未关联任何 API 令牌</div>';
    } else {
      tokens.forEach(t => {
        const tSt = t.status == 1 ? 'ok' : 'bad';
        const tStTxt = t.status == 1 ? '启用' : '禁用';
        h += '<div style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px;font-size:12.5px">'
          + '<div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px">'
          + '<b>' + esc(t.name || ('令牌 #' + t.id)) + '</b>'
          + '<span class="pill ' + tSt + '" style="font-size:11px">' + tStTxt + '</span>'
          + '</div>'
          + '<div style="color:var(--mut);font-size:11.5px">累计调用 ' + (t.usage_count || 0) + ' 次 · 最后使用 ' + esc(t.last_used || '-') + '</div>'
          + '</div>';
      });
    }
    h += '</div>';
    /* 访问资源历史 */
    h += '<div class="um-section"><h4>📜 访问资源历史（最近 ' + logs.length + ' 条）</h4>';
    if (logs.length === 0) {
      h += '<div class="um-empty">暂无 API 调用记录<br><span style="font-size:12px;color:var(--mut)">该用户可能未通过 API 访问过资源，或历史已过期清理</span></div>';
    } else {
      h += '<div class="um-logs">';
      logs.forEach(l => {
        const isErr = l.response_code && l.response_code >= 400;
        h += '<div class="um-log">'
          + '<div class="ln1">'
          + '<span class="ep">' + esc(l.endpoint || '-') + '</span>'
          + '<span class="code' + (isErr ? ' err' : '') + '">' + (l.response_code || 200) + '</span>'
          + '</div>'
          + '<div class="ln2">'
          + '<span>' + esc(l.token_name || '-') + '</span>'
          + '<span>' + esc(l.created_at || '-') + ' · ' + (l.cost_ms || 0) + 'ms</span>'
          + '</div>'
          + '</div>';
      });
      h += '</div>';
    }
    h += '</div>';
    body.innerHTML = h;
  } catch (e) {
    body.innerHTML = '<div class="err">加载失败：' + esc(e.message) + '</div>';
  }
}
function closeUserDetail() {
  document.getElementById('userModalMask').style.display = 'none';
  document.getElementById('userModal').style.display = 'none';
}
async function toggleUserStatus(id, status) {
  if (!confirm('确定要' + (status == 1 ? '启用' : '禁用') + '该用户吗？')) return;
  const d = await api('/api/v1/admin/user_save', {method:'POST', body:JSON.stringify({id, status})});
  if (!d.success) { alert(d.error || '操作失败'); return; }
  loadUsers();
}
async function editUser(id) {
  const d = await api('/api/v1/admin/user_list');
  if (!d.success) { alert('加载失败'); return; }
  /* ★ v3.1.1 fix：接口返回的字段是 items（原来写成 users，永远找不到人 →「用户不存在」） */
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
  const saveD = await api('/api/v1/admin/user_save', {method:'POST', body:JSON.stringify(payload)});
  if (!saveD.success) { alert(saveD.error || '保存失败'); return; }
  alert('用户已更新');
  loadUsers();
}
async function deleteUser(id) {
  if (!confirm('确定要删除该用户吗？此操作不可恢复！')) return;
  const d = await api('/api/v1/admin/user_del', {method:'POST', body:JSON.stringify({id})});
  if (!d.success) { alert(d.error || '删除失败'); return; }
  alert('用户已删除');
  loadUsers();
}

// ★ v1.9.0: 自动刮削功能（v3.1.6 美化状态提示与日志）
function renderScrapePanel() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="ext-note">💤 就绪 · 选好类型和数量后点「开始刮削」</div>';
}

async function startScrape() {
  const status = document.getElementById('scrapeStatus');
  const log = document.getElementById('scrapeLog');
  const btn = document.getElementById('scrapeGo');
  const type = document.getElementById('scrapeType').value;
  const limit = parseInt(document.getElementById('scrapeLimit').value) || 50;

  btn.disabled = true;
  status.innerHTML = '<div class="ext-note note-warn">⏳ 正在刮削… 本次处理 ' + limit + ' 条，请勿关闭页面</div>';
  log.style.display = 'block';
  log.innerHTML = '<b>[开始]</b> 类型=' + (type || '全部') + ' · 数量=' + limit + '<br>';

  try {
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({auto_scrape:true, type:type, limit:limit})});
    if (!d.success) {
      status.innerHTML = '<div class="ext-note note-err">✗ 刮削失败：' + esc(d.error || '未知错误') + '</div>';
      log.innerHTML += '<b>[失败]</b> ' + esc(d.error || '未知错误') + '<br>';
      return;
    }
    /* v3.9.5：TMDB API Key 未配置时，后端返回 data.error */
    if (d.data.error) {
      status.innerHTML = '<div class="ext-note note-err">✗ ' + esc(d.data.error) + '</div>';
      log.innerHTML += '<b>[失败]</b> ' + esc(d.data.error) + '<br>';
      return;
    }

    const scraped = d.data.scraped || 0;
    const failed = d.data.failed || 0;
    const total = d.data.total || (scraped + failed);
    /* ★ v3.5.7：把最近一次失败数量回填到面板统计框（0 灰 / >0 红） */
    const fe = document.getElementById('scrapeFailed');
    if (fe) { fe.textContent = String(failed); fe.className = 'scrape-stat ' + (failed > 0 ? 'bad' : 'zero'); }
    let msg = '✓ 刮削完成！处理 ' + total + ' 条，成功补充 ' + scraped + ' 张图片，失败 ' + failed + ' 条';
    /* v3.9.5：显示失败原因详情（最多 5 条） */
    if (failed > 0 && d.data.errors && d.data.errors.length) {
      msg += '，最近失败原因：' + d.data.errors.join('；');
    }
    status.innerHTML = '<div class="ext-note note-ok">' + msg + '</div>';
    log.innerHTML += '<b>[完成]</b> 处理 ' + total + ' 条，成功 ' + scraped + ' 条，失败 ' + failed + ' 条<br>';
    if (failed > 0 && d.data.errors && d.data.errors.length) {
      log.innerHTML += '<b>[失败详情]</b> ' + d.data.errors.join('；') + '<br>';
    }

    if (document.getElementById('tab-items').style.display !== 'none') {
      loadItems();
    }
  } catch (e) {
    status.innerHTML = '<div class="ext-note note-err">✗ 请求失败：' + esc(e.message) + '</div>';
    log.innerHTML += '<b>[错误]</b> ' + esc(e.message) + '<br>';
  } finally {
    btn.disabled = false;
  }
}

function clearScrapeResult() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="ext-note">💤 就绪</div>';
  const log = document.getElementById('scrapeLog');
  log.style.display = 'none';
  log.innerHTML = '';
  /* ★ v3.5.7：清除结果时失败数量统计一并归零 */
  const fe = document.getElementById('scrapeFailed');
  if (fe) { fe.textContent = '0'; fe.className = 'scrape-stat zero'; }
}

/* ============ 本地资源导入（v3.1.2） ============ */
let LP_ITEMS = [], LE_ITEMS = [];
/* v3.2.4：预览筛选状态（panType / matched / status） */
let LP_FILTER = {pan:'', matched:'', status:''};
let LE_FILTER = {pan:'', matched:'', status:''};
/* v3.2.4：链接检测结果缓存 url => {ok,msg,pan} */
let LP_STATUS = {}, LE_STATUS = {};
const LOCAL_FALLBACK_TYPES = {movie:'电影',tv:'电视剧',anime:'动漫',short:'短剧',variety:'综艺',game:'游戏',book:'书籍',music:'音乐',other:'其他'};
function localTypeOptions(sel){
  sel = sel || document.getElementById('lpType');
  if(!sel) return;
  sel.innerHTML = '';
  const labels = (SCHEMA && SCHEMA.labels) ? SCHEMA.labels : LOCAL_FALLBACK_TYPES;
  for(const k in labels){
    const o=document.createElement('option'); o.value=k; o.textContent=labels[k]; sel.appendChild(o);
  }
}
function loadLocal(){
  localTypeOptions(document.getElementById('lpType'));
  localTypeOptions(document.getElementById('lmType'));
  localTypeOptions(document.getElementById('leType'));
}
function switchLocalTab(name){
  document.querySelectorAll('#localSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.stab===name));
  const titles = {paste:'📋 粘贴解析', manual:'✏️ 手动输入', excel:'📊 Excel 导入'};
  document.getElementById('localModalTitle').textContent = titles[name] || '本地资源导入';
  document.getElementById('local-paste').style.display  = (name==='paste')  ? 'block' : 'none';
  document.getElementById('local-manual').style.display = (name==='manual') ? 'block' : 'none';
  document.getElementById('local-excel').style.display  = (name==='excel')  ? 'block' : 'none';
  document.getElementById('localOverlay').style.display = 'flex';
}
function closeLocalModal(){
  document.getElementById('localOverlay').style.display = 'none';
}
function renderLocalPreview(resultId, countId, rowId, items, holder, filterId){
  const box = document.getElementById(resultId);
  const row = document.getElementById(rowId);
  const frow = document.getElementById(filterId);
  const F = (holder === 'LP_ITEMS') ? LP_FILTER : LE_FILTER;
  const S = (holder === 'LP_ITEMS') ? LP_STATUS  : LE_STATUS;
  if(!items.length){
    box.innerHTML = '<div class="hint">未识别到资源</div>';
    row.style.display='none';
    if(frow) frow.style.display='none';
    return;
  }
  // 记录原始索引，筛选后删除时仍能定位正确位置
  items.forEach((it, i) => { it.__idx = i; });
  // 聚合网盘类型（按 pan_type，无则按 pan_name）
  const pans = {};
  items.forEach(it=>{
    const p = (it.pan_type || it.pan_name || 'other');
    const n = (it.pan_name || '其它');
    pans[p] = pans[p] || {name:n, count:0};
    pans[p].count++;
  });
  // 状态计数
  const cAll = items.length;
  let cUsed=0, cFresh=0, cOk=0, cDead=0, cUn=0;
  items.forEach(it=>{
    if(it.matched) cUsed++; else cFresh++;
    const s = S[it.url||''];
    if(s && s.ok===true) cOk++;
    else if(s && s.ok===false) cDead++;
    else cUn++;
  });
  // 渲染筛选栏
  if(frow){
    // 小工具：生成 onclick 里的 JSON 片段，双引号转成 &quot; 以免破坏 HTML 属性
    const q = JSON.stringify;
    const escQ = s => s.replace(/"/g,'&quot;');
    const patch = p => escQ(q(p));
    let fh = '<div class="fg">📄 网盘：'
      + '<button class="'+(F.pan===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({pan:''})+')">全部<span class="cnt">'+cAll+'</span></button>';
    Object.keys(pans).forEach(k=>{
      const n = pans[k].name || '其它';
      fh += '<button class="'+(F.pan===k?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({pan:k})+')">'+esc(n)+'<span class="cnt">'+pans[k].count+'</span></button>';
    });
    fh += '</div><div class="fg">📌 匹配：'
      + '<button class="'+(F.matched===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:''})+')">全部<span class="cnt">'+cAll+'</span></button>'
      + '<button class="'+(F.matched==='yes'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:'yes'})+')">未用过<span class="cnt">'+cFresh+'</span></button>'
      + '<button class="'+(F.matched==='no'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:'no'})+')">已在用<span class="cnt">'+cUsed+'</span></button>'
      + '</div><div class="fg">🔎 失效：'
      + '<button class="'+(F.status===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:''})+')">全部<span class="cnt">'+cAll+'</span></button>'
      + '<button class="'+(F.status==='ok'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'ok'})+')">有效<span class="cnt">'+cOk+'</span></button>'
      + '<button class="'+(F.status==='dead'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'dead'})+')">失效<span class="cnt">'+cDead+'</span></button>'
      + '<button class="'+(F.status==='un'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'un'})+')">未检测<span class="cnt">'+cUn+'</span></button>'
      + '</div>';
    frow.innerHTML = fh;
    frow.style.display='flex';
  }
  // 按筛选过滤
  const filtered = items.filter(it=>{
    if(F.pan && (it.pan_type || it.pan_name || 'other') !== F.pan) return false;
    if(F.matched==='yes' && it.matched) return false;
    if(F.matched==='no' && !it.matched) return false;
    if(F.status){
      const s = S[it.url||''];
      if(F.status==='ok' && !(s && s.ok===true)) return false;
      if(F.status==='dead' && !(s && s.ok===false)) return false;
      if(F.status==='un' && s) return false;
    }
    return true;
  });
  // 渲染表格（v3.2.9：改为卡片列表，只显示资源名称 + 是否已匹配）
  let h = '<div class="lpv-title">✅ 识别结果 · 共 <b>'+filtered.length+'</b> 条（筛选前 '+items.length+' 条）</div>';
  if(!filtered.length){
    h += '<div class="hint" style="padding:14px 0;text-align:center">当前筛选条件下没有资源，换一个筛选试试。</div>';
  } else {
    h += '<div class="lpv-cards">';
    let n=0;
    filtered.forEach(it=>{
      n++;
      const usedClass = it.matched ? 'used' : 'fresh';
      const usedText = it.matched ? '✅ 已在用' : '🆕 未用过';
      h += '<div class="lpv-card">'
        + '<div class="lpv-card-head">'
        + '<span class="lpv-card-idx">'+n+'</span>'
        + '<div class="lpv-card-main">'
        + '<b class="lpv-card-title">'+esc(it.title||'(未命名)')+'</b>'
        + '<span class="lpv-card-url">'+esc(it.url||'')+'</span>'
        + '</div>'
        + '<span class="st-lc '+usedClass+'">'+usedText+'</span>'
        + '<button class="ghost sm" onclick="localRemoveRow(\''+holder+'\','+it.__idx+')">删</button>'
        + '</div>'
        + '</div>';
    });
    h += '</div>';
  }
  box.innerHTML = h;
  document.getElementById(countId).textContent = filtered.length;
  row.style.display = 'flex';
}
function setLocalFilter(holder, patch){
  const F = (holder === 'LP_ITEMS') ? LP_FILTER : LE_FILTER;
  Object.assign(F, patch);
  const items = (holder === 'LP_ITEMS') ? LP_ITEMS : LE_ITEMS;
  const id = (holder === 'LP_ITEMS')
    ? {r:'lpResult', c:'lpCount', w:'lpImportRow', f:'lpFilterRow'}
    : {r:'leResult', c:'leCount', w:'leImportRow', f:'leFilterRow'};
  renderLocalPreview(id.r, id.c, id.w, items, holder, id.f);
}

/* v3.2.4：批量检测预览列表里的链接是否失效（走 6 家网盘官方分享接口） */
async function localCheckLinks(holder){
  const items = (holder === 'LP_ITEMS') ? LP_ITEMS : LE_ITEMS;
  const S = (holder === 'LP_ITEMS') ? LP_STATUS : LE_STATUS;
  const btnId = (holder === 'LP_ITEMS') ? 'lpCheckBtn' : 'leCheckBtn';
  const id = (holder === 'LP_ITEMS')
    ? {r:'lpResult', c:'lpCount', w:'lpImportRow', f:'lpFilterRow'}
    : {r:'leResult', c:'leCount', w:'leImportRow', f:'leFilterRow'};
  if(!items.length){ alert('没有可检测的资源'); return; }
  const btn = document.getElementById(btnId);
  if(btn){ btn.disabled = true; btn.textContent = '检测中…'; }
  const urls = items.map(it=>it.url||'').filter(u=>u!=='');
  // 单次最多 40 条，分批调用避免超时
  const results = {};
  const BATCH = 20;
  try {
    for(let i=0; i<urls.length; i+=BATCH){
      const batch = urls.slice(i, i+BATCH);
      const d = await api('/api/v1/admin/local_check_links', {method:'POST', body:{urls:batch, limit:batch.length}});
      if(!d.success){ alert(d.error||'检测失败'); break; }
      (d.data.results||[]).forEach(r=>{ results[r.url] = {ok:r.ok, msg:r.msg, pan:r.pan, code:r.code}; });
      if(btn) btn.textContent = '检测中… '+(Math.min(i+BATCH, urls.length))+'/'+urls.length;
    }
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  }
  Object.assign(S, results);
  if(btn){ btn.disabled = false; btn.textContent = '🔎 检测失效'; }
  renderLocalPreview(id.r, id.c, id.w, items, holder, id.f);
  const ok = Object.keys(results).filter(k=>results[k].ok===true).length;
  const dead = Object.keys(results).filter(k=>results[k].ok===false).length;
  alert('检测完成：共 '+Object.keys(results).length+' 条，有效 '+ok+' 条，失效 '+dead+' 条');
}
function localRemoveRow(holder, i){
  if(holder==='LP_ITEMS'){ LP_ITEMS.splice(i,1); renderLocalPreview('lpResult','lpCount','lpImportRow', LP_ITEMS, 'LP_ITEMS', 'lpFilterRow'); }
  else { LE_ITEMS.splice(i,1); renderLocalPreview('leResult','leCount','leImportRow', LE_ITEMS, 'LE_ITEMS', 'leFilterRow'); }
}
async function localParse(){
  const text = document.getElementById('lpText').value.trim();
  if(!text){ alert('请先粘贴分享内容'); return; }
  const d = await busy(null, '解析中…', async ()=> await api('/api/v1/admin/local_parse',{method:'POST',body:{text}}));
  if(!d.success){ alert(d.error); return; }
  LP_ITEMS = d.data.items || [];
  LP_STATUS = {};  // 重新解析后清空旧的检测状态
  LP_FILTER = {pan:'', matched:'', status:''};
  renderLocalPreview('lpResult','lpCount','lpImportRow', LP_ITEMS, 'LP_ITEMS', 'lpFilterRow');
}
function localParseClear(){
  LP_ITEMS=[]; LP_STATUS={}; LP_FILTER={pan:'', matched:'', status:''};
  document.getElementById('lpResult').innerHTML='';
  document.getElementById('lpImportRow').style.display='none';
  const lpfr=document.getElementById('lpFilterRow'); if(lpfr){ lpfr.innerHTML=''; lpfr.style.display='none'; }
  document.getElementById('lpText').value='';
  closeLocalModal();
}
async function localImportParsed(){
  if(!LP_ITEMS.length){ alert('没有可导入的资源'); return; }
  const type = document.getElementById('lpType').value;
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:LP_ITEMS, type}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  localParseClear();
}
async function localManualSubmit(){
  const title = document.getElementById('lmTitle').value.trim();
  const url = document.getElementById('lmUrl').value.trim();
  if(!title || !url){ alert('资源名称和地址必填'); return; }
  const item = {
    title: title,
    url: url,
    type: document.getElementById('lmType').value,
    poster: document.getElementById('lmPoster').value.trim(),
    overview: document.getElementById('lmOverview').value.trim(),
    genres: document.getElementById('lmGenres').value.trim()
  };
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:[item]}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  ['lmTitle','lmUrl','lmPoster','lmOverview','lmGenres'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  closeLocalModal();
}
async function localExcelParse(){
  const fileInput = document.getElementById('leFile');
  if(!fileInput.files || !fileInput.files.length){ alert('请先选择 Excel / CSV 文件'); return; }
  const fd = new FormData();
  fd.append('file', fileInput.files[0]);
  const d = await busy(null, '解析中…', async ()=> await api('/api/v1/admin/local_excel',{method:'POST', body: fd}));
  if(!d.success){ alert(d.error); return; }
  LE_ITEMS = d.data.items || [];
  LE_STATUS = {};  // 重新解析后清空旧的检测状态
  LE_FILTER = {pan:'', matched:'', status:''};
  renderLocalPreview('leResult','leCount','leImportRow', LE_ITEMS, 'LE_ITEMS', 'leFilterRow');
}
function localExcelClear(){
  LE_ITEMS=[]; LE_STATUS={}; LE_FILTER={pan:'', matched:'', status:''};
  document.getElementById('leResult').innerHTML='';
  document.getElementById('leImportRow').style.display='none';
  const lefr=document.getElementById('leFilterRow'); if(lefr){ lefr.innerHTML=''; lefr.style.display='none'; }
  document.getElementById('leFile').value='';
  closeLocalModal();
}
async function localExcelImport(){
  if(!LE_ITEMS.length){ alert('没有可导入的记录'); return; }
  const type = document.getElementById('leType').value;
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:LE_ITEMS, type}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  localExcelClear();
}

/* ---- 网盘扫码登录（v3.1.4）：弹二维码 → 2.5s 轮询 → 成功自动回填 Cookie 框 ---- */
let PANQR={pan:null,timer:null,state:null,busy:false};
function panQrStatus(t,color){
  const el=document.getElementById('panQrStatus'); if(!el) return;
  el.innerText=t||''; el.style.color=color||'';
}
async function panQrStart(code){
  PANQR.pan=code; PANQR.state=null;
  clearInterval(PANQR.timer); PANQR.timer=null;
  const m=document.getElementById('panQrModal');
  if(!m) return;
  m.style.display='flex';
  document.getElementById('panQrTitle').innerText=((PAN_ACCOUNTS[code]||{}).name||code)+' · 扫码登录';
  document.getElementById('panQrHint').innerText='';
  document.getElementById('panQrImg').innerHTML='<div class="hint">生成二维码中…</div>';
  panQrStatus('');
  const d=await api('/api/v1/admin/pan_qr_create',{method:'POST',body:{pan:code}});
  if(!d.success){
    document.getElementById('panQrImg').innerHTML='';
    panQrStatus('❌ '+(d.error||'发起扫码失败'),'#c0392b');
    return;
  }
  PANQR.state=d.data.state;
  const box=document.getElementById('panQrImg');
  if(d.data.mode==='image'){
    /* 百度：服务端直接返回二维码图片（data URI） */
    box.innerHTML='<div style="background:#fff;padding:10px;border-radius:12px"><img src="'+d.data.content+'" style="display:block;width:200px;height:200px" alt="二维码"></div>';
  } else {
    /* 夸克 / UC / 阿里：本地生成二维码（内容在浏览器里，不经第三方） */
    box.innerHTML='<div id="panQrQr" style="background:#fff;padding:10px;border-radius:12px"></div>';
    try{
      new QRCode(document.getElementById('panQrQr'),{text:d.data.content,width:200,height:200,correctLevel:QRCode.CorrectLevel.M});
    }catch(e){
      box.innerHTML='<div class="hint">二维码生成失败，请用右侧「获取Cookie」手动粘贴</div>';
      return;
    }
  }
  const app=(PAN_QR[code]&&PAN_QR[code].app)||'对应网盘 App';
  document.getElementById('panQrHint').innerText='请用'+app+'的「扫一扫」扫描二维码，然后在手机上确认登录。二维码 5 分钟左右过期。';
  panQrStatus('等待扫码…');
  PANQR.timer=setInterval(panQrTick,2500);
}
async function panQrTick(){
  if(!PANQR.pan||PANQR.busy) return;
  PANQR.busy=true;
  try{
    const d=await api('/api/v1/admin/pan_qr_poll',{method:'POST',body:{pan:PANQR.pan,state:PANQR.state}});
    if(!d.success){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('❌ '+(d.error||'轮询失败'),'#c0392b'); return; }
    const s=d.data.status||'waiting';
    if(s==='waiting'){ panQrStatus('等待扫码…'); }
    else if(s==='scanned'){ panQrStatus('已扫码，请在手机上确认'); }
    else if(s==='expired'){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('二维码已过期，请点「刷新二维码」','#d69e00'); }
    else if(s==='error'){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('❌ '+(d.data.note||'换取凭据失败'),'#c0392b'); }
    else if(s==='confirmed'){
      clearInterval(PANQR.timer); PANQR.timer=null;
      const key='pan_'+PANQR.pan+'_cookie', v=d.data.cookie||'';
      const el=document.getElementById('S_'+key);
      if(el) el.value=v;
      const pci=document.getElementById('pci_'+PANQR.pan);
      if(pci) pci.innerText='Cookie 已填写（'+v.length+' 字符）· 刚扫码获取';
      panQrStatus('✅ 登录成功，已回填到 Cookie 框','#1e9e6a');
      const msg=document.getElementById('siteMsg');
      if(msg){ msg.innerText='扫码成功：「'+((PAN_ACCOUNTS[PANQR.pan]||{}).name||PANQR.pan)+'」凭据已回填，请点页面底部「保存设置」保存。'; msg.style.color='#1e9e6a'; }
      setTimeout(panQrClose,1300);
    }
  } finally { PANQR.busy=false; }
}
function panQrRefresh(){ if(PANQR.pan) panQrStart(PANQR.pan); }
function panQrClose(){
  clearInterval(PANQR.timer); PANQR.timer=null; PANQR.pan=null; PANQR.state=null;
  const m=document.getElementById('panQrModal'); if(m) m.style.display='none';
}
</script>
</body>
</html>
