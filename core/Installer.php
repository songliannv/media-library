<?php
/**
 * 媒资资料库 · 安装内核（core/Installer.php）
 * ==================================================================
 * 一份实现、两处使用：
 *   · 网页安装器     install.php              —— 未安装时可用；装完自动 404
 *   · 命令行安装器   tools/install-cli.php     —— 仅命令行可执行，公网 404
 *
 * 为什么网页安装器又回来了（v1.6.3）：
 *   v1.4.0 曾把 install.php 从站点根彻底删掉，理由是"挂在公网上会被别人重装"。
 *   但这样一来，宝塔用户（拿不到终端 / 不想敲命令）就**根本没法装站**，
 *   源码等于白给。v1.6.3 起改为**带自锁的网页安装器**：
 *     - 只要 `config/install.lock` 存在，或 `config/config.php` 的 db 段已填真值
 *       （即 ml_install_state() 判定为"已安装"），install.php 立刻以 404 收场，
 *       与文件不存在完全一样 —— 没人能借它重装站点或读环境信息；
 *     - 只有在"尚未安装"这个必然的时间窗里它才可用，而那正是站长最需要它的时候。
 *   这与 WordPress / Discuz 的做法一致，安全性与易用性兼顾。
 *
 * 兼容 PHP 5.6 ~ 8.2：不用 ??、fn()、返回类型、标量类型提示、\Throwable、
 * 数组解构、?->、match、str_contains、random_bytes（7.0+）。
 * 纯函数 + 显式参数，不依赖 Config/DB 等业务类（未安装时那些类会 exit）。
 */

require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/* ================================================================== */
/* 小工具                                                             */
/* ================================================================== */

if (!function_exists('mlinst_root')) {
    /** 由本文件位置推出站点根（core/ 的上级） */
    function mlinst_root()
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    }
}

if (!function_exists('mlinst_rand_token')) {
    /** 生成随机令牌（PHP 5.6 可用，不依赖 random_bytes） */
    function mlinst_rand_token()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $b = @openssl_random_pseudo_bytes(16, $strong);
            if ($b !== false && strlen($b) >= 16) {
                return substr(bin2hex($b), 0, 16);
            }
        }
        return substr(md5(uniqid('ml', true) . mt_rand(100000, 999999)), 0, 16);
    }
}

if (!function_exists('mlinst_rand_pass')) {
    /** 生成易读随机串（给"一键生成数据库口令"用，去掉了易混字符） */
    function mlinst_rand_pass($len = 16)
    {
        $pool = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max  = strlen($pool) - 1;
        $out  = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $pool[mt_rand(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('mlinst_q')) {
    /** PHP 单引号字符串安全转义 */
    function mlinst_q($v)
    {
        return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $v) . "'";
    }
}

if (!function_exists('mlinst_eq')) {
    /** 恒时比较（PHP 5.6 无 hash_equals） */
    function mlinst_eq($a, $b)
    {
        $a = (string) $a;
        $b = (string) $b;
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $d = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $d |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $d === 0;
    }
}

/* ================================================================== */
/* 表结构（唯一来源；sql/install.sql 与之一致）                          */
/* ================================================================== */

if (!function_exists('mlinst_tables')) {
    /** 返回建表 SQL 数组（全部 IF NOT EXISTS，可重复执行） */
    function mlinst_tables()
    {
        return array(
            "CREATE TABLE IF NOT EXISTS media_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source        VARCHAR(32)  NOT NULL DEFAULT '',
  source_id     VARCHAR(64)  NOT NULL DEFAULT '',
  type          VARCHAR(32)  NOT NULL DEFAULT '',
  title         VARCHAR(512) NOT NULL DEFAULT '',
  original_title VARCHAR(512) NOT NULL DEFAULT '',
  year          SMALLINT     DEFAULT NULL,
  rating        DECIMAL(6,2) DEFAULT NULL,
  vote_count    INT          DEFAULT NULL,
  api_calls     INT UNSIGNED NOT NULL DEFAULT 0,
  clicks        INT UNSIGNED NOT NULL DEFAULT 0,
  genres        TEXT,
  poster        VARCHAR(1024) NOT NULL DEFAULT '',
  backdrop      VARCHAR(1024) NOT NULL DEFAULT '',
  overview      TEXT,
  download_url  MEDIUMTEXT   NULL,
  extra         TEXT,
  payload       MEDIUMTEXT,
  cached_at     DATETIME     DEFAULT NULL,
  refresh_at    DATETIME     DEFAULT NULL,
  updated_at    DATETIME     DEFAULT NULL,
  UNIQUE KEY uq_src (source, source_id, type),
  KEY idx_type (type),
  KEY idx_title (title(191)),
  KEY idx_year (year),
  KEY idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS sync_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task       VARCHAR(64) NOT NULL DEFAULT '',
  detail     TEXT,
  items      INT DEFAULT 0,
  created_at DATETIME DEFAULT NULL,
  KEY idx_task (task)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS link_checks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id    INT UNSIGNED NOT NULL,
  label      VARCHAR(64)  NOT NULL DEFAULT '',
  url        VARCHAR(2048) NOT NULL DEFAULT '',
  status     TINYINT NOT NULL DEFAULT 0,
  http_code  SMALLINT NOT NULL DEFAULT 0,
  checked_at DATETIME DEFAULT NULL,
  KEY idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS app_settings (
  `k`          VARCHAR(64) NOT NULL,
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            /* ---- v1.6.2 起的注册与资源请求（原先由 User::ensureTables 懒建，这里预建一份）---- */
            "CREATE TABLE IF NOT EXISTS `app_users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `email`      VARCHAR(128) NOT NULL DEFAULT '',
  `pass_hash`  VARCHAR(255) NOT NULL DEFAULT '',
  `status`     TINYINT      NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用 2=待审核',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `req_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `lock_until` DATETIME     DEFAULT NULL,
  `created_at` DATETIME     DEFAULT NULL,
  `last_login` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台注册用户'",

            "CREATE TABLE IF NOT EXISTS `app_requests` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `title`      VARCHAR(255) NOT NULL DEFAULT '',
  `type`       VARCHAR(32)  NOT NULL DEFAULT '',
  `year`       SMALLINT     DEFAULT NULL,
  `note`       TEXT,
  `contact`    VARCHAR(128) NOT NULL DEFAULT '',
  `status`     VARCHAR(16)  NOT NULL DEFAULT 'pending',
  `admin_note` TEXT,
  `item_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at` DATETIME     DEFAULT NULL,
  `updated_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_title` (`title`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资源请求'",
        );
    }
}

if (!function_exists('mlinst_default_adapters')) {
    function mlinst_default_adapters()
    {
        return array('tmdb', 'rawg', 'bangumi');
    }
}

if (!function_exists('mlinst_default_pan')) {
    function mlinst_default_pan()
    {
        return array('夸克', '迅雷', '光鸭', '百度', 'UC');
    }
}

/* ================================================================== */
/* 生成配置文件内容                                                    */
/* ================================================================== */

if (!function_exists('mlinst_config_content')) {
    /**
     * 生成 config/config.php 内容
     * @param array $db     host/port/dbname/user/pass
     * @param string $tmdbKey
     * @param string $rawgKey
     * @param string $adminToken
     * @param array  $adapters 启用的数据源
     */
    function mlinst_config_content($db, $tmdbKey, $rawgKey, $adminToken, $adapters)
    {
        $t  = "\n    ";
        $s  = "<?php\n";
        $s .= "/**\n * 媒资资料库 API - 配置文件（由安装器自动生成 " . date('Y-m-d H:i:s') . "）\n";
        $s .= " * 如需改动数据库/密钥，直接改本文件即可；也可在后台「系统设置」里改（优先级更高）。\n */\n\n";
        /* 生成的配置也必须带"反直接访问"守卫：否则 /config/config.php 会被
           PHP 直接执行（返回 200），把内部文件的存在与路径暴露出去。 */
        $s .= "/* 安全守卫：本文件只应被入口（index.php / check.php / cron）引入；\n";
        $s .= "   直接被网址请求时返回 404 —— 见 core/Guard.php。 */\n";
        $s .= "if (is_file(__DIR__ . '/../core/Guard.php')) {\n";
        $s .= "    require_once __DIR__ . '/../core/Guard.php';\n";
        $s .= "    if (function_exists('ml_guard_shield')) { ml_guard_shield(__FILE__); }\n";
        $s .= "}\n\n";
        $s .= "return [\n";
        $s .= $t . "// 数据库（宝塔新建的 MySQL 库）\n";
        $s .= $t . "'db' => [\n";
        $s .= $t . "    'host'     => " . mlinst_q($db['host']) . ",\n";
        $s .= $t . "    'port'     => " . (int) $db['port'] . ",\n";
        $s .= $t . "    'dbname'   => " . mlinst_q($db['dbname']) . ",\n";
        $s .= $t . "    'user'     => " . mlinst_q($db['user']) . ",\n";
        $s .= $t . "    'pass'     => " . mlinst_q($db['pass']) . ",\n";
        $s .= $t . "    'charset'  => 'utf8mb4',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// TMDB（影视/人物） https://www.themoviedb.org/settings/api 申请\n";
        $s .= $t . "'tmdb' => [\n";
        $s .= $t . "    'api_key'   => " . mlinst_q($tmdbKey) . ",\n";
        $s .= $t . "    'base'      => 'https://api.themoviedb.org/3',\n";
        $s .= $t . "    'image_base'=> 'https://image.tmdb.org/t/p/',\n";
        $s .= $t . "    'lang'      => 'zh-CN',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// RAWG（游戏） https://rawg.io/apidocs 申请\n";
        $s .= $t . "'rawg' => [\n";
        $s .= $t . "    'api_key'   => " . mlinst_q($rawgKey) . ",\n";
        $s .= $t . "    'base'      => 'https://rawg.io/api',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// Bangumi（动漫） https://bangumi.github.io/api/\n";
        $s .= $t . "'bangumi' => [\n";
        $s .= $t . "    'base'      => 'https://api.bgm.tv',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 启用的数据源适配器\n";
        $s .= $t . "'adapters' => [" . mlinst_q($adapters[0])
            . (isset($adapters[1]) && $adapters[1] !== '' ? ", " . mlinst_q($adapters[1]) : '')
            . (isset($adapters[2]) && $adapters[2] !== '' ? ", " . mlinst_q($adapters[2]) : '') . "],\n\n";
        $s .= $t . "// 缓存策略\n";
        $s .= $t . "'cache' => [\n";
        $s .= $t . "    'ttl_days'   => 7,\n";
        $s .= $t . "    'stale_days' => 30,\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// TMDB 兼容代理是否强制校验 key（默认 false，ZBLOK 改 base URL 即用）\n";
        $s .= $t . "'proxy' => [\n";
        $s .= $t . "    'require_key' => false,\n";
        $s .= $t . "    'proxy_key'   => '',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 后台管理 token（请求头 X-Admin-Token 或 ?token=）\n";
        $s .= $t . "'admin' => [\n";
        $s .= $t . "    'token' => " . mlinst_q($adminToken) . ",\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// ================= 站点设置（v1.6.0 起；建议都到后台「站点设置」里改） =================\n";
        $s .= $t . "'site' => [\n";
        $s .= $t . "    'name'      => '媒资资料库', 'name_hide' => 0, 'slogan' => '',\n";
        $s .= $t . "    'logo'      => '', 'favicon' => '', 'url' => '',\n";
        $s .= $t . "    'footer_intro' => '', 'declare' => '', 'copyright' => '',\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// SEO：首页三件套 + 标题模板 + 统计代码 + robots / sitemap\n";
        $s .= $t . "'seo' => [\n";
        $s .= $t . "    'title' => '', 'keywords' => '', 'description' => '', 'stats_code' => '',\n";
        $s .= $t . "    'pattern_type' => '{type} - 第{page}页 - {site}',\n";
        $s .= $t . "    'pattern_item' => '{title}({year}) - {type} - {site}',\n";
        $s .= $t . "    'item_desc_len' => 0, 'robots' => '', 'sitemap' => 1, 'sitemap_size' => 5000,\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 跳转与扫码 + 前台模板（target 与 qr 都为空 = 不拦截访客）\n";
        $s .= $t . "'redirect' => [\n";
        $s .= $t . "    'mode' => 'jump_scan', 'target' => '', 'mobile' => 0, 'qr' => '', 'qr_tip' => '',\n";
        $s .= $t . "    'tpl' => 'classic', 'simple_mode' => 'auto', 'nav_show' => 'home,latest',\n";
        $s .= $t . "    'nav_external' => '', 'rank_style' => 'grid', 'latest' => 1,\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 网盘账号（后台「站点设置 → 网盘链接」；只保存与检测，不会登录你的网盘）\n";
        $s .= $t . "'pan' => [\n";
        $s .= $t . "    'group' => 'quark,aliyun,baidu,uc,xunlei,guangya',\n";
        $s .= $t . "    // 'quark_cookie' => '', 'quark_dir' => '', 'quark_tmp' => '',  // 其余同理\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 接口配置：PanSou 网盘搜索（采集后自动补网盘下载地址）\n";
        $s .= $t . "'pansou' => [\n";
        $s .= $t . "    'enable' => 0, 'mode' => 'local', 'url' => 'http://127.0.0.1:8888',\n";
        $s .= $t . "    'user' => '', 'pass' => '', 'line' => 'PanSou 主线路', 'types' => '',\n";
        $s .= $t . "    'auto' => 1, 'policy' => 'exact', 'match' => 80, 'max' => 5,\n";
        $s .= $t . "    'delay' => 1000, 'ttl' => 720,\n";
        $s .= $t . "],\n\n";
        $s .= $t . "// 资源请求（注册 + 求片区）；两张表由程序首次使用时自动创建\n";
        $s .= $t . "'req' => [\n";
        $s .= $t . "    'open' => 1, 'logon' => 1, 'audit' => 0, 'verify' => 1,\n";
        $s .= $t . "    'guest_view' => 1, 'public' => 1, 'contact' => 1, 'nav' => 1,\n";
        $s .= $t . "    'daily' => 5, 'mintitle' => 2,\n";
        $s .= $t . "    'page_title' => '资源请求', 'page_intro' => '', 'notice' => '',\n";
        $s .= $t . "],\n";
        $s .= "];\n";
        return $s;
    }
}

if (!function_exists('mlinst_pan_content')) {
    /** 生成 config/pan_types.php 内容 */
    function mlinst_pan_content($list)
    {
        $lines = array();
        foreach ($list as $x) {
            $lines[] = "    '" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $x) . "',";
        }
        return "<?php\n/**\n * 网盘类型（后台「下载链接」下拉用）\n * 新增网盘只改本文件即可；升级包覆盖本文件也不会影响数据库与密钥配置。\n */\n\n"
            . "/* 安全守卫：本文件只应被入口引入；直接被网址请求时返回 404 —— 见 core/Guard.php。 */\n"
            . "if (is_file(__DIR__ . '/../core/Guard.php')) {\n"
            . "    require_once __DIR__ . '/../core/Guard.php';\n"
            . "    if (function_exists('ml_guard_shield')) { ml_guard_shield(__FILE__); }\n"
            . "}\n\n"
            . "return [\n"
            . implode("\n", $lines) . "\n];\n";
    }
}

if (!function_exists('mlinst_write')) {
    /** 写文件（已存在则先备份为 .bak-日期时间），返回 bool */
    function mlinst_write($file, $content)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (file_exists($file)) {
            @copy($file, $file . '.bak-' . date('Ymd-His'));
        }
        return @file_put_contents($file, $content) !== false;
    }
}

/* ================================================================== */
/* 环境自检                                                            */
/* ================================================================== */

if (!function_exists('mlinst_env_check')) {
    /**
     * 逐项检查运行环境
     * @return array 每项：label / ok(bool) / level(fail|warn|ok) / detail / fix
     */
    function mlinst_env_check($root)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $out  = array();

        $phpOk = version_compare(PHP_VERSION, '5.6.0', '>=');
        $out[] = array(
            'label'  => 'PHP 版本 ≥ 5.6',
            'ok'     => $phpOk,
            'level'  => $phpOk ? 'ok' : 'fail',
            'detail' => '当前 ' . PHP_VERSION . '（' . php_sapi_name() . '）',
            'fix'    => $phpOk ? '' : '宝塔 → 网站 → 设置 → PHP 版本，选 7.4 / 8.0 等；本程序支持 5.6 ~ 8.2',
        );

        foreach (array('pdo_mysql' => '数据库访问必需', 'curl' => '采集上游数据必需', 'json' => '接口与配置读写必需') as $ext => $why) {
            $has   = extension_loaded($ext);
            $out[] = array(
                'label'  => '扩展 ' . $ext,
                'ok'     => $has,
                'level'  => $has ? 'ok' : 'fail',
                'detail' => $has ? $why : ('缺少（' . $why . '）'),
                'fix'    => $has ? '' : '宝塔 → 网站 → 设置 → PHP → 安装扩展，勾选 ' . $ext . ' 后重试',
            );
        }

        $mb = extension_loaded('mbstring');
        $out[] = array(
            'label'  => '扩展 mbstring',
            'ok'     => true,
            'level'  => $mb ? 'ok' : 'warn',
            'detail' => $mb ? '已启用（中文标题截断更准确）' : '未启用（不影响运行，建议装上）',
            'fix'    => $mb ? '' : '宝塔 → PHP → 安装扩展 → mbstring',
        );

        $ssl = extension_loaded('openssl');
        $out[] = array(
            'label'  => '扩展 openssl',
            'ok'     => true,
            'level'  => $ssl ? 'ok' : 'warn',
            'detail' => $ssl ? '已启用（令牌随机性更好）' : '未启用（会用 md5+mt_rand 兜底，仍可用）',
            'fix'    => '',
        );

        $cfgDir = $root . '/config';
        if (!is_dir($cfgDir)) {
            @mkdir($cfgDir, 0755, true);
        }
        $cfgW = is_dir($cfgDir) && is_writable($cfgDir);
        $out[] = array(
            'label'  => 'config/ 目录可写',
            'ok'     => $cfgW,
            'level'  => $cfgW ? 'ok' : 'fail',
            'detail' => $cfgW ? '可写（安装器要在这里生成 config.php 与 install.lock）' : '不可写：' . $cfgDir,
            'fix'    => $cfgW ? '' : '宝塔 → 网站 → 设置 → 网站目录 → 权限，给站点目录 755、属主 www',
        );

        $rootW = is_writable($root);
        $out[] = array(
            'label'  => '站点根目录可写',
            'ok'     => true,
            'level'  => $rootW ? 'ok' : 'warn',
            'detail' => $rootW ? '可写' : '不可写（安装仍可完成；只是 uisc/ 需手动创建）',
            'fix'    => $rootW ? '' : '宝塔 → 网站目录权限给 www 属主；或手动创建 uisc/ 目录',
        );

        $uisc = is_dir($root . '/uisc');
        if (!$uisc && $rootW) {
            @mkdir($root . '/uisc', 0755, true);
            $uisc = is_dir($root . '/uisc');
        }
        $out[] = array(
            'label'  => 'uisc/ 静态页目录',
            'ok'     => true,
            'level'  => $uisc ? 'ok' : 'warn',
            'detail' => $uisc ? '已就绪（内页静态化输出目录）' : '尚未创建（后台「重建静态页」时自动建）',
            'fix'    => '',
        );

        return $out;
    }
}

if (!function_exists('mlinst_env_ok')) {
    /** 自检项里有没有 fail（有则不允许继续安装） */
    function mlinst_env_ok($items)
    {
        foreach ($items as $it) {
            if (!empty($it['level']) && $it['level'] === 'fail') {
                return false;
            }
        }
        return true;
    }
}

/* ================================================================== */
/* 输入清洗                                                            */
/* ================================================================== */

if (!function_exists('mlinst_normalize')) {
    /**
     * 清洗安装输入（网页与 CLI 共用）
     * @param array $in 原始输入
     * @return array 干净输入（含 adapters 数组）
     */
    function mlinst_normalize($in)
    {
        $g = function ($k, $d = '') use ($in) {
            return isset($in[$k]) && is_scalar($in[$k]) ? trim((string) $in[$k]) : $d;
        };

        $host = $g('host', '127.0.0.1');
        if ($host === '') {
            $host = '127.0.0.1';
        }
        $port = (int) $g('port', '3306');
        if ($port < 1 || $port > 65535) {
            $port = 3306;
        }

        $ads = array();
        if (isset($in['adapters']) && is_array($in['adapters'])) {
            foreach ($in['adapters'] as $a) {
                $a = strtolower(trim((string) $a));
                if (in_array($a, array('tmdb', 'rawg', 'bangumi'), true) && !in_array($a, $ads, true)) {
                    $ads[] = $a;
                }
            }
        }
        if (empty($ads)) {
            $ads = mlinst_default_adapters();
        }

        return array(
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $g('dbname'),
            'user'     => $g('user'),
            'pass'     => isset($in['pass']) ? (string) $in['pass'] : '',
            'token'    => $g('token'),
            'tmdb'     => $g('tmdb'),
            'rawg'     => $g('rawg'),
            'site_url' => rtrim($g('site_url'), '/'),
            'site_name' => $g('site_name', '媒资资料库'),
            'adapters' => $ads,
        );
    }
}

if (!function_exists('mlinst_validate')) {
    /** 校验清洗后的输入，返回错误文字（空 = 通过） */
    function mlinst_validate($in)
    {
        if ($in['dbname'] === '') {
            return '数据库名不能为空（宝塔 → 数据库 → 新建时那个库名）。';
        }
        if ($in['user'] === '') {
            return '数据库用户名不能为空。';
        }
        if ($in['token'] !== '' && strlen($in['token']) < 6) {
            return '后台管理令牌太短，请至少 6 位（留空则自动生成）。';
        }
        return '';
    }
}

/* ================================================================== */
/* 连接数据库                                                          */
/* ================================================================== */

if (!function_exists('mlinst_connect')) {
    /**
     * 建立 PDO 连接
     * @return PDO 失败时抛 Exception
     */
    function mlinst_connect($in)
    {
        $dsn = 'mysql:host=' . $in['host'] . ';port=' . (int) $in['port']
             . ';dbname=' . $in['dbname'] . ';charset=utf8mb4';
        return new PDO($dsn, $in['user'], $in['pass'], array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 6,
        ));
    }
}

if (!function_exists('mlinst_dsn_tip')) {
    /** 连接失败时给常见原因（网页与 CLI 共用同一份文案） */
    function mlinst_dsn_tip()
    {
        return array(
            '数据库名 / 用户名 / 密码写错（宝塔 → 数据库 里可改密码）',
            '数据库还没建（先在宝塔 → 数据库里新建一个）',
            'MySQL 8 + PHP 5.6 的 caching_sha2_password 认证不兼容：在宝塔 → 数据库 → 该库用户 → 改密码时选 mysql_native_password',
            '数据库地址不对（宝塔本机一般填 127.0.0.1；用了独立数据库服务器就填它的 IP）',
        );
    }
}

/* ================================================================== */
/* 一键安装                                                            */
/* ================================================================== */

if (!function_exists('mlinst_install')) {
    /**
     * 完整安装流程
     * @param string $root 站点根
     * @param array  $raw  原始输入（会被清洗）
     * @return array ok/error/steps/token/mysql/files/config_file/lock_file
     */
    function mlinst_install($root, $raw)
    {
        $root  = rtrim(str_replace('\\', '/', $root), '/');
        $steps = array();
        $res   = array(
            'ok'          => false,
            'error'       => '',
            'steps'       => array(),
            'token'       => '',
            'mysql'       => '',
            'files'       => array(),
            'config_file' => $root . '/config/config.php',
            'lock_file'   => $root . '/config/install.lock',
        );

        $in = mlinst_normalize($raw);

        /* ---- 0) 必须处于"未安装"状态（防并发 / 防绕过） ---- */
        $st = ml_install_state($root);
        if (!empty($st['installed'])) {
            $res['error'] = '本站已安装（' . $st['reason'] . '）。要重装请先删除 config/install.lock 与 config/config.php。';
            $steps[] = array('label' => '安装状态检查', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $steps[] = array('label' => '安装状态检查', 'ok' => true, 'detail' => '尚未安装，可以继续（' . $st['reason'] . '）');

        /* ---- 1) 环境自检 ---- */
        $env = mlinst_env_check($root);
        if (!mlinst_env_ok($env)) {
            $bad = array();
            foreach ($env as $it) {
                if ($it['level'] === 'fail') {
                    $bad[] = $it['label'];
                }
            }
            $res['error'] = '环境不满足：' . implode('、', $bad);
            $steps[] = array('label' => '环境自检', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $steps[] = array('label' => '环境自检', 'ok' => true, 'detail' => 'PHP ' . PHP_VERSION . '，必需扩展齐全，config/ 可写');

        /* ---- 2) 入参校验 ---- */
        $verr = mlinst_validate($in);
        if ($verr !== '') {
            $res['error'] = $verr;
            $steps[] = array('label' => '参数校验', 'ok' => false, 'detail' => $verr);
            $res['steps'] = $steps;
            return $res;
        }
        if ($in['token'] === '') {
            $in['token'] = mlinst_rand_token();
        }
        $res['token'] = $in['token'];
        if ($in['site_name'] === '') {
            $in['site_name'] = '媒资资料库';
        }
        $steps[] = array('label' => '参数校验', 'ok' => true, 'detail' => '数据库 ' . $in['user'] . '@' . $in['host'] . ':' . $in['port'] . '/' . $in['dbname']);

        /* ---- 3) 连接数据库 ---- */
        try {
            $pdo = mlinst_connect($in);
        } catch (Exception $e) {
            $res['error'] = '数据库连接失败：' . $e->getMessage();
            $steps[] = array('label' => '连接数据库', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $ver = '';
        try {
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (Exception $e) {
            $ver = '';
        }
        $res['mysql'] = $ver;
        $steps[] = array('label' => '连接数据库', 'ok' => true, 'detail' => '连接成功' . ($ver !== '' ? '，MySQL ' . $ver : ''));

        /* ---- 4) 建表 ---- */
        try {
            foreach (mlinst_tables() as $sql) {
                $pdo->exec($sql);
            }
        } catch (Exception $e) {
            $res['error'] = '建表失败：' . $e->getMessage();
            $steps[] = array('label' => '创建数据表', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $steps[] = array('label' => '创建数据表', 'ok' => true,
            'detail' => 'media_items / sync_log / link_checks / app_settings / app_users / app_requests');

        /* ---- 5) 生成 config/config.php ---- */
        $dbCfg = array(
            'host'   => $in['host'],
            'port'   => (int) $in['port'],
            'dbname' => $in['dbname'],
            'user'   => $in['user'],
            'pass'   => $in['pass'],
        );
        $cfgText = mlinst_config_content($dbCfg, $in['tmdb'], $in['rawg'], $in['token'], $in['adapters']);

        /* 站点信息带上（站点名 + 网址），省去装完再进后台填 */
        if ($in['site_name'] !== '' || $in['site_url'] !== '') {
            $cfgText = str_replace(
                "'name'      => '媒资资料库', 'name_hide' => 0, 'slogan' => '',",
                "'name'      => " . mlinst_q($in['site_name']) . ", 'name_hide' => 0, 'slogan' => '',",
                $cfgText
            );
            if ($in['site_url'] !== '') {
                $cfgText = str_replace(
                    "'logo'      => '', 'favicon' => '', 'url' => '',",
                    "'logo'      => '', 'favicon' => '', 'url' => " . mlinst_q($in['site_url']) . ",",
                    $cfgText
                );
            }
        }

        if (!mlinst_write($res['config_file'], $cfgText)) {
            $res['error'] = '写入 config/config.php 失败（目录权限不足）';
            $steps[] = array('label' => '生成 config/config.php', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        /* 写完再做一次内容抽检，避免"文件写了但内容是空的"这类诡异情况 */
        $back = (string) @file_get_contents($res['config_file']);
        if (strpos($back, '<?php') !== 0 || strpos($back, "'dbname'") === false) {
            $res['error'] = 'config/config.php 内容异常（可能磁盘已满或被安全软件改写）';
            $steps[] = array('label' => '生成 config/config.php', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $res['files'][] = 'config/config.php';
        $steps[] = array('label' => '生成 config/config.php', 'ok' => true, 'detail' => '已写入数据库配置与后台令牌');

        /* ---- 6) config/pan_types.php（已存在则不动，避免冲掉自定义网盘） ---- */
        $panFile = $root . '/config/pan_types.php';
        if (!file_exists($panFile)) {
            if (mlinst_write($panFile, mlinst_pan_content(mlinst_default_pan()))) {
                $res['files'][] = 'config/pan_types.php';
                $steps[] = array('label' => '生成 config/pan_types.php', 'ok' => true,
                    'detail' => '默认：' . implode(' / ', mlinst_default_pan()));
            } else {
                $steps[] = array('label' => '生成 config/pan_types.php', 'ok' => true,
                    'detail' => '写入失败，但后台内置了一样的默认值，不影响使用');
            }
        } else {
            $steps[] = array('label' => '生成 config/pan_types.php', 'ok' => true, 'detail' => '已存在，保持不变');
        }

        /* ---- 7) 写安装锁（写完本安装器立即失效） ---- */
        $lockTxt = "installed at " . date('c') . "\n"
                 . "via " . (php_sapi_name() === 'cli' ? 'tools/install-cli.php' : 'install.php (web)') . "\n"
                 . "version " . (defined('ML_APP_VERSION') ? ML_APP_VERSION : '') . "\n";
        if (@file_put_contents($res['lock_file'], $lockTxt) === false) {
            $res['error'] = '写入 config/install.lock 失败：安装器无法自动失效，请手动创建该文件（内容随意）';
            $steps[] = array('label' => '写入安装锁 install.lock', 'ok' => false, 'detail' => $res['error']);
            $res['steps'] = $steps;
            return $res;
        }
        $res['files'][] = 'config/install.lock';
        $steps[] = array('label' => '写入安装锁 install.lock', 'ok' => true, 'detail' => '已锁定：install.php 从此对所有人返回 404');

        /* ---- 8) 安装信息备忘（放 config/ 下，公网访问 404，比放站点根安全） ---- */
        $info = "安装时间: " . date('Y-m-d H:i:s') . "\n"
              . "站点目录: " . $root . "\n"
              . "数据库  : " . $in['user'] . '@' . $in['host'] . ':' . $in['port'] . '/' . $in['dbname'] . "\n"
              . "后台令牌: " . $in['token'] . "\n"
              . "※ 本文件含敏感信息（后台令牌），记下后建议直接删除；放在 config/ 下公网访问不到。\n";
        if (@file_put_contents($root . '/config/install-info.txt', $info) !== false) {
            $res['files'][] = 'config/install-info.txt';
        }

        /* ---- 9) 回写"站点网址"到 config，让 canonical/robots 立刻正确 ---- */
        if ($in['site_url'] !== '') {
            $steps[] = array('label' => '站点信息', 'ok' => true, 'detail' => '站点名「' . $in['site_name'] . '」，网址 ' . $in['site_url']);
        }

        $res['ok']    = true;
        $res['steps'] = $steps;
        return $res;
    }
}
