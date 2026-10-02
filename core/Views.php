<?php
namespace Core;require_once __DIR__.'/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/Categories.php';require_once __DIR__.'/Site.php';require_once __DIR__.'/Settings.php';require_once __DIR__.'/User.php';class Views{public static function adminUrl($a=''){$b=trim((string)Config::sub('admin','dir','admin'),'/');if($b===''||!preg_match('/^[A-Za-z0-9_-]+$/',$b)||substr($b,-5)!=='admin'){$b='admin';}$b='/'.ltrim($b,'/');return $b.($a!==''?'/'.ltrim($a,'/'):'');}public static function typeLabel($a){return Categories::label($a);}public static function esc($a){return htmlspecialchars((string)((isset($a)?$a:'')),ENT_QUOTES,'UTF-8');}public static function topbarHtml($a=''){$b=Site::info();$c=self::esc($b['name']);$d='';if($b['logo']!==''){$d='<img class="logo" src="'.self::esc(Site::asset($b['logo'])).'" alt="'.$c.'">';}else{$d='<span class="dot"></span>';}$e='<b class="sname">'.$c.'</b>';$f='';if($b['slogan']!==''){$f='<span class="tslogan" title="'.self::esc($b['slogan']).'">'.self::esc($b['slogan']).'</span>';}return'<header class="topbar"><div class="in">'.'<a class="brand" href="/">'.$d.$e.'</a>'.$f.$a.self::userArea().'</div></header>';}public static function userArea(){$a=User::me();if($a){return'<span class="uarea">'.'<a class="admin" href="/request">我的请求</a>'.'<a class="admin" href="/user/logout">退出</a>'.'</span>';}$b=User::cfg();if(!$b['open']){return'';}return'<span class="uarea">'.'<a class="admin" href="/user/login">登录</a>'.'<a class="admin" href="/user/register">注册</a>'.'</span>';}public static function bodyClass(){$a='simple';$b='tpl-'.$a;$c=(string)Config::sub('redirect','simple_mode','auto');if(!in_array($c,array('light','dark','auto'),true)){$c='auto';}if($c==='auto'){$b.=' mode-auto';}elseif($a==='simple'){$b.=($c==='dark')?' mode-dark':' mode-light';}return $b;}public static function footerHtml(){$a=Site::info();$b='<footer class="foot" id="contact"><div class="fin">';if($a['footer_intro']!==''){$b.='<p class="fintro">'.Site::text($a['footer_intro']).'</p>';}if($a['declare']!==''){$b.='<p class="fdec">声明：'.self::esc($a['declare']).'</p>';}if($a['copyright']!==''){$b.='<p class="fcopy">'.Site::text($a['copyright']).'</p>';}$b.='<p class="fbrand">'.self::esc($a['name']).' · 本站数据整理自公开条目信息</p>';$b.='</div></footer>';return $b;}public static function css(){return <<<CSS
:root{--maxw:1180px;--pad:18px;--bg:#f5f6fa;--card:#fff;--line:#eceef3;--pri:#5b5bd6;--pri2:#8b5cf6;--txt:#1b1f2a;--mut:#9aa1b1;--gold:#f5a524;--clk:#10b981;--hot:#f43f5e;--shadow:0 6px 22px rgba(27,31,42,.06);--shadow-h:0 16px 38px rgba(91,91,214,.18)}
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--txt);line-height:1.65;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{max-width:100%}
.topbar{position:sticky;top:0;z-index:30;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;box-shadow:0 2px 20px rgba(91,91,214,.30)}
.topbar .in{max-width:var(--maxw);margin:0 auto;padding:13px 18px;display:flex;align-items:center;gap:14px;position:relative}
.brand{font-weight:800;font-size:18px;white-space:nowrap;display:flex;align-items:center;gap:9px;letter-spacing:.3px;flex-shrink:0}
.brand .dot{width:22px;height:22px;border-radius:7px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 1px rgba(255,255,255,.35) inset}
.brand .dot:after{content:"";width:9px;height:9px;border-radius:50%;background:#fff}
/* ★ v3.6.2：提示语移到顶栏，与站名同排，字号小一号；过长省略号保护 */
.tslogan{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;opacity:.88;letter-spacing:.2px;font-weight:400}
/* 搜索框：改为流式布局与提示语/按钮同排（v3.6.2 起不再绝对定位居中） */
.search{flex:0 1 38%;min-width:240px;max-width:520px;display:flex;gap:8px;margin:0;position:relative;z-index:2}
.search input{flex:1;padding:9px 14px;border:0;border-radius:10px;font-size:14px;outline:none;background:rgba(255,255,255,.94);color:var(--txt);min-width:0}
.search button{padding:9px 18px;border:0;border-radius:10px;background:rgba(255,255,255,.20);color:#fff;cursor:pointer;font-size:14px;transition:background .15s;flex-shrink:0}
.search button:hover{background:rgba(255,255,255,.34)}
.topbar a.admin{color:#fff;opacity:.88;font-size:13px;white-space:nowrap;padding:7px 12px;border-radius:9px;background:rgba(255,255,255,.12);transition:background .15s;flex-shrink:0}
.topbar a.admin:hover{background:rgba(255,255,255,.24)}
/* v3.6.2：用户区整体右对齐（管理后台按钮不再各自 margin-left:auto 挤位） */
.uarea{margin-left:auto;display:flex;align-items:center;gap:8px;flex:0 0 auto}
/* 首页 banner */
.banner{background:linear-gradient(120deg,#5b5bd6,#8b5cf6 60%,#a855f7);color:#fff;position:relative;overflow:hidden}
.banner:after{content:"";position:absolute;right:-60px;top:-60px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.10)}
.banner:before{content:"";position:absolute;left:-40px;bottom:-90px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.08)}
.banner .in{max-width:var(--maxw);margin:0 auto;padding:34px 18px 30px;position:relative;z-index:1}
.banner h1{margin:0 0 8px;font-size:28px;letter-spacing:.5px}
    .banner p{margin:0;opacity:.92;font-size:14.5px}
    /* ★ v3.6.9：首页首屏三大组 —— 常规标签 / 热门导演 / 热门演员 */
    .tg-group{margin-bottom:16px}
    .tg-title{color:#fff;font-weight:700;font-size:15px;letter-spacing:.3px;margin-bottom:10px;opacity:.95}
    .tg-list{display:flex;flex-wrap:wrap;gap:9px}
    .tg-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 15px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.26);color:#fff;font-size:13.5px;text-decoration:none;transition:background .15s,border-color .15s;white-space:nowrap}
    .tg-pill:hover{background:rgba(255,255,255,.30);border-color:rgba(255,255,255,.55)}
    .tg-pill b{font-size:12px;font-weight:400;opacity:.82}
    @media (max-width:560px){.tg-title{font-size:14px}.tg-pill{font-size:12.5px;padding:6px 12px;gap:4px}}
    .banner .badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.banner .badges span{background:rgba(255,255,255,.16);padding:6px 13px;border-radius:20px;font-size:13px}
.wrap{max-width:var(--maxw);margin:22px auto;padding:0 18px;overflow:hidden}
.toolbar{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.sortbtns{display:flex;gap:6px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:5px;box-shadow:var(--shadow)}
.sortbtns a{padding:8px 15px;border-radius:9px;font-size:14px;color:var(--mut);transition:.15s}
.sortbtns a:hover{color:var(--pri)}
.sortbtns a.on{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;box-shadow:0 6px 16px rgba(91,91,214,.32)}
.filters{display:flex;gap:8px;flex-wrap:wrap}
.filters a{padding:8px 15px;border:1px solid var(--line);background:var(--card);border-radius:20px;font-size:13px;color:var(--mut);transition:.15s}
.filters a:hover{border-color:var(--pri);color:var(--pri)}
.filters a.on{border-color:transparent;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(164px,1fr));gap:18px}
/* v4.0.10：不满一行时该排自动填占位（凑满 7 个位，占位透明） */
.grid > .ph{opacity:0;pointer-events:none;visibility:hidden}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:transform .18s,box-shadow .18s;box-shadow:var(--shadow)}
.card:hover{transform:translateY(-6px);box-shadow:var(--shadow-h)}
.c-thumb{position:relative;aspect-ratio:2/3;background:#eef0f5;overflow:hidden}
.c-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s}
.card:hover .c-thumb img{transform:scale(1.07)}
.c-noposter{display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:13px;height:100%}
.c-type{position:absolute;left:8px;top:8px;background:rgba(27,31,42,.72);color:#fff;font-size:12px;padding:3px 10px;border-radius:20px}
.c-dl{position:absolute;right:8px;top:8px;background:linear-gradient(120deg,#10b981,#34d399);color:#fff;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:20px;box-shadow:0 4px 12px rgba(16,185,129,.4)}
.c-body{padding:11px 13px 14px}
.c-title{display:flex;overflow:hidden;white-space:nowrap;font-weight:600;font-size:15px;line-height:1.35}
/* ★ v3.6.5：长标题不折行、超长隐藏；悬停滚动显示完整（无缝循环，副本 t-dup 撑开 2 倍宽度） */
.c-title .t-in{display:inline-block;white-space:nowrap;flex:0 0 auto}
.card:hover .c-title.ml{animation:c-title-scroll 8s linear infinite}
.card:hover .c-title.ml:hover{animation-play-state:paused}   /* 停在标题上可细看 */
@keyframes c-title-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.c-sub{color:var(--mut);font-size:12.5px;margin:3px 0 9px}
.c-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
/* ★ v3.6.1：评分 / 热度 / 点击三个指标横向一排、三等分等宽；内容过长省略号 */
.c-meta .chip{display:flex;align-items:center;justify-content:center;min-width:0;overflow:hidden;text-overflow:ellipsis;padding:3px 6px}
.chip{font-size:12px;padding:3px 9px;border-radius:8px;background:var(--bg);color:var(--mut);white-space:nowrap}
.chip-r{color:var(--gold);background:#fff7e6}
.chip-clk{color:var(--clk);background:#e9f9f1}
.chip-hot{color:#e8590c;background:#fff1e6}
.pager{display:flex;gap:10px;align-items:center;justify-content:center;margin:28px 0 10px}
.pager a,.pager span{padding:9px 17px;border:1px solid var(--line);background:var(--card);border-radius:10px;font-size:14px;color:var(--mut);transition:.15s}
.pager a:hover{border-color:var(--pri);color:var(--pri);box-shadow:var(--shadow)}
.empty{color:var(--mut);padding:40px 4px;text-align:center}
.foot{text-align:center;color:var(--mut);font-size:13px;padding:32px 0 44px}
/* ===== 内页 ===== */
.hero{position:relative;min-height:360px;display:flex;align-items:flex-end;color:#fff;overflow:hidden}
.hero .bg{position:absolute;inset:0;background:linear-gradient(135deg,#3a2a5e,#6b3f8f);background-size:cover;background-position:center;transform:scale(1.05)}
.hero .mask{position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,17,26,.20),rgba(15,17,26,.90))}
.hero .in{position:relative;max-width:var(--maxw);margin:0 auto;padding:34px 18px;display:flex;gap:26px;width:100%}
.hero .poster{width:184px;flex:0 0 184px;aspect-ratio:2/3;border-radius:16px;overflow:hidden;background:#222;box-shadow:0 16px 38px rgba(0,0,0,.5);position:relative}
.hero .poster img{width:100%;height:100%;object-fit:cover;display:block}
.hero .poster .np{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:13px}
.hero .info{flex:1;min-width:0;padding-bottom:4px}
/* ★ v4.3.15：按用户图示重排 —— 标题与类型标签、指标胶囊同一横排（标题左，标签/指标依次向右，
   窄屏自动换行）；简介文字直接填进 hero 头部，不再单独成卡。
   ★ v4.4.0：按用户二次反馈 —— 类型标签与指标整体「移到最右面」（margin-left:auto 贴右对齐）。 */
.hero .topline{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:8px}
.hero h1{margin:0;font-size:28px;line-height:1.25;letter-spacing:.3px}
.hero .ot{opacity:.82;font-size:14px;margin-bottom:10px}
.hero .tags{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto}
.hero .tags .t{background:rgba(255,255,255,.16);padding:4px 12px;border-radius:20px;font-size:13px}
.hero .stats{display:flex;gap:10px;flex-wrap:wrap}
/* ★ v4.4.7：简介铺满 hero 可用宽度（去掉 900px 上限，右侧不再留白）；正文 1.5 倍试验 */
.hero .syn{margin-top:14px;font-size:21px;line-height:1.9;color:rgba(255,255,255,.88)}
.hero .stats .s{background:rgba(255,255,255,.12);padding:8px 15px;border-radius:11px;font-size:13px}
.hero .stats .s b{font-size:16px;margin-left:2px}
.hero .stats .s.gold b{color:#ffcf6b}
.hero .stats .s.clk b{color:#7ef0c0}
.hero .stats .s.hot b{color:#ffb36b}
.detail{max-width:var(--maxw);margin:24px auto;padding:0 18px;display:grid;grid-template-columns:1fr;gap:22px}
.block{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px 22px;box-shadow:var(--shadow)}
.block h2{margin:0 0 14px;font-size:17px;display:flex;align-items:center;gap:9px}
.block h2:before{content:"";width:4px;height:17px;background:linear-gradient(180deg,var(--pri),var(--pri2));border-radius:3px;display:inline-block}
.ov{color:#3a3f4d;font-size:15px;white-space:pre-wrap}
.dl{list-style:none;padding:0;margin:0;display:flex;flex-direction:row;flex-wrap:wrap;gap:10px 12px;align-items:center;justify-content:flex-start}
.dl li{background:transparent;border:0;border-radius:0;padding:0;margin:0;word-break:normal;display:inline-flex;align-items:center;gap:0;transition:none}
.dl li:hover{border-color:transparent;background:transparent;box-shadow:none}
.dl li a{color:inherit;font-size:inherit;word-break:normal;text-decoration:none}
.dl-tag{flex:0 0 auto;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-size:12.5px;font-weight:600;padding:4px 12px;border-radius:20px;white-space:nowrap}
/* v4.2.6：把胶囊样式直接给 <a>（dl-btn），取代 .dl-tag + <a> 两个"光鸭"的冗余结构；
   同时 .dl-cell 去掉 li 卡片玻璃底，让胶囊按钮横向成排排列 */
.dl-cell{display:inline-flex;align-items:center;justify-content:center;background:transparent;border:0;padding:0;margin:0 4px 8px 0}
.dl-cell:hover{background:transparent;border-color:transparent;box-shadow:none;transform:none}
.dl-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff !important;font-size:21px;font-weight:600;padding:13px 30px;border-radius:28px;white-space:nowrap;text-decoration:none;transition:transform .15s,box-shadow .2s}
.dl-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(124,92,255,.35)}
.dl-raw{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:19px;color:#3a3f4d;white-space:pre-wrap;word-break:break-all}
.block .hint{color:var(--mut);font-size:18px;margin-top:10px}
/* v3.4.6：auto-fit 让空轨道自动塌缩 → 2 项各 50% / 3 项各 33% / 4+ 项按每行 3 列铺 */
.kv{display:grid;grid-template-columns:repeat(3,1fr);gap:12px 22px}
/* ★ v4.4.7：信息块正文 1.5 倍（标签/值/胶囊）
   ★ v4.4.9：3 列网格 —— 导演 1/3、演员 2/3（kv-w2），单字段块占满（kv-w3）；窄屏塌成单列 */
.kv-w2{grid-column:span 2}
.kv-w3{grid-column:span 3}
.kv .k{color:var(--mut);font-size:18px;margin-bottom:4px;letter-spacing:.2px}
.kv .v{font-size:21.5px;line-height:1.7;word-break:break-word}
/* ★ v3.7.2：导演/演员可点击人名胶囊（详情页信息块） */
.kv .info-pill{display:inline-flex;align-items:center;padding:5px 14px;margin:3px 6px 3px 0;border-radius:999px;background:var(--bg);border:1px solid var(--line);color:var(--txt);font-size:19px;text-decoration:none;transition:.15s;white-space:nowrap}
.kv .info-pill:hover{border-color:var(--pri);color:var(--pri);background:var(--card);box-shadow:0 3px 10px rgba(27,31,42,.08)}
.cast{display:flex;gap:14px;overflow-x:auto;padding-bottom:6px}
.cast .p{flex:0 0 110px;text-align:center}
.cast .p img{width:110px;height:150px;object-fit:cover;border-radius:11px;background:#eee}
.cast .p .nm{font-size:13px;margin-top:6px;font-weight:600}
.cast .p .ch{font-size:12px;color:var(--mut)}
.cast .p .p-np{width:110px;height:150px;background:#eee;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px}
/* v3.4.4：主要演职人员可左右拖动 + 箭头按钮；箭头默认隐藏，内容溢出后自动显示 */
.cast-wrap{position:relative;display:flex;align-items:flex-start;gap:4px}
.cast-wrap .cast{flex:1 1 auto;min-width:0;cursor:grab;scroll-behavior:smooth}
.cast-wrap .cast.dragging{cursor:grabbing;scroll-behavior:auto}
.cast-nav{flex:0 0 auto;width:32px;height:170px;border:1px solid var(--line);background:var(--card);color:var(--pri);border-radius:10px;cursor:pointer;display:none;align-items:center;justify-content:center;font-size:20px;line-height:1;transition:.15s;padding:0}
.cast-nav.show{display:flex}
.cast-nav:hover{background:linear-gradient(135deg,var(--pri),var(--pri2));color:#fff;border-color:var(--pri);box-shadow:0 6px 14px rgba(91,91,214,.22)}
.cast-nav:disabled{opacity:.3;cursor:not-allowed;background:var(--card);color:var(--mut);border-color:var(--line);box-shadow:none}
.cast-nav .a{pointer-events:none}
@media(max-width:680px){.cast-nav{height:150px;width:28px;font-size:17px}.cast-wrap .cast{padding-bottom:4px}}
.backbar{max-width:var(--maxw);margin:10px auto 0;padding:0 18px}
.backbar a{display:inline-block;padding:10px 20px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;border-radius:10px;font-size:14px;box-shadow:0 8px 20px rgba(91,91,214,.3)}
@media(max-width:680px){.hero .in{flex-direction:column}.hero .poster{width:140px;flex:0 0 140px}.hero h1{font-size:22px}.grid{grid-template-columns:repeat(auto-fill,minmax(132px,1fr))}.banner h1{font-size:22px}}
/* ===== 站点设置（LOGO / 站点名 / 宣传语 / 页脚）：后台「站点设置」页控制 ===== */
a.brand{color:#fff}
.brand .logo{width:26px;height:26px;border-radius:7px;object-fit:cover;background:#fff;flex:0 0 26px}
.brand .sname{font-weight:800}
.brand .slogan{font-weight:400;font-size:12.5px;opacity:.86;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.brand .slogan:before{content:"·";margin-right:6px;opacity:.7}
.foot .fin{max-width:var(--maxw);margin:0 auto;padding:0 18px;text-align:left}
.foot .fintro,.foot .fcopy{color:var(--mut);font-size:12.5px;line-height:1.95;margin:0 0 8px}
.foot .fdec{color:#b3b9c6;font-size:12px;line-height:1.95;margin:0 0 8px}
.foot .fbrand{color:var(--mut);font-size:12.5px;text-align:center;padding-top:10px;border-top:1px solid var(--line);margin-top:14px}
@media(max-width:680px){.brand .slogan{display:none}.foot .fin{text-align:center}}
/* ===== 顶部导航 / 前台模板 / 榜单样式 / 最新列表 =====
   全部由后台「站点设置 → 扫描+跳转」控制，未配置时走内置默认。 */
.mainnav{background:rgba(0,0,0,.13);border-top:1px solid rgba(255,255,255,.10)}
.mainnav .navin{max-width:var(--maxw);margin:0 auto;padding:0 18px;display:flex;gap:4px;overflow-x:auto}
.mainnav .navin::-webkit-scrollbar{height:0}
.mainnav a{color:#fff;opacity:.9;font-size:13.5px;padding:9px 13px;border-radius:8px;white-space:nowrap;transition:.15s}
.mainnav a:hover{background:rgba(255,255,255,.16);opacity:1}
/* 全网榜单「无图模式」：卡片变横向纯文字行 */
.grid-plain .card{display:flex;align-items:center;border-radius:12px}
.grid-plain .card:hover{transform:none}
.grid-plain .card .c-thumb{display:none}
.grid-plain .card .c-body{flex:1;min-width:0;padding:13px 16px}
.grid-plain .card .c-title{max-height:none}   /* v3.6.5：单行省略/滚动由基础 .c-title 统一处理 */
/* 最新更新区块 —— v3.4.7：改为一行 2 列（用户偏好），窄屏回退 1 列 */
.latest{margin:0 0 22px}
.latest h2{margin:0 0 12px;font-size:16px;display:flex;align-items:center;gap:8px}
.latest h2:before{content:"";width:4px;height:16px;border-radius:2px;background:linear-gradient(120deg,var(--pri),var(--pri2))}
.latest-list{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;display:grid;grid-template-columns:1fr 1fr;gap:0}
.latest-list a{display:flex;gap:10px;padding:11px 16px;border-bottom:1px solid var(--line);font-size:14px;transition:.15s;min-width:0}
.latest-list a:hover{background:#fafaff;color:var(--pri)}
/* 每行最后一列：右侧无线；每行最后一个：下侧无线 */
.latest-list a:nth-child(2n){border-left:1px solid var(--line)}
.latest-list a:nth-last-child(-n+2){border-bottom:0}
.latest-list .l-t{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.latest-list .l-m{color:var(--mut);font-size:12.5px;white-space:nowrap;flex-shrink:0}
@media(max-width:680px){.latest-list{grid-template-columns:1fr}.latest-list a:nth-child(2n){border-left:0}.latest-list a:nth-last-child(-n+2){border-bottom:1px solid var(--line)}.latest-list a:last-child{border-bottom:0}}
/* 简约模板：更紧凑、去装饰 */
.tpl-simple .banner .in{padding:24px 18px 20px}
.tpl-simple .banner h1{font-size:23px}
.tpl-simple .banner:before,.tpl-simple .banner:after{display:none}
.tpl-simple .sortbtns{box-shadow:none}
.tpl-simple .card{box-shadow:none}
.tpl-simple .block{box-shadow:none}
/* 简约模板 · 暗黑模式 */
.tpl-simple.mode-dark{--bg:#111827;--card:#1b2233;--line:#2b3348;--txt:#e5e9f2;--mut:#98a2b8;--shadow:0 6px 22px rgba(0,0,0,.35)}
.tpl-simple.mode-dark .card:hover{background:#212a3e}
.tpl-simple.mode-dark .filters a{background:var(--card)}
.tpl-simple.mode-dark .latest-list a:hover{background:#212a3e}
.tpl-simple.mode-dark .ov{color:#ffffff}
.tpl-simple.mode-dark .dl-raw{color:#d8deec}
/* v4.2.4：简介/下载地址/主要演职人员 等小标题 —— 从 #f2f5fb 提到纯白 #ffffff（用户要求"不是灰白是纯白"）；
   v4.2.3 那次用 `.tpl-simple.mode-dark` 选择器没生效（站点实际 body 是 tpl-classic，压根没 tpl-simple），此次同时补 body.mode-auto */
.tpl-simple.mode-dark .block h2{color:#ffffff}
.tpl-simple.mode-dark .latest h2{color:#ffffff}
.tpl-simple.mode-dark .dl li:hover{background:var(--card)}

/* ============================================================
   响应式自适应（v1.6.1）
   断点：>=1800 超宽 / >=1400 大屏 / <=1024 平板横屏 /
         <=768 平板竖屏 / <=560 手机 / <=380 小屏手机
   另覆盖：触屏去 hover 粘滞 / 刘海安全区 / 跟随系统深色 /
          减少动画 / 打印 / 键盘焦点 / iOS 输入框防缩放
   ============================================================ */

/* --- 基础：禁止 iOS 横屏放大字号、长英文词与长网址强制换行 --- */
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{overflow-wrap:break-word}
.mainnav .navin,.cast,.toolbar{-webkit-overflow-scrolling:touch}
.mainnav .navin::-webkit-scrollbar,.cast::-webkit-scrollbar,.toolbar::-webkit-scrollbar{height:0;width:0}

/* --- 键盘可达性：只在键盘聚焦时出现描边，鼠标点击不显示 --- */
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid rgba(91,91,214,.5);outline-offset:2px;border-radius:6px}
.topbar a:focus-visible,.mainnav a:focus-visible,.hero a:focus-visible{outline-color:#fff}

/* --- 超宽屏 / 大屏：容器与卡片适度放大，避免两侧留白过多 --- */
@media (min-width:1400px){
  :root{--maxw:1320px}
  .grid{grid-template-columns:repeat(auto-fill,minmax(176px,1fr))}
}
@media (min-width:1800px){
  :root{--maxw:1480px}
  .grid{grid-template-columns:repeat(auto-fill,minmax(190px,1fr))}
}

/* --- 平板横屏及以下（<=1024）：收窄边距 --- */
@media (max-width:1024px){
  .banner .in{padding-top:28px;padding-bottom:24px}
  .bn-notice{margin-top:-12px}
  .hero .in{padding-top:26px;padding-bottom:26px;gap:20px}
}

/* --- 平板竖屏（<=768） --- */
@media (max-width:768px){
  .grid{grid-template-columns:repeat(auto-fill,minmax(152px,1fr));gap:14px}
  .detail{gap:18px}
  .block{padding:18px 17px}
}

/* --- 手机（<=560）：顶栏折行、工具栏横滑、卡片 2-3 列、内页竖排 --- */
@media (max-width:560px){
  :root{--pad:13px}
  /* 顶栏：LOGO 与按钮缩小，搜索框换到第二行铺满 */
  .topbar .in{flex-wrap:wrap;gap:9px;padding-top:10px;padding-bottom:10px}
  .brand{font-size:16px}
  .brand .logo{width:23px;height:23px;flex:0 0 23px}
  .brand .dot{width:20px;height:20px;flex:0 0 20px}
  .search{order:3;flex:1 0 100%;max-width:none;margin-left:0;gap:7px;position:static;transform:none;left:auto;top:auto;width:100%}
  .search input{font-size:16px;padding:10px 13px}   /* 16px 可阻止 iOS 聚焦时整页放大 */
  .search button{padding:10px 15px;font-size:13.5px}
  .topbar a.admin{margin-left:auto;padding:8px 11px;font-size:12px}
  /* v4.2.5：窄屏下隐藏 tslogan（本站仅做资源索引那条免责声明），让 brand 与「← 返回主页」贴左，
     uarea（我的请求 / 退出）单独靠右——不再让「返回主页」被 slogan 挤到左侧换行显示 */
  .topbar .tslogan{display:none}
  .topbar a.admin{padding:6px 9px;font-size:11.5px}
  .uarea{gap:6px}
  /* 极窄屏（≤420px）再压紧一档，让 brand + 返回主页 + uarea 三块尽量塞进一行 */
  @media (max-width:420px){
    .topbar .in{gap:7px;padding:9px 11px}
    .brand{font-size:15px}
    .topbar a.admin{padding:5px 8px;font-size:11px}
    .uarea{gap:5px}
  }
  /* 导航：加高触摸目标，横向滑动 */
  .mainnav .navin{padding-top:2px;padding-bottom:2px}
  .mainnav a{padding:11px 13px;font-size:13px}
  /* 首屏 */
  .banner .in{padding-top:24px;padding-bottom:22px}
  .banner h1{font-size:21px}
  .banner p{font-size:13.5px}
  .banner .badges{gap:7px;margin-top:13px}
  .banner .badges span{padding:5px 11px;font-size:12px}
  /* 工具栏：整条横向滑动，不再折成两行把首屏顶下去 */
  .toolbar{flex-wrap:nowrap;overflow-x:auto;justify-content:flex-start;gap:10px;margin-bottom:14px;padding-bottom:3px}
  .sortbtns{flex:0 0 auto}
  .filters{flex:0 0 auto;flex-wrap:nowrap}
  .sortbtns a,.filters a{white-space:nowrap}
  /* 网格与卡片 */
  .wrap{margin:16px auto}
  .grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
  .c-body{padding:10px 11px 12px}
  .c-title{font-size:14px}
  .c-sub{font-size:12px}
  .chip{font-size:11.5px;padding:3px 8px}
  /* 内页 hero 与详情 */
  .hero{min-height:300px}
  .hero .in{padding-top:22px;padding-bottom:22px;gap:16px}
  .hero .poster{width:124px;flex:0 0 124px;border-radius:13px}
  .hero h1{font-size:20px}
  .hero .ot{font-size:13px}
  .hero .tags .t{font-size:12.5px;padding:4px 10px}
  .hero .stats{gap:7px}
  .hero .stats .s{padding:7px 11px;font-size:12.5px}
  .hero .stats .s b{font-size:15px}
  .block{padding:16px 15px;border-radius:13px}
  .block h2{font-size:16px}
  .ov{font-size:14px}
  .detail{margin-top:18px;gap:16px}
  .kv{grid-template-columns:1fr;gap:9px 0}
  .kv-w2,.kv-w3{grid-column:auto}
  .cast{gap:11px}
  .cast .p{flex:0 0 96px}
  .cast .p img,.cast .p .p-np{width:96px;height:132px}
  /* 下载地址：标签与地址改为竖排，长网址不再被挤成两三个字一行 */
  .dl li{flex-direction:column;align-items:flex-start;gap:7px;padding:12px 13px}
  .dl li a{font-size:18px}
  .dl-raw{font-size:17px}
  /* ★ v4.4.7：手机端正文同步 1.5 倍（略低于桌面端，防长词溢出） */
  .kv .k{font-size:16px}
  .kv .v{font-size:19.5px}
  .kv .info-pill{font-size:17px}
  .hero .syn{font-size:19px}
  .backbar a{display:block;text-align:center;padding:11px 18px}
  /* 分页 */
  .pager{gap:7px;margin:20px 0 8px}
  .pager a,.pager span{padding:9px 13px;font-size:13px}
  /* 最新更新：标题独占一行，分类与年份折到下一行 */
  .latest{margin-bottom:18px}
  .latest-list a{flex-wrap:wrap;gap:3px 10px;padding:11px 13px}
  .latest-list .l-t{flex:1 0 100%;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  /* 页脚 */
  .foot{padding-top:26px}
  .foot .fin{text-align:center}
  .foot .fintro,.foot .fcopy,.foot .fdec{font-size:12px}
}

/* --- 小屏手机（<=380）：进一步压缩，保证 320px 仍能并排两张卡 --- */
@media (max-width:380px){
  .banner h1{font-size:19px}
  .banner .badges span{font-size:11.5px;padding:4px 9px}
  .grid{grid-template-columns:repeat(auto-fill,minmax(126px,1fr));gap:10px}
  .c-title{font-size:13px}
  .sortbtns a,.filters a{padding:7px 11px;font-size:12.5px}
  .pager a,.pager span{padding:8px 11px;font-size:12.5px}
  .hero .poster{width:104px;flex:0 0 104px}
  .hero h1{font-size:18px}
  .mainnav a{padding:10px 11px;font-size:12.5px}
}

/* --- 手机横屏（高度很小的横屏场景）：首屏与 hero 不再占满一屏 --- */
@media (max-height:520px) and (orientation:landscape){
  .banner .in{padding-top:18px;padding-bottom:16px}
  .bn-notice{margin-top:-4px}
  .banner .badges{display:none}
  .hero{min-height:auto}
  .hero .in{padding-top:16px;padding-bottom:16px}
  .topbar{position:static}
}

/* --- 触屏 / 粗指针：取消 hover 造成的位移与粘滞高亮 --- */
@media (hover:none){
  .card:hover{transform:none;box-shadow:var(--shadow)}
  .card:hover .c-thumb img{transform:none}
  .dl li:hover{background:var(--bg);border-color:var(--line)}
  .mainnav a:hover{background:none;opacity:.9}
  .sortbtns a:not(.on):hover{color:var(--mut)}
  .filters a:not(.on):hover{border-color:var(--line);color:var(--mut)}
  .pager a:hover{border-color:var(--line);color:var(--mut);box-shadow:none}
  .latest-list a:hover{background:none;color:inherit}
  .search button:hover{background:rgba(255,255,255,.20)}
  .topbar a.admin:hover{background:rgba(255,255,255,.12)}
}

/* --- 系统「减少动态效果」：关掉位移与过渡 --- */
@media (prefers-reduced-motion:reduce){
  *,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
  .card:hover{transform:none}
  .card:hover .c-thumb img{transform:none}
}

/* --- 系统「高对比度」：加深文字与边框 --- */
@media (prefers-contrast:more){
  :root{--mut:#5c6474;--line:#c9cdd8}
  .chip{background:#eceef5;color:#454b59}
}

/* --- 打印：去掉导航与交互元素，正文可读 --- */
@media print{
  .topbar,.mainnav,.banner,.toolbar,.pager,.backbar,.foot,.latest{display:none!important}
  body{background:#fff;color:#000}
  .wrap,.detail{max-width:none;margin:0;padding:0}
  .grid{grid-template-columns:repeat(3,1fr);gap:10px}
  .card,.block{box-shadow:none;border-color:#d8dbe2;break-inside:avoid;page-break-inside:avoid}
  .hero{min-height:auto;color:#000}
  .hero .bg,.hero .mask{display:none}
  .hero h1,.hero .ot{color:#000}
  .hero .tags .t,.hero .stats .s{background:#f1f3f7;color:#111}
  .hero .stats .s b,.hero .stats .s.gold b,.hero .stats .s.clk b,.hero .stats .s.hot b{color:#111}
  .block h2:before{background:#666}
  .dl li{background:#fafafa}
  a{text-decoration:underline}
}

/* --- 跟随系统深色：后台把「明暗模式」设为「跟随系统」时生效（经典/简约通用） --- */
@media (prefers-color-scheme:dark){
  body.mode-auto{--bg:#0f1420;--card:#182034;--line:#28324a;--txt:#e6eaf3;--mut:#94a0b8;
    --shadow:0 6px 22px rgba(0,0,0,.42);--shadow-h:0 16px 38px rgba(0,0,0,.58);color-scheme:dark}
  body.mode-auto .dl-raw{color:#d8deec}
  /* v4.0.8 → v4.2.4：暗色模式简介 .ov 五次提亮（#c3cad8 太暗 → #e5e9f2 仍偏灰 → #eef2fb → #f2f5fb → 现 #ffffff 纯白；
     用户"改成白的"明确要纯白。同时 .block h2/.latest h2 之前用 `.tpl-simple.mode-dark` 选择器没匹配到（站点 body 是 tpl-classic），
     此次统一用 `body.mode-auto`，纯白 #ffffff */
  body.mode-auto .ov{color:#ffffff}
  /* v4.2.4：.block h2 / .latest h2 与 .ov 同纯白，标题不再偏灰；也覆盖 `.tpl-simple.mode-dark` 那套未生效的写法 */
  body.mode-auto .block h2,body.mode-auto .latest h2{color:#ffffff}
  body.mode-auto .tpl-simple.mode-dark .block h2,body.mode-auto .tpl-simple.mode-dark .latest h2,body.mode-auto .tpl-simple.mode-dark .ov{color:#ffffff}
  body.mode-auto .chip{background:#222c42}
  body.mode-auto .chip-r{background:#3a2f12;color:#f5b942}
  body.mode-auto .chip-clk{background:#103026;color:#3ddc97}
  body.mode-auto .chip-hot{background:#3a2412;color:#ff9f5b}
  body.mode-auto .c-thumb{background:#232c45}
  body.mode-auto .cast .p img,body.mode-auto .cast .p .p-np{background:#232c45}
  body.mode-auto .dl li:hover{background:#1d263b;border-color:var(--pri)}
  body.mode-auto .latest-list a:hover{background:#1d263b}
  body.mode-auto .hero .poster{background:#0b0f18}
  body.mode-auto .foot .fdec{color:#7d879c}
  body.mode-auto .banner:before,body.mode-auto .banner:after{opacity:.45}
}

/* --- 顶栏用户区（登录 / 注册 / 我的请求）--- */
.uarea{display:flex;align-items:center;gap:8px;white-space:nowrap}
.uarea .uname{color:#fff;font-size:13px;opacity:.92;max-width:120px;overflow:hidden;text-overflow:ellipsis}
@media(max-width:680px){.uarea .uname{display:none}.uarea a.admin{padding:8px 11px;font-size:12px}}
@media(hover:none){.uarea a.admin:hover{background:rgba(255,255,255,.12)}}

/* --- 首页热门标签云（★ v1.8.8：首页首屏由 banner 改为标签云） --- */
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.tagcloud .in{padding:20px 18px 14px}
.tc-stage{position:relative;width:100%;min-height:190px}
.tc-t{position:absolute;left:0;top:0;display:inline-block;white-space:nowrap;padding:5px 13px;border-radius:19px;
  background:rgba(255,255,255,.15);color:#fff;font-size:13.5px;line-height:1.4;
  transform:translate3d(0,0,0);
  transition:transform 2.8s cubic-bezier(.37,0,.36,1),background .2s,color .2s,box-shadow .2s;
  will-change:transform;-webkit-tap-highlight-color:transparent}
.tc-t.tc-h1{font-size:17.5px;font-weight:700;background:rgba(255,255,255,.24)}
.tc-t.tc-h2{font-size:15px;background:rgba(255,255,255,.19)}
.tagcloud.tc-init .tc-t{transition:none!important}
.tagcloud.on .tc-t{transition:transform .58s cubic-bezier(.22,.9,.28,1),background .2s,color .2s,box-shadow .2s}
.tagcloud.on .tc-t:hover{background:#fff;color:var(--pri);box-shadow:0 10px 24px rgba(0,0,0,.22)}
.tc-empty{margin:0;padding:44px 0;text-align:center;font-size:14px;opacity:.92}
    /* --- 列表页「当前标签」提示条 --- */
.tagbar{display:flex;align-items:center;gap:9px;margin:0 0 14px;font-size:13.5px;color:var(--mut);flex-wrap:wrap}
.tagbar b{color:var(--txt);font-weight:600}
.tagbar a{padding:4px 12px;border:1px solid var(--line);border-radius:20px;background:var(--card);color:var(--mut);font-size:12.5px;transition:.15s}
.tagbar a:hover{border-color:var(--pri);color:var(--pri)}
/* ★ v3.8.3：PanSou 搜索提示条 */
.pansou-bar{display:flex;align-items:center;gap:8px;margin:0 0 14px;padding:10px 14px;border-radius:10px;background:linear-gradient(135deg,rgba(91,91,214,.12),rgba(139,92,246,.08));border:1px solid rgba(91,91,214,.25);font-size:13.5px;color:var(--txt);line-height:1.5}
.pansou-bar b{color:var(--pri);font-weight:600}
/* --- 管理入口主按钮（★ v1.8.8：原先这段 CSS 被误粘在最新更新列表里，会当纯文本显示） --- */
.adm-btn{margin:18px 0 8px;text-align:center}
.adm-btn a{display:inline-block;background:linear-gradient(120deg,#5b5bd6,#8b5cf6);color:#fff;font-size:15px;font-weight:600;padding:13px 28px;border-radius:12px;text-decoration:none;box-shadow:0 8px 22px rgba(91,91,214,.28);transition:box-shadow .15s,transform .15s}
.adm-btn a:hover{box-shadow:0 12px 30px rgba(91,91,214,.38);transform:translateY(-1px)}
@media(max-width:680px){
  .tagcloud .in{padding:15px 13px 11px}
  .tc-t{padding:4px 10px;font-size:12.5px;border-radius:16px}
  .tc-t.tc-h1{font-size:15px}
  .tc-t.tc-h2{font-size:13.5px}
  .tagbar{font-size:12.5px}
  .adm-btn a{width:100%;text-align:center}
}

/* --- v3.1.3：顶栏公告下沉 banner 居中 / 空状态横跨整行居中 / 页脚居中限宽 --- */
/* ★ v3.1.9：公告负 margin 上提，贴近顶栏搜索框（banner padding-top 34px 显得空） */
/* ★ v3.4.9：公告改为搜索框与标签之间垂直居中（上下各留一半 margin） */
.bn-notice{margin:14px 0;text-align:center;font-size:13px;opacity:.88;letter-spacing:.3px;line-height:1.7}
.grid .empty{grid-column:1/-1;padding:56px 4px;font-size:14.5px}
.foot .fin{max-width:860px;text-align:center}
.foot .fdec{margin:0 0 10px}
@media(max-width:680px){
  .bn-notice{font-size:12.5px;margin:10px 0}
  .grid .empty{padding:42px 4px;font-size:13.5px}
}

/* --- 刘海屏 / 底部横条安全区（放在最后，确保左右内边距不被前面的简写覆盖） --- */
@supports(padding:max(0px)){
  .topbar .in,.banner .in,.wrap,.hero .in,.detail,.backbar,.mainnav .navin,.foot .fin{
    padding-left:max(var(--pad),env(safe-area-inset-left));
    padding-right:max(var(--pad),env(safe-area-inset-right));
  }
  .foot{padding-bottom:max(44px,calc(env(safe-area-inset-bottom) + 22px))}
}

/* ============================================================
   ★ v3.7.8 千万级站点现代化主题（默认暗色 + 玻璃拟态）
   设计参考：Netflix / IMDb / Vika / 现代 SaaS 后台
   关键特性：
     - 默认暗色（现代影视站主流）
     - 玻璃拟态卡片（backdrop-filter blur）
     - 渐变光效背景（多色 radial gradient）
     - 动画加载骨架（loading skeleton）
     - 现代悬停效果（3D 倾斜 + 光晕）
     - 中文字体优化（PingFang SC / Noto Sans SC）
   ============================================================ */

/* 现代字体栈：中文优先，英文回退 */
:root{
  font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Hiragino Sans GB","Microsoft YaHei","Noto Sans SC",Roboto,"Segoe UI",sans-serif;
}

/* 默认暗色（无 body.mode-auto 时也走暗色） */
:root{
  --bg:#0a0e1a;--card:#12172b;--line:#1f2640;--pri:#7c5cff;--pri2:#c026d3;
  --txt:#f0f2f8;--mut:#8892b0;--gold:#ffb742;--clk:#34d399;--hot:#fb7185;
  --shadow:0 8px 28px rgba(0,0,0,.48);--shadow-h:0 22px 52px rgba(124,92,255,.28);
  --glass:rgba(255,255,255,.06);--glass-bd:rgba(255,255,255,.12);
}

/* 渐变光效背景（body 上多层 radial-gradient，营造深度感） */
body{
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(124,92,255,.18), transparent 60%),
    radial-gradient(900px 500px at 90% 10%, rgba(192,38,211,.14), transparent 60%),
    radial-gradient(700px 400px at 50% 100%, rgba(52,211,153,.08), transparent 60%),
    linear-gradient(180deg, #0a0e1a 0%, #0f1424 100%);
  background-attachment:fixed;
}

/* 顶栏：玻璃拟态 + 渐变 */
.topbar{
  background:linear-gradient(120deg,rgba(124,92,255,.85),rgba(192,38,211,.75));
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(255,255,255,.10);
  box-shadow:0 4px 24px rgba(124,92,255,.30);
}

/* 卡片：玻璃拟态 */
.card{
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.02));
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border:1px solid var(--glass-bd);
  box-shadow:var(--shadow);
  transition:transform .28s cubic-bezier(.22,.9,.28,1),box-shadow .28s,border-color .28s;
}
.card:hover{
  transform:translateY(-8px) scale(1.015);
  border-color:rgba(124,92,255,.45);
  box-shadow:var(--shadow-h),0 0 0 1px rgba(124,92,255,.20);
}
.card:hover .c-thumb img{transform:scale(1.10)}

/* 卡片缩略图：暗色背景 + 现代圆角 */
.c-thumb{background:linear-gradient(135deg,#1a2138,#0f1424);border-radius:16px 16px 0 0}

/* 排序按钮：玻璃拟态 */
.sortbtns{
  background:var(--glass);
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
  border:1px solid var(--glass-bd);
}
.sortbtns a.on{
  background:linear-gradient(120deg,var(--pri),var(--pri2));
  box-shadow:0 8px 20px rgba(124,92,255,.40);
}

/* Banner：更强的光效 */
.banner{
  background:
    radial-gradient(circle at 20% 30%, rgba(192,38,211,.30), transparent 45%),
    radial-gradient(circle at 80% 70%, rgba(124,92,255,.35), transparent 45%),
    linear-gradient(120deg,#5b5bd6,#8b5cf6 60%,#a855f7);
  position:relative;overflow:hidden;
}
.banner:before,.banner:after{
  filter:blur(30px);
  animation:bannerFloat 12s ease-in-out infinite;
}
@keyframes bannerFloat{
  0%,100%{transform:translate(0,0) scale(1)}
  50%{transform:translate(20px,-15px) scale(1.1)}
}

/* Hero 区：暗色渐变 */
.hero{
  background:linear-gradient(135deg,#0f1424,#1a0f2e);
  border-top:1px solid var(--glass-bd);
}
.hero .bg{
  background:linear-gradient(135deg,rgba(15,20,36,.5),rgba(26,15,46,.5)),linear-gradient(135deg,#3a2a5e,#6b3f8f);
}
.hero .poster{
  border:1px solid var(--glass-bd);
  box-shadow:0 20px 48px rgba(0,0,0,.60),0 0 0 1px rgba(255,255,255,.05);
}

/* 标签胶囊：玻璃拟态 */
.tg-pill{
  background:var(--glass);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1px solid var(--glass-bd);
  transition:transform .15s,background .2s,border-color .2s;
}
.tg-pill:hover{
  transform:translateY(-2px);
  background:rgba(255,255,255,.22);
  border-color:rgba(255,255,255,.45);
}

/* 详情块：玻璃拟态 */
.block{
  background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01));
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border:1px solid var(--glass-bd);
  box-shadow:var(--shadow);
}
.block h2:before{
  background:linear-gradient(180deg,var(--pri),var(--pri2));
  box-shadow:0 0 12px rgba(124,92,255,.50);
}

/* 下载链接：玻璃拟态 + 悬停光晕 */
.dl li{
  background:var(--glass);
  border:1px solid var(--glass-bd);
  transition:transform .15s,background .2s,border-color .2s,box-shadow .2s;
}
.dl li:hover{
  background:rgba(124,92,255,.10);
  border-color:var(--pri);
  box-shadow:0 8px 24px rgba(124,92,255,.20);
  transform:translateY(-1px);
}

/* 搜索框：现代圆角 + 聚焦光晕 */
/* ★ v3.8.4：修复两个导致「输入框无法输入」的问题：
   1. 文字颜色 —— 原规则 color:var(--txt)=#f0f2f8 在白色背景上完全不可见，
      用户实际能输入但看不到文字，误以为「无法输入」。改为深色 #1a1a2e。
   2. Chrome backdrop-filter 交互缺陷 —— .topbar 的 backdrop-filter:blur(12px)
      会让子表单控件在非独立合成层时失去焦点/键入响应。
      加 transform:translateZ(0) 强制输入框进入独立合成层。 */
.search input{
  background:rgba(255,255,255,.95);
  border-radius:12px;
  color:#1a1a2e;
  transform:translateZ(0);
  transition:box-shadow .2s,transform .15s;
}
.search input::placeholder{color:#8892b0}
.search input:focus{
  box-shadow:0 0 0 3px rgba(124,92,255,.35);
  transform:translateZ(0) scale(1.01);
}

/* 骨架屏（loading skeleton） */
.skeleton{
  background:linear-gradient(90deg,rgba(255,255,255,.04) 25%,rgba(255,255,255,.10) 50%,rgba(255,255,255,.04) 75%);
  background-size:200% 100%;
  animation:skeleton-shimmer 1.5s ease-in-out infinite;
  border-radius:8px;
}
@keyframes skeleton-shimmer{
  0%{background-position:200% 0}
  100%{background-position:-200% 0}
}

/* 分页：玻璃拟态 */
.pager a,.pager span{
  background:var(--glass);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1px solid var(--glass-bd);
  transition:transform .15s,background .2s,border-color .2s;
}
.pager a:hover{
  background:rgba(124,92,255,.15);
  border-color:var(--pri);
  transform:translateY(-2px);
}
.pager a.on,.pager span.on{
  background:linear-gradient(120deg,var(--pri),var(--pri2));
  border-color:transparent;
  box-shadow:0 8px 20px rgba(124,92,255,.35);
}

/* Chip 徽章：玻璃拟态 */
.chip{
  background:var(--glass);
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
  border:1px solid var(--glass-bd);
}
.chip-r{background:rgba(255,183,66,.12);border-color:rgba(255,183,66,.30);color:var(--gold)}
.chip-clk{background:rgba(52,211,153,.12);border-color:rgba(52,211,153,.30);color:var(--clk)}
.chip-hot{background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.30);color:var(--hot)}

/* 类型徽章：渐变 */
.c-type{
  background:linear-gradient(120deg,rgba(124,92,255,.85),rgba(192,38,211,.75));
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.20);
  font-weight:600;
  box-shadow:0 4px 12px rgba(124,92,255,.30);
}

/* 下载徽章：绿色渐变 + 光晕 */
.c-dl{
  background:linear-gradient(120deg,#10b981,#34d399);
  box-shadow:0 6px 18px rgba(16,185,129,.45),0 0 0 1px rgba(255,255,255,.15);
  font-weight:700;
}

/* 管理入口按钮：3D 渐变 */
.adm-btn a{
  background:linear-gradient(135deg,#7c5cff 0%,#c026d3 50%,#ec4899 100%);
  box-shadow:0 10px 28px rgba(124,92,255,.40),0 0 0 1px rgba(255,255,255,.10);
  transition:transform .2s,box-shadow .2s;
}
.adm-btn a:hover{
  transform:translateY(-2px);
  box-shadow:0 16px 36px rgba(124,92,255,.55),0 0 0 1px rgba(255,255,255,.20);
}

/* 品牌 logo：动效 */
.brand{
  transition:transform .2s;
}
.brand:hover{
  transform:scale(1.03);
}
.brand .dot{
  background:linear-gradient(135deg,rgba(255,255,255,.30),rgba(255,255,255,.15));
  box-shadow:0 0 0 1px rgba(255,255,255,.40) inset,0 0 12px rgba(255,255,255,.30);
}

/* 页脚：暗色渐变 */
.foot{
  background:linear-gradient(180deg,transparent,rgba(0,0,0,.30));
  padding-top:60px;
}

/* 滚动条：现代暗色 */
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-track{background:rgba(255,255,255,.04)}
::-webkit-scrollbar-thumb{background:rgba(124,92,255,.40);border-radius:10px}
::-webkit-scrollbar-thumb:hover{background:rgba(124,92,255,.60)}

/* 选中文字：品牌色 */
::selection{background:rgba(124,92,255,.35);color:#fff}

/* 焦点可见（无障碍） */
:focus-visible{outline:2px solid var(--pri);outline-offset:2px;border-radius:4px}

/* 减少动态效果 */
@media (prefers-reduced-motion:reduce){
  body{background-attachment:scroll}
  .banner:before,.banner:after{animation:none}
  .skeleton{animation:none}
  .card,.card:hover,.tg-pill:hover,.dl li:hover,.pager a:hover,.adm-btn a:hover,.brand:hover{transform:none}
}

CSS;}public static function cardHtml(array $a){$b=(int)((isset($a['id'])?$a['id']:0));$c=self::esc(self::cleanTitle((isset($a['title'])?$a['title']:'未命名')));$d=!empty($a['year'])?('· '.$a['year']):'';$e=($a['rating']!==null&&$a['rating']!=='')?number_format((float)$a['rating'],1):'—';$f=self::typeLabel((isset($a['type'])?$a['type']:''));$g=(isset($a['poster'])?$a['poster']:'');$h=self::imgUrl($g);$i=(int)((isset($a['clicks'])?$a['clicks']:0));$j=self::formatCount($i);$k=self::hasDownloads((isset($a['download_url'])?$a['download_url']:''));$l=self::popularityOf((isset($a['extra'])?$a['extra']:''));if((isset($a['source'])&&$a['source']==='hongguoduanju')&&(isset($a['type'])&&$a['type']==='short')){$m=self::shortStat((isset($a['extra'])?$a['extra']:''),'hot');if($m>0){$l=self::intCount($m);}$n=self::shortStat((isset($a['extra'])?$a['extra']:''),'clk');if($n>0){$j=self::intCount($n);}}$o=($l!=='')?'<span class="chip chip-hot" title="热度（短剧=点赞，无点赞取播放量）">🔥 '.$l.'</span>':'';$p=$h?'<img src="'.self::esc($h).'"'.self::imgFallback($g).' alt="'.self::esc((isset($a['title'])?$a['title']:'')).'" loading="lazy">':'<div class="c-noposter">无封面</div>';$q=$k?'<span class="c-dl">可下载</span>':'';return <<<HTML
<a class="card" href="/uisc/{$b}.html">
  <div class="c-thumb">{$p}<span class="c-type">{$f}</span>{$q}</div>
    <div class="c-body">
    <!-- v3.6.5：标题单行不折行，超长省略；悬停由 .ml 触发滚动显示完整（t-dup 为无缝滚动副本） -->
    <div class="c-title"><span class="t-in">{$c}</span><span class="t-in t-dup" aria-hidden="true">{$c}</span></div>
    <div class="c-sub">{$f} {$d}</div>
    <div class="c-meta">
      <span class="chip chip-r">★ {$e}</span>
      {$o}
      <span class="chip chip-clk">点击 {$j}</span>
    </div>
  </div>
</a>
HTML;}public static function intCount($a){$a=(int)round((float)$a);if($a<9999){return(string)$a;}$b=array(1e9=>'B',1e6=>'M',1e3=>'K');foreach($b as $c=>$d){if($a>=$c){return(string)(int)round($a/$c).$d;}}return(string)$a;}public static function shortStat($a,$b){$c=is_array($a)?$a:json_decode((string)$a,true);if(!is_array($c)){return 0.0;}if($b==='hot'){foreach(array('heat_count','praise_count','praise','like_count','play_count','popularity')as $d){if(isset($c[$d])&&is_numeric($c[$d])&&(float)$c[$d]>0){return(float)$c[$d];}}}elseif($b==='clk'){foreach(array('favorite_count','favorite','collect_count','collect')as $d){if(isset($c[$d])&&is_numeric($c[$d])&&(float)$c[$d]>0){return(float)$c[$d];}}}return 0.0;}public static function popularityOf($a){$b=null;if(is_array($a)){$b=$a;}elseif(is_string($a)&&$a!==''){$b=json_decode($a,true);}if(!is_array($b)||!isset($b['popularity'])||!is_numeric($b['popularity'])){return'';}$c=(float)$b['popularity'];if($c<=0){return'';}return self::intCount($c);}public static function formatCount($a){$a=abs((float)$a);if($a<9999){return($a==floor($a))?(string)(int)$a:number_format($a,2);}$b=array(1e12=>'T',1e9=>'B',1e6=>'M',1e3=>'K');foreach($b as $c=>$d){if($a>=$c/1e3){$e=$a/$c;if($e>=100){return number_format($e,0).$d;}if($e>=10){return number_format($e,1).$d;}return number_format($e,2).$d;}}return number_format($a/1e12,2).'T';}public static function hasDownloads($a){foreach(self::parseDownloads($a)as $b){if(trim((string)((isset($b['url'])?$b['url']:'')))!==''){return true;}}return false;}public static function homeHtml(array $a,$b,$c,$d,$e,$f,$g,$h=array(),$i='',$j=null,$k=''){$l=self::esc($f);$m='';if($k!==''){$m='<div class="pansou-bar">'.self::esc($k).'</div>';}$n=['api_calls'=>'热度','clicks'=>'点击','updated_at'=>'最新','rating'=>'评分',];$o=[''=>'全部'];foreach(Categories::TYPES as $p){$o[$p]=Categories::label($p);}try{$q=\Core\CacheStore::stats();if(is_array($q)&&isset($q['by_type'])){$r=array_keys($q['by_type']);foreach($o as $s=>$t){if($s!==''&&!in_array($s,$r,true)){unset($o[$s]);}}}}catch(\Throwable $u){}$v=($i!==''?('&tag='.urlencode($i)):'');$w='';if(is_array($j)&&isset($j['name'])&&(string)$j['name']!==''){$w='/person/'.rawurlencode((string)(isset($j['kind'])?$j['kind']:'director')).'/'.rawurlencode((string)$j['name']);}$x=function($a,$b,$c)use($f,$i,$w){if($w!==''){$d=$w.'?sort='.rawurlencode((string)$a);if($b!==''){$d.='&type='.rawurlencode((string)$b);}if($c>0){$d.='&page='.(int)$c;}return $d;}$d='/?sort='.rawurlencode((string)$a).'&type='.rawurlencode((string)$b).'&q='.urlencode((string)$f);if($i!==''){$d.='&tag='.urlencode((string)$i);}if($c>0){$d.='&page='.(int)$c;}return $d;};$y='';foreach($n as $s=>$z){$a1=$s===$b?' class="on"':'';$y.='<a'.$a1 .' href="'.self::esc($x($s,$c,0)).'">'.$z.'</a>';}$b1='';foreach($o as $s=>$z){$a1=$s===$c?' class="on"':'';$b1 .='<a'.$a1 .' href="'.self::esc($x($b,$s,0)).'">'.$z.'</a>';}$c1='';foreach($a as $d1){$c1 .=self::cardHtml($d1);}if($c1===''){$c1='<p class="empty">'.($w!==''?'这位影人名下暂无已入库的作品。':'暂无资料，去后台添加或触发一次同步吧。').'</p>';}$e1=max(1,(int)ceil($e/$g));$f1='';if($e1>1){$g1=$d>1?'<a href="'.self::esc($x($b,$c,$d-1)).'">上一页</a>':'<span>上一页</span>';$h1=$d<$e1?'<a href="'.self::esc($x($b,$c,$d+1)).'">下一页</a>':'<span>下一页</span>';$f1='<div class="pager">'.$g1 .'<span>第 '.$d.' / '.$e1 .' 页</span>'.$h1 .'</div>';}$i1=(isset($n[$b])?$n[$b]:'点击');$j1=(isset($o[$c])?$o[$c]:'全部');$k1=number_format($e);$l1=Site::info();$m1=($c===''&&$f===''&&$i===''&&(int)$d===1);$n1=Site::headMeta($m1?'home':'list',array('type_label'=>($c!==''?$j1:''),'sort_label'=>$i1,'page'=>$d,));$o1='<form class="search" action="/" method="get">'.'<input type="hidden" name="sort" value="'.self::esc($b).'">'.'<input type="hidden" name="type" value="'.self::esc($c).'">'.'<input type="text" name="q" value="'.$l.'" placeholder="搜索标题…">'.'<button type="submit">搜索</button></form>';$p1=(bool)Config::sub('admin','show_link',1);$q1='';if($p1){$q1='<a class="admin" href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a>';}$r1=self::topbarHtml($o1 .$q1);$s1=self::footerHtml();$t1=$l1['slogan']!==''?self::esc($l1['slogan']):'影视 · 剧集 · 动漫 · 综艺 · 短剧 · 游戏';$u1='一站式资源资料库，支持在线检索与一键下载。';$v1='';$w1='';$x1='';if(class_exists('Core\\CacheStore')&&class_exists('Core\\DB')){$y1=array();$z1=array();$a2=array('directors'=>array(),'actors'=>array());try{$y1=\Core\CacheStore::regularTags();}catch(\Throwable $u){$y1=array();}try{$z1=\Core\CacheStore::hotTags(50);}catch(\Throwable $u){$z1=array();}try{$a2=\Core\CacheStore::hotPeople(16);}catch(\Throwable $u){$a2=array('directors'=>array(),'actors'=>array());}$b2=array();foreach($z1 as $c2){$b2[(string)$c2['tag']]=(int)$c2['clicks'];}$d2=array();$e2=function($a,$b,$c,$d)use(&$d2){if($a===''||isset($d2[$a])){return;}$a=trim((string)$a);if($a===''){return;}if(preg_match('/^[-—–−_~？?·、,，\s]+$/u',$a)){return;}if(preg_match('/^[\x{4e00}-\x{9fff}·]+$/u',$a)){if(mb_strlen($a,'UTF-8')<=1){return;}if(mb_strlen($a,'UTF-8')<=2&&strpos($a,'·')!==false){return;}}if(preg_match('/^[A-Za-z0-9\.\s]+$/',$a)&&mb_strlen($a,'UTF-8')<3){return;}$d2[$a]=array('tag'=>$a,'items'=>(int)$b,'clicks'=>(int)$c,'kind'=>$d);};foreach($y1 as $p){$f2=(string)(isset($p['tag'])?$p['tag']:'');if($f2===''){continue;}$e2($f2,(int)(isset($p['items'])?$p['items']:0),(int)(isset($b2[$f2])?$b2[$f2]:0),'tag');}foreach((isset($a2['directors'])&&is_array($a2['directors']))?$a2['directors']:array()as $g2){$h2=trim((string)(isset($g2['name'])?$g2['name']:''));if($h2===''){continue;}$e2($h2,(int)(isset($g2['items'])?$g2['items']:0),(int)(isset($g2['clicks'])?$g2['clicks']:0),'director');}foreach((isset($a2['actors'])&&is_array($a2['actors']))?$a2['actors']:array()as $g2){$h2=trim((string)(isset($g2['name'])?$g2['name']:''));if($h2===''){continue;}$e2($h2,(int)(isset($g2['items'])?$g2['items']:0),(int)(isset($g2['clicks'])?$g2['clicks']:0),'actor');}$i2=array_values($d2);usort($i2,function($b,$a){$c=$b['clicks']>0?$b['clicks']:$b['items'];$d=$a['clicks']>0?$a['clicks']:$a['items'];return $d<=>$c;});$i2=array_slice($i2,0,50);$j2=0;foreach($i2 as $k2){$j2++;$l2='tc-t';if($j2<=8){$l2 .=' tc-h1';}elseif($j2<=22){$l2 .=' tc-h2';}$f2=(string)$k2['tag'];$m2=$k2['clicks']>0?$k2['clicks']:$k2['items'];$n2=($k2['kind']==='director')?'/person/director/'.rawurlencode($f2):(($k2['kind']==='actor')?'/person/actor/'.rawurlencode($f2):'/tag/'.urlencode($f2));$w1 .='<a class="'.$l2 .'" href="'.self::esc($n2).'" data-hot="'.$m2 .'"'.' title="'.self::esc($f2).'：'.(int)$k2['items'].' 部 · 热度 '.$m2 .'">'.self::esc($f2).'</a>';}if($w1===''){$w1='<p class="tc-empty">暂无热门标签 —— 请先到后台同步一次资料。</p>';}$v1='<section class="banner tagcloud" id="mlTagCloud">'."\n".'  <div class="in">'."\n".'    <h1 class="sr-only">'.$t1 .'</h1>'."\n".'    <div class="tc-stage" id="mlTcStage">'.$w1 .'</div>'."\n".'  </div>'."\n".'</section>';}if($w1!==''&&strpos($w1,'tc-t')!==false){$x1=<<<JSC
<script>
(function(){
  var box=document.getElementById('mlTagCloud');
  var stage=document.getElementById('mlTcStage');
  if(!box||!stage){return;}
  var allTags=[].slice.call(stage.querySelectorAll('.tc-t'));
  if(!allTags.length){return;}
  var tags=allTags;
  /* 窄屏只留前 24 个：50 个标签在手机上会把首屏占满 */
  function applyMax(){
    var mx=window.innerWidth<680?24:50;
    for(var i=0;i<allTags.length;i++){allTags[i].style.display=(i<mx)?'':'none';}
    tags=allTags.slice(0,mx);
  }
  applyMax();
  function mq1(q){return window.matchMedia?window.matchMedia(q):null;}
  var r1=mq1('(prefers-reduced-motion: reduce)');
  var r2=mq1('(hover:none)');
  var reduce=!!(r1&&r1.matches);
  var touch=!!(r2&&r2.matches);
  var base=[],arr=[],cur='scatter',timer=null,rt=null;

  function shuffle(a){
    for(var i=a.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=a[i];a[i]=a[j];a[j]=t;}
    return a;
  }
  /* 行装箱：不重叠地占满舞台；jitter=true 时加随机抖动（= 不规则散落） */
  var GX=12,GY=12,JX=6,JY=5;   /* 间距 / 抖动幅度：抖动必须小于间距的一半，否则相邻标签会互相压住 */
  function place(list,jitter){
    var W=stage.clientWidth||600,out=[],rowX=0,rowY=0,rowH=0;
    for(var i=0;i<list.length;i++){
      var el=list[i],r=el.getBoundingClientRect();
      var w=Math.ceil(r.width),h=Math.ceil(r.height);
      if(rowX>0&&rowX+w>W){rowY+=rowH+GY;rowX=0;rowH=0;}
      var x=rowX,y=rowY;
      if(jitter){
        x+=(Math.random()*2-1)*JX;
        y+=(Math.random()*2-1)*JY;
        var mx=W-w;
        if(mx<0){mx=0;}
        if(x<0){x=0;}
        if(x>mx){x=mx;}
        if(y<0){y=0;}
      }
      out.push([el,x,y]);
      rowX+=w+GX;
      if(h>rowH){rowH=h;}
    }
    return {pos:out,height:rowY+rowH};
  }
  function apply(ps){
    for(var i=0;i<ps.length;i++){ps[i][0].style.transform='translate3d('+ps[i][1]+'px,'+ps[i][2]+'px,0)';}
  }
  function setClass(on){
    if(!box.classList){return;}
    if(on){box.classList.add('on');}else{box.classList.remove('on');}
  }
  function rebuild(){
    var a=place(tags,false);
    var b=place(shuffle(tags.slice()),true);
    arr=a.pos;
    base=b.pos;
    stage.style.minHeight=(Math.max(a.height,b.height)+16)+'px';
  }
  /* 「乱飞」：每 2.8 秒重新抖动一次坐标，配合 2.8s 过渡 = 持续缓慢漂移 */
  function drift(){
    var out=[];
    for(var i=0;i<base.length;i++){
      var x=base[i][1]+(Math.random()*2-1)*4;
      var y=base[i][2]+(Math.random()*2-1)*3;
      if(x<0){x=0;}
      if(y<0){y=0;}
      out.push([base[i][0],x,y]);
    }
    apply(out);
  }
  function startTimer(){
    if(reduce||timer){return;}
    timer=window.setInterval(function(){if(cur==='scatter'){drift();}},2800);
  }
  function stopTimer(){
    if(timer){window.clearInterval(timer);timer=null;}
  }
  function setMode(m){
    if(m===cur){return;}
    cur=m;
    if(m==='arrange'){
      setClass(true);
      stopTimer();
      apply(arr);
    }else{
      setClass(false);
      apply(base);
      startTimer();
    }
  }

  rebuild();
  if(reduce){
    cur='arrange';
    setClass(true);
    apply(arr);
  }else{
    box.classList.add('tc-init');
    apply(base);
    window.setTimeout(function(){box.classList.remove('tc-init');},60);
    startTimer();
  }

  /* 鼠标移入 -> 按热度（DOM 顺序即热度降序）排成整齐标签墙；移出 -> 恢复漂浮 */
  box.addEventListener('mouseenter',function(){if(!reduce){setMode('arrange');}});
  box.addEventListener('mouseleave',function(){if(!reduce){setMode('scatter');}});
  /* 触屏设备没有 hover：点空白处切换排列 / 漂浮（点标签本身仍然跳转） */
  if(touch&&!reduce){
    stage.addEventListener('click',function(e){
      var el=e.target;
      while(el&&el!==stage){
        if((' '+el.className+' ').indexOf(' tc-t ')>-1){return;}
        el=el.parentNode;
      }
      setMode(cur==='arrange'?'scatter':'arrange');
    });
  }
  /* 尺寸变化：重新排版（先重排再按当前模式落位，避免错位） */
  window.addEventListener('resize',function(){
    if(rt){window.clearTimeout(rt);}
    rt=window.setTimeout(function(){
      applyMax();
      rebuild();
      apply(cur==='arrange'?arr:base);
    },200);
  });
})();
</script>
JSC;}$o2='';if($i!==''){$o2='<div class="tagbar">当前标签：<b>'.self::esc($i).'</b>'.'<a href="/?sort='.self::esc($b).'&type='.self::esc($c).'&q='.urlencode($f).'">清除标签</a></div>';}if($w!==''){$p2=(isset($j['kind'])&&$j['kind']==='actor')?'actor':'director';$q2=($p2==='actor')?'演员':'导演';$r2=($p2==='actor')?'⭐':'🎬';$o2='<div class="tagbar">'.$r2 .' '.$q2 .'：<b>'.self::esc((string)$j['name']).'</b>'.' · 已入库作品 <b>'.number_format($e).'</b> 部'.'<a href="/">返回全部</a></div>';}$s2=self::bodyClass();$t2=((string)Config::sub('redirect','rank_style','grid')==='list')?' grid-plain':'';$u2='';if(is_array($h)&&$h&&$m1&&Site::flag(Config::sub('redirect','latest',1))){$v2='';foreach($h as $w2){$x2=(int)((isset($w2['id'])?$w2['id']:0));$y2=self::esc((isset($w2['title'])?$w2['title']:'未命名'));$z2=self::typeLabel((isset($w2['type'])?$w2['type']:''));$a3=!empty($w2['year'])?(' · '.(int)$w2['year']):'';$v2 .='<a href="/uisc/'.$x2 .'.html"><span class="l-t">'.$y2 .'</span>'.'<span class="l-m">'.$z2 .$a3 .'</span></a>';}if($v2!==''){$u2='<div class="latest"><h2>最新更新</h2><div class="latest-list">'.$v2 .'</div></div>';}}$b3='';if($p1){$b3='<div class="adm-btn"><a href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a></div>';}$c3=<<<JSC
<script>
(function(){
  function mark(){
    var els=document.querySelectorAll('.c-title');
    for(var i=0;i<els.length;i++){
      var el=els[i], t=el.querySelector('.t-in');
      if(!t) continue;
      el.classList.toggle('ml', t.scrollWidth>el.clientWidth);
    }
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', mark); }
  else { mark(); }
  window.addEventListener('resize', mark);
  setTimeout(mark, 250); setTimeout(mark, 900);
})();
</script>
JSC;$d3=<<<JSC
<script>
(function(){
  var PER=21;
  function fix(){
    var grids=document.querySelectorAll('.wrap > .grid');
    for(var gi=0;gi<grids.length;gi++){
      var grid=grids[gi];
      var cards=grid.querySelectorAll(':scope > .card');
      var phs=grid.querySelectorAll(':scope > .ph');
      while(phs.length) phs[phs.length-1].remove();
      if(!cards.length) continue;
      var n=PER-cards.length;
      if(n<0) n=0;
      for(var k=0;k<n;k++){
        var d=document.createElement('div');
        d.className='ph';
        grid.appendChild(d);
      }
    }
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', fix); }
  else { fix(); }
  window.addEventListener('resize', fix);
  setTimeout(fix, 250); setTimeout(fix, 900);
})();
</script>
JSC;$e3=class_exists('\Core\AntiBot')?\Core\AntiBot::watermark():'';$f3=<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$n1}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$s2}">
{$e3}
{$r1}

{$v1}

<div class="wrap">
  {$o2}
  {$m}
  <div class="toolbar">
    <div class="sortbtns">{$y}</div>
    <div class="filters">{$b1}</div>
  </div>
  {$u2}
  <div class="grid{$t2}">{$c1}</div>
  {$f1}
</div>
{$s1}
{$x1}
{$c3}
{$d3}
</body>
</html>
HTML;return str_replace('{CSS_PLACEHOLDER}',self::css(),$f3);}public static function renderItemPage(array $a){$b=(int)((isset($a['id'])?$a['id']:0));$c=(isset($a['type'])?$a['type']:'');$d=(isset($a['source'])?$a['source']:'');$e=self::esc(self::cleanTitle((isset($a['title'])?$a['title']:'未命名')));$f=self::esc((isset($a['original_title'])?$a['original_title']:''));$g=!empty($a['year'])?(int)$a['year']:'';$h=($a['rating']!==null&&$a['rating']!=='')?number_format((float)$a['rating'],1):'—';$i=self::typeLabel($c);$j=(isset($a['poster'])?$a['poster']:'');$k=self::imgUrl($j);$l=self::imgUrl((isset($a['backdrop'])?$a['backdrop']:''));$m=(int)((isset($a['clicks'])?$a['clicks']:0));$n=self::formatCount($m);$o=self::popularityOf((isset($a['extra'])?$a['extra']:''));$p=($o!=='')?'<span class="s hot" title="热度（短剧=点赞，无点赞取播放量）">热度<b>🔥 '.$o.'</b></span>':'';$q=($d==='hongguoduanju'&&$c==='short');$r=$q?' data-static="1"':'';if($q){$s=self::shortStat((isset($a['extra'])?$a['extra']:''),'hot');if($s>0){$o=self::intCount($s);$p='<span class="s hot" title="短剧热度=点赞（无点赞取播放量）">热度<b>🔥 '.$o.'</b></span>';}$t=self::shortStat((isset($a['extra'])?$a['extra']:''),'clk');if($t>0){$n=self::intCount($t);}}$u=[];static $v=null;if($v===null){$v=array();try{foreach(\Core\CacheStore::allPersonTags()as $w){$v[$w]=true;}}catch(\Throwable $x){}}$y=array();$z=json_decode((isset($a['extra'])?(string)$a['extra']:''),true);if(is_array($z)){foreach(array('director','actors')as $a1){if(!isset($z[$a1])||!is_string($z[$a1])){continue;}foreach(preg_split('/[、,，]/u',$z[$a1])as $b1){$b1=trim($b1);if($b1!==''){$y[$b1]=true;}}}}foreach(json_decode((isset($a['genres'])?$a['genres']:'[]'),true)?:[]as $c1){if(!$c1){continue;}if(!\Core\CacheStore::isCjkTag($c1)){continue;}if(isset($v[$c1])||isset($y[$c1])){continue;}$u[]=$c1;}$d1='';if($u){foreach($u as $c1){$e1=self::esc($c1);$f1='/tag/'.urlencode($c1);$d1 .='<a class="t" href="'.self::esc($f1).'" title="查看「'.$e1 .'」标签下的全部内容">'.$e1 .'</a>';}}$g1=$l?'style="background-image:url('.self::esc($l).')"':'';$h1=$k?'<img src="'.self::esc($k).'"'.self::imgFallback($j).' alt="'.$e.'" loading="lazy">':'<div class="np">无封面</div>';$i1=self::esc((isset($a['overview'])?$a['overview']:''));$j1=$i1?'<div class="syn">'.$i1 .'</div>':'';$k1='';$l1=self::downloadBlock($a);$m1=self::extraBlock($a,$d,$c);$n1=Site::headMeta('item',array('id'=>$b,'title'=>isset($a['title'])?(string)$a['title']:'','type_label'=>$i,'year'=>($g!==''?(string)$g:''),'overview'=>isset($a['overview'])?(string)$a['overview']:'','poster'=>$k,));$o1=self::topbarHtml('<a class="admin" href="/">← 返回主页</a>');$p1=self::footerHtml();$q1=self::bodyClass();$r1=(bool)Config::sub('admin','show_link',1);$s1='';if($r1){$s1='<div class="adm-btn"><a href="'.self::esc(self::adminUrl()).'">'.(Site::flag(Config::sub('admin','show_icon',1))?'🛡 ':'').'管理后台</a></div>';}$t1=class_exists('\Core\AntiBot')?\Core\AntiBot::watermark():'';$u1=<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$n1}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$q1}">
{$t1}
{$o1}

<section class="hero">
  <div class="bg" {$g1}></div>
  <div class="mask"></div>
  <div class="in">
    <div class="poster">{$h1}</div>
    <div class="info">
      <div class="topline">
        <h1>{$e}</h1>
        <div class="tags"><span class="t">{$i}</span>{$d1}</div>
        <div class="stats">
          <span class="s gold">评分<b>{$h}</b></span>
          <span class="s">年份<b>{$g}</b></span>
          {$p}
          <span class="s clk">点击<b id="m-clicks"{$r}>{$n}</b></span>
        </div>
      </div>
      <div class="ot">{$f}</div>
      {$j1}
    </div>
  </div>
</section>

<div class="detail">
  {$l1}
  {$m1}
</div>

<div class="backbar"><a href="/">← 返回资料库主页</a></div>
{$p1}

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
</body>
</html>
HTML;return str_replace('{CSS_PLACEHOLDER}',self::css(),$u1);}private static function extraBlock(array $a,$b,$c){$d=json_decode((isset($a['payload'])?$a['payload']:'{}'),true)?:[];$e=[];$f=json_decode((isset($a['extra'])?$a['extra']:'{}'),true);$g=(is_array($f)&&(!empty($f['cast'])||!empty($f['actors'])));if($g){$e[]=self::castBlock($d,$a);}if($b==='hongguoduanju'&&$c==='short'){$e[]=self::animeBlock($d);}if($c==='person'){$e[]=self::personBlock($d,$a);}$e[]=self::extraFieldsBlock($a);return implode('',array_filter($e));}private static function downloadBlock(array $a){$b=self::parseDownloads((isset($a['download_url'])?$a['download_url']:''));if(!$b){return'';}$c='';foreach($b as $d){$e=(string)((isset($d['label'])?$d['label']:'下载'));$f=self::normalizePanLabel($e);$f=self::esc($f);$g=trim((string)((isset($d['url'])?$d['url']:'')));if($g===''){continue;}if(preg_match('#^https?://#i',$g)){$h=self::esc($g);$c.='<li class="dl-cell"><a class="dl-btn" href="'.$g.'" target="_blank" rel="noopener" title="点击下载：'.$h.'">'.$f.'</a></li>';}else{$c.='<li><span class="dl-tag">'.$f.'</span><span class="dl-raw">'.self::esc($g).'</span></li>';}}if($c===''){return'';}return'<div class="block"><h2>下载地址</h2><ul class="dl">'.$c.'</ul>'.'<p class="hint">点击网盘按钮即可跳转到对应网盘下载页；如链接失效，请联系站长更新。</p></div>';}private static function normalizePanLabel($a){$a=trim((string)$a);if($a===''){return'下载';}if(substr($a,-2)==='网盘'){return $a;}if(preg_match('#^[A-Za-z0-9_.-]+$#',$a)){return $a;}$b=preg_match_all('/./us',$a);if($b<=4&&$b>=2){return $a.'网盘';}return $a;}private static function cleanTitle($a){$b=(string)$a;$b=preg_replace('/[\s]*[({\[【]\s*tmdb-\d+\s*[)}\]】][\s]*/i',' ',$b);$b=preg_replace('/[\s]*tmdb-\d+[\s]*/i',' ',$b);$b=preg_replace('/\s{2,}/',' ',$b);return trim($b);}private static function parseDownloads($a){$a=trim($a);if($a===''){return[];}if(strpos($a,'[')===0){$b=json_decode($a,true);if(is_array($b)){$c=[];foreach($b as $d){if(is_array($d)){$c[]=['label'=>(isset($d['label'])?$d['label']:'下载'),'url'=>(isset($d['url'])?$d['url']:'')];}elseif(is_string($d)){$c[]=['label'=>'下载','url'=>$d];}}return $c;}}$e=array_filter(array_map('trim',preg_split('/\r?\n/',$a)),function($a){return $a!=='';});$c=[];foreach($e as $f){$c[]=['label'=>'下载','url'=>$f];}return $c;}private static function imgUrl($a){$a=(string)$a;if($a===''||strpos($a,'https://image.tmdb.org/t/p/')!==0){return $a;}if(!Config::sub('image_proxy','enable',1)){return $a;}return'/mimg.php?p='.rawurlencode(substr($a,strlen('https://image.tmdb.org')));}private static function imgFallback($a){$a=(string)$a;if(strpos($a,'https://image.tmdb.org/t/p/')!==0){return'';}return' onerror="this.onerror=null;this.src=\'https://image.tmdb.org/t/p/'.self::esc(substr($a,strlen('https://image.tmdb.org/t/p/'))).'\'"';}private static function extraFieldsBlock(array $a){$b=json_decode((isset($a['extra'])?$a['extra']:'{}'),true);if(!is_array($b)||!$b){if(in_array((string)(isset($a['type'])?$a['type']:''),array('movie','tv','short','anime','variety'),true)){return self::kvBlock(Categories::label((string)(isset($a['type'])?$a['type']:'')).'信息',[['key'=>'导演','val'=>'暂无']]);}return'';}$c=[];$d=in_array((string)(isset($a['type'])?$a['type']:''),array('movie','tv','short','anime','variety'),true);foreach(Categories::EXTRA_LABELS as $e=>$f){$g=($e==='director'&&$d);if(!isset($b[$e])||$b[$e]===''||$b[$e]===null){if($g){$c[]=['key'=>$f,'val'=>'暂无'];}continue;}if($e==='author'&&(isset($a['type'])?$a['type']:'')==='short'){continue;}if($e==='runtime'&&(isset($a['type'])?$a['type']:'')==='short'){continue;}if(is_numeric($b[$e])&&(int)$b[$e]===0){continue;}if($e==='director'||$e==='actors'||$e==='writers'){$h=($e==='director')?'director':'actor';$i='';foreach(preg_split('/[、,，]/u',(string)$b[$e])as $j){$j=Categories::cleanPersonName($j);if($j===''){continue;}$i.='<a class="info-pill" href="/person/'.$h.'/'.rawurlencode($j).'"'.' title="查看「'.self::esc($j).'」的作品">'.self::esc($j).'</a>';}if($i!==''){$c[]=['key'=>$f,'val'=>$i,'html'=>true,'w'=>($e==='actors'?2:1)];}elseif($g){$c[]=['key'=>$f,'val'=>'暂无'];}continue;}$k=$b[$e];if(is_array($k)){$l=array();foreach($k as $m){if(is_array($m)){$n='';if(isset($m['date'])&&$m['date']!==''){$n=(string)$m['date'];}if(isset($m['title'])&&$m['title']!==''){$n.=($n!==''?' · ':'').(string)$m['title'];}if(isset($m['country'])&&$m['country']!==''){$n.=($n!==''?' · ':'').(string)$m['country'];}if($n===''){foreach(array('name','iso','title','key')as $o){if(isset($m[$o])&&is_scalar($m[$o])&&(string)$m[$o]!==''){$n.=($n!==''?'、':'').(string)$m[$o];}}}if($n!==''){$l[]=$n;}}elseif(is_scalar($m)&&(string)$m!==''){$l[]=(string)$m;}}$k=implode('、',array_unique($l));if($k===''){continue;}}if(is_scalar($k)){$c[]=['key'=>$f,'val'=>(string)$k];}}if(empty($c)){return'';}$p=Categories::label((isset($a['type'])?$a['type']:'')).'信息';return self::kvBlock($p,$c);}private static function castBlock(array $a,array $b=array()){$c=json_decode((isset($b['extra'])?$b['extra']:'{}'),true);$d=array();if(is_array($c)&&isset($c['cast'])&&is_array($c['cast'])&&!empty($c['cast'])){$d=$c['cast'];}if(empty($d)&&isset($a['credits']['cast'])&&is_array($a['credits']['cast'])&&!empty($a['credits']['cast'])){$d=$a['credits']['cast'];}if(!empty($d)&&is_array($d)){$d=array_slice($d,0,14);$e='';foreach($d as $f){if(!is_array($f)){continue;}$g=Categories::cleanPersonName(isset($f['name'])?$f['name']:'');if($g===''){continue;}$h=self::esc($g);$i=self::esc((isset($f['character'])?$f['character']:''));$j=!empty($f['profile'])?$f['profile']:(!empty($f['profile_path'])?'https://image.tmdb.org/t/p/w300'.$f['profile_path']:'');if($j===''){$j=\Core\PeoplePhoto::url($g);}$k=self::imgUrl($j);$l=$k?'<img src="'.self::esc($k).'"'.self::imgFallback($j).' alt="'.$h.'" loading="lazy">':'<div class="p-np">演员</div>';$e.='<div class="p">'.$l.'<div class="nm">'.$h.'</div><div class="ch">'.$i.'</div></div>';}if($e!==''){return'<div class="block"><h2>主要演职人员</h2>'.self::castWrapHtml($e,'主要演职人员').'</div>';}}if(is_array($c)&&!empty($c['actors'])){$m=$c['actors'];$n=array();if(is_array($m)){foreach($m as $o){$o=Categories::cleanPersonName($o);if($o!=='')$n[]=$o;}}else{foreach(preg_split('/[、,，\s]+/u',(string)$m)as $o){$o=Categories::cleanPersonName($o);if($o!==''){$n[]=$o;}}}$n=array_slice($n,0,20);if(empty($n)){return'';}$e='';foreach($n as $g){$k=\Core\PeoplePhoto::url($g);$h=self::esc($g);$l=$k?'<img src="'.self::esc($k).'" alt="'.$h.'" loading="lazy">':'<div class="p-np">演员</div>';$e.='<div class="p">'.$l.'<div class="nm">'.$h.'</div></div>';}return'<div class="block"><h2>主要演员</h2>'.self::castWrapHtml($e,'主要演员').'</div>';}return'';}private static function castWrapHtml($a,$b){return'<div class="cast-wrap">'.'<button type="button" class="cast-nav" dir="-1" aria-label="向左滚动'.self::esc($b).'"><span class="a">‹</span></button>'.'<div class="cast" role="list">'.$a.'</div>'.'<button type="button" class="cast-nav" dir="1" aria-label="向右滚动'.self::esc($b).'"><span class="a">›</span></button>'.'</div>';}private static function animeBlock(array $a){$b=[];if(!empty($a['eps']))$b[]=['key'=>'话数','val'=>self::esc($a['eps'])];if(!empty($a['air_date']))$b[]=['key'=>'开播','val'=>self::esc($a['air_date'])];$c=[];foreach(((isset($a['studios'])?$a['studios']:[]))as $d){if(isset($d['name']))$c[]=$d['name'];}if($c)$b[]=['key'=>'制作公司','val'=>implode('、',array_unique($c))];$e=[];foreach(((isset($a['tags'])?$a['tags']:[]))as $f){if(is_array($f)){$e[]=(isset($f['name'])?$f['name']:'');}else{$e[]=$f;}}$e=array_filter(array_slice($e,0,8));if($e)$b[]=['key'=>'标签','val'=>implode('、',$e)];if(empty($b))return'';return self::kvBlock('番剧信息',$b);}private static function personBlock(array $a,array $b){$c=[];if(!empty($a['known_for_department']))$c[]=['key'=>'职业','val'=>self::esc($a['known_for_department'])];if(!empty($a['birthday']))$c[]=['key'=>'出生','val'=>self::esc($a['birthday'])];if(!empty($a['place_of_birth']))$c[]=['key'=>'出生地','val'=>self::esc($a['place_of_birth'])];if(empty($c))return'';return self::kvBlock('人物信息',$c);}private static function kvBlock($a,array $b){$c='';$d=count($b);foreach($b as $e=>$f){$g=(isset($f['html'])&&$f['html'])?$f['val']:self::esc($f['val']);$h=(isset($f['w'])&&(int)$f['w']>0)?(int)$f['w']:(($d===1)?3:1);$i='kv-it'.($h===2?' kv-w2':($h===3?' kv-w3':''));$c.='<div class="'.$i.'"><div class="k">'.self::esc($f['key']).'</div><div class="v">'.$g.'</div></div>';}return'<div class="block"><h2>'.self::esc($a).'</h2><div class="kv">'.$c.'</div></div>';}}