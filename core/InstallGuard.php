<?php
/**
 * 媒资资料库 · 安装状态判定（index.php / check.php / tools/install-cli.php 共用）
 * ==================================================================
 * 为什么需要它：
 *   发布包会附带 config/config.php 的**示例模板**，里面的数据库口令是
 *   CHANGE_ME 之类的占位值。如果只判断「文件存在不存在」，就会出现
 *   「还没填数据库信息却提示已安装完成」的假象。
 *   所以这里以「db 段是否填了真值」为准：
 *     - 有 config/install.lock            → 已安装
 *     - config.php 的 db 已填真值          → 已安装
 *     - config.php 存在但 db 还是占位/空   → **未安装**（首页给安装引导页）
 *     - config.php 不存在                  → 未安装
 *
 * 纯函数、无副作用；直接读文件文本而不 include，避免配置文件本身有语法
 * 错误时把本页也带崩。兼容 PHP 5.6 ~ 8.2。
 */

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

if (!function_exists('ml_cfg_path')) {
    function ml_cfg_path($root)
    {
        return rtrim($root, '/\\') . '/config/config.php';
    }
}

if (!function_exists('ml_lock_path')) {
    function ml_lock_path($root)
    {
        return rtrim($root, '/\\') . '/config/install.lock';
    }
}

if (!function_exists('ml_cfg_text')) {
    /** 读取 config/config.php 的原始文本（不执行、不 include） */
    function ml_cfg_text($root)
    {
        $f = ml_cfg_path($root);
        return file_exists($f) ? (string) @file_get_contents($f) : '';
    }
}

if (!function_exists('ml_db_configured')) {
    /** config.php 里的 db 段是否已填「真值」 */
    function ml_db_configured($root)
    {
        $txt = ml_cfg_text($root);
        if ($txt === '') {
            return false;
        }
        // 取出 'db' => [ ... ] 这一段（db 内只有标量，取到第一个 ] 即为整段）
        if (!preg_match("/'db'\s*=>\s*\[(.*?)\]/s", $txt, $m)) {
            return false;
        }
        $seg  = $m[1];
        $pick = function ($k) use ($seg) {
            return preg_match("/'" . $k . "'\s*=>\s*'([^']*)'/", $seg, $mm) ? trim($mm[1]) : '';
        };

        $name = $pick('dbname');
        $user = $pick('user');
        $pass = $pick('pass');

        if ($name === '' || $user === '') {
            return false;
        }
        // 占位口令一律视为「未填」。注意：**空口令不算占位**，
        // 以免把「真的用空密码连库」的站点误判成未安装。
        $placeholders = array(
            'CHANGE_ME', 'change_me', 'CHANGE_ME_PASSWORD',
            'YOUR_PASSWORD', 'YOUR_DB_PASSWORD', 'YOUR_DB_PASS', 'PLACEHOLDER',
        );
        foreach ($placeholders as $p) {
            if (strcasecmp($pass, $p) === 0) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('ml_install_state')) {
    /**
     * 返回安装状态
     *   installed   bool  是否已安装
     *   lock        bool  config/install.lock 是否存在
     *   cfg_exists  bool  config/config.php 是否存在
     *   db_ready    bool  db 段是否已填真值
     *   reason      string lock | db | sample | none
     */
    function ml_install_state($root)
    {
        $lock      = file_exists(ml_lock_path($root));
        $cfgExists = ml_cfg_text($root) !== '';
        $dbReady   = ml_db_configured($root);

        if ($lock) {
            $reason = 'lock';
        } elseif ($dbReady) {
            $reason = 'db';
        } elseif ($cfgExists) {
            $reason = 'sample';   // 只有示例/模板配置
        } else {
            $reason = 'none';
        }

        return array(
            'installed'  => ($lock || $dbReady),
            'lock'       => $lock,
            'cfg_exists' => $cfgExists,
            'db_ready'   => $dbReady,
            'reason'     => $reason,
        );
    }
}
