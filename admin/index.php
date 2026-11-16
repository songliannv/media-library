<?php
use Core\Config;use Core\User;if(!defined('ML_APP')){define('ML_APP',true);}if(!defined('ML_ROOT')){define('ML_ROOT',dirname(__DIR__));}if(!defined('ML_APP_VERSION')&&is_file(__DIR__.'/../core/Version.php')){require_once __DIR__.'/../core/Version.php';}if(!defined('ML_APP_VERSION')){define('ML_APP_VERSION','未同步');}header('Content-Type: text/html; charset=utf-8');header('X-Robots-Tag: noindex, nofollow');require_once __DIR__.'/../core/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/../core/Session.php';require_once __DIR__.'/../core/DB.php';require_once __DIR__.'/../core/Config.php';require_once __DIR__.'/../core/User.php';function ml_admin_asset($a){$b=trim((string)Config::sub('admin','dir','admin'),'/');if($b===''||!preg_match('/^[A-Za-z0-9_-]+$/',$b)){$b='admin';}$c=explode('/',$b);array_pop($c);$d=$c?'/'.implode('/',$c):'';return $d.'/assets/'.ltrim((string)$a,'/');}function ml_admin_js_list(){return array('admin-core.js','admin-overview.js','admin-api.js','admin-items.js','admin-sync.js','admin-links.js','admin-site.js','admin-request.js','admin-shell.js','admin-media.js','admin-pan.js','admin-boot.js',);}$SESSION_OK=ml_session_boot(User::SESS);function ml_ad_csrf(){if(empty($_SESSION['ml_ad_csrf'])){$_SESSION['ml_ad_csrf']=bin2hex(random_bytes(32));}return(string)$_SESSION['ml_ad_csrf'];}$ADMIN_DIR=trim((string)Config::sub('admin','dir','admin'),'/');if($ADMIN_DIR===''||!preg_match('/^[A-Za-z0-9_-]+$/',$ADMIN_DIR)||substr($ADMIN_DIR,-5)!=='admin'){$ADMIN_DIR='admin';}$ADMIN_URL='/'.$ADMIN_DIR.'/';$LOGIN_ERR='';$LOGIN_OK='';$FOCUS='';$__adDo=isset($_POST['ad_do'])?(string)$_POST['ad_do']:'';if($__adDo===''&&isset($_POST['u'],$_POST['p'])){$__adDo='login';}if($__adDo==='login'){$FOCUS='user';if(!$SESSION_OK){$LOGIN_ERR='服务器无法保存登录会话（session 不可写），所以登不上去。'.'请打开 check.php 看「会话」一栏，或让服务商把 PHP 的 session.save_path 目录设为可写。';}elseif(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$LOGIN_ERR='页面已过期（登录会话没保持住），请再提交一次；若反复如此，请打开 check.php 看「会话」一栏。';}elseif(isset($_POST['ml_hp'])&&trim((string)$_POST['ml_hp'])!==''){$LOGIN_ERR='检测到异常提交，已忽略。';}else{$u=isset($_POST['u'])?(string)$_POST['u']:'';$p=isset($_POST['p'])?(string)$_POST['p']:'';$r=User::login($u,$p);if(!empty($r['ok'])){if(User::isAdmin()){header('Location: '.$ADMIN_URL);exit;}User::logout();$LOGIN_ERR='该账号不是管理员，无法进入后台。';}else{$LOGIN_ERR=(string)$r['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='forgot'){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$LOGIN_ERR='页面已过期，请重新提交。';}else{$r=User::requestReset(isset($_POST['account'])?(string)$_POST['account']:'','admin');if(!empty($r['ok'])){$LOGIN_OK=(string)$r['msg'];}else{$LOGIN_ERR=(string)$r['msg'];}}}if(isset($_POST['ad_do'])&&$_POST['ad_do']==='reset'){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$LOGIN_ERR='页面已过期，请重新提交。';}else{$r=User::doReset(isset($_POST['token'])?(string)$_POST['token']:'',isset($_POST['new'])?(string)$_POST['new']:'',isset($_POST['new2'])?(string)$_POST['new2']:'');if(!empty($r['ok'])){$LOGIN_OK=(string)$r['msg'].' 请用新密码登录。';}else{$LOGIN_ERR=(string)$r['msg'];}}}if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['logout'])){if(!isset($_POST['csrf'])||!User::secureEquals(ml_ad_csrf(),(string)$_POST['csrf'])){$LOGIN_ERR='页面已过期，请重新提交。';}else{User::logout();header('Location: '.$ADMIN_URL);exit;}}$ME=User::me();if(!$ME||!User::isAdmin()){$MODE='login';$RESET_TOKEN='';if(isset($_GET['reset'])){$MODE='reset';$RESET_TOKEN=(string)$_GET['reset'];}elseif(isset($_GET['forgot'])){$MODE='forgot';}if($LOGIN_OK!==''&&$MODE==='reset'){$MODE='login';}$resetValid=($MODE==='reset')?(User::verifyResetToken($RESET_TOKEN)!==null):false;define('ML_ADMIN_UI_CLOSED',true);require __DIR__.'/ui/login.php';exit;}define('ML_ADMIN_UI',true);?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>媒资资料库 · 管理后台</title>
<link rel="stylesheet" href="<?php echo ml_admin_asset('css/admin.css');?>?v=<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'1';?>">
<!-- ★ v5.2.8：后台设计系统 v2（精修层）—— 必须排在 admin.css 之后，
     只在同名类上做视觉升级，不新增/不改任何既有类名；删掉这行即可完全回滚。 -->
<link rel="stylesheet" href="<?php echo ml_admin_asset('css/admin-theme.css');?>?v=<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'1';?>">
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
<body data-admin-dir="<?php echo htmlspecialchars((string)$ADMIN_DIR,ENT_QUOTES,'UTF-8');?>">
<?php ?>
<div class="admin-layout">
<?php require __DIR__.'/ui/topbar.php';?>

<main class="admin-main">
  <div class="content-topbar">
    <button type="button" class="menubtn" onclick="toggleSidebar()" aria-label="折叠 / 展开菜单">☰</button>
    <div class="ct-title" id="ctTitle">概览</div>
    <div class="ct-actions">
      <button type="button" class="ghost sm" onclick="clearAllCache(this)" title="清除标签/人物/接口等全部文件缓存与多级缓存，下次加载自动重建">清除缓存</button>
      <button type="button" class="ghost sm" onclick="openPwd()">修改密码</button>
      <button type="button" class="themebtn" onclick="toggleTheme()" title="切换明暗主题">🌗</button>
    </div>
  </div>
  <div class="admin-content">
<?php ?>
<?php require __DIR__.'/ui/tab-overview.php';?>
<?php require __DIR__.'/ui/tab-items.php';?>
<?php require __DIR__.'/ui/tab-pending.php';?>
<?php require __DIR__.'/ui/tab-tags.php';?>
<?php require __DIR__.'/ui/tab-site.php';?>
<?php require __DIR__.'/ui/tab-request.php';?>
<?php require __DIR__.'/ui/tab-apitoken.php';?>
<?php require __DIR__.'/ui/tab-scrape.php';?>
<?php require __DIR__.'/ui/tab-matching.php';?>
<?php require __DIR__.'/ui/tab-local.php';?>
<?php require __DIR__.'/ui/tab-source.php';?>
<!-- ★ v5.2.9：「用户中心」页签已下线（与「资源请求 → 注册用户」重复），
     用户详情面板随迁到 admin/ui/tab-request.php。 -->

  </div>
</main>
</div>

<?php require __DIR__.'/ui/modals.php';?>

<script src="<?php echo ml_admin_asset('js/qrcode.min.js');?>?v=<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'1';?>" defer></script>

<?php ?>
<?php foreach(ml_admin_js_list()as $__js):?>
<script src="<?php echo ml_admin_asset('js/'.$__js);?>?v=<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'1';?>" defer></script>
<?php endforeach;?>
</body>
</html>
