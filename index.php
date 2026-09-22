<?php
/**
 * 前端控制器 / 路由
 *  - /admin*              后台管理界面（带 token 的纯前端 SPA）
 *  - /3/*                 TMDB 兼容代理（ZBLOK 把 TMDB base URL 改成这里即可）
 *  - /api/v1/*            统一 API（跨源搜索/列表/详情）
 *  - /request             资源请求页（需注册登录；提交后站长在后台处理）
 *  - /user/*              注册 / 登录 / 退出
 *
 * 部署（宝塔 Nginx 伪静态）：
 *   location / { try_files $uri $uri/ /index.php?$query_string; }
 *
 * 安全：本文件是站点唯一合法入口，故在此定义 ML_APP 常量并最早加载
 *       core/Guard.php —— 安装类路径与敏感路径会被直接 404（详见 Guard.php）。
 *       安装：网页安装器 install.php（仅"未安装"时可用，装完自动 404）
 *             或命令行 php tools/install-cli.php —— 两者共用 core/Installer.php。
 */
define('ML_APP', true);
define('ML_APP_VERSION', '1.6.4');

require_once __DIR__ . '/core/Guard.php';      // ← 必须最早加载
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/Http.php';
require_once __DIR__ . '/core/Json.php';
require_once __DIR__ . '/core/CacheStore.php';
require_once __DIR__ . '/core/Categories.php';
require_once __DIR__ . '/core/Site.php';    // 站点信息 / SEO / 跳转扫码
require_once __DIR__ . '/core/LinkChecker.php';
require_once __DIR__ . '/core/Views.php';
require_once __DIR__ . '/core/StaticGen.php';
require_once __DIR__ . '/adapters/Adapter.php';
require_once __DIR__ . '/adapters/Tmdb.php';
require_once __DIR__ . '/adapters/Rawg.php';
require_once __DIR__ . '/adapters/Bangumi.php';
require_once __DIR__ . '/api/tmdb_proxy.php';
require_once __DIR__ . '/api/unified.php';

use Core\Json;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

/* ---------------------------------------------------------------- */
/* 入口守卫：安装路径黑洞 + 敏感目录/后缀一律 404                      */
/*   （先于任何业务逻辑，避免被用来探测站点结构）                      */
/* ---------------------------------------------------------------- */
ml_guard_boot($path);

Json::cors();

/* ---------------------------------------------------------------- */
/* 未安装守卫                                                        */
/*   没有 config/config.php，或 db 段还是 CHANGE_ME 占位值时，直接跑业务  */
/*   会抛数据库连接错误。这里给一个友好引导页，告诉站长怎么装。        */
/*   注意排除 check / health 自身，否则它们会被挡住。                  */
/* ---------------------------------------------------------------- */
$__helperPaths = array('/check', '/check.php', '/health', '/health.php');
$__isHelper    = in_array($path, $__helperPaths, true);
$__guardFile   = __DIR__ . '/core/InstallGuard.php';
if (!$__isHelper && file_exists($__guardFile)) {
    require_once $__guardFile;
    if (function_exists('ml_install_state')) {
        $__st = ml_install_state(__DIR__);
        if (empty($__st['installed'])) {
            $__hasSample = file_exists(__DIR__ . '/config/config.sample.php');
            $__hasCli    = file_exists(__DIR__ . '/tools/install-cli.php');
            $__hasWeb    = file_exists(__DIR__ . '/install.php');
            header('Content-Type: text/html; charset=utf-8');
            header('HTTP/1.1 200 OK');
            ?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>站点尚未安装 · 媒资资料库</title>
<style>
:root{--bg:#f1f5f9;--card:#fff;--bd:#e2e8f0;--fg:#0f172a;--mut:#475569;--cbg:#f1f5f9;--cfg:#be123c;--brand:#4f46e5;--wbg:#fef3c7;--wfg:#92400e;--sbg:#ecfdf5;--sfg:#065f46}
@media (prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#111c2e;--bd:#1e293b;--fg:#e2e8f0;--mut:#94a3b8;--cbg:#0f172a;--cfg:#fda4af;--brand:#6366f1;--wbg:#3a2e0a;--wfg:#fcd34d;--sbg:#0c2a22;--sfg:#6ee7b7}}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--fg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:18px;padding:34px 30px;max-width:620px;width:100%;box-shadow:0 18px 44px rgba(15,23,42,.08)}
h1{font-size:20px;margin-bottom:12px}
p{color:var(--mut);font-size:13.5px;line-height:1.85;margin-bottom:10px}
code{background:var(--cbg);padding:2px 6px;border-radius:5px;font-family:Consolas,monospace;color:var(--cfg);font-size:12.5px}
ol{margin:6px 0 0 20px;color:var(--mut);font-size:13.5px;line-height:2}
ol code{white-space:nowrap}
.go{display:inline-block;margin:6px 0 2px;background:var(--brand);color:#fff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 22px;border-radius:11px;box-shadow:0 8px 20px rgba(79,70,229,.28)}
.gohint{margin-top:9px;color:var(--mut);font-size:12.5px;line-height:1.75}
.divide{height:1px;background:var(--bd);margin:18px 0 14px}
.warn{margin-top:14px;background:var(--wbg);color:var(--wfg);border-radius:10px;padding:12px 14px;font-size:13px;line-height:1.75}
.safe{margin-top:10px;background:var(--sbg);color:var(--sfg);border-radius:10px;padding:12px 14px;font-size:13px;line-height:1.75}
@media (max-width:640px){body{padding:14px}.card{padding:26px 20px;border-radius:14px}.go{display:block;text-align:center}}
</style></head><body>
<div class="card">
  <h1>🛠 站点尚未安装</h1>
  <p><?php
    if ($__st['reason'] === 'sample') {
        echo '检测到 <code>config/config.php</code> 仍是<b>示例配置</b>（数据库信息未填写），所以还不能对外提供数据。';
    } else {
        echo '没有找到可用的 <code>config/config.php</code>，所以还不能对外提供数据。';
    }
    ?></p>
  <?php if ($__hasWeb): ?>
  <div class="divide"></div>
  <a class="go" href="/install.php">🚀 开始网页安装</a>
  <div class="gohint">在浏览器里按向导填好数据库信息即可，<b>不用终端</b>。装好后 <code>install.php</code> 会立刻自动 404，别人无法借它重装本站。</div>
  <?php endif; ?>
  <div class="divide"></div>
  <p><b>也可以这样做：</b></p>
  <ol>
    <?php if ($__hasWeb): ?>
    <li><b>打开安装向导</b>：浏览器访问 <code>/install.php</code>（就是上面的按钮），按向导填数据库信息，自动建表并生成配置。</li>
    <?php endif; ?>
    <li><b>命令行安装</b>：<?php if ($__hasCli): ?>在宝塔 <b>终端</b> 里进入站点目录，执行 <code>php tools/install-cli.php</code>，按提示填数据库信息，会自动建表并生成配置。<?php else: ?>把发布包里的 <code>tools/install-cli.php</code> 上传到站点 <code>tools/</code>，在宝塔终端执行 <code>php tools/install-cli.php</code> 完成安装。<?php endif; ?></li>
    <li><b>手动安装</b>：<?php if ($__hasSample): ?>把 <code>config/config.sample.php</code> 复制为 <code>config/config.php</code> 并填好数据库信息，<?php else: ?>新建 <code>config/config.php</code> 并填好数据库信息，<?php endif; ?>再导入 <code>sql/install.sql</code> 建表。</li>
    <li>装好后访问 <code>check.php?token=你的后台令牌</code> 做一次完整体检。</li>
  </ol>
  <?php if ($__hasWeb): ?>
  <div class="warn">⚠️ 点按钮若仍是 <b>404</b>：说明伪静态里还留着旧规则
    <code>location ~* ^/(install|setup|upgrade|update)</code>，Nginx 会把安装器一并拦掉。
    把它改成 <code>location ~* ^/(setup|upgrade|update)</code>（<b>去掉 install</b>）保存即可。</div>
  <?php endif; ?>
  <div class="safe">🔒 <b>网页安装器是带自锁的</b>：只在「尚未安装」时可用；装好后会写入 <code>config/install.lock</code>，它立刻对所有人返回 404，没人能借它重装本站或读取环境信息。</div>
</div></body></html>
            <?php
            return;
        }
    }
}

/* ---------------------------------------------------------------- */
/* SEO 辅助文件：/robots.txt 与 /sitemap.xml                          */
/*   内容由后台「站点设置 → SEO」控制；留空走内置默认。                 */
/*   必须排在下面的通用路由之前，且不走 Guard 的敏感后缀拦截。          */
/* ---------------------------------------------------------------- */
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo \Core\Site::robotsTxt();
    return;
}
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo \Core\Site::sitemapXml();
    return;
}

if (strpos($path, '/admin') === 0) {
    require __DIR__ . '/admin/index.php';
    return;
}
if ($path === '/' || $path === '') {
    /* ------------------------------------------------------------ */
    /* 扫描 + 跳转拦截（后台「站点设置 → 扫描+跳转」）                 */
    /*   jump_only  仅跳转：meta refresh 直接跳走                    */
    /*   scan_only  仅扫码：只显示二维码                             */
    /*   jump_scan  跳转 + 扫码：先展示二维码，几秒后跳转             */
    /*   redirect_mobile = 否 时只拦 PC 访客，手机直接进站            */
    /* ★ 只拦首页入口 —— 内页是已生成的静态文件，拦住反而伤收录。      */
    /* ------------------------------------------------------------ */
    if (class_exists('\\Core\\Site')) {
        $__ic = \Core\Site::interceptHtml();
        if ($__ic !== null) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
                header('HTTP/1.1 200 OK');
                header('Cache-Control: no-store, no-cache, must-revalidate');
            }
            echo $__ic;
            return;
        }
    }
    require __DIR__ . '/home.php';
    return;
}
/* ---------------------------------------------------------------- */
/* 前台用户中心：注册 / 登录 / 退出 / 资源请求                         */
/*   /user/register  /user/login  /user/logout  /request              */
/*   表单是原生 POST + 重定向，不依赖 JS；写操作全部带 CSRF 令牌。       */
/* ---------------------------------------------------------------- */
if ($path === '/request' || strpos($path, '/user') === 0) {
    require __DIR__ . '/user.php';
    return;
}
if (strpos($path, '/track') === 0) {
    require __DIR__ . '/track.php';
    return;
}
// 环境自检（check.php）：/check、/health、/check.php 均可访问（需令牌，见 check.php）
if ($path === '/check' || $path === '/check.php' || $path === '/health') {
    require __DIR__ . '/check.php';
    return;
}
if (strpos($path, '/3/') === 0) {
    \Api\TmdbProxy::handle($path);
    return;
}
if (strpos($path, '/api/v1/') === 0) {
    \Api\Unified::handle($path);
    return;
}

Json::error(404, 'Not Found', ['path' => $path]);
