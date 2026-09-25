<?php
$ROOT=rtrim(str_replace('\\','/',dirname(__FILE__)),'/');if(!defined('ML_APP_VERSION')){define('ML_APP_VERSION','3.1.1');}require_once $ROOT.'/core/Guard.php';require_once $ROOT.'/core/Session.php';require_once $ROOT.'/core/InstallGuard.php';require_once $ROOT.'/core/Installer.php';$MLST=ml_install_state($ROOT);if(!empty($MLST['installed'])){ml_guard_deny();}$SESSION_OK=ml_session_boot();if($SESSION_OK&&empty($_SESSION['mlinst_csrf'])){$_SESSION['mlinst_csrf']=mlinst_rand_token();}$CSRF=$SESSION_OK&&isset($_SESSION['mlinst_csrf'])?(string)$_SESSION['mlinst_csrf']:'';function ml_ins_h($a){return htmlspecialchars((string)$a,ENT_QUOTES,'UTF-8');}$ENV=mlinst_env_check($ROOT);$ENV_OK=mlinst_env_ok($ENV);$RESULT=null;$ERR='';$FORM=array();$scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';$hostHdr=isset($_SERVER['HTTP_HOST'])?(string)$_SERVER['HTTP_HOST']:'';$DEF_URL=$hostHdr!==''?($scheme.'://'.$hostHdr):'';if(isset($_POST['do'])&&$_POST['do']==='install'){$FORM=$_POST;$tries=isset($_SESSION['mlinst_tries'])?(int)$_SESSION['mlinst_tries']:0;$lastTry=isset($_SESSION['mlinst_last'])?(int)$_SESSION['mlinst_last']:0;if($tries>=20&&(time()-$lastTry)<600){$ERR='尝试次数过多，请等 10 分钟后再试。';}elseif(!isset($_POST['csrf'])||!mlinst_eq($_POST['csrf'],$CSRF)){$ERR='页面已过期（安全令牌不匹配），请重新填写提交一次。';}elseif(isset($_POST['ml_hp'])&&trim((string)$_POST['ml_hp'])!==''){$ERR='检测到异常提交，已忽略。';}elseif(!$ENV_OK){$ERR='环境自检未通过，请先按下方红色提示处理后重试。';}else{$_SESSION['mlinst_tries']=$tries+1;$_SESSION['mlinst_last']=time();$RESULT=mlinst_install($ROOT,$_POST);if(empty($RESULT['ok'])){$ERR=(string)$RESULT['error'];}else{$_SESSION['mlinst_csrf']=mlinst_rand_token();$_SESSION['mlinst_tries']=0;}}}$DONE=($RESULT!==null&&!empty($RESULT['ok']));function ml_ins_val($a,$b,$c=''){return isset($a[$b])&&is_scalar($a[$b])?(string)$a[$b]:$c;}$V_HOST=ml_ins_val($FORM,'host','127.0.0.1');$V_PORT=ml_ins_val($FORM,'port','3306');$V_DB=ml_ins_val($FORM,'dbname','');$V_USER=ml_ins_val($FORM,'user','');$V_PASS=isset($FORM['pass'])?(string)$FORM['pass']:'';$V_NAME=ml_ins_val($FORM,'site_name','媒资资料库');$V_URL=ml_ins_val($FORM,'site_url',$DEF_URL);$V_AUSER=ml_ins_val($FORM,'admin_user','');$BASE_URL=$DEF_URL!==''?$DEF_URL:'https://你的域名';?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>安装向导 · 媒资资料库</title>
<meta name="robots" content="noindex,nofollow">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f1f5f9;--card:#fff;--line:#e2e8f0;--txt:#0f172a;--mut:#64748b;--brand:#4f46e5;--brand2:#7c3aed;--ok:#059669;--warn:#d97706;--bad:#dc2626;--soft:#f8fafc}
@media (prefers-color-scheme:dark){
  :root{--bg:#0b1120;--card:#131c31;--line:#243049;--txt:#e8edf7;--mut:#94a3b8;--soft:#0f1729}
}
html,body{min-height:100%}
body{background:var(--bg);color:var(--txt);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;font-size:14px;line-height:1.7;padding:28px 16px 56px}
.wrap{max-width:840px;margin:0 auto}
.hero{background:linear-gradient(120deg,var(--brand),var(--brand2));border-radius:18px;padding:26px 28px;color:#fff;box-shadow:0 16px 40px rgba(79,70,229,.24)}
.hero h1{font-size:21px;font-weight:700;letter-spacing:.2px}
.ver{font-size:13px;font-weight:500;opacity:.75;margin-left:6px;background:rgba(255,255,255,.15);padding:1px 8px;border-radius:999px}
.hero p{margin-top:6px;font-size:13px;opacity:.92;line-height:1.7}
.pill{display:inline-block;margin-bottom:12px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.32);border-radius:999px;padding:3px 12px;font-size:12px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px 24px;margin-top:16px;box-shadow:0 8px 26px rgba(15,23,42,.05)}
h2{font-size:15.5px;margin-bottom:4px;display:flex;align-items:center;gap:8px}
h2 .n{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:var(--brand);color:#fff;font-size:12px;font-weight:700;flex:0 0 auto}
.sub{color:var(--mut);font-size:12.5px;margin-bottom:14px}
table.env{width:100%;border-collapse:collapse}
table.env td{padding:7px 0;border-bottom:1px solid var(--line);font-size:13px;vertical-align:top}
table.env tr:last-child td{border-bottom:0}
.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:8px;flex:0 0 auto;transform:translateY(-1px)}
.d-ok{background:var(--ok)}.d-warn{background:var(--warn)}.d-fail{background:var(--bad)}
.fix{color:var(--bad);font-size:12px;margin-top:2px}
.muted{color:var(--mut)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:13px 16px;margin-top:6px}
.grid .full{grid-column:1/-1}
label{display:block;font-size:12.5px;color:var(--mut);margin-bottom:5px}
input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--soft);color:var(--txt);font-size:13.5px;font-family:inherit;outline:none;transition:.15s}
input[type=text]:focus,input[type=password]:focus{border-color:var(--brand);background:var(--card);box-shadow:0 0 0 3px rgba(79,70,229,.14)}
.hint{color:var(--mut);font-size:11.5px;margin-top:4px}
.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 26px;border:0;border-radius:11px;background:linear-gradient(120deg,var(--brand),var(--brand2));color:#fff;font-size:14.5px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;box-shadow:0 10px 24px rgba(79,70,229,.28);transition:.15s}
.btn:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(79,70,229,.34)}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--txt);box-shadow:none;font-weight:500;padding:10px 18px;font-size:13px}
.btn.ghost:hover{border-color:var(--brand);color:var(--brand)}
.acts{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px}
.err{margin-top:16px;background:rgba(220,38,38,.09);border:1px solid rgba(220,38,38,.32);color:var(--bad);border-radius:12px;padding:13px 16px;font-size:13px}
.tips{margin-top:10px;font-size:12.5px;color:var(--mut);padding-left:18px}
.tips li{margin:3px 0}
ol.steps{list-style:none;counter-reset:s}
ol.steps li{counter-increment:s;position:relative;padding:9px 0 9px 34px;border-bottom:1px solid var(--line);font-size:13px}
ol.steps li:last-child{border-bottom:0}
ol.steps li::before{content:counter(s);position:absolute;left:0;top:9px;width:22px;height:22px;border-radius:50%;background:rgba(5,150,105,.14);color:var(--ok);font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center}
.tk{font-family:Consolas,Monaco,monospace;background:var(--soft);border:1px dashed var(--brand);border-radius:10px;padding:11px 14px;font-size:15px;font-weight:700;letter-spacing:.6px;color:var(--brand);word-break:break-all;margin:6px 0 4px}
code{font-family:Consolas,Monaco,monospace;background:var(--soft);border:1px solid var(--line);border-radius:5px;padding:1px 6px;font-size:12.5px;word-break:break-all}
pre{background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:12px 14px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.6;margin-top:8px}
.okbox{background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.3);border-radius:12px;padding:14px 16px;font-size:13px}
.okbox b{color:var(--ok)}
.lock{background:rgba(100,116,139,.1);border:1px solid var(--line);border-radius:12px;padding:12px 16px;font-size:12.5px;color:var(--mut);margin-top:14px}
.foot{text-align:center;color:var(--mut);font-size:12px;margin-top:22px}
@media (max-width:640px){
  body{padding:16px 12px 40px}
  .hero{padding:20px 18px;border-radius:14px}
  .hero h1{font-size:18px}
  .card{padding:18px 16px;border-radius:14px}
  .grid{grid-template-columns:1fr}
  .btn{width:100%}
  .acts{gap:9px}
}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <div class="pill"><?php echo $DONE?'✅ 安装完成':'🛠 安装向导';?></div>
    <h1>媒资资料库 · <?php echo $DONE?'安装完成':'网页安装向导';?> <span class="ver">v<?php echo defined('ML_APP_VERSION')?ML_APP_VERSION:'2.2.8';?></span></h1>
    <p><?php echo $DONE?'数据库已就绪、配置文件已生成、管理员账号已创建。可以直接登录后台了。':'跟着下面两步走：环境自检 → 填数据库与管理员账号 → 点一下安装。';?></p>
  </div>

<?php if($DONE):?>

  <div class="card">
    <h2><span class="n">✓</span>安装成功</h2>
    <div class="sub">数据库已连接<?php echo $RESULT['mysql']!==''?'（MySQL '.ml_ins_h($RESULT['mysql']).'）':'';?>，表结构与配置文件均已就绪。</div>
    <ol class="steps">
      <?php foreach($RESULT['steps']as $st):?>
      <li><?php echo ml_ins_h($st['label']);?> —— <?php echo ml_ins_h($st['detail']);?></li>
      <?php endforeach;?>
    </ol>
    <div class="okbox" style="margin-top:16px">
      <b>🔑 管理员账号（用于登录后台）</b>
      <div class="tk"><?php echo ml_ins_h($RESULT['admin_user']);?></div>
      <div class="muted" style="font-size:12px">密码是你刚才填的那个。用这个账号登录后台；忘记了可以删掉 <code>app_users</code> 里这条记录、重新安装，或让我给你加个改密入口。</div>
    </div>
    <div class="lock" style="margin-top:14px">💡 <b>给外部调用者的 API 令牌不在这里发。</b>
      等进后台后，到「<b>API 调用</b>」菜单里逐个创建 —— 每个调用者一个令牌，可分别限额、限 IP、查调用记录。</div>
  </div>

  <div class="card">
    <h2><span class="n">→</span>接下来做什么</h2>
    <div class="sub">按顺序来，两分钟能全部搞定。</div>
    <ul class="tips" style="list-style:none;padding:0">
      <li style="margin:8px 0"><b>1. 配伪静态（必须，否则内页与后台都会 404）</b><br>
        宝塔 → 网站 → 设置 → <b>伪静态</b> → 选「自定义」，粘贴：<br>
        <pre>location / { try_files $uri $uri/ /index.php?$query_string; }

location ^~ /config/   { return 404; }
location ^~ /sql/      { return 404; }
location ^~ /core/     { return 404; }
location ^~ /cron/     { return 404; }
location ^~ /adapters/ { return 404; }
location ^~ /tools/    { return 404; }

location ~* \.(sql|md|lock|bak|ini|log|env|sh|yml|yaml|conf|sample)$ { return 404; }
location ~* ^/(setup|upgrade|update) { return 404; }</pre>
        <span class="muted">注意：<code>^~</code> 不能省；这条规则里<b>故意没有</b> install —— 安装器靠自身判定，已装好就自动 404。</span>
      </li>
      <li style="margin:8px 0"><b>2. 登录后台并配置</b>：<code><?php echo ml_ins_h($BASE_URL);?>/admin</code> —— 用上面的管理员账号登录<br>
        进去后到「<b>系统设置</b>」填 TMDB Key；到「<b>站点设置</b>」填站点名称、网址、SEO。</li>
      <li style="margin:8px 0"><b>3. 跑一次自检</b>：<code><?php echo ml_ins_h($BASE_URL);?>/check.php</code></li>
      <li style="margin:8px 0"><b>4. 采集数据</b>：后台 →「同步采集」→ 同步热门；再到宝塔「计划任务」加每小时任务
        <code>php <?php echo ml_ins_h($ROOT);?>/cron/sync_hourly.php</code></li>
      <li style="margin:8px 0"><b>5. 开放 API 给他人</b>：后台 →「<b>API 调用</b>」→ 新建令牌，把令牌和调用方式发给调用者。</li>
      <li style="margin:8px 0"><b>6. 清理</b>：<code>config/install-info.txt</code>（含账号备忘，记下后删掉）；
        <code>check.php</code> 确认无误后可改名或删除。</li>
    </ul>
    <div class="lock">🔒 <b>安装器已自动锁死。</b>已写入 <code>config/install.lock</code>，从现在起
      <code>/install.php</code> 对任何人（包括你）都返回 404，与文件不存在完全一样。
      将来要重装或迁移服务器：删掉 <code>config/install.lock</code> 与 <code>config/config.php</code> 后本页面会重新可用，
      或者在终端执行 <code>php tools/install-cli.php --force</code>。</div>
    <div class="acts">
      <a class="btn" href="<?php echo ml_ins_h($BASE_URL);?>/admin">进入后台管理</a>
      <a class="btn ghost" href="<?php echo ml_ins_h($BASE_URL);?>/check.php">打开环境自检</a>
    </div>
  </div>

<?php else:?>

  <div class="card">
    <h2><span class="n">1</span>环境自检</h2>
    <div class="sub">不通过的项目会标红，按提示处理后刷新本页即可。</div>
    <table class="env">
      <?php foreach($ENV as $it):?>
      <tr>
        <td style="width:190px;white-space:nowrap"><span class="dot d-<?php echo ml_ins_h($it['level']);?>"></span><?php echo ml_ins_h($it['label']);?></td>
        <td>
          <?php echo ml_ins_h($it['detail']);?>
          <?php if(!empty($it['fix'])&&$it['level']!=='ok'):?><div class="fix">→ <?php echo ml_ins_h($it['fix']);?></div><?php endif;?>
        </td>
      </tr>
      <?php endforeach;?>
    </table>
  </div>

  <form method="post" action="install.php" autocomplete="off">
  <input type="hidden" name="do" value="install">
  <input type="hidden" name="csrf" value="<?php echo ml_ins_h($CSRF);?>">
  <input class="hp" type="text" name="ml_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
  <input type="hidden" name="site_url_def" value="<?php echo ml_ins_h($DEF_URL);?>">

  <div class="card">
    <h2><span class="n">2</span>数据库与管理员账号</h2>
    <div class="sub">这里建的是<b>你自己的管理员账号</b>（登录后台用）。发给别人的 API 令牌后续在后台「API 调用」页创建。</div>
    <div class="grid">
      <div>
        <label>数据库地址</label>
        <input type="text" name="host" value="<?php echo ml_ins_h($V_HOST);?>" placeholder="127.0.0.1">
        <div class="hint">宝塔本机就填 127.0.0.1；独立数据库服务器填它的 IP</div>
      </div>
      <div>
        <label>端口</label>
        <input type="text" name="port" value="<?php echo ml_ins_h($V_PORT);?>" placeholder="3306">
        <div class="hint">默认 3306</div>
      </div>
      <div>
        <label>数据库名</label>
        <input type="text" name="dbname" value="<?php echo ml_ins_h($V_DB);?>" placeholder="media_library" required>
      </div>
      <div>
        <label>数据库用户名</label>
        <input type="text" name="user" value="<?php echo ml_ins_h($V_USER);?>" placeholder="media_library" required>
      </div>
      <div class="full">
        <label>数据库密码</label>
        <input type="text" name="pass" value="<?php echo ml_ins_h($V_PASS);?>" placeholder="宝塔建库时设的密码">
        <div class="hint">如果 MySQL 8 账号认证异常，请在宝塔数据库设置中重新设置该用户密码</div>
      </div>
      <div class="full" style="border-top:1px solid var(--line);padding-top:16px;margin-top:8px">
        <label>管理员账号 <span style="color:var(--bad)">*</span></label>
        <input type="text" name="admin_user" value="<?php echo ml_ins_h($V_AUSER);?>" placeholder="推荐直接填邮箱，如 admin@example.com" required autocomplete="off">
        <div class="hint">这是<b>你自己的</b>后台账号，可用邮箱（推荐，忘记密码时重置邮件发这里）或 3~32 位字母数字。别忘。</div>
      </div>
      <div>
        <label>管理员密码 <span style="color:var(--bad)">*</span></label>
        <input type="password" name="admin_pass" value="" placeholder="至少 6 位" required autocomplete="new-password">
      </div>
      <div>
        <label>再输一次密码 <span style="color:var(--bad)">*</span></label>
        <input type="password" name="admin_pass2" value="" placeholder="两次要一致" required autocomplete="new-password">
      </div>
      <div class="full" style="border-top:1px solid var(--line);padding-top:16px;margin-top:8px">
        <label>站点名称</label>
        <input type="text" name="site_name" value="<?php echo ml_ins_h($V_NAME);?>" placeholder="媒资资料库">
      </div>
      <div class="full">
        <label>站点网址</label>
        <input type="text" name="site_url" value="<?php echo ml_ins_h($V_URL);?>" placeholder="https://你的域名">
        <div class="hint">用于 canonical / robots.txt / sitemap.xml，结尾不要带 /</div>
      </div>
    </div>

    <?php if($ERR!==''):?>
    <div class="err">
      <b>安装未完成：</b><?php echo ml_ins_h($ERR);?>
      <?php if($RESULT!==null&&!empty($RESULT['steps'])):?>
      <div style="margin-top:8px">
        <?php foreach($RESULT['steps']as $st):?>
          <div><?php echo $st['ok']?'✅':'❌';?> <?php echo ml_ins_h($st['label']);?> —— <?php echo ml_ins_h($st['detail']);?></div>
        <?php endforeach;?>
      </div>
      <?php endif;?>
      <?php if(stripos($ERR,'连接失败')!==false):?>
      <div style="margin-top:8px">常见原因：<ul class="tips">
        <?php foreach(mlinst_dsn_tip()as $tp):?><li><?php echo ml_ins_h($tp);?></li><?php endforeach;?>
      </ul></div>
      <?php endif;?>
    </div>
    <?php endif;?>

    <div class="acts">
      <button class="btn" type="submit"<?php echo $ENV_OK?'':' disabled style="opacity:.5;cursor:not-allowed"';?>>开始安装</button>
      <span class="muted" style="font-size:12.5px">安装会：建 6 张表 → 生成 config/config.php → 写 config/install.lock 锁死本页面</span>
    </div>
    <?php if(!$ENV_OK):?>
    <div class="err">环境自检有未通过项，先按上面红色提示处理后再刷新本页。</div>
    <?php endif;?>
  </div>
  </form>

  <div class="lock">🔒 <b>关于安全：</b>本站<b>没有</b>常驻的安装器。本页面只在「尚未安装」时可用 ——
    一旦写入 <code>config/install.lock</code>（或你手填的 <code>config/config.php</code> 里数据库信息已生效），
    它立刻对所有访问者返回 404，没人能借此重装站点。
    想用终端安装也行：<code>php tools/install-cli.php</code>（两份入口共用同一套安装内核）。</div>

<?php endif;?>

  <div class="foot">媒资资料库 · 安装向导 ｜ 未安装状态专用页面</div>
</div>
</body>
</html>
