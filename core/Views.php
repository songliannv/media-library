<?php
namespace Core;require_once __DIR__.'/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/Categories.php';require_once __DIR__.'/Site.php';require_once __DIR__.'/Settings.php';require_once __DIR__.'/User.php';class Views{public static function adminUrl($a=''){$b=trim((string)Config::sub('admin','dir','admin'),'/');if($b===''||!preg_match('/^[A-Za-z0-9_-]+$/',$b)||substr($b,-5)!=='admin'){$b='admin';}$b='/'.ltrim($b,'/');return $b.($a!==''?'/'.ltrim($a,'/'):'');}public static function typeLabel($a){return Categories::label($a);}public static function esc($a){return htmlspecialchars((string)((isset($a)?$a:'')),ENT_QUOTES,'UTF-8');}public static function topbarHtml($a=''){$b=Site::info();$c=self::esc($b['name']);$d='';if($b['logo']!==''){$d='<img class="logo" src="'.self::esc(Site::asset($b['logo'])).'" alt="'.$c.'">';}else{$d='<span class="dot"></span>';}$e='<b class="sname">'.$c.'</b>';$f='';if($b['slogan']!==''){$f='<span class="tslogan" title="'.self::esc($b['slogan']).'">'.self::esc($b['slogan']).'</span>';}return'<header class="topbar"><div class="in">'.'<a class="brand" href="/">'.$d.$e.'</a>'.$f.$a.self::userArea().'</div></header>';}public static function userArea(){$a=User::me();if($a){return'<span class="uarea">'.'<a class="admin" href="/request">我的请求</a>'.'<a class="admin" href="/user/logout">退出</a>'.'</span>';}$b=User::cfg();if(!$b['open']){return'';}return'<span class="uarea">'.'<a class="admin" href="/user/login">登录</a>'.'<a class="admin" href="/user/register">注册</a>'.'</span>';}public static function bodyClass(){$a='simple';$b='tpl-'.$a;$b.=' mode-auto';return $b;}public static function footerHtml(){$a=Site::info();$b='<footer class="foot" id="contact"><div class="fin">';if($a['footer_intro']!==''){$b.='<p class="fintro">'.Site::text($a['footer_intro']).'</p>';}if($a['declare']!==''){$b.='<p class="fdec">声明：'.self::esc($a['declare']).'</p>';}if($a['copyright']!==''){$b.='<p class="fcopy">'.Site::text($a['copyright']).'</p>';}$b.='<p class="fbrand">'.self::esc($a['name']).' · 本站数据整理自公开条目信息</p>';$b.='</div></footer>';return $b;}public static function showAdminEntry(){if(!Config::sub('admin','show_link',1)){return false;}try{return class_exists('Core\\User')&&User::isAdmin();}catch(\Throwable $a){return false;}}public static function jsTag($a){$b=dirname(__DIR__).'/assets/js/'.basename($a);if(!is_file($b)){return'<!-- js 未能加载：assets/js/'.self::esc($a).' 缺失 -->';}$c=defined('ML_APP_VERSION')?ML_APP_VERSION:'1';return'<script src="'.self::esc(self::assetUrl('assets/js/'.basename($a))).'?v='.rawurlencode($c).'" defer></script>';}public static function pageViewJs(){return self::jsTag('pageview.js');}public static function dlClickJs(){return self::jsTag('dlclick.js');}public static function pageViewAttr($a=0){$a=(int)$a;return $a>0?(' data-item-id="'.$a.'"'):'';}public static function pageCacheJs($a,$b){if($a){return'';}if((int)$b<2){return'';}return self::jsTag('pagecache.js');}public static function assetMissingNotice($a){return'<div style="position:relative;z-index:99999;background:#7a1020;color:#fff;'.'font:14px/1.7 system-ui,-apple-system,Segoe UI,sans-serif;padding:10px 16px;text-align:center">'.'<b>样式未加载</b>：服务器缺少 <code>'.self::esc($a).'</code>。'.'请用完整发布包覆盖（<code>assets/</code> 目录必须一起上传）。</div>';}public static function css($a=false){static $b=null;if($b===null){$c=__DIR__.'/../assets/css/site.css';$d=@file_get_contents($c);$b=($d===false||$d==='')?'':$d;}if($b===''){return'/* site.css 未能读取，样式无法加载 —— 请检查 assets/css/site.css 是否已随包上传 */';}$e=$b;if(!$a){return $e;}$f=array('/^[ \t]*[^{}\n]*(?:\.tc-[a-z0-9-]*|\.tagcloud)[^{}\n]*\{[^}]*\}[ \t]*$/m','/^[ \t]*[^{}\n]*\.banner[^{}\n]*(?:\{[^}]*\})?[ \t]*$/m','/*banner 内部排版：单行 @media 内的最后一个声明块 → 删整行*/','/^[ \t]*@media[^{}\n]*\{(?:[^{}]*\{[^}]*\})*[^{}]*\.banner (?:h1|p|badges)[^{}\n]*\{[^}]*\}[ \t]*$/m','/^[ \t]*[^{}\n]*\.latest(?:-list)?[^{}\n]*(?:\{[^}]*\})?[ \t]*$/m','/^[ \t]*@media[^{}\n]*\{(?:[^{}]*\{[^}]*\})*[^{}]*\.latest(?:-list)?[^{}\n]*\{[^}]*\}[ \t]*$/m',);$g=$e;foreach($f as $h){if(strpos($h,'/*')===0){continue;}$i=preg_replace($h,'',$g);if($i!==null){$g=$i;}}if(self::cssBalance($g)!==0){return $e;}return $g;}private static function cssBalance($a){$c=0;for($b=0,$d=strlen($a);$b<$d;$b++){if($a[$b]==='{'){$c++;}elseif($a[$b]==='}'){$c--;}}return $c;}public static function assetUrl($a){return'/'.ltrim($a,'/');}public static function cssLinks($a=false,$b='',$c='',$d=array()){$e=defined('ML_APP_VERSION')?ML_APP_VERSION:'1';$f='';if($a){$f.='<style>'.self::css(true).'</style>';}else{$g=__DIR__.'/../assets/css/site.css';if(!is_file($g)){$f.=self::assetMissingNotice('assets/css/site.css');}else{$f.='<link rel="stylesheet" href="'.self::esc(self::assetUrl('assets/css/site.css')).'?v='.rawurlencode($e).'">';}}if($b!==''){if($c!==''&&!is_file(__DIR__.'/../'.$c)){$f.=self::assetMissingNotice($c);}else{$f.=$b;}}if(is_array($d)){foreach($d as $h){$h=(string)$h;if($h===''){continue;}if(!is_file(__DIR__.'/../'.$h)){$f.=self::assetMissingNotice($h);}else{$f.='<link rel="stylesheet" href="'.self::esc(self::assetUrl($h)).'?v='.rawurlencode($e).'">';}}}$i=__DIR__.'/../assets/css/print.css';if(!is_file($i)){$f.=self::assetMissingNotice('assets/css/print.css');}else{$f.='<link rel="stylesheet" media="print" href="'.self::esc(self::assetUrl('assets/css/print.css')).'?v='.rawurlencode($e).'">';}return $f;}public static function cardHtml(array $a){$b=(int)((isset($a['id'])?$a['id']:0));$c=self::esc(self::cleanTitle((isset($a['title'])?$a['title']:'未命名')));$d=!empty($a['year'])?('· '.$a['year']):'';$e=($a['rating']!==null&&$a['rating']!=='')?number_format((float)$a['rating'],1):'—';$f=self::typeLabel((isset($a['type'])?$a['type']:''));$g=(isset($a['poster'])?$a['poster']:'');$h=self::imgUrl($g);$i=(int)((isset($a['clicks'])?$a['clicks']:0));$j=self::formatCount($i);$k=self::hasDownloads((isset($a['download_url'])?$a['download_url']:''));$l=self::popularityOf((isset($a['extra'])?$a['extra']:''));if((isset($a['source'])&&$a['source']==='hongguoduanju')&&(isset($a['type'])&&$a['type']==='short')){$m=self::shortStat((isset($a['extra'])?$a['extra']:''),'hot');if($m>0){$l=self::intCount($m);}$n=self::shortStat((isset($a['extra'])?$a['extra']:''),'clk');if($n>0){$j=self::intCount($n);}}$o=($l!=='')?'<span class="chip chip-hot" title="热度（短剧=点赞，无点赞取播放量）">🔥 '.$l.'</span>':'';$p=$h?'<img src="'.self::esc($h).'"'.self::imgFallback($g).' alt="'.self::esc((isset($a['title'])?$a['title']:'')).'" loading="lazy">':'<div class="c-noposter">无封面</div>';$q=$k?'<span class="c-dl">可下载</span>':'';return <<<HTML
<a class="card" href="/uisc/{$b}.html">
  <div class="c-thumb">{$p}<span class="c-type">{$f}</span>{$q}</div>
    <div class="c-body">
    <!-- v3.6.5：标题单行不折行，超长省略；悬停由 .ml 触发滚动显示完整（t-dup 为无缝滚动副本） -->
    <div class="c-title"><span class="t-in">{$c}</span><span class="t-in t-dup" aria-hidden="true">{$c}</span></div>
    <!-- v5.3.7：删除 .c-sub（「类型 · 年份」副标题）—— 分类页左侧已有类型维度、顶部可筛年份，卡片上再重复一次是噪音；评分 / 热度 / 点击保留在 .c-meta -->
    <div class="c-meta">
      <span class="chip chip-r">★ {$e}</span>
      {$o}
      <span class="chip chip-clk">点击 {$j}</span>
    </div>
  </div>
</a>
HTML;}public static function intCount($a){$a=(int)round((float)$a);if($a<9999){return(string)$a;}$b=array(1e9=>'B',1e6=>'M',1e3=>'K');foreach($b as $c=>$d){if($a>=$c){return(string)(int)round($a/$c).$d;}}return(string)$a;}public static function shortStat($a,$b){$c=is_array($a)?$a:json_decode((string)$a,true);if(!is_array($c)){return 0.0;}if($b==='hot'){foreach(array('heat_count','praise_count','praise','like_count','play_count','popularity')as $d){if(isset($c[$d])&&is_numeric($c[$d])&&(float)$c[$d]>0){return(float)$c[$d];}}}elseif($b==='clk'){foreach(array('favorite_count','favorite','collect_count','collect')as $d){if(isset($c[$d])&&is_numeric($c[$d])&&(float)$c[$d]>0){return(float)$c[$d];}}}return 0.0;}public static function popularityOf($a){$b=null;if(is_array($a)){$b=$a;}elseif(is_string($a)&&$a!==''){$b=json_decode($a,true);}if(!is_array($b)||!isset($b['popularity'])||!is_numeric($b['popularity'])){return'';}$c=(float)$b['popularity'];if($c<=0){return'';}return self::intCount($c);}public static function formatCount($a){$a=abs((float)$a);if($a<9999){return($a==floor($a))?(string)(int)$a:number_format($a,2);}$b=array(1e12=>'T',1e9=>'B',1e6=>'M',1e3=>'K');foreach($b as $c=>$d){if($a>=$c/1e3){$e=$a/$c;if($e>=100){return number_format($e,0).$d;}if($e>=10){return number_format($e,1).$d;}return number_format($e,2).$d;}}return number_format($a/1e12,2).'T';}public static function hasDownloads($a){foreach(self::parseDownloads($a)as $b){if(trim((string)((isset($b['url'])?$b['url']:'')))!==''){return true;}}return false;}public static function homeHtml(array $a,$b,$c,$d,$e,$f,$g,$h=array(),$i='',$j=null,$k=''){$l=self::esc($f);$m='';if($k!==''){$m='<div class="pansou-bar">'.self::esc($k).'</div>';}$n=['api_calls'=>'热度','clicks'=>'点击','updated_at'=>'最新','rating'=>'评分',];$o=[''=>'全部'];foreach(Categories::TYPES as $p){$o[$p]=Categories::label($p);}try{$q=\Core\CacheStore::stats();if(is_array($q)&&isset($q['by_type'])){$r=array_keys($q['by_type']);foreach($o as $s=>$t){if($s!==''&&!in_array($s,$r,true)){unset($o[$s]);}}}}catch(\Throwable $u){}$v=($i!==''?('&tag='.urlencode($i)):'');$w='';if(is_array($j)&&isset($j['name'])&&(string)$j['name']!==''){$w='/person/'.rawurlencode((string)(isset($j['kind'])?$j['kind']:'director')).'/'.rawurlencode((string)$j['name']);}$x=function($a,$b,$c)use($f,$i,$w){if($w!==''){$d=$w.'?sort='.rawurlencode((string)$a);if($b!==''){$d.='&type='.rawurlencode((string)$b);}if($c>0){$d.='&page='.(int)$c;}return $d;}$d='/?sort='.rawurlencode((string)$a).'&type='.rawurlencode((string)$b).'&q='.urlencode((string)$f);if($i!==''){$d.='&tag='.urlencode((string)$i);}if($c>0){$d.='&page='.(int)$c;}return $d;};$y='';foreach($n as $s=>$z){$a1=$s===$b?' class="on"':'';$y.='<a'.$a1 .' href="'.self::esc($x($s,$c,0)).'">'.$z.'</a>';}$b1='';foreach($o as $s=>$z){$a1=$s===$c?' class="on"':'';$b1 .='<a'.$a1 .' href="'.self::esc($x($b,$s,0)).'">'.$z.'</a>';}$c1='';foreach($a as $d1){$c1 .=self::cardHtml($d1);}if($c1===''){$e1='<p class="empty">'.($w!==''?'这位影人名下暂无已入库的作品。':'暂无资料，去后台添加或触发一次同步吧。').'</p><p class="empty-actions">'.'<a class="btn" href="/">返回首页</a>'.'<a class="btn ghost" href="/?sort='.self::esc($b).'&type='.self::esc($c).'">看全部条目</a>'.'</p>';$c1=$e1;}$f1=max(1,(int)ceil($e/$g));$g1='';if($f1>1){$h1=$d>1?'<a data-ml-page="'.(int)($d-1).'" href="'.self::esc($x($b,$c,$d-1)).'">上一页</a>':'<span>上一页</span>';$i1=$d<$f1?'<a data-ml-page="'.(int)($d+1).'" href="'.self::esc($x($b,$c,$d+1)).'">下一页</a>':'<span>下一页</span>';$g1='<div class="pager">'.$h1 .'<span>第 '.$d.' / '.$f1 .' 页</span>'.$i1 .'</div>';}$j1=(isset($n[$b])?$n[$b]:'点击');$k1=(isset($o[$c])?$o[$c]:'全部');$l1=number_format($e);$m1=Site::info();$n1=($c===''&&$f===''&&$i===''&&(int)$d===1);$o1=Site::headMeta($n1?'home':'list',array('type_label'=>($c!==''?$k1:''),'sort_label'=>$j1,'page'=>$d,));$p1='<form class="search" action="/" method="get">'.'<input type="hidden" name="sort" value="'.self::esc($b).'">'.'<input type="hidden" name="type" value="'.self::esc($c).'">'.'<input type="text" name="q" value="'.$l.'" placeholder="搜索标题…">'.'<button type="submit">搜索</button></form>';$q1=self::showAdminEntry();$r1='';if($q1){$r1='<a class="admin" href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a>';}$s1=self::topbarHtml($p1 .$r1);$t1=self::footerHtml();$u1=$m1['slogan']!==''?self::esc($m1['slogan']):'影视 · 剧集 · 动漫 · 综艺 · 短剧 · 游戏';$v1='一站式资源资料库，支持在线检索与一键下载。';$w1='';$x1='';$y1='';$z1=($f!=='');if(!$z1&&class_exists('Core\\CacheStore')&&class_exists('Core\\DB')){$a2=array();$b2=array();$c2=array('directors'=>array(),'actors'=>array());try{$a2=\Core\CacheStore::regularTags();}catch(\Throwable $u){$a2=array();}try{$b2=\Core\CacheStore::hotTags(50);}catch(\Throwable $u){$b2=array();}try{$c2=\Core\CacheStore::hotPeople(16);}catch(\Throwable $u){$c2=array('directors'=>array(),'actors'=>array());}$d2=array();foreach($b2 as $e2){$d2[(string)$e2['tag']]=(int)$e2['clicks'];}$f2=array();$g2=function($a,$b,$c,$d)use(&$f2){$a=preg_replace('/[\x{3000}\x{00A0}\x{200B}-\x{200D}\x{200E}\x{200F}\x{FEFF}]/u','',(string)$a);$a=trim($a);if($a===''||isset($f2[$a])){return;}if(preg_match('/^[-—–−_~？?·、,，\s]+$/u',$a)){return;}if(preg_match('/^[\x{4e00}-\x{9fff}·]+$/u',$a)){if(mb_strlen($a,'UTF-8')<=1){return;}if(mb_strlen($a,'UTF-8')<=2&&strpos($a,'·')!==false){return;}}if(preg_match('/^[A-Za-z0-9\.\s]+$/',$a)&&mb_strlen($a,'UTF-8')<3){return;}$f2[$a]=array('tag'=>$a,'items'=>(int)$b,'clicks'=>(int)$c,'kind'=>$d);};foreach($a2 as $p){$h2=(string)(isset($p['tag'])?$p['tag']:'');if($h2===''){continue;}$g2($h2,(int)(isset($p['items'])?$p['items']:0),(int)(isset($d2[$h2])?$d2[$h2]:0),'tag');}foreach((isset($c2['directors'])&&is_array($c2['directors']))?$c2['directors']:array()as $i2){$j2=trim((string)(isset($i2['name'])?$i2['name']:''));if($j2===''){continue;}$g2($j2,(int)(isset($i2['items'])?$i2['items']:0),(int)(isset($i2['clicks'])?$i2['clicks']:0),'director');}foreach((isset($c2['actors'])&&is_array($c2['actors']))?$c2['actors']:array()as $i2){$j2=trim((string)(isset($i2['name'])?$i2['name']:''));if($j2===''){continue;}$g2($j2,(int)(isset($i2['items'])?$i2['items']:0),(int)(isset($i2['clicks'])?$i2['clicks']:0),'actor');}$k2=array_values($f2);usort($k2,function($b,$a){$c=$b['clicks']>0?$b['clicks']:$b['items'];$d=$a['clicks']>0?$a['clicks']:$a['items'];return $d<=>$c;});$k2=array_slice($k2,0,50);$l2=0;foreach($k2 as $m2){$l2++;$n2='tc-t';if($l2<=8){$n2 .=' tc-h1';}elseif($l2<=22){$n2 .=' tc-h2';}$h2=(string)$m2['tag'];$o2=$m2['clicks']>0?$m2['clicks']:$m2['items'];$p2=($m2['kind']==='director')?'/person/director/'.rawurlencode($h2):(($m2['kind']==='actor')?'/person/actor/'.rawurlencode($h2):'/tag/'.urlencode($h2));$x1 .='<a class="'.$n2 .'" href="'.self::esc($p2).'" data-hot="'.$o2 .'"'.' title="'.self::esc($h2).'：'.(int)$m2['items'].' 部 · 热度 '.$o2 .'">'.self::esc($h2).'</a>';}if($x1===''){$x1='<p class="tc-empty">暂无热门标签 —— 请先到后台同步一次资料。</p>';}$w1='<section class="banner tagcloud" id="mlTagCloud">'."\n".'  <div class="in">'."\n".'    <h1 class="sr-only">'.$u1 .'</h1>'."\n".'    <div class="tc-stage" id="mlTcStage">'.$x1 .'</div>'."\n".'  </div>'."\n".'</section>';}if($x1!==''&&strpos($x1,'tc-t')!==false){$y1=self::jsTag('tagcloud.js');}$q2='';if($i!==''){$q2='<div class="tagbar">当前标签：<b>'.self::esc($i).'</b>'.'<a href="/?sort='.self::esc($b).'&type='.self::esc($c).'&q='.urlencode($f).'">清除标签</a></div>';}if($w!==''){$r2=(isset($j['kind'])&&$j['kind']==='actor')?'actor':'director';$s2=($r2==='actor')?'演员':'导演';$t2=($r2==='actor')?'⭐':'🎬';$q2='<div class="tagbar">'.$t2 .' '.$s2 .'：<b>'.self::esc((string)$j['name']).'</b>'.' · 已入库作品 <b>'.number_format($e).'</b> 部'.'<a href="/">返回全部</a></div>';}$u2=self::bodyClass();$v2='';$w2='';if(is_array($h)&&$h&&$n1){$x2='';foreach($h as $y2){$z2=(int)((isset($y2['id'])?$y2['id']:0));$a3=self::esc((isset($y2['title'])?$y2['title']:'未命名'));$b3=self::typeLabel((isset($y2['type'])?$y2['type']:''));$c3=!empty($y2['year'])?(' · '.(int)$y2['year']):'';$x2 .='<a href="/uisc/'.$z2 .'.html"><span class="l-t">'.$a3 .'</span>'.'<span class="l-m">'.$b3 .$c3 .'</span></a>';}if($x2!==''){$w2='<div class="latest"><h2>最新更新</h2><div class="latest-list">'.$x2 .'</div></div>';}}$d3='';if($q1){$d3='<div class="adm-btn"><a href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a></div>';}$e3=self::jsTag('title-scroll.js');$f3=self::jsTag('grid-auto.js');$g3=self::pageCacheJs($z1,$f1);$h3=self::pageViewJs();$i3=self::pageViewAttr(0);$j3=self::dlClickJs();$k3=class_exists('\Core\AntiBot')?\Core\AntiBot::watermark():'';$l3=<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$o1}
{CSS_LINKS}
</head>
<body class="{$u2}"{$i3}>
{$k3}
{$s1}

{$w1}

<div class="wrap">
  {$q2}
  {$m}
  <div class="toolbar">
    <div class="sortbtns">{$y}</div>
    <div class="filters">{$b1}</div>
  </div>
  {$w2}
  <div class="grid{$v2}">{$c1}</div>
  {$g1}
</div>
{$t1}
{$y1}
{$e3}
{$f3}
{$g3}
{$h3}
{$j3}
</body>
</html>
HTML;return str_replace('{CSS_LINKS}',self::cssLinks($z1),$l3);}public static function renderItemPage(array $a){$b=(int)((isset($a['id'])?$a['id']:0));$c=(isset($a['type'])?$a['type']:'');$d=(isset($a['source'])?$a['source']:'');$e=self::esc(self::cleanTitle((isset($a['title'])?$a['title']:'未命名')));$f=self::esc((isset($a['original_title'])?$a['original_title']:''));$g=!empty($a['year'])?(int)$a['year']:'';$h=($a['rating']!==null&&$a['rating']!=='')?number_format((float)$a['rating'],1):'—';$i=self::typeLabel($c);$j=(isset($a['poster'])?$a['poster']:'');$k=self::imgUrl($j);$l=self::imgUrl((isset($a['backdrop'])?$a['backdrop']:''));$m=(int)((isset($a['clicks'])?$a['clicks']:0));$n=self::formatCount($m);$o=self::popularityOf((isset($a['extra'])?$a['extra']:''));$p=($o!=='')?'<span class="s hot" title="热度（短剧=点赞，无点赞取播放量）">热度<b>🔥 '.$o.'</b></span>':'';$q=($d==='hongguoduanju'&&$c==='short');$r=$q?' data-static="1"':'';if($q){$s=self::shortStat((isset($a['extra'])?$a['extra']:''),'hot');if($s>0){$o=self::intCount($s);$p='<span class="s hot" title="短剧热度=点赞（无点赞取播放量）">热度<b>🔥 '.$o.'</b></span>';}$t=self::shortStat((isset($a['extra'])?$a['extra']:''),'clk');if($t>0){$n=self::intCount($t);}}$u=[];static $v=null;if($v===null){$v=array();try{foreach(\Core\CacheStore::allPersonTags()as $w){$v[$w]=true;}}catch(\Throwable $x){}}$y=array();$z=json_decode((isset($a['extra'])?(string)$a['extra']:''),true);if(is_array($z)){foreach(array('director','actors')as $a1){if(!isset($z[$a1])||!is_string($z[$a1])){continue;}foreach(preg_split('/[、,，]/u',$z[$a1])as $b1){$b1=trim($b1);if($b1!==''){$y[$b1]=true;}}}}foreach(json_decode((isset($a['genres'])?$a['genres']:'[]'),true)?:[]as $c1){if(!$c1){continue;}if(!\Core\CacheStore::isCjkTag($c1)){continue;}if(isset($v[$c1])||isset($y[$c1])){continue;}$u[]=$c1;}$d1='';if($u){foreach($u as $c1){$e1=self::esc($c1);$f1='/tag/'.urlencode($c1);$d1 .='<a class="t" href="'.self::esc($f1).'" title="查看「'.$e1 .'」标签下的全部内容">'.$e1 .'</a>';}}$g1=$l?'style="background-image:url('.self::esc($l).')"':'';$h1=$k?'<img src="'.self::esc($k).'"'.self::imgFallback($j).' alt="'.$e.'" loading="lazy">':'<div class="np">无封面</div>';$i1=self::downloadBlock($a);$j1=self::extraBlock($a,$d,$c);$k1=Site::headMeta('item',array('id'=>$b,'title'=>isset($a['title'])?(string)$a['title']:'','type_label'=>$i,'year'=>($g!==''?(string)$g:''),'overview'=>isset($a['overview'])?(string)$a['overview']:'','poster'=>$k,));$l1=self::topbarHtml('<a class="admin" href="/">← 返回主页</a>');$m1=self::footerHtml();$n1=self::bodyClass();$o1=self::showAdminEntry();$p1='';if($o1){$p1='<div class="adm-btn"><a href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a></div>';}$q1=class_exists('\Core\AntiBot')?\Core\AntiBot::watermark():'';$r1=self::pageViewJs();$s1=self::pageViewAttr($b);$t1=self::dlClickJs();$u1=<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$k1}
{CSS_LINKS}
</head>
<body class="{$n1}"{$s1}>
{$q1}
{$l1}

<section class="hero">
  <div class="bg" {$g1}></div>
  <div class="mask"></div>
  <div class="in">
    <div class="poster">{$h1}</div>
    <div class="info">
      <div class="topline">
        <h1>{$e}</h1>
        <!-- ★ v4.9.3：hero 里【不再显示「类型」标签】（短剧 / 电影 / 电视剧 / 动漫 / 综艺 /
             游戏 / 书籍 / 音乐 / 其他 那一批）。
             理由（用户诉求）：类型在海报、面包屑、分类页到处都有，hero 第一屏再列一遍
             属于重复信息，且挤占了标题与指标的空间。
             ★ 保留的是「分类标签」（犯罪 / 剧情 / 悬疑 …，来自 genres，可点进 /tag/）——
               它们是条目的**题材**信息，与类型不是一回事，不能一起删。
               类型文字仍用于 SEO（上方 headMeta 的 type_label）与页面其它位置，未受影响。 -->
        <!-- ★ v4.9.4：分类标签与指标「包成一组」整体靠右（.hero-right）。
             ★★ v4.9.3 的坑：靠右对齐只挂在 .tags 的 margin-left:auto 上，
                而分类标签为空的条目（本例：genres 为空，截图「逗鸟外传：萌宝满天飞」）
                整块 .tags 不输出 ⇒ .stats 就贴回标题右边，指标又跑到中间去了。
                只有把两组放进同一个容器、由容器承担 auto，才与「有没有标签」无关。 -->
        <div class="hero-right">
        {CLASSTAGS}
        <div class="stats">
          <span class="s gold">评分<b>{$h}</b></span>
          {$p}
          <span class="s clk">点击<b id="m-clicks"{$r}>{$n}</b></span>
        </div>
        </div>
      </div>
      <div class="ot">{$f}</div>
      <!-- ★ v4.7.9：此处原为「简介（TMDB 长简介）」渲染位，已移除；
           说明见上方清理「简介」占位变量处。
           ★★ 本段注释必须完全避开「美元符号 + 花括号 + 变量名」这种形状 ——
              本模板是 PHP heredoc，**写在 HTML 注释里的变量插值照样会被解析**，
              而该变量 v4.7.9 已删除 ⇒ 生成静态页时抛
              Warning: Undefined variable（线上曾因此报错）。 -->
    </div>
  </div>
</section>

<div class="detail">
  {$i1}
  {$j1}
</div>

<div class="backbar"><a href="/">← 返回资料库主页</a></div>
{$m1}

<img src="/track?id={$b}&t=click" width="1" height="1" alt="" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">
<script>
(function(){
  // v3.4.6：大数字自适应缩写（与 PHP formatCount 保持一致：9999 起 K/M/B/T）
  function fmt(n){
    n = Math.abs(+n || 0);
    if (n < 9999) return (n === Math.floor(n)) ? String(n) : n.toFixed(2);
    var units = [{d:1e12,s:'T'},{d:1e9,s:'B'},{d:1e6,s:'M'},{d:1e3,s:'K'}];
    for (var i=0;i<units.length;i++){
      var u = units[i];
      if (n >= u.d / 1000){
        var v = n / u.d;
        if (v >= 100) return v.toFixed(0) + u.s;
        if (v >= 10)  return v.toFixed(1) + u.s;
        return v.toFixed(2) + u.s;
      }
    }
    return (n / 1e12).toFixed(2) + 'T';
  }
  var elC=document.getElementById('m-clicks'); if(!elC) return;
  if(elC.getAttribute('data-static')) return;   // v3.6.7：短剧点击=收藏，不再用本地点击数覆盖
  fetch('/api/v1/metric/{$b}').then(function(r){return r.json();}).then(function(d){
    if(d && d.success && d.data){ elC.textContent=fmt(d.data.clicks); }
  }).catch(function(){});
})();
</script>
<script>
/* v3.4.4：主要演职人员左右箭头 + 鼠标/触摸拖拽 + 滚轮横向滚动 */
(function(){
  var wraps=document.querySelectorAll('.cast-wrap'); if(!wraps.length) return;
  var touch=null;
  Array.prototype.forEach.call(wraps,function(w){
    var c=w.querySelector('.cast'), prev=w.querySelector('.cast-nav[dir="-1"]'), next=w.querySelector('.cast-nav[dir="1"]');
    if(!c) return;
    function update(){
      var atS=(c.scrollLeft<=1), atE=(c.scrollLeft+c.clientWidth>=c.scrollWidth-1);
      if(prev) prev.classList.toggle('show', c.scrollWidth>c.clientWidth+1);
      if(next) next.classList.toggle('show', c.scrollWidth>c.clientWidth+1);
      if(prev) prev.disabled=atS;
      if(next) next.disabled=atE;
    }
    function step(d){
      var gap=parseFloat((window.getComputedStyle(c).columnGap||window.getComputedStyle(c).gap||'14'))||14;
      var step=110+gap;
      if(window.innerWidth<680) step=96+11;
      c.scrollLeft=Math.max(0, Math.min(c.scrollWidth-c.clientWidth, c.scrollLeft+d*step));
    }
    if(prev) prev.addEventListener('click', function(){ step(-1); });
    if(next) next.addEventListener('click', function(){ step(1); });
    /* 鼠标按住拖动滚动 */
    c.addEventListener('mousedown', function(e){
      touch={x:e.pageX, l:c.scrollLeft, moved:false};
      c.classList.add('dragging');
    });
    window.addEventListener('mousemove', function(e){
      if(!touch) return;
      var dx=e.pageX-touch.x;
      if(Math.abs(dx)>3) touch.moved=true;
      c.scrollLeft=touch.l-dx;
    });
    function release(){ touch=null; c.classList.remove('dragging'); }
    window.addEventListener('mouseup', release);
    window.addEventListener('mouseleave', release);
    c.addEventListener('click', function(e){
      if(touch && touch.moved){ e.preventDefault(); e.stopPropagation(); }
    }, true);
    /* 竖滚滚轮 → 横向滚动（横滚优先走原生） */
    c.addEventListener('wheel', function(e){
      var d=(Math.abs(e.deltaX)>=Math.abs(e.deltaY))?e.deltaX:e.deltaY;
      if(!d) return;
      if(c.scrollLeft+d>=c.scrollWidth-c.clientWidth-1 || c.scrollLeft+d<=1){ return; }
      c.scrollLeft+=d; e.preventDefault();
    }, {passive:false});
    c.addEventListener('scroll', update);
    window.addEventListener('resize', update);
    update();
  setTimeout(update, 250);
  setTimeout(update, 900);
  });
})();
</script>
{$r1}
{$t1}
</body>
</html>
HTML;$u1=str_replace('{CLASSTAGS}',$d1!==''?'<div class="tags">'.$d1 .'</div>':'',$u1);return str_replace('{CSS_LINKS}',self::cssLinks(false,'','',array('assets/css/item.css')),$u1);}private static function extraBlock(array $a,$b,$c){$d=json_decode((isset($a['payload'])?$a['payload']:'{}'),true)?:[];$e=[];$f=json_decode((isset($a['extra'])?$a['extra']:'{}'),true);$g=(is_array($f)&&(!empty($f['cast'])||!empty($f['actors'])));if($g){$e[]=self::castBlock($d,$a);}if($b==='hongguoduanju'&&$c==='short'){$e[]=self::animeBlock($d);}if($c==='person'){$e[]=self::personBlock($d,$a);}$e[]=self::extraFieldsBlock($a);return implode('',array_filter($e));}private static function downloadBlock(array $a){$b=self::parseDownloads((isset($a['download_url'])?$a['download_url']:''));if(!$b){return'';}$c='';$d=(int)(isset($a['id'])?$a['id']:0);foreach($b as $e=>$f){$g=(string)((isset($f['label'])?$f['label']:'下载'));$h=self::normalizePanLabel($g);$h=self::esc($h);$i=trim((string)((isset($f['url'])?$f['url']:'')));if($i===''){continue;}if(preg_match('#^https?://#i',$i)){$j=self::esc($i);if(self::panPending($i)){$c.='<li class="dl-cell"><a class="dl-btn" href="/dl/go/'.(int)$d.'/'.(int)$e.'" target="_blank" rel="noopener" data-dl-go="1" data-dl-id="'.(int)$d.'" data-dl-idx="'.(int)$e.'">'.$h.'</a></li>';}else{$c.='<li class="dl-cell"><a class="dl-btn" href="'.$j.'" target="_blank" rel="noopener" title="点击下载：'.$j.'" data-dl-id="'.$d.'" data-dl-idx="'.(int)$e.'">'.$h.'</a></li>';}}else{$c.='<li><span class="dl-tag">'.$h.'</span><span class="dl-raw">'.self::esc($i).'</span></li>';}}if($c===''){return'';}return'<div class="block"><h2>下载地址</h2><ul class="dl">'.$c.'</ul></div>';}private static function panPending($url){try{if(!class_exists('Core\\PanTransfer')){$f=__DIR__.'/PanTransfer.php';if(!is_file($f)){return false;}require_once $f;}return \Core\PanTransfer::isPendingTransfer($url);}catch(\Throwable $e){return false;}}private static function normalizePanLabel($a){$a=trim((string)$a);if($a===''){return'下载';}if(preg_match('#^[A-Za-z0-9_.-]+$#',$a)){return $a;}if(substr($a,-2)==='下载'){$a=trim(substr($a,0,-2));if($a===''){return'下载';}if(preg_match('#^[A-Za-z0-9_.-]+$#',$a)){return $a;}}if(strpos($a,'网盘')!==false||substr($a,-2)==='云盘'){return $a;}$b=preg_match_all('/./us',$a);if($b<=4&&$b>=2){return $a.'网盘';}return $a;}private static function cleanTitle($a){$b=(string)$a;$b=preg_replace('/[\s]*[({\[【]\s*tmdb-\d+\s*[)}\]】][\s]*/i',' ',$b);$b=preg_replace('/[\s]*tmdb-\d+[\s]*/i',' ',$b);$b=preg_replace('/\s{2,}/',' ',$b);return trim($b);}private static function parseDownloads($a){$a=trim($a);if($a===''){return[];}if(strpos($a,'[')===0){$b=json_decode($a,true);if(is_array($b)){$c=[];foreach($b as $d){if(is_array($d)){$c[]=['label'=>(isset($d['label'])?$d['label']:'下载'),'url'=>(isset($d['url'])?$d['url']:'')];}elseif(is_string($d)){$c[]=['label'=>'下载','url'=>$d];}}return $c;}}$e=array_filter(array_map('trim',preg_split('/\r?\n/',$a)),function($a){return $a!=='';});$c=[];foreach($e as $f){$c[]=['label'=>'下载','url'=>$f];}return $c;}private static function imgUrl($a){$a=(string)$a;if($a===''||strpos($a,'https://image.tmdb.org/t/p/')!==0){return $a;}if(!Config::sub('image_proxy','enable',1)){return $a;}return'/mimg.php?p='.rawurlencode(substr($a,strlen('https://image.tmdb.org')));}private static function imgFallback($a){$a=(string)$a;if(strpos($a,'https://image.tmdb.org/t/p/')!==0){return'';}return' onerror="this.onerror=null;this.src=\'https://image.tmdb.org/t/p/'.self::esc(substr($a,strlen('https://image.tmdb.org/t/p/'))).'\'"';}private static function extraFieldsBlock(array $a){$b=json_decode((isset($a['extra'])?$a['extra']:'{}'),true);$c=(string)(isset($a['type'])?$a['type']:'');if(!is_array($b)||!$b){if(in_array($c,array('movie','tv','short','anime','variety'),true)){$d=[['key'=>'导演','val'=>'暂无']];if(in_array($c,array('movie','tv','short','anime'),true)){$d[]=['key'=>'演员','val'=>'暂无','w'=>2];}return self::kvBlock(Categories::label($c).'信息',$d);}return'';}$e=[];$f=in_array($c,array('movie','tv','short','anime','variety'),true);$g=in_array($c,array('movie','tv','short','anime'),true);foreach(Categories::EXTRA_LABELS as $h=>$i){$j=$f&&($h==='director'||($h==='actors'&&$g));if(!isset($b[$h])||$b[$h]===''||$b[$h]===null){if($j){$e[]=['key'=>$i,'val'=>'暂无','w'=>($h==='actors'?2:1)];}continue;}if($h==='author'&&(isset($a['type'])?$a['type']:'')==='short'){continue;}if($h==='runtime'&&(isset($a['type'])?$a['type']:'')==='short'){continue;}if(is_numeric($b[$h])&&(int)$b[$h]===0){continue;}if($h==='director'||$h==='actors'||$h==='writers'){$k=($h==='director')?'director':'actor';$l='';foreach(preg_split('/[、,，]/u',(string)$b[$h])as $m){$m=Categories::cleanPersonName($m);if($m===''){continue;}$l.='<a class="info-pill" href="/person/'.$k.'/'.rawurlencode($m).'"'.' title="查看「'.self::esc($m).'」的作品">'.self::esc($m).'</a>';}if($l!==''){$e[]=['key'=>$i,'val'=>$l,'html'=>true,'w'=>($h==='actors'?2:1)];}elseif($j){$e[]=['key'=>$i,'val'=>'暂无','w'=>($h==='actors'?2:1)];}continue;}$n=$b[$h];if(is_array($n)){$o=array();foreach($n as $p){if(is_array($p)){$q='';if(isset($p['date'])&&$p['date']!==''){$q=(string)$p['date'];}if(isset($p['title'])&&$p['title']!==''){$q.=($q!==''?' · ':'').(string)$p['title'];}if(isset($p['country'])&&$p['country']!==''){$q.=($q!==''?' · ':'').(string)$p['country'];}if($q===''){foreach(array('name','iso','title','key')as $r){if(isset($p[$r])&&is_scalar($p[$r])&&(string)$p[$r]!==''){$q.=($q!==''?'、':'').(string)$p[$r];}}}if($q!==''){$o[]=$q;}}elseif(is_scalar($p)&&(string)$p!==''){$o[]=(string)$p;}}$n=implode('、',array_unique($o));if($n===''){continue;}}if(is_scalar($n)){$e[]=['key'=>$i,'val'=>(string)$n];}}if(empty($e)){return'';}$s=Categories::label((isset($a['type'])?$a['type']:'')).'信息';return self::kvBlock($s,$e);}private static function castBlock(array $a,array $b=array()){$c=json_decode((isset($b['extra'])?$b['extra']:'{}'),true);$d=array();if(is_array($c)&&isset($c['cast'])&&is_array($c['cast'])&&!empty($c['cast'])){$d=$c['cast'];}if(empty($d)&&isset($a['credits']['cast'])&&is_array($a['credits']['cast'])&&!empty($a['credits']['cast'])){$d=$a['credits']['cast'];}if(!empty($d)&&is_array($d)){$d=array_slice($d,0,14);$e='';foreach($d as $f){if(!is_array($f)){continue;}$g=Categories::cleanPersonName(isset($f['name'])?$f['name']:'');if($g===''){continue;}$h=self::esc($g);$i=self::esc((isset($f['character'])?$f['character']:''));$j=!empty($f['profile'])?$f['profile']:(!empty($f['profile_path'])?'https://image.tmdb.org/t/p/w300'.$f['profile_path']:'');if($j===''){$j=\Core\PeoplePhoto::url($g);}$k=self::imgUrl($j);$l=$k?'<img src="'.self::esc($k).'"'.self::imgFallback($j).' alt="'.$h.'" loading="lazy">':'<div class="p-np">演员</div>';$e.='<div class="p">'.$l.'<div class="nm">'.$h.'</div><div class="ch">'.$i.'</div></div>';}if($e!==''){return'<div class="block"><h2>主要演职人员</h2>'.self::castWrapHtml($e,'主要演职人员').'</div>';}}if(is_array($c)&&!empty($c['actors'])){$m=$c['actors'];$n=array();if(is_array($m)){foreach($m as $o){$o=Categories::cleanPersonName($o);if($o!=='')$n[]=$o;}}else{foreach(preg_split('/[、,，\s]+/u',(string)$m)as $o){$o=Categories::cleanPersonName($o);if($o!==''){$n[]=$o;}}}$n=array_slice($n,0,20);if(empty($n)){return'';}$e='';foreach($n as $g){$k=\Core\PeoplePhoto::url($g);$h=self::esc($g);$l=$k?'<img src="'.self::esc($k).'" alt="'.$h.'" loading="lazy">':'<div class="p-np">演员</div>';$e.='<div class="p">'.$l.'<div class="nm">'.$h.'</div></div>';}return'<div class="block"><h2>主要演员</h2>'.self::castWrapHtml($e,'主要演员').'</div>';}return'';}private static function castWrapHtml($a,$b){return'<div class="cast-wrap">'.'<button type="button" class="cast-nav" dir="-1" aria-label="向左滚动'.self::esc($b).'"><span class="a">‹</span></button>'.'<div class="cast" role="list">'.$a.'</div>'.'<button type="button" class="cast-nav" dir="1" aria-label="向右滚动'.self::esc($b).'"><span class="a">›</span></button>'.'</div>';}private static function animeBlock(array $a){$b=[];if(!empty($a['eps']))$b[]=['key'=>'话数','val'=>self::esc($a['eps'])];if(!empty($a['air_date']))$b[]=['key'=>'开播','val'=>self::esc($a['air_date'])];$c=[];foreach(((isset($a['studios'])?$a['studios']:[]))as $d){if(isset($d['name']))$c[]=$d['name'];}if($c)$b[]=['key'=>'制作公司','val'=>implode('、',array_unique($c))];$e=[];foreach(((isset($a['tags'])?$a['tags']:[]))as $f){if(is_array($f)){$e[]=(isset($f['name'])?$f['name']:'');}else{$e[]=$f;}}$e=array_filter(array_slice($e,0,8));if($e)$b[]=['key'=>'标签','val'=>implode('、',$e)];if(empty($b))return'';return self::kvBlock('番剧信息',$b);}private static function personBlock(array $a,array $b){$c=[];if(!empty($a['known_for_department']))$c[]=['key'=>'职业','val'=>self::esc($a['known_for_department'])];if(!empty($a['birthday']))$c[]=['key'=>'出生','val'=>self::esc($a['birthday'])];if(!empty($a['place_of_birth']))$c[]=['key'=>'出生地','val'=>self::esc($a['place_of_birth'])];if(empty($c))return'';return self::kvBlock('人物信息',$c);}private static function kvBlock($a,array $b){$c='';$d=count($b);foreach($b as $e=>$f){$g=(isset($f['html'])&&$f['html'])?$f['val']:self::esc($f['val']);$h=(isset($f['w'])&&(int)$f['w']>0)?(int)$f['w']:(($d===1)?3:1);$i='kv-it'.($h===2?' kv-w2':($h===3?' kv-w3':''));$c.='<div class="'.$i.'"><div class="k">'.self::esc($f['key']).'</div><div class="v">'.$g.'</div></div>';}return'<div class="block"><h2>'.self::esc($a).'</h2><div class="kv">'.$c.'</div></div>';}}