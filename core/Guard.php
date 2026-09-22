<?php
/**
 * 媒资资料库 · 安全守卫（core/Guard.php）
 * ==================================================================
 * 为什么需要它：
 *   站点装好之后，最大的两个公网风险是
 *     ① 别人访问安装器把站点重装（或读到环境信息）—— 所以安装器已从
 *        站点根移除，且本守卫把「任何安装相关路径」直接 404 掉；
 *     ② 别人直接请求内部 PHP 文件（core/*.php、adapters/*.php、api/*.php…）
 *        或下载 config/ sql/ 里的配置、SQL 文件，从而摸清站点结构。
 *
 * 三个能力：
 *   ml_guard_boot($path)    入口守卫：安装路径黑洞 + 敏感目录/后缀一律 404
 *   ml_guard_shield($file)  内部文件守卫：本身被直接请求时 404（伪装成不存在）
 *   ml_guard_status($root)  防护状态：给后台「安全防护」页与自检页展示
 *
 * 设计原则：
 *   - 被拦截时返回一个**普通 404 页面**，绝不提示"需要令牌 / 被保护"，
 *     避免攻击者据此确认文件存在。
 *   - 覆盖 Nginx 与 Apache 两种部署（Nginx 的建议规则见 DEPLOY.md）。
 *   - 纯函数、无副作用；兼容 PHP 5.6 ~ 8.2。
 */

/* ================================================================== */
/* 统一 404 出口                                                       */
/* ================================================================== */

if (!function_exists('ml_guard_deny')) {
    /**
     * 以"资源不存在"的姿态结束请求。
     * 不暴露任何防护细节，URL 原样回显也不做（避免反射型注入）。
     */
    function ml_guard_deny()
    {
        if (!headers_sent()) {
            header('HTTP/1.1 404 Not Found');
            header('Status: 404 Not Found');

            $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
            $isJson = (strpos($accept, 'application/json') !== false);

            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo '{"success":false,"error":"Not Found"}';
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
                   . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                   . '<title>404 Not Found</title></head>'
                   . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;'
                   . 'justify-content:center;font-family:-apple-system,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;'
                   . 'background:#f8fafc;color:#64748b">'
                   . '<div style="text-align:center"><div style="font-size:58px;font-weight:700;color:#cbd5e1">404</div>'
                   . '<div style="margin-top:6px;font-size:14px">Not Found</div></div>'
                   . '</body></html>';
            }
        }
        exit;
    }
}

/* ================================================================== */
/* ① 入口守卫：安装路径黑洞 + 敏感路径                                  */
/* ================================================================== */

if (!function_exists('ml_guard_boot')) {
    /**
     * 在 index.php 最早期调用。
     * @param string $path 当前请求路径（已去掉 query，例如 /install.php）
     */
    function ml_guard_boot($path)
    {
        $path = (string) $path;
        if ($path === '') {
            return;
        }

        /* --- 1) 安装 / 初始化类路径一律 404 ---
           ★ 唯一例外：精确的 /install.php（与 /install）放行 —— 交给 install.php
             自己判定：已安装时它主动返回 404，未安装时才显示安装向导（v1.6.3）。
             其余 install / setup / upgrade / update 类路径仍然一律 404，
             包括 install.php.bak、setup.php 这些"残留/伪装"文件。 */
        $__isInstaller = ($path === '/install.php' || $path === '/install' || $path === '/install/');
        if (!$__isInstaller
            && preg_match('#^/(install|setup|upgrade|update)([-_.][a-z0-9]+)?(\.[a-z0-9]+)?(/|$)#i', $path)) {
            ml_guard_deny();
        }

        /* --- 2) 敏感目录不得从公网直达 ---
           （宝塔 Nginx 建议再配 location ~* /(config|sql|core|adapters|cron|tools)/ { deny all; }；
             在没配规则时，这段 PHP 兜底仍能拦住"走到 index.php 的路由"。） */
        if (preg_match('#^/(config|sql|core|adapters|cron|tools|data|logs|backup|bak)(/|$)#i', $path)) {
            ml_guard_deny();
        }

        /* --- 3) 敏感后缀不得下载 ---
           配置、SQL、文档、备份、锁文件等。注意不要拦 .html/.json（静态内页与 API 要用）。 */
        if (preg_match('#\.(sql|md|lock|bak|bak[0-9]*|ini|log|env|sh|bash|yml|yaml|conf|dist|sample|swp|swo|orig|save|old|tmp|sqlite|db)$#i', $path)) {
            ml_guard_deny();
        }

        /* --- 4) 点文件（.git/.env/.htaccess 等）不得访问 --- */
        if (preg_match('#/\.#', $path)) {
            ml_guard_deny();
        }
    }
}

/* ================================================================== */
/* ② 内部文件守卫：本身被直接请求时 404                                  */
/* ================================================================== */

if (!function_exists('ml_guard_shield')) {
    /**
     * 放在每一个"只应被引入、不应被直接访问"的 PHP 文件开头：
     *
     *     require_once __DIR__ . '/Guard.php';   // 子目录里注意改成 ../core/Guard.php
     *     ml_guard_shield(__FILE__);
     *
     * 判定方式：若当前正在执行的"入口脚本"就是这个文件本身，说明有人
     * 直接用 URL 请求了它 → 伪装 404。
     * 正常被 index.php / check.php / cron 引入时，入口脚本是别的文件，
     * 因此不会有任何影响；站点根目录的 index.php 是合法入口，不加这行。
     */
    function ml_guard_shield($file)
    {
        if (defined('ML_APP')) {
            return;                                     // 由合法入口引入，放行
        }
        if (php_sapi_name() === 'cli') {
            return;                                     // 命令行运行（cron / CLI 安装器）
        }
        if (empty($_SERVER['SCRIPT_FILENAME'])) {
            return;                                     // 非常规 SAPI，保守放行
        }
        $self = @realpath((string) $_SERVER['SCRIPT_FILENAME']);
        $me   = @realpath((string) $file);
        if ($self !== false && $me !== false && $self === $me) {
            ml_guard_deny();
        }
    }
}

/* ================================================================== */
/* ③ 防护状态（后台「安全防护」页 / 自检页展示）                          */
/* ================================================================== */

if (!function_exists('ml_guard_status')) {
    /** 需要带"反直接访问"守卫的内部文件清单（相对站点根） */
    function ml_guard_shield_files()
    {
        return array(
            'core/Config.php', 'core/DB.php', 'core/Http.php', 'core/Json.php',
            'core/CacheStore.php', 'core/Categories.php', 'core/LinkChecker.php',
            'core/Views.php', 'core/StaticGen.php', 'core/InstallGuard.php', 'core/Settings.php',
            'core/Sync.php', 'core/Site.php', 'core/PanSou.php',
            'core/User.php', 'core/UserViews.php',
            'adapters/Adapter.php', 'adapters/Tmdb.php', 'adapters/Rawg.php', 'adapters/Bangumi.php',
            'api/tmdb_proxy.php', 'api/unified.php',
            'home.php', 'track.php', 'user.php',
            'config/config.sample.php',
        );
    }

    /** 需要放"拒访 index.php"的目录 */
    function ml_guard_dirs()
    {
        return array('config', 'sql', 'core', 'adapters', 'cron', 'tools');
    }

    /** 单个文件是否已带守卫（读文本找 ml_guard_shield） */
    function ml_guard_file_shielded($abs)
    {
        if (!is_file($abs)) {
            return false;
        }
        $txt = (string) @file_get_contents($abs);
        return (strpos($txt, 'ml_guard_shield(') !== false);
    }

    /**
     * 汇总当前防护状态
     * @return array
     */
    function ml_guard_status($root)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        $inst = array();

        // 安装器是否已从站点根移除（最重要的一项）
        $inst['install_removed'] = !file_exists($root . '/install.php');
        $inst['install_ghosts']  = array();     // 残留的安装类文件
        foreach (array('install.php', 'setup.php', 'install.php.bak', 'install.lock.bak') as $g) {
            if (file_exists($root . '/' . $g)) {
                $inst['install_ghosts'][] = $g;
            }
        }
        $inst['lock']          = file_exists($root . '/config/install.lock');
        $inst['cfg_exists']    = file_exists($root . '/config/config.php');
        $inst['check_exists']  = file_exists($root . '/check.php');
        $inst['cli_installer'] = file_exists($root . '/tools/install-cli.php');
        $inst['tools_denied']  = file_exists($root . '/tools/index.php');

        // 各敏感目录的拒访文件
        $dirs = array();
        foreach (ml_guard_dirs() as $d) {
            $dirs[$d] = file_exists($root . '/' . $d . '/index.php');
        }
        $inst['dir_guards'] = $dirs;

        // 内部文件守卫覆盖率
        $missing = array();
        $total   = 0;
        foreach (ml_guard_shield_files() as $f) {
            $abs = $root . '/' . $f;
            if (!is_file($abs)) {
                continue;                           // 文件不存在就不算缺口
            }
            $total++;
            if (!ml_guard_file_shielded($abs)) {
                $missing[] = $f;
            }
        }
        $inst['shield_total']   = $total;
        $inst['shield_missing'] = $missing;

        // 伪静态 / Nginx 规则已否由 check.php 的实际 HTTP 探测给出，这里只报状态
        $inst['htaccess'] = file_exists($root . '/.htaccess');

        return $inst;
    }
}

/* Guard.php 自身也应该在被直接请求时 404（它只在站点根 index.php 之前被引入）。 */
ml_guard_shield(__FILE__);
