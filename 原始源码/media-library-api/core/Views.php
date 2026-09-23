<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/* 站点设置（站点名 / LOGO / SEO / 页脚）与分类标签：让本文件也能被
   StaticGen / cron 单独引入，不依赖 index.php 的加载顺序。 */
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/Site.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/User.php';          // 顶栏用户区 / 导航「资源请求」入口

/**
 * 视图层：主页/内页共用的 CSS + 卡片 + 内页 HTML 渲染。
 * 设计参考主流影视/资源站：干净卡片列表 + 条目内页 hero。
 * 内页为「自包含静态 HTML」：CSS 内联，图片走 CDN。
 *
 * 说明：API 调用次数属于内部指标，仅后台可见；前台只展示评分 / 年份 / 点击。
 *       下载区仅在填写了链接时才渲染，未填写则整块隐藏。
 */
class Views
{
    /** 返回后台路径（来自 config.admin.dir，默认 'admin'） */
    public static function adminUrl($suffix = '')
    {
        $d = trim((string) Config::sub('admin', 'dir', 'admin'));
        if ($d === '') { $d = 'admin'; }
        // 强制以 admin 结尾，防止误填
        if (strpos($d, 'admin') !== strlen($d) - 5) { $d = 'admin'; }
        $d = '/' . ltrim($d, '/');
        return $d . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '');
    }

    /** 类型中文标签（统一走 Categories） */
    public static function typeLabel($t)
    {
        return Categories::label($t);
    }

    public static function esc($s)
    {
        return htmlspecialchars((string) ((isset($s) ? $s : '')), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 顶栏：LOGO + 站点名（可在后台隐藏）+ 宣传语，右侧由调用方传入。
     * 站点信息全部来自后台「基础设置」页。
     */
    public static function topbarHtml($right = '')
    {
        $info = Site::info();
        $name = self::esc($info['name']);

        $logo = '';
        if ($info['logo'] !== '') {
            $logo = '<img class="logo" src="' . self::esc(Site::asset($info['logo'])) . '" alt="' . $name . '">';
        } else {
            $logo = '<span class="dot"></span>';
        }
        // 隐藏名称只在「有 LOGO」时才真正隐藏，否则顶栏会空白
        $showName = (!$info['name_hide']) || $info['logo'] === '';
        $nameHtml = $showName ? '<b class="sname">' . $name . '</b>' : '';

        $slogan = '';
        if ($info['slogan'] !== '') {
            $slogan = '<span class="slogan">' . self::esc($info['slogan']) . '</span>';
        }
        return '<header class="topbar"><div class="in">'
             . '<a class="brand" href="/">' . $logo . $nameHtml . $slogan . '</a>'
             . $right
             . self::userArea()
             . '</div>' . self::navHtml() . '</header>';
    }

    /**
     * 顶栏右侧的用户区。
     *   未登录（且站长开放了注册）→ 登录 / 注册
     *   已登录                  → 我的请求 · 用户名 / 退出
     *
     * 静态内页由 cron/CLI 生成，命令行下 User::me() 恒为 null，
     * 所以生成出来的页面就是访客看到的「登录 / 注册」—— 不会出现"后台预览有人名、线上没有"的错觉。
     * 样式在 Views::css() 的 .uarea（不是 UserViews 里，因为首页与内页也要用到）。
     */
    public static function userArea()
    {
        $me = User::me();
        if ($me) {
            return '<span class="uarea">'
                 . '<a class="admin" href="/request">我的请求</a>'
                 . '<span class="uname">' . self::esc((isset($me['username']) ? $me['username'] : '')) . '</span>'
                 . '<a class="admin" href="/user/logout">退出</a>'
                 . '</span>';
        }
        $c = User::cfg();
        if (!$c['open']) { return ''; }
        return '<span class="uarea">'
             . '<a class="admin" href="/user/login">登录</a>'
             . '<a class="admin" href="/user/register">注册</a>'
             . '</span>';
    }

    /**
     * 顶部导航。
     * 显示哪些入口由后台「站点设置 → 扫描+跳转 → 顶部导航显示」勾选决定；
     * 「顶部其他外链」支持一行一个「文字|网址」。
     * 未配置时默认「首页 / 最新更新」，保证顶栏不会空掉。
     */
    public static function navHtml()
    {
        $items = array(
            'home'    => array('首页',     '/'),
            'all'     => array('全网榜单', '/?sort=api_calls'),
            'top'     => array('Top250',   '/?sort=rating'),
            'game'    => array('游戏排行', '/?type=game&sort=rating'),
            'latest'  => array('最新更新', '/?sort=updated_at'),
            'request' => array(User::pageTitle(), '/request'),
            'contact' => array('联系我们', '/#contact'),
        );
        $on = Settings::strToList(Config::sub('redirect', 'nav_show', 'home,latest'));
        if (!$on) {
            $on = array('home', 'latest');
        }
        $h = '';
        foreach ($on as $k) {
            if (!isset($items[$k])) {
                continue;                      // 认不出来的值直接忽略，不报错
            }
            $h .= '<a href="' . self::esc($items[$k][1]) . '">' . self::esc($items[$k][0]) . '</a>';
        }

        /* 「资源请求」入口：后台「站点设置 → 资源请求」开着就加；
           已经在上面的勾选列表里出现过就不重复加。 */
        $reqCfg = User::cfg();
        if ($reqCfg['nav'] && !in_array('request', $on, true)) {
            $h .= '<a href="/request">' . self::esc(User::pageTitle()) . '</a>';
        }

        /* 自定义外链：一行一个「链接文字|网址」 */
        $ext = (string) Config::sub('redirect', 'nav_external', '');
        if (trim($ext) !== '') {
            foreach (preg_split('/[\r\n]+/', $ext) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '|') === false) {
                    continue;
                }
                $parts = explode('|', $line, 2);
                $txt = trim($parts[0]);
                $url = trim($parts[1]);
                if ($txt === '' || $url === '') {
                    continue;
                }
                $h .= '<a href="' . self::esc($url) . '" target="_blank" rel="noopener nofollow">'
                    . self::esc($txt) . '</a>';
            }
        }
        if ($h === '') {
            return '';
        }
        return '<nav class="mainnav"><div class="navin">' . $h . '</div></nav>';
    }

    /**
     * <body> 的模板类名：前台模板（经典 classic / 简约 simple）
     * + 简约模板的明暗模式（light / dark）。后台「扫描+跳转 → 前端模板」控制。
     */
    public static function bodyClass()
    {
        $tpl = (string) Config::sub('redirect', 'tpl', 'classic');
        if (!in_array($tpl, array('classic', 'simple'), true)) {
            $tpl = 'classic';
        }
        $cls = 'tpl-' . $tpl;

        /* 明暗模式：
           auto  = 跟随系统（经典 / 简约都支持，靠 prefers-color-scheme 生效）
           dark  = 强制暗色（仅简约模板有暗色皮肤，经典模板忽略）
           light = 强制亮色（默认） */
        $mode = (string) Config::sub('redirect', 'simple_mode', 'auto');
        if (!in_array($mode, array('light', 'dark', 'auto'), true)) {
            $mode = 'auto';
        }
        if ($mode === 'auto') {
            $cls .= ' mode-auto';
        } elseif ($tpl === 'simple') {
            $cls .= ($mode === 'dark') ? ' mode-dark' : ' mode-light';
        }
        return $cls;
    }

    /** 页脚：底部介绍 + 声明 + 版权（后台「基础设置」页控制） */
    public static function footerHtml()
    {
        $info = Site::info();
        $h = '<footer class="foot" id="contact"><div class="fin">';
        if ($info['footer_intro'] !== '') {
            $h .= '<p class="fintro">' . Site::text($info['footer_intro']) . '</p>';
        }
        if ($info['declare'] !== '') {
            $h .= '<p class="fdec">声明：' . self::esc($info['declare']) . '</p>';
        }
        if ($info['copyright'] !== '') {
            $h .= '<p class="fcopy">' . Site::text($info['copyright']) . '</p>';
        }
        $h .= '<p class="fbrand">' . self::esc($info['name'])
            . ' · 本站数据整理自公开条目信息</p>';
        $h .= '</div></footer>';
        return $h;
    }

    /** 共享样式（主页与静态内页都内联同一份，保证视觉一致） */
    public static function css()
    {
        return <<<CSS
:root{--maxw:1180px;--pad:18px;--bg:#f5f6fa;--card:#fff;--line:#eceef3;--pri:#5b5bd6;--pri2:#8b5cf6;--txt:#1b1f2a;--mut:#9aa1b1;--gold:#f5a524;--clk:#10b981;--hot:#f43f5e;--shadow:0 6px 22px rgba(27,31,42,.06);--shadow-h:0 16px 38px rgba(91,91,214,.18)}
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--txt);line-height:1.65;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{max-width:100%}
.topbar{position:sticky;top:0;z-index:30;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;box-shadow:0 2px 20px rgba(91,91,214,.30)}
.topbar .in{max-width:var(--maxw);margin:0 auto;padding:13px 18px;display:flex;align-items:center;gap:16px}
.brand{font-weight:800;font-size:18px;white-space:nowrap;display:flex;align-items:center;gap:9px;letter-spacing:.3px}
.brand .dot{width:22px;height:22px;border-radius:7px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 1px rgba(255,255,255,.35) inset}
.brand .dot:after{content:"";width:9px;height:9px;border-radius:50%;background:#fff}
.search{flex:1;display:flex;gap:8px;max-width:520px;margin-left:auto}
.search input{flex:1;padding:9px 14px;border:0;border-radius:10px;font-size:14px;outline:none;background:rgba(255,255,255,.94);color:var(--txt)}
.search button{padding:9px 18px;border:0;border-radius:10px;background:rgba(255,255,255,.20);color:#fff;cursor:pointer;font-size:14px;transition:background .15s}
.search button:hover{background:rgba(255,255,255,.34)}
.topbar a.admin{color:#fff;opacity:.88;font-size:13px;white-space:nowrap;padding:7px 12px;border-radius:9px;background:rgba(255,255,255,.12);transition:background .15s}
.topbar a.admin:hover{background:rgba(255,255,255,.24)}
/* 首页 banner */
.banner{background:linear-gradient(120deg,#5b5bd6,#8b5cf6 60%,#a855f7);color:#fff;position:relative;overflow:hidden}
.banner:after{content:"";position:absolute;right:-60px;top:-60px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.10)}
.banner:before{content:"";position:absolute;left:-40px;bottom:-90px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.08)}
.banner .in{max-width:var(--maxw);margin:0 auto;padding:34px 18px 30px;position:relative;z-index:1}
.banner h1{margin:0 0 8px;font-size:28px;letter-spacing:.5px}
.banner p{margin:0;opacity:.92;font-size:14.5px}
.banner .badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.banner .badges span{background:rgba(255,255,255,.16);padding:6px 13px;border-radius:20px;font-size:13px}
.wrap{max-width:var(--maxw);margin:22px auto;padding:0 18px}
.toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.sortbtns{display:flex;gap:6px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:5px;box-shadow:var(--shadow)}
.sortbtns a{padding:8px 15px;border-radius:9px;font-size:14px;color:var(--mut);transition:.15s}
.sortbtns a:hover{color:var(--pri)}
.sortbtns a.on{background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;box-shadow:0 6px 16px rgba(91,91,214,.32)}
.filters{display:flex;gap:8px;flex-wrap:wrap}
.filters a{padding:8px 15px;border:1px solid var(--line);background:var(--card);border-radius:20px;font-size:13px;color:var(--mut);transition:.15s}
.filters a:hover{border-color:var(--pri);color:var(--pri)}
.filters a.on{border-color:transparent;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(164px,1fr));gap:18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:transform .18s,box-shadow .18s;box-shadow:var(--shadow)}
.card:hover{transform:translateY(-6px);box-shadow:var(--shadow-h)}
.c-thumb{position:relative;aspect-ratio:2/3;background:#eef0f5;overflow:hidden}
.c-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s}
.card:hover .c-thumb img{transform:scale(1.07)}
.c-noposter{display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:13px;height:100%}
.c-type{position:absolute;left:8px;top:8px;background:rgba(27,31,42,.72);color:#fff;font-size:12px;padding:3px 10px;border-radius:20px}
.c-dl{position:absolute;right:8px;top:8px;background:linear-gradient(120deg,#10b981,#34d399);color:#fff;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:20px;box-shadow:0 4px 12px rgba(16,185,129,.4)}
.c-body{padding:11px 13px 14px}
.c-title{font-weight:600;font-size:15px;line-height:1.35;max-height:2.7em;overflow:hidden}
.c-sub{color:var(--mut);font-size:12.5px;margin:3px 0 9px}
.c-meta{display:flex;gap:6px;flex-wrap:wrap}
.chip{font-size:12px;padding:3px 9px;border-radius:8px;background:var(--bg);color:var(--mut);white-space:nowrap}
.chip-r{color:var(--gold);background:#fff7e6}
.chip-clk{color:var(--clk);background:#e9f9f1}
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
.hero h1{margin:0 0 8px;font-size:28px;line-height:1.25;letter-spacing:.3px}
.hero .ot{opacity:.82;font-size:14px;margin-bottom:12px}
.hero .tags{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.hero .tags .t{background:rgba(255,255,255,.16);padding:4px 12px;border-radius:20px;font-size:13px}
.hero .stats{display:flex;gap:10px;flex-wrap:wrap}
.hero .stats .s{background:rgba(255,255,255,.12);padding:8px 15px;border-radius:11px;font-size:13px}
.hero .stats .s b{font-size:16px;margin-left:2px}
.hero .stats .s.gold b{color:#ffcf6b}
.hero .stats .s.clk b{color:#7ef0c0}
.detail{max-width:var(--maxw);margin:24px auto;padding:0 18px;display:grid;grid-template-columns:1fr;gap:22px}
.block{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px 22px;box-shadow:var(--shadow)}
.block h2{margin:0 0 14px;font-size:17px;display:flex;align-items:center;gap:9px}
.block h2:before{content:"";width:4px;height:17px;background:linear-gradient(180deg,var(--pri),var(--pri2));border-radius:3px;display:inline-block}
.ov{color:#3a3f4d;font-size:15px;white-space:pre-wrap}
.dl{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.dl li{background:var(--bg);border:1px solid var(--line);border-radius:11px;padding:12px 14px;word-break:break-all;display:flex;gap:11px;align-items:center;transition:.15s}
.dl li:hover{border-color:var(--pri);background:#f7f7ff}
.dl-tag{flex:0 0 auto;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-size:12.5px;font-weight:600;padding:4px 12px;border-radius:20px;white-space:nowrap}
.dl li a{color:var(--pri);font-size:14px;font-weight:600;word-break:break-all}
.dl-raw{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;color:#3a3f4d;white-space:pre-wrap;word-break:break-all}
.block .hint{color:var(--mut);font-size:12px;margin-top:10px}
.kv{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px 18px}
.kv .k{color:var(--mut);font-size:13px}
.kv .v{font-size:14.5px}
.cast{display:flex;gap:14px;overflow-x:auto;padding-bottom:6px}
.cast .p{flex:0 0 110px;text-align:center}
.cast .p img{width:110px;height:150px;object-fit:cover;border-radius:11px;background:#eee}
.cast .p .nm{font-size:13px;margin-top:6px;font-weight:600}
.cast .p .ch{font-size:12px;color:var(--mut)}
.cast .p .p-np{width:110px;height:150px;background:#eee;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px}
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
.grid-plain .card .c-title{max-height:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* 最新更新区块 */
.latest{margin:0 0 22px}
.latest h2{margin:0 0 12px;font-size:16px;display:flex;align-items:center;gap:8px}
.latest h2:before{content:"";width:4px;height:16px;border-radius:2px;background:linear-gradient(120deg,var(--pri),var(--pri2))}
.latest-list{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.latest-list a{display:flex;gap:12px;padding:11px 16px;border-bottom:1px solid var(--line);font-size:14px;transition:.15s}
.latest-list a:last-child{border-bottom:0}
.latest-list a:hover{background:#fafaff;color:var(--pri)}
.latest-list .l-t{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.latest-list .l-m{color:var(--mut);font-size:12.5px;white-space:nowrap}
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
.tpl-simple.mode-dark .ov{color:#c3cad8}
.tpl-simple.mode-dark .dl-raw{color:#c3cad8}
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
  .search{order:3;flex:1 0 100%;max-width:none;margin-left:0;gap:7px}
  .search input{font-size:16px;padding:10px 13px}   /* 16px 可阻止 iOS 聚焦时整页放大 */
  .search button{padding:10px 15px;font-size:13.5px}
  .topbar a.admin{margin-left:auto;padding:8px 11px;font-size:12px}
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
  .toolbar{flex-wrap:nowrap;overflow-x:auto;gap:10px;margin-bottom:14px;padding-bottom:3px}
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
  .cast{gap:11px}
  .cast .p{flex:0 0 96px}
  .cast .p img,.cast .p .p-np{width:96px;height:132px}
  /* 下载地址：标签与地址改为竖排，长网址不再被挤成两三个字一行 */
  .dl li{flex-direction:column;align-items:flex-start;gap:7px;padding:12px 13px}
  .dl li a{font-size:13px}
  .dl-raw{font-size:12.5px}
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
  .hero .stats .s b,.hero .stats .s.gold b,.hero .stats .s.clk b{color:#111}
  .block h2:before{background:#666}
  .dl li{background:#fafafa}
  a{text-decoration:underline}
}

/* --- 跟随系统深色：后台把「明暗模式」设为「跟随系统」时生效（经典/简约通用） --- */
@media (prefers-color-scheme:dark){
  body.mode-auto{--bg:#0f1420;--card:#182034;--line:#28324a;--txt:#e6eaf3;--mut:#94a0b8;
    --shadow:0 6px 22px rgba(0,0,0,.42);--shadow-h:0 16px 38px rgba(0,0,0,.58);color-scheme:dark}
  body.mode-auto .ov,body.mode-auto .dl-raw{color:#c3cad8}
  body.mode-auto .chip{background:#222c42}
  body.mode-auto .chip-r{background:#3a2f12;color:#f5b942}
  body.mode-auto .chip-clk{background:#103026;color:#3ddc97}
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
.tc-note{margin:10px 0 0;font-size:12.5px;opacity:.84;text-align:center}
.tc-note b{font-weight:600}
.tc-empty{margin:0;padding:44px 0;text-align:center;font-size:14px;opacity:.92}
/* --- 列表页「当前标签」提示条 --- */
.tagbar{display:flex;align-items:center;gap:9px;margin:0 0 14px;font-size:13.5px;color:var(--mut);flex-wrap:wrap}
.tagbar b{color:var(--txt);font-weight:600}
.tagbar a{padding:4px 12px;border:1px solid var(--line);border-radius:20px;background:var(--card);color:var(--mut);font-size:12.5px;transition:.15s}
.tagbar a:hover{border-color:var(--pri);color:var(--pri)}
/* --- 管理入口主按钮（★ v1.8.8：原先这段 CSS 被误粘在最新更新列表里，会当纯文本显示） --- */
.adm-btn{margin:18px 0 8px;text-align:center}
.adm-btn a{display:inline-block;background:linear-gradient(120deg,#5b5bd6,#8b5cf6);color:#fff;font-size:15px;font-weight:600;padding:13px 28px;border-radius:12px;text-decoration:none;box-shadow:0 8px 22px rgba(91,91,214,.28);transition:box-shadow .15s,transform .15s}
.adm-btn a:hover{box-shadow:0 12px 30px rgba(91,91,214,.38);transform:translateY(-1px)}
@media(max-width:680px){
  .tagcloud .in{padding:15px 13px 11px}
  .tc-t{padding:4px 10px;font-size:12.5px;border-radius:16px}
  .tc-t.tc-h1{font-size:15px}
  .tc-t.tc-h2{font-size:13.5px}
  .tc-note{font-size:11.5px}
  .tagbar{font-size:12.5px}
  .adm-btn a{width:100%;text-align:center}
}

/* --- 刘海屏 / 底部横条安全区（放在最后，确保左右内边距不被前面的简写覆盖） --- */
@supports(padding:max(0px)){
  .topbar .in,.banner .in,.wrap,.hero .in,.detail,.backbar,.mainnav .navin,.foot .fin{
    padding-left:max(var(--pad),env(safe-area-inset-left));
    padding-right:max(var(--pad),env(safe-area-inset-right));
  }
  .foot{padding-bottom:max(44px,calc(env(safe-area-inset-bottom) + 22px))}
}

CSS;
    }

    /** 主页卡片 */
    public static function cardHtml(array $row)
    {
        $id     = (int) ((isset($row['id']) ? $row['id'] : 0));
        $title  = self::esc((isset($row['title']) ? $row['title'] : '未命名'));
        $year   = !empty($row['year']) ? ('· ' . $row['year']) : '';
        $rating = ($row['rating'] !== null && $row['rating'] !== '') ? number_format((float) $row['rating'], 1) : '—';
        $type   = self::typeLabel((isset($row['type']) ? $row['type'] : ''));
        $poster = (isset($row['poster']) ? $row['poster'] : '');
        $clk    = (int) ((isset($row['clicks']) ? $row['clicks'] : 0));
        $hasDl  = self::hasDownloads((isset($row['download_url']) ? $row['download_url'] : ''));
        $thumb  = $poster
            ? '<img src="' . self::esc($poster) . '" alt="' . self::esc((isset($row['title']) ? $row['title'] : '')) . '" loading="lazy">'
            : '<div class="c-noposter">无封面</div>';
        $dlBadge = $hasDl ? '<span class="c-dl">可下载</span>' : '';
        return <<<HTML
<a class="card" href="/uisc/{$id}.html">
  <div class="c-thumb">{$thumb}<span class="c-type">{$type}</span>{$dlBadge}</div>
  <div class="c-body">
    <div class="c-title">{$title}</div>
    <div class="c-sub">{$type} {$year}</div>
    <div class="c-meta">
      <span class="chip chip-r">★ {$rating}</span>
      <span class="chip chip-clk">点击 {$clk}</span>
    </div>
  </div>
</a>
HTML;
    }

    /** 是否填写了有效下载链接（空则不展示下载相关） */
    public static function hasDownloads($raw)
    {
        foreach (self::parseDownloads($raw) as $l) {
            if (trim((string) ((isset($l['url']) ? $l['url'] : ''))) !== '') { return true; }
        }
        return false;
    }

    /** 主页完整 HTML */
    public static function homeHtml(array $rows,$sort,$type,$page,$total,$q,$perPage,$latest = array(),$tag = '')
    {
        $encQ = self::esc($q);
        $sorts = [
            'api_calls'  => '热度',
            'clicks'     => '点击',
            'updated_at' => '最新',
            'rating'     => '评分',
        ];
        $types = ['' => '全部'];
        foreach (Categories::TYPES as $t) { $types[$t] = Categories::label($t); }
        /* ★ v1.8.8：所有筛选/排序链接都带上当前标签，切换时不会丢掉标签条件 */
        $tagQ = ($tag !== '' ? ('&tag=' . urlencode($tag)) : '');
        $sortHtml = '';
        foreach ($sorts as $k => $label) {
            $on = $k === $sort ? ' class="on"' : '';
            $sortHtml .= '<a' . $on . ' href="/?sort=' . $k . '&type=' . self::esc($type) . '&q=' . urlencode($q) . $tagQ . '">' . $label . '</a>';
        }
        $filterHtml = '';
        foreach ($types as $k => $label) {
            $on = $k === $type ? ' class="on"' : '';
            $filterHtml .= '<a' . $on . ' href="/?sort=' . self::esc($sort) . '&type=' . $k . '&q=' . urlencode($q) . $tagQ . '">' . $label . '</a>';
        }
        $grid = '';
        foreach ($rows as $r) { $grid .= self::cardHtml($r); }
        if ($grid === '') { $grid = '<p class="empty">暂无资料，去后台添加或触发一次同步吧。</p>'; }

        $totalPages = max(1, (int) ceil($total / $perPage));
        $pager = '';
        if ($totalPages > 1) {
            $prev = $page > 1 ? '<a href="/?sort=' . self::esc($sort) . '&type=' . self::esc($type) . '&q=' . urlencode($q) . $tagQ . '&page=' . ($page - 1) . '">上一页</a>' : '<span>上一页</span>';
            $next = $page < $totalPages ? '<a href="/?sort=' . self::esc($sort) . '&type=' . self::esc($type) . '&q=' . urlencode($q) . $tagQ . '&page=' . ($page + 1) . '">下一页</a>' : '<span>下一页</span>';
            $pager = '<div class="pager">' . $prev . '<span>第 ' . $page . ' / ' . $totalPages . ' 页</span>' . $next . '</div>';
        }

        $sortName = (isset($sorts[$sort]) ? $sorts[$sort] : '点击');
        $typeName = (isset($types[$type]) ? $types[$type] : '全部');
        $totalLbl = number_format($total);
        $info     = Site::info();

        // 无筛选、无搜索、第一页 = 首页，用首页那套 SEO；否则按列表页模板生成
        $isHome   = ($type === '' && $q === '' && $tag === '' && (int) $page === 1);
        $headMeta = Site::headMeta($isHome ? 'home' : 'list', array(
            'type_label' => ($type !== '' ? $typeName : ''),
            'sort_label' => $sortName,
            'page'       => $page,
        ));
        $searchForm = '<form class="search" action="/" method="get">'
            . '<input type="hidden" name="sort" value="' . self::esc($sort) . '">'
            . '<input type="hidden" name="type" value="' . self::esc($type) . '">'
            . '<input type="text" name="q" value="' . $encQ . '" placeholder="搜索标题…">'
            . '<button type="submit">搜索</button></form>';
        /* ★ v1.6.5：管理后台路径来自 config.admin.dir，不再硬编码 /admin */
        $__showAdminLink = (bool) Config::sub('admin', 'show_link', 1);
        $__adminHref = '';
        if ($__showAdminLink) {
            $__adminHref = '<a class="admin" href="' . self::esc(self::adminUrl()) . '">'
                . (Site::flag(Config::sub('admin', 'show_icon', 1)) ? '🛡 ' : '')
                . '管理后台</a>';
        }
        $topbar = self::topbarHtml($searchForm . $__adminHref);
        $footer = self::footerHtml();
        $bannerTitle = $info['slogan'] !== '' ? self::esc($info['slogan']) : '影视 · 剧集 · 动漫 · 综艺 · 短剧 · 游戏';
        $bannerSub   = '一站式资源资料库，支持在线检索与一键下载。';

        /* ★ v1.8.8：首页首屏改为「50 个热门标签云」。
           只有在真首页（无筛选 / 无搜索 / 无标签 / 第一页）才查标签，其它情况沿用原 banner，
           既省掉每次翻页的一次全表聚合，也保证列表页信息完整。 */
        $hotTags    = array();
        $bannerHtml = '';
        $tagLinks   = '';
        $tagCloudJs = '';
        if ($isHome && class_exists('Core\\CacheStore') && class_exists('Core\\DB')) {
            try { $hotTags = \Core\CacheStore::hotTags(50); } catch (\Exception $e) { $hotTags = array(); }
        }
        if ($isHome) {
            $ti = 0;
            foreach ($hotTags as $tg) {
                $ti++;
                $cls = 'tc-t';
                if ($ti <= 8)      { $cls .= ' tc-h1'; }
                elseif ($ti <= 22) { $cls .= ' tc-h2'; }
                $tn = (string) $tg['tag'];
                $tagLinks .= '<a class="' . $cls . '" href="/?tag=' . urlencode($tn) . '" data-hot="' . (int) $tg['clicks'] . '"'
                           . ' title="' . self::esc($tn) . '：' . (int) $tg['items'] . ' 部 · 累计点击 ' . (int) $tg['clicks'] . ' 次">'
                           . self::esc($tn) . '</a>';
            }
            if ($tagLinks === '') {
                $tagLinks = '<p class="tc-empty">暂无热门标签 —— 请先到后台同步一次资料。</p>';
                $tagNote  = '热门标签 · 鼠标移入自动排列，点击标签查看该标签下的资料';
            } else {
                $tagNote  = '共 <b>' . count($hotTags) . '</b> 个热门标签 · 按累计点击量排序 · 鼠标移入自动归位，点击标签查看该标签下的资料';
            }
            $bannerHtml = '<section class="banner tagcloud" id="mlTagCloud">' . "\n"
                . '  <div class="in">' . "\n"
                . '    <h1 class="sr-only">' . $bannerTitle . '</h1>' . "\n"
                . '    <div class="tc-stage" id="mlTcStage">' . $tagLinks . '</div>' . "\n"
                . '    <p class="tc-note">' . $tagNote . '</p>' . "\n"
                . '  </div>' . "\n"
                . '</section>';

            if ($tagLinks !== '' && strpos($tagLinks, 'tc-t') !== false) {
                $tagCloudJs = <<<JSC
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
JSC;
            }
        } else {
            $bannerHtml = '<section class="banner"><div class="in">' . "\n"
                . '  <h1>' . $bannerTitle . '</h1>' . "\n"
                . '  <p>' . $bannerSub . '</p>' . "\n"
                . '  <div class="badges">' . "\n"
                . '    <span>共收录 ' . $totalLbl . ' 部</span>' . "\n"
                . '    <span>当前分类：' . $typeName . '</span>' . "\n"
                . '    <span>排序：按' . $sortName . '</span>' . "\n"
                . '  </div>' . "\n"
                . '</div></section>';
        }

        /* 列表页（点了标签进来）显示「当前标签」提示条 */
        $tagBar = '';
        if ($tag !== '') {
            $tagBar = '<div class="tagbar">当前标签：<b>' . self::esc($tag) . '</b>'
                . '<a href="/?sort=' . self::esc($sort) . '&type=' . self::esc($type) . '&q=' . urlencode($q) . '">清除标签</a></div>';
        }

        /* 前台模板 / 榜单样式 / 最新更新区块：后台「站点设置 → 扫描+跳转」控制 */
        $bodyClass = self::bodyClass();
        $gridCls   = ((string) Config::sub('redirect', 'rank_style', 'grid') === 'list') ? ' grid-plain' : '';
        $latestHtml = '';
        if (is_array($latest) && $latest && $isHome
            && Site::flag(Config::sub('redirect', 'latest', 1))) {
            $li = '';
            foreach ($latest as $lr) {
                $lid  = (int) ((isset($lr['id']) ? $lr['id'] : 0));
                $lt   = self::esc((isset($lr['title']) ? $lr['title'] : '未命名'));
                $ltp  = self::typeLabel((isset($lr['type']) ? $lr['type'] : ''));
                $ly   = !empty($lr['year']) ? (' · ' . (int) $lr['year']) : '';
                $li  .= '<a href="/uisc/' . $lid . '.html"><span class="l-t">' . $lt . '</span>'
                      . '<span class="l-m">' . $ltp . $ly . '</span></a>';
            }
            if ($li !== '') {
                $latestHtml = '<div class="latest"><h2>最新更新</h2><div class="latest-list">' . $li . '</div></div>';
            }
        }


        /* 管理后台入口主按钮（独立于顶栏链接） */
        $__admBtn = '';
        if ($__showAdminLink) {
            $__admBtn = '<div class="adm-btn"><a href="'
                . self::esc(self::adminUrl()) . '">'
                . (Site::flag(Config::sub('admin', 'show_icon', 1)) ? '🛡 ' : '')
                . '管理后台</a></div>';
        }

                $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$headMeta}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$bodyClass}">
{$topbar}

{$bannerHtml}

<div class="wrap">
  {$tagBar}
  <div class="toolbar">
    <div class="sortbtns">{$sortHtml}</div>
    <div class="filters">{$filterHtml}</div>
  </div>
  {$latestHtml}
  <div class="grid{$gridCls}">{$grid}</div>
  {$pager}
</div>
{$footer}
{$tagCloudJs}
</body>
</html>
HTML;
        return str_replace('{CSS_PLACEHOLDER}', self::css(), $html);
    }

    /** 内页完整 HTML（静态生成用，按 type/source 分支渲染） */
    public static function renderItemPage(array $row)
    {
        $id     = (int) ((isset($row['id']) ? $row['id'] : 0));
        $type   = (isset($row['type']) ? $row['type'] : '');
        $source = (isset($row['source']) ? $row['source'] : '');
        $title  = self::esc((isset($row['title']) ? $row['title'] : '未命名'));
        $ot     = self::esc((isset($row['original_title']) ? $row['original_title'] : ''));
        $year   = !empty($row['year']) ? (int) $row['year'] : '';
        $rating = ($row['rating'] !== null && $row['rating'] !== '') ? number_format((float) $row['rating'], 1) : '—';
        $typeLb = self::typeLabel($type);
        $poster = (isset($row['poster']) ? $row['poster'] : '');
        $back   = (isset($row['backdrop']) ? $row['backdrop'] : '');
        $clk    = (int) ((isset($row['clicks']) ? $row['clicks'] : 0));
        $genres = [];
        foreach (json_decode((isset($row['genres']) ? $row['genres'] : '[]'), true) ?: [] as $g) { if ($g) $genres[] = self::esc($g); }
        $genresHtml = '';
        if ($genres) {
            foreach ($genres as $g) { $genresHtml .= '<span class="t">' . $g . '</span>'; }
        }

        $bgStyle = $back ? 'style="background-image:url(' . self::esc($back) . ')"' : '';
        $posterHtml = $poster
            ? '<img src="' . self::esc($poster) . '" alt="' . $title . '" loading="lazy">'
            : '<div class="np">无封面</div>';

        $overview = self::esc((isset($row['overview']) ? $row['overview'] : ''));
        $overviewHtml = $overview ? '<div class="block"><h2>简介</h2><div class="ov">' . $overview . '</div></div>' : '';

        // 下载地址块（后台填写；为空不展示）
        $dlHtml = self::downloadBlock($row);

        // 类型相关区块（含 extra 扩展字段）
        $extra = self::extraBlock($row, $source, $type);

        // SEO：标题/关键词/描述按「内页模板 + 条目真实内容」生成（后台「SEO 设置」可改模板）
        $headMeta = Site::headMeta('item', array(
            'id'         => $id,
            'title'      => isset($row['title']) ? (string) $row['title'] : '',
            'type_label' => $typeLb,
            'year'       => ($year !== '' ? (string) $year : ''),
            'overview'   => isset($row['overview']) ? (string) $row['overview'] : '',
            'poster'     => $poster,
        ));
        $topbar = self::topbarHtml('<a class="admin" href="/">← 返回主页</a>');
        $footer = self::footerHtml();
        $bodyClass = self::bodyClass();


        /* 管理后台入口主按钮（独立于顶栏链接） */
        $__admBtn = '';
        if ($__showAdminLink) {
            $__admBtn = '<div class="adm-btn"><a href="'
                . self::esc(self::adminUrl()) . '">'
                . (Site::flag(Config::sub('admin', 'show_icon', 1)) ? '🛡 ' : '')
                . '管理后台</a></div>';
        }

                $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$headMeta}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$bodyClass}">
{$topbar}

<section class="hero">
  <div class="bg" {$bgStyle}></div>
  <div class="mask"></div>
  <div class="in">
    <div class="poster">{$posterHtml}</div>
    <div class="info">
      <h1>{$title}</h1>
      <div class="ot">{$ot}</div>
      <div class="tags"><span class="t">{$typeLb}</span>{$genresHtml}</div>
      <div class="stats">
        <span class="s gold">评分<b>{$rating}</b></span>
        <span class="s">年份<b>{$year}</b></span>
        <span class="s clk">点击<b id="m-clicks">{$clk}</b></span>
      </div>
    </div>
  </div>
</section>

<div class="detail">
  {$overviewHtml}
  {$dlHtml}
  {$extra}
</div>

<div class="backbar"><a href="/">← 返回资料库主页</a></div>
{$footer}

<img src="/track?id={$id}&t=click" width="1" height="1" alt="" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">
<script>
(function(){
  var elC=document.getElementById('m-clicks'); if(!elC) return;
  fetch('/api/v1/metric/{$id}').then(function(r){return r.json();}).then(function(d){
    if(d && d.success && d.data){ elC.textContent=d.data.clicks; }
  }).catch(function(){});
})();
</script>
</body>
</html>
HTML;
        return str_replace('{CSS_PLACEHOLDER}', self::css(), $html);
    }

    /** 按来源/类型生成差异化区块（电影剧集=演职员；游戏=平台/厂商；动漫=话数/制作；extra=分类扩展字段） */
    private static function extraBlock(array $row,$source,$type)
    {
        $p = json_decode((isset($row['payload']) ? $row['payload'] : '{}'), true) ?: [];
        $blocks = [];

        if (($source === 'tmdb') && in_array($type, ['movie', 'tv'], true)) {
            $blocks[] = self::castBlock($p);
        }
        if ($source === 'rawg' && $type === 'game') {
            $blocks[] = self::gameBlock($p);
        }
        if ($source === 'hongguoduanju' && $type === 'short') {
            $blocks[] = self::animeBlock($p);
        }
        if ($type === 'person') {
            $blocks[] = self::personBlock($p, $row);
        }
        // 分类扩展字段（手动新增/后台补充的 集数/导演/平台 等）
        $blocks[] = self::extraFieldsBlock($row);

        return implode('', array_filter($blocks));
    }

    /** 下载地址块：支持多条网盘（JSON 数组 [{label,url}]，兼容旧版纯文本多行），无有效链接则整块隐藏 */
    private static function downloadBlock(array $row)
    {
        $links = self::parseDownloads((isset($row['download_url']) ? $row['download_url'] : ''));
        if (!$links) { return ''; }
        $items = '';
        foreach ($links as $l) {
            $label = self::esc((isset($l['label']) ? $l['label'] : '下载'));
            $url   = trim((string) ((isset($l['url']) ? $l['url'] : '')));
            if ($url === '') { continue; }
            if (preg_match('#^https?://#i', $url)) {
                $items .= '<li><span class="dl-tag">' . $label . '</span><a href="' . self::esc($url) . '" target="_blank" rel="noopener">' . self::esc($url) . '</a></li>';
            } else {
                $items .= '<li><span class="dl-tag">' . $label . '</span><span class="dl-raw">' . self::esc($url) . '</span></li>';
            }
        }
        if ($items === '') { return ''; }
        return '<div class="block"><h2>下载地址</h2><ul class="dl">' . $items . '</ul>'
             . '<p class="hint">如链接失效，请联系站长更新。</p></div>';
    }

    /** 解析下载链接：新格式 JSON 数组 / 旧格式纯文本多行 */
    private static function parseDownloads($raw)
    {
        $raw = trim($raw);
        if ($raw === '') { return []; }
        if (strpos($raw, '[') === 0) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $out = [];
                foreach ($dec as $e) {
                    if (is_array($e)) {
                        $out[] = ['label' => (isset($e['label']) ? $e['label'] : '下载'), 'url' => (isset($e['url']) ? $e['url'] : '')];
                    } elseif (is_string($e)) {
                        $out[] = ['label' => '下载', 'url' => $e];
                    }
                }
                return $out;
            }
        }
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), function ($x) { return $x !== ''; });
        $out = [];
        foreach ($lines as $x) {
            $out[] = ['label' => '下载', 'url' => $x];
        }
        return $out;
    }

    /** 渲染 media_items.extra(JSON) 中的分类扩展字段 */
    private static function extraFieldsBlock(array $row)
    {
        $ex = json_decode((isset($row['extra']) ? $row['extra'] : '{}'), true);
        if (!is_array($ex) || !$ex) { return ''; }
        $rows = [];
        foreach (Categories::EXTRA_LABELS as $k => $label) {
            if (isset($ex[$k]) && $ex[$k] !== '' && $ex[$k] !== null) {
                $rows[] = ['key' => $label, 'val' => (string) $ex[$k]];
            }
        }
        if (empty($rows)) { return ''; }
        $title = Categories::label((isset($row['type']) ? $row['type'] : '')) . '信息';
        return self::kvBlock($title, $rows);
    }

    private static function castBlock(array $p)
    {
        $cast = (isset($p['credits']['cast']) ? $p['credits']['cast'] : []);
        if (!is_array($cast) || !$cast) { return ''; }
        $cast = array_slice($cast, 0, 14);
        $html = '';
        foreach ($cast as $c) {
            $nm  = self::esc((isset($c['name']) ? $c['name'] : ''));
            $ch  = self::esc((isset($c['character']) ? $c['character'] : ''));
            $pp  = !empty($c['profile_path']) ? 'https://image.tmdb.org/t/p/w185' . $c['profile_path'] : '';
            $img = $pp ? '<img src="' . self::esc($pp) . '" alt="' . $nm . '" loading="lazy">' : '<div class="p-np">无照</div>';
            $html .= '<div class="p">' . $img . '<div class="nm">' . $nm . '</div><div class="ch">' . $ch . '</div></div>';
        }
        return '<div class="block"><h2>主要演职人员</h2><div class="cast">' . $html . '</div></div>';
    }

    private static function gameBlock(array $p)
    {
        $rows = [];
        $plats = [];
        foreach (((isset($p['platforms']) ? $p['platforms'] : [])) as $x) { if (isset($x['platform']['name'])) $plats[] = $x['platform']['name']; }
        $devs = [];
        foreach (((isset($p['developers']) ? $p['developers'] : [])) as $x) { if (isset($x['name'])) $devs[] = $x['name']; }
        $pubs = [];
        foreach (((isset($p['publishers']) ? $p['publishers'] : [])) as $x) { if (isset($x['name'])) $pubs[] = $x['name']; }
        $gens = [];
        foreach (((isset($p['genres']) ? $p['genres'] : [])) as $x) { if (isset($x['name'])) $gens[] = $x['name']; }
        if ($plats) $rows[] = ['key' => '平台', 'val' => implode('、', array_unique($plats))];
        if ($devs)  $rows[] = ['key' => '开发商', 'val' => implode('、', array_unique($devs))];
        if ($pubs)  $rows[] = ['key' => '发行商', 'val' => implode('、', array_unique($pubs))];
        if (!empty($p['released'])) $rows[] = ['key' => '发行日期', 'val' => self::esc($p['released'])];
        if ($gens)  $rows[] = ['key' => '类型', 'val' => implode('、', array_unique($gens))];
        if (empty($rows)) return '';
        return self::kvBlock('游戏信息', $rows);
    }

    private static function animeBlock(array $p)
    {
        $rows = [];
        if (!empty($p['eps']))      $rows[] = ['key' => '话数', 'val' => self::esc($p['eps'])];
        if (!empty($p['air_date']))  $rows[] = ['key' => '开播', 'val' => self::esc($p['air_date'])];
        $studs = [];
        foreach (((isset($p['studios']) ? $p['studios'] : [])) as $x) { if (isset($x['name'])) $studs[] = $x['name']; }
        if ($studs) $rows[] = ['key' => '制作公司', 'val' => implode('、', array_unique($studs))];
        $tags = [];
        foreach (((isset($p['tags']) ? $p['tags'] : [])) as $t) { if (is_array($t)) { $tags[] = (isset($t['name']) ? $t['name'] : ''); } else { $tags[] = $t; } }
        $tags = array_filter(array_slice($tags, 0, 8));
        if ($tags) $rows[] = ['key' => '标签', 'val' => implode('、', $tags)];
        if (empty($rows)) return '';
        return self::kvBlock('番剧信息', $rows);
    }

    private static function personBlock(array $p, array $row)
    {
        $rows = [];
        if (!empty($p['known_for_department'])) $rows[] = ['key' => '职业', 'val' => self::esc($p['known_for_department'])];
        if (!empty($p['birthday']))             $rows[] = ['key' => '出生', 'val' => self::esc($p['birthday'])];
        if (!empty($p['place_of_birth']))       $rows[] = ['key' => '出生地', 'val' => self::esc($p['place_of_birth'])];
        if (empty($rows)) return '';
        return self::kvBlock('人物信息', $rows);
    }

    private static function kvBlock($title, array $rows)
    {
        $html = '';
        foreach ($rows as $r) {
            $html .= '<div><div class="k">' . self::esc($r['key']) . '</div><div class="v">' . self::esc($r['val']) . '</div></div>';
        }
        return '<div class="block"><h2>' . self::esc($title) . '</h2><div class="kv">' . $html . '</div></div>';
    }
}
