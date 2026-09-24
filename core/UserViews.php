<?php
namespace Core;require_once __DIR__.'/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/Config.php';require_once __DIR__.'/Settings.php';require_once __DIR__.'/Categories.php';require_once __DIR__.'/Site.php';require_once __DIR__.'/User.php';require_once __DIR__.'/Views.php';class UserViews{public static function esc($a){return Views::esc($a);}private static function label($a){return Categories::label($a);}private static function typeOptions($a){$a=(string)$a;$b='<option value="">不限 / 其他</option>';foreach(Categories::TYPES as $c){$b.='<option value="'.self::esc($c).'"'.($c===$a?' selected':'').'>'.self::esc(self::label($c)).'</option>';}return $b;}public static function head($a,$b='',$c=true){$d=Site::info();$e=$d['name'];$f=($a!==''?$a.' - '.$e:$e);$g='<title>'.self::esc($f).'</title>'."\n";if($b!==''){$g.='<meta name="description" content="'.self::esc($b).'">'."\n";}$g.=$c?'<meta name="robots" content="noindex,follow">'."\n":'<meta name="robots" content="index,follow">'."\n";if($d['favicon']!==''){$g.='<link rel="icon" href="'.self::esc(Site::asset($d['favicon'])).'">'."\n";}$h=trim((string)Config::sub('seo','stats_code',''));if($h!==''){$g.=$h."\n";}return $g;}public static function shell($a,$b,$c=true,$d='',$e=''){$f=self::head($a,$e,$c);$g=Views::topbarHtml($d);$h=Views::footerHtml();$i=Views::bodyClass();$j=Views::css().self::ucss();$k=<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$f}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$i}">
{$g}
<div class="u-wrap">
{$b}
</div>
{$h}
</body>
</html>
HTML;return str_replace('{CSS_PLACEHOLDER}',$j,$k);}public static function flashHtml(){$a=User::flashGet();if(!$a){return'';}$b=($a['kind']==='ok')?'ok':(($a['kind']==='info')?'info':'err');return'<div class="u-flash '.$b.'">'.self::esc($a['msg']).'</div>';}public static function registerHtml(array $a=array()){$b=User::cfg();$c='注册账号';$d='<a class="admin" href="/">← 返回主页</a>';if(!$b['open']){$e='<div class="u-card"><div class="u-lock">'.'<div class="ico">🔒</div><h3>暂未开放注册</h3>'.'<p>站长关闭了注册入口。已注册的用户可以直接登录。</p>'.'<a class="u-btn" href="/user/login">去登录</a>'.'</div></div>';return self::shell($c,$e,true,$d);}$f='';if($b['verify']){$g=User::captchaNew();$f='<div class="u-field"><label>验证码 *</label>'.'<div class="u-cap"><span class="q">'.self::esc($g).'</span>'.'<input class="u-in" type="text" name="captcha" inputmode="numeric" autocomplete="off" placeholder="填计算结果" required>'.'</div><div class="tip">防止机器人批量注册，算一下就好。</div></div>';}$h=$b['audit']?'<div class="u-flash info">本站开启了注册审核：提交后需要管理员通过才能登录，请留意站长的回复。</div>':'';$i=isset($a['username'])?(string)$a['username']:'';$j=isset($a['email'])?(string)$a['email']:'';$k=<<<HTML
{$h}
<form method="post" action="/user/register" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input class="u-hp" type="text" name="ml_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="u-field">
    <label>邮箱 *</label>
    <input class="u-in" type="email" name="email" value="{E}" maxlength="128" placeholder="邮箱即账号，登录与找回密码都用它" required>
    <div class="tip">账号就是邮箱，注册后不可修改；忘记密码时重置链接会发到这里。</div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>密码 *</label>
      <input class="u-in" type="password" name="password" minlength="6" maxlength="64" placeholder="至少 6 位" required>
    </div>
    <div class="u-field">
      <label>确认密码 *</label>
      <input class="u-in" type="password" name="password2" minlength="6" maxlength="64" placeholder="再输一次" required>
    </div>
  </div>
  {CAP}
  <button class="u-btn" type="submit">注册并登录</button>
</form>
<p class="u-alt">已经有账号了？<a href="/user/login">直接登录</a></p>
HTML;$k=str_replace(array('{CSRF}','{CAP}'),array(self::esc(User::csrf()),$f),$k);$k=str_replace(array('{U}','{E}'),array(self::esc($i),self::esc($j)),$k);$e='<div class="u-card u-narrow">'.'<div class="u-head"><h1>注册账号</h1>'.'<p>注册后即可在<a href="/request">资源请求</a>页填写你想找的资源，站长会逐条处理。</p></div>'.self::flashHtml().$k.'</div>';return self::shell($c,$e,true,$d,'注册本站账号，提交你想找的资源。');}public static function loginHtml(array $a=array()){$b=User::cfg();$c='登录';$d='<a class="admin" href="/">← 返回主页</a>';$e=$b['open']?'<p class="u-alt">还没有账号？<a href="/user/register">注册一个</a></p>':'<p class="u-alt">本站暂未开放注册，请联系站长开通账号。</p>';$f=isset($a['username'])?(string)$a['username']:'';$g=<<<HTML
<form method="post" action="/user/login" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>邮箱</label>
    <input class="u-in" type="email" name="username" value="{U}" maxlength="128" autocomplete="username" placeholder="注册时的邮箱" required>
  </div>
  <div class="u-field">
    <label>密码</label>
    <input class="u-in" type="password" name="password" maxlength="64" autocomplete="current-password" required>
  </div>
  <button class="u-btn" type="submit">登录</button>
</form>
<p class="u-alt"><a href="/user/forgot">忘记密码？</a></p>
{$e}
HTML;$g=str_replace('{CSRF}',self::esc(User::csrf()),$g);$g=str_replace('{U}',self::esc($f),$g);$h='<div class="u-card u-narrow">'.'<div class="u-head"><h1>登录</h1><p>登录后即可提交与查看你的资源请求。</p></div>'.self::flashHtml().$g.'</div>';return self::shell($c,$h,true,$d,'登录本站账号，提交你想找的资源。');}public static function forgotHtml(){$a='找回密码';$b='<a class="admin" href="/">← 返回主页</a>';if(!Mail::ready()){$c='<div class="u-card u-narrow">'.'<div class="u-head"><h1>找回密码</h1></div>'.self::flashHtml().'<div class="u-lock"><div class="ico">📮</div>'.'<h3>本站还没有配置邮件服务</h3>'.'<p>站长尚未开启 SMTP 发信，暂时无法自助找回密码。<br>请直接联系站长重置，给您带来不便敬请谅解。</p>'.'<a class="u-btn ghost" href="/user/login">返回登录</a>'.'</div></div>';return self::shell($a,$c,true,$b,'找回账号密码。');}$d=<<<HTML
<form method="post" action="/user/forgot" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input class="u-hp" type="text" name="ml_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="u-field">
    <label>注册邮箱</label>
    <input class="u-in" type="email" name="account" maxlength="128" placeholder="注册时用的邮箱" required>
    <div class="tip">系统会把「设置新密码」的链接发到这个邮箱，链接 60 分钟内有效。</div>
  </div>
  <button class="u-btn" type="submit">发送找回邮件</button>
</form>
<p class="u-alt">想起来了？<a href="/user/login">返回登录</a></p>
HTML;$d=str_replace('{CSRF}',self::esc(User::csrf()),$d);$c='<div class="u-card u-narrow">'.'<div class="u-head"><h1>找回密码</h1><p>填入账号信息，我们把重置链接发到你的注册邮箱。</p></div>'.self::flashHtml().$d.'</div>';return self::shell($a,$c,true,$b,'找回账号密码。');}public static function resetHtml($a,$b){$c='设置新密码';$d='<a class="admin" href="/">← 返回主页</a>';if(!$b){$e='<div class="u-card u-narrow">'.'<div class="u-head"><h1>设置新密码</h1></div>'.self::flashHtml().'<div class="u-lock"><div class="ico">⏳</div>'.'<h3>链接无效或已过期</h3>'.'<p>重置链接只能使用一次，且 60 分钟内有效。<br>请重新走一遍「找回密码」获取新链接。</p>'.'<a class="u-btn" href="/user/forgot">重新找回密码</a>'.'</div></div>';return self::shell($c,$e,true,$d,'设置新密码。');}$f=<<<HTML
<form method="post" action="/user/reset" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input type="hidden" name="token" value="{TOKEN}">
  <div class="u-field">
    <label>新密码</label>
    <input class="u-in" type="password" name="password" minlength="6" maxlength="64" placeholder="至少 6 位" required>
  </div>
  <div class="u-field">
    <label>确认新密码</label>
    <input class="u-in" type="password" name="password2" minlength="6" maxlength="64" placeholder="再输一次" required>
  </div>
  <button class="u-btn" type="submit">保存新密码</button>
</form>
HTML;$f=str_replace(array('{CSRF}','{TOKEN}'),array(self::esc(User::csrf()),self::esc($a)),$f);$e='<div class="u-card u-narrow">'.'<div class="u-head"><h1>设置新密码</h1><p>链接验证通过，请设置新的登录密码。</p></div>'.self::flashHtml().$f.'</div>';return self::shell($c,$e,true,$d,'设置新密码。');}public static function passwordHtml(array $a){$b='修改密码';$c='<a class="admin" href="/">← 返回主页</a>';$d=(isset($a['email'])&&(string)$a['email']!=='')?(string)$a['email']:'';$e=($d!=='')?'账号邮箱：'.self::esc($d).'。忘记密码时，重置链接会发到这里。':'<b style="color:#c0392b">还没绑定邮箱</b>——新注册的账号邮箱即账号；老账号建议补绑一个常用邮箱，忘记密码才能自助重置。';$f=<<<HTML
<form method="post" action="/user/password" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>账号邮箱</label>
    <input class="u-in" type="email" name="email" value="{EMAIL}" maxlength="128" placeholder="用于登录与找回密码">
    <div class="tip">{EMAIL_TIP}</div>
  </div>
  <div class="u-field">
    <label>当前密码</label>
    <input class="u-in" type="password" name="old" maxlength="64" autocomplete="current-password" placeholder="填现在的登录密码">
    <div class="tip">只想绑定邮箱、不改密码的话，下面两项留空即可。</div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>新密码</label>
      <input class="u-in" type="password" name="new" minlength="6" maxlength="64" autocomplete="new-password" placeholder="至少 6 位">
    </div>
    <div class="u-field">
      <label>确认新密码</label>
      <input class="u-in" type="password" name="new2" minlength="6" maxlength="64" autocomplete="new-password" placeholder="再输一次">
    </div>
  </div>
  <button class="u-btn" type="submit">保存</button>
</form>
<p class="u-alt"><a href="/request">返回资源请求</a></p>
HTML;$f=str_replace(array('{CSRF}','{EMAIL}','{EMAIL_TIP}'),array(self::esc(User::csrf()),self::esc($d),$e),$f);$g='<div class="u-card u-narrow">'.'<div class="u-head"><h1>修改密码</h1>'.'<p>改密码只需当前密码确认；邮箱绑定好之后，忘记密码就能自助重置了。</p></div>'.self::flashHtml().$f.'</div>';return self::shell($b,$g,true,$c,'修改账号密码或绑定邮箱。');}public static function requestHtml(array $a){$b=User::cfg();$c=User::pageTitle();$d=isset($a['me'])?$a['me']:null;$e='<a class="admin" href="/">← 返回主页</a>';if($d){$e='<a class="admin" href="/user/password">修改密码</a>'.$e;}$f='<div class="u-head"><h1>'.self::esc($c).'</h1>';$g=$b['page_intro']!==''?$b['page_intro']:'想看但站里还没收录的资源，写在这里，站长会定期处理。';$f.='<p>'.User::text($g).'</p></div>';$h=isset($a['counts'])?$a['counts']:array();$i=User::reqStatusText();$j='<div class="u-stats">';$j.='<span class="u-pill">共 <b>'.(int)(isset($h['total'])?$h['total']:0).'</b> 条</span>';foreach($i as $l=>$k){$j.='<span class="u-pill">'.self::esc($k).' <b>'.(int)(isset($h[$l])?$h[$l]:0).'</b></span>';}$j.='</div>';$m=($b['logon']&&!$d);$n='';if($m){$o='';if($b['open']){$o.='<a class="u-btn" href="/user/register">注册新账号</a> ';}$o.='<a class="u-btn ghost" href="/user/login">已有账号，去登录</a>';$n='<div class="u-lock"><div class="ico">🔐</div>'.'<h3>请先注册并登录</h3>'.'<p>本站的资源请求需要账号才能提交，这样你也能在页面里随时查看处理进度。</p>'.$o.'</div>';}else{$p='';if($b['contact']){$q=($d&&!empty($d['email']))?(string)$d['email']:'';$p='<div class="u-field"><label>联系方式（选填）</label>'.'<input class="u-in" type="text" name="contact" value="'.self::esc($q).'" maxlength="128"'.' placeholder="邮箱 / 微信 / QQ，方便找到后通知你"></div>';}$r=$b['daily']>0?'每人每天最多提交 '.(int)$b['daily'].' 条；':'';$n=<<<HTML
<form method="post" action="/request" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>要找的资源名称 *</label>
    <input class="u-in" type="text" id="rq_title" name="title" maxlength="120" placeholder="例如：庆余年 第二季" required>
    <div class="u-hintbox" id="rqHint"></div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>类型</label>
      <select class="u-in" name="type">{TYPES}</select>
    </div>
    <div class="u-field">
      <label>年份（选填）</label>
      <input class="u-in" type="number" name="year" min="1900" max="2100" placeholder="如 2024">
    </div>
  </div>
  {CONTACT}
  <div class="u-field">
    <label>补充说明（选填）</label>
    <textarea class="u-in" name="note" maxlength="500" placeholder="哪个版本 / 清晰度 / 字幕要求 / 其他线索…"></textarea>
    <div class="tip">写清楚一点，站长更容易找对。{$r}不要重复提交同一条。</div>
  </div>
  <button class="u-btn" type="submit">提交请求</button>
</form>
HTML;$n=str_replace('{CSRF}',self::esc(User::csrf()),$n);$n=str_replace('{TYPES}',self::typeOptions(''),$n);$n=str_replace('{CONTACT}',$p,$n);}$s='';if($d){$s='<div class="u-card"><div class="u-head"><h1 style="font-size:16px">我的请求</h1>'.'<p>共 '.(int)(isset($a['myTotal'])?$a['myTotal']:0).' 条。'.'还没处理的可以自己撤回。</p></div>'.self::reqList(isset($a['myList'])?$a['myList']:array(),true).'</div>';}$t='';if($b['public']&&($d||$b['guest_view'])){$u=self::pager((int)(isset($a['page'])?$a['page']:1),(int)(isset($a['perPage'])?$a['perPage']:20),(int)(isset($a['total'])?$a['total']:0));$t='<div class="u-card"><div class="u-head"><h1 style="font-size:16px">大家都在找</h1>'.'<p>站长按提交顺序处理，已找到的会在这里变成「已找到」。</p></div>'.self::reqList(isset($a['list'])?$a['list']:array(),false).$u.'</div>';}$v=$b['notice']!==''?'<div class="u-flash info">'.User::text($b['notice']).'</div>':'';$w=$f.self::flashHtml().$j.$v.'<div class="u-card">'.$n.'</div>'.$s.$t.self::reqScript();return self::shell($c,$w,true,$e,$g);}private static function reqList(array $a,$b){if(!$a){return'<div class="u-empty">'.($b?'还没有提交过请求。':'还没有人提交请求，你可以是第一个。').'</div>';}$c=User::reqStatusText();$d='<div class="u-list">';foreach($a as $e){$f=(string)(isset($e['status'])?$e['status']:'pending');$g=isset($c[$f])?$c[$f]:'待处理';$h=(int)(isset($e['id'])?$e['id']:0);$i=(string)(isset($e['title'])?$e['title']:'');$j=(string)(isset($e['type'])?$e['type']:'');$k=isset($e['year'])&&$e['year']!==null&&$e['year']!==''?(int)$e['year']:0;$l=(string)(isset($e['created_at'])?$e['created_at']:'');$m=array();if($j!==''){$m[]=self::label($j);}if($k>0){$m[]=$k;}if(!$b&&!empty($e['username'])){$m[]='由 '.(string)$e['username'].' 提交';}if($l!==''){$m[]=substr($l,0,16);}$n=(string)(isset($e['note'])?$e['note']:'');$o=(string)(isset($e['admin_note'])?$e['admin_note']:'');$p=(int)(isset($e['item_id'])?$e['item_id']:0);$d.='<div class="u-item">';$d.='<div class="t">'.self::esc($i).' <span class="u-badge '.User::statusClass($f).'">'.self::esc($g).'</span>'.'</div>';if($m){$d.='<div class="m">'.self::esc(implode(' · ',$m)).'</div>';}if($n!==''){$d.='<div class="n">'.User::text($n).'</div>';}if($b&&$o!==''){$d.='<div class="u-reply"><b>站长回复：</b>'.User::text($o).'</div>';}if($p>0){$d.='<div class="m">站内已收录：<a href="/uisc/'.$p.'.html" target="_blank" rel="noopener">'.'点这里查看 →</a></div>';}if($b&&$f==='pending'){$d.='<form method="post" action="/request" style="margin-top:10px"'.' onsubmit="return confirm(\'确定撤回这条请求吗？\')">'.'<input type="hidden" name="csrf" value="'.self::esc(User::csrf()).'">'.'<input type="hidden" name="do" value="del">'.'<input type="hidden" name="id" value="'.$h.'">'.'<button class="u-btn ghost sm" type="submit">撤回</button>'.'</form>';}$d.='</div>';}return $d.'</div>';}private static function pager($a,$b,$c){$d=max(1,(int)ceil($c/max(1,$b)));if($d<=1){return'';}$e='<div class="u-pager">';$e.=($a>1)?'<a href="/request?p='.($a-1).'">上一页</a>':'<span>上一页</span>';$e.='<span>第 '.$a.' / '.$d.' 页</span>';$e.=($a<$d)?'<a href="/request?p='.($a+1).'">下一页</a>':'<span>下一页</span>';return $e.'</div>';}private static function reqScript(){return <<<HTML
<script>
(function(){
  var el = document.getElementById('rq_title');
  var box = document.getElementById('rqHint');
  if(!el || !box){ return; }
  var timer = null;
  function hide(){ box.className = 'u-hintbox'; box.innerHTML = ''; }
  el.addEventListener('input', function(){
    var v = (el.value || '').trim();
    if(timer) { clearTimeout(timer); }
    if(v.length < 2){ hide(); return; }
    timer = setTimeout(function(){
      fetch('/request?probe=' + encodeURIComponent(v))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(!d || !d.success || !d.data || !d.data.items || !d.data.items.length){ hide(); return; }
          var its = d.data.items, s = '站内可能已经有这些，先看看是不是你要的：';
          for(var i=0;i<its.length;i++){
            s += ' <a href="' + its[i].url + '" target="_blank" rel="noopener">' + its[i].title + '</a>';
          }
          box.className = 'u-hintbox on';
          box.innerHTML = s;
        })
        .catch(function(){ hide(); });
    }, 350);
  });
})();
</script>
HTML;}public static function ucss(){return <<<CSS
/* ---------- 注册 / 登录 / 资源请求 页面 ---------- */
.u-wrap{max-width:var(--maxw);margin:0 auto;padding:22px var(--pad) 0}
.u-narrow{max-width:520px;margin-left:auto;margin-right:auto}
.u-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:22px;margin-bottom:16px;color:var(--txt)}
.u-head{margin-bottom:16px}
.u-head h1{font-size:20px;margin:0 0 7px;letter-spacing:.3px}
.u-head p{color:var(--mut);font-size:13.5px;line-height:1.95;margin:0}
.u-head a{color:var(--pri);font-weight:600}
.u-field{margin-bottom:14px}
.u-field>label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--txt)}
.u-field .tip{color:var(--mut);font-size:12px;margin-top:6px;line-height:1.7}
.u-in{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px;font-size:14px;background:var(--bg);color:var(--txt);outline:none;transition:border-color .15s,box-shadow .15s;-webkit-appearance:none;appearance:none}
.u-in:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.14)}
textarea.u-in{min-height:92px;resize:vertical;line-height:1.75;font-family:inherit}
select.u-in{background-image:none}
.u-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.u-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 24px;border:0;border-radius:10px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:.15s;text-decoration:none;line-height:1.4}
.u-btn:hover{filter:brightness(1.06);box-shadow:var(--shadow-h)}
.u-btn.ghost{background:transparent;border:1px solid var(--line);color:var(--txt);font-weight:500}
.u-btn.ghost:hover{background:var(--bg);box-shadow:none;filter:none}
.u-btn.sm{padding:7px 14px;font-size:13px;font-weight:500}
.u-alt{color:var(--mut);font-size:13px;margin:16px 0 0;text-align:center}
.u-alt a{color:var(--pri);font-weight:600}
.u-flash{border-radius:12px;padding:12px 15px;font-size:13.5px;line-height:1.85;margin-bottom:14px;border:1px solid transparent}
.u-flash.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.u-flash.err{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.u-flash.info{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.u-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.u-pill{background:var(--card);border:1px solid var(--line);border-radius:999px;padding:6px 13px;font-size:12.5px;color:var(--mut)}
.u-pill b{color:var(--txt)}
.u-list{display:flex;flex-direction:column;gap:10px}
.u-item{border:1px solid var(--line);border-radius:12px;padding:14px 16px;background:var(--bg)}
.u-item .t{font-weight:700;font-size:14.5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;word-break:break-word}
.u-item .m{color:var(--mut);font-size:12.5px;margin-top:7px;line-height:1.85}
.u-item .m a{color:var(--pri)}
.u-item .n{font-size:13px;margin-top:8px;line-height:1.85;opacity:.94;word-break:break-word}
.u-badge{font-size:11.5px;font-weight:700;border-radius:999px;padding:3px 10px;white-space:nowrap}
.u-badge.pending{background:#fef3c7;color:#92400e}
.u-badge.found{background:#dcfce7;color:#166534}
.u-badge.notfound{background:#f1f5f9;color:#475569}
.u-badge.replied{background:#dbeafe;color:#1e40af}
.u-badge.closed{background:#f3f4f6;color:#6b7280}
.u-reply{margin-top:9px;padding:10px 13px;border-left:3px solid var(--pri);background:var(--card);border-radius:0 9px 9px 0;font-size:13px;line-height:1.85}
.u-empty{color:var(--mut);font-size:13.5px;padding:26px 8px;text-align:center}
.u-hintbox{display:none;margin-top:9px;font-size:13px;line-height:2;border-radius:11px;padding:11px 13px;background:var(--bg);border:1px dashed var(--line);color:var(--mut)}
.u-hintbox.on{display:block}
.u-hintbox a{color:var(--pri);font-weight:600;margin-right:10px;white-space:nowrap}
.u-lock{text-align:center;padding:26px 12px}
.u-lock .ico{font-size:34px;line-height:1}
.u-lock h3{margin:12px 0 8px;font-size:16px}
.u-lock p{color:var(--mut);font-size:13.5px;line-height:1.95;margin:0 0 18px}
.u-cap{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.u-cap .q{background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:10px 15px;font-size:15px;font-weight:800;letter-spacing:1.5px;color:var(--pri);white-space:nowrap}
.u-cap .u-in{flex:1;min-width:130px}
.u-hp{position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;opacity:0;overflow:hidden}
.u-pager{display:flex;gap:8px;justify-content:center;align-items:center;margin:18px 0 2px;flex-wrap:wrap}
.u-pager a,.u-pager span{border:1px solid var(--line);border-radius:9px;padding:7px 14px;font-size:13px;background:var(--bg);color:var(--txt)}
.u-pager span:last-child,.u-pager span:first-child{color:var(--mut)}
.u-pager a:hover{border-color:var(--pri);color:var(--pri)}

/* 顶部用户区（顶栏里的登录 / 注册 / 我的请求） */
.uarea{display:flex;align-items:center;gap:8px;white-space:nowrap}
.uarea .uname{color:#fff;font-size:13px;opacity:.92;max-width:120px;overflow:hidden;text-overflow:ellipsis}

/* ≤900：两栏改单栏 */
@media (max-width:900px){
  .u-wrap{padding-top:18px}
}
/* ≤560：表单两列改一列，按钮铺满（拇指更好点） */
@media (max-width:560px){
  .u-card{padding:17px 15px;border-radius:14px}
  .u-head h1{font-size:18px}
  .u-row2{grid-template-columns:1fr;gap:0}
  .u-btn{width:100%}
  .u-btn.sm{width:auto}
  .u-in{font-size:16px}          /* 16px 可阻止 iOS 聚焦时整页放大 */
  .u-lock{padding:18px 4px}
}
@media (max-width:380px){
  .u-head h1{font-size:17px}
  .u-pill{padding:5px 10px;font-size:12px}
}
/* 触屏：去掉 hover 粘滞（手机上点完不会"卡住浮起"） */
@media (hover:none){
  .u-btn:hover{filter:none;box-shadow:none}
  .u-btn.ghost:hover{background:transparent}
  .u-pager a:hover{border-color:var(--line);color:var(--txt)}
}
@media (prefers-reduced-motion:reduce){
  .u-btn,.u-in{transition:none}
}
/* 打印：去掉顶栏页脚，卡片边框简化 */
@media print{
  .topbar,.mainnav,.foot,.u-pager,.u-btn{display:none!important}
  .u-card{box-shadow:none;border-color:#ddd}
}
/* 暗色：简约模板强制暗色 */
.tpl-simple.mode-dark .u-flash.ok{background:#10321f;color:#6ee7b7;border-color:#1c4a33}
.tpl-simple.mode-dark .u-flash.err{background:#3a1a1c;color:#fca5a5;border-color:#5a2a2d}
.tpl-simple.mode-dark .u-flash.info{background:#152238;color:#93c5fd;border-color:#1e3a5f}
.tpl-simple.mode-dark .u-badge.pending{background:#3a2f12;color:#fbbf24}
.tpl-simple.mode-dark .u-badge.found{background:#10321f;color:#6ee7b7}
.tpl-simple.mode-dark .u-badge.notfound{background:#26304a;color:#c3cad8}
.tpl-simple.mode-dark .u-badge.replied{background:#152238;color:#93c5fd}
.tpl-simple.mode-dark .u-badge.closed{background:#232b3c;color:#9aa3b8}
.tpl-simple.mode-dark .u-in{background:#131a29}
.tpl-simple.mode-dark .u-item,.tpl-simple.mode-dark .u-pill,.tpl-simple.mode-dark .u-pager a,.tpl-simple.mode-dark .u-pager span{background:#1b2233}
.tpl-simple.mode-dark .u-reply{background:#1b2233}
/* 暗色：跟随系统（经典与简约模板通用） */
@media (prefers-color-scheme:dark){
  body.mode-auto .u-flash.ok{background:#10321f;color:#6ee7b7;border-color:#1c4a33}
  body.mode-auto .u-flash.err{background:#3a1a1c;color:#fca5a5;border-color:#5a2a2d}
  body.mode-auto .u-flash.info{background:#152238;color:#93c5fd;border-color:#1e3a5f}
  body.mode-auto .u-badge.pending{background:#3a2f12;color:#fbbf24}
  body.mode-auto .u-badge.found{background:#10321f;color:#6ee7b7}
  body.mode-auto .u-badge.notfound{background:#26304a;color:#c3cad8}
  body.mode-auto .u-badge.replied{background:#152238;color:#93c5fd}
  body.mode-auto .u-badge.closed{background:#232b3c;color:#9aa3b8}
  body.mode-auto .u-in{background:#131a29}
  body.mode-auto .u-item,body.mode-auto .u-pill,body.mode-auto .u-pager a,body.mode-auto .u-pager span{background:#182034}
  body.mode-auto .u-reply{background:#182034}
}
/* 刘海屏 / 底部横条安全区（放在本文件样式表最后） */
@supports(padding:max(0px)){
  .u-wrap{padding-left:max(var(--pad),env(safe-area-inset-left));padding-right:max(var(--pad),env(safe-area-inset-right))}
}
CSS;}}