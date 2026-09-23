<?php
/**
 * 会话引导（core/Session.php）
 * ==================================================================
 * 宝塔上「登录后跳回登录页 / 点登录没反应 / 提示页面已过期」绝大多数
 * 不是账号密码问题，而是**会话存不住**：
 *
 *   ① session.save_path 指向的目录不存在，或存在但不可写
 *      （PHP-FPM 以 www 身份运行，而该目录属 root）；
 *   ② 站点实际是 HTTPS，但 $_SERVER['HTTPS'] 为空（宝塔反代 / CDN 回源），
 *      cookie 被标成 Secure 后浏览器直接丢弃 → 每次请求都是新会话；
 *   ③ 会话目录被 PHP 的 gc 清得过早。
 *
 * 这里统一处理：挑一个真正可写的会话目录、按真实协议设置 cookie、
 * 失败时自动换目录重试一次。
 *
 * 设计原则：纯函数、无副作用（只在被调用时启动会话），兼容 PHP 5.6 ~ 8.2。
 */

/* ================================================================== */
/* 协议判定：是否 HTTPS（含反代场景）                                    */
/* ================================================================== */

if (!function_exists('ml_https_on')) {
    /**
     * 判断当前请求是否走 HTTPS。兼容宝塔反代 / CDN：
     * 反代只改 X-Forwarded-Proto，不会给 PHP 传 HTTPS=on。
     * @return bool
     */
    function ml_https_on()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['HTTP_FRONT_END_HTTPS'])
            && strtolower((string) $_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
            return true;
        }
        return false;
    }
}

/* ================================================================== */
/* 会话目录：挑一个真正可写的                                            */
/* ================================================================== */

if (!function_exists('ml_session_path')) {
    /**
     * 返回一个**可写**的会话目录；全都不行时返回空串（用系统默认）。
     * @return string
     */
    function ml_session_path()
    {
        $cands = array();

        /* ① 当前配置值（宝塔常见写法 "N;/path" 或 "N;MODE;/path"） */
        $cur = (string) @ini_get('session.save_path');
        if ($cur !== '') {
            if (strpos($cur, ';') !== false) {
                $parts = explode(';', $cur);
                $cur = trim((string) end($parts));
            }
            if ($cur !== '') { $cands[] = $cur; }
        }

        /* ② 系统临时目录（优先，不在站点目录里，不会被公网下载） */
        $tmp = rtrim((string) sys_get_temp_dir(), '/\\');
        if ($tmp !== '') {
            $cands[] = $tmp . '/ml_sessions';
            $cands[] = $tmp;
        }

        /* ③ 最后一招：站点 data/sessions（会额外放下拒访文件，避免被直接下载） */
        if (defined('ML_ROOT')) {
            $cands[] = rtrim((string) ML_ROOT, '/\\') . '/data/sessions';
        }

        foreach ($cands as $p) {
            if ($p === '') { continue; }
            if (!is_dir($p)) { @mkdir($p, 0755, true); }
            if (is_dir($p) && is_writable($p)) {
                /* 若落在站点目录里，补两个拒访文件，防止会话文件被直接下载 */
                if (defined('ML_ROOT')) {
                    $root = rtrim(str_replace('\\', '/', (string) ML_ROOT), '/');
                    if (strpos(str_replace('\\', '/', $p), $root . '/') === 0) {
                        if (!file_exists($p . '/index.php')) {
                            @file_put_contents($p . '/index.php', "<?php http_response_code(404); exit;");
                        }
                        if (!file_exists($p . '/.htaccess')) {
                            @file_put_contents($p . '/.htaccess', "Require all denied\nDeny from all\n");
                        }
                    }
                }
                return $p;
            }
        }
        return '';
    }
}

/* ================================================================== */
/* 启动会话                                                            */
/* ================================================================== */

if (!function_exists('ml_session_boot')) {
    /**
     * 启动会话。返回 true 表示会话真的可用（能读能写）。
     *
     * @param string $name 会话名；留空表示沿用当前/默认名
     * @param int    $ttl  cookie 生命周期与 gc 上限（秒）
     * @return bool
     */
    function ml_session_boot($name = '', $ttl = 86400)
    {
        if (PHP_SAPI === 'cli') { return false; }                    // 命令行（cron / 安装器）不建会话
        if (!function_exists('session_start')) { return false; }
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            return true;                                             // 已有活跃会话，直接复用
        }
        if (headers_sent()) { return false; }                        // 已经有输出了，会话无法再开

        /* --- 会话目录兜底：宝塔上最常见的坑 --- */
        $path = ml_session_path();
        if ($path !== '') { @session_save_path($path); }

        /* --- 安全项 --- */
        @ini_set('session.use_strict_mode', '1');     // 拒绝未知的会话 ID
        @ini_set('session.use_only_cookies', '1');    // 禁止把 ID 塞进 URL
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');     // 拒绝 JS 读取
        @ini_set('session.cookie_samesite', 'Lax');   // PHP 7.3+ 生效，低版本自动忽略
        @ini_set('session.gc_maxlifetime', (string) (int) $ttl);

        $https = ml_https_on();
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            @session_set_cookie_params(array(
                'lifetime' => (int) $ttl,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            @session_set_cookie_params((int) $ttl, '/', '', (bool) $https, true);
        }
        if ($name !== '') { @session_name($name); }

        $ok = (bool) @session_start();

        /* --- 指定目录仍失败 → 换临时目录再试一次 --- */
        if (!$ok) {
            $alt = rtrim((string) sys_get_temp_dir(), '/\\') . '/ml_sessions';
            if (!is_dir($alt)) { @mkdir($alt, 0755, true); }
            if (is_dir($alt) && is_writable($alt) && $alt !== $path) {
                @session_save_path($alt);
                $ok = (bool) @session_start();
            }
        }
        return $ok;
    }
}

/* ================================================================== */
/* 会话健康度（自检页 / 登录页展示用）                                   */
/* ================================================================== */

if (!function_exists('ml_session_health')) {
    /**
     * 汇总会话现状，供自检与登录页排障展示。
     * 注意：只读，不会主动开启新会话文件。
     * @return array
     */
    function ml_session_health()
    {
        $savePath   = (string) @ini_get('session.save_path');
        $realPath   = ml_session_path();
        $active     = (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE);
        $writable   = ($realPath !== '' && is_dir($realPath) && is_writable($realPath));
        $sessionDir = ($realPath !== '') ? $realPath : trim((string) @sys_get_temp_dir());

        return array(
            'active'        => $active,
            'name'          => (string) @session_name(),
            'save_path'     => $savePath,
            'save_path_ok'  => $writable,
            'real_path'     => $sessionDir,
            'handler'       => (string) @ini_get('session.save_handler'),
            'gc_maxlife'    => (int) @ini_get('session.gc_maxlifetime'),
            'https'         => ml_https_on(),
            'cookie_set'    => !empty($_COOKIE[(string) @session_name()]),
            'headers_sent'  => headers_sent(),
        );
    }
}

/* ================================================================== */
/* 本文件被直接请求时伪装 404（与其它 core 文件一致）                      */
/* ================================================================== */
if (function_exists('ml_guard_shield')) { ml_guard_shield(__FILE__); }
