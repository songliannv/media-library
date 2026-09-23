<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
require_once __DIR__ . '/Session.php';
ml_guard_shield(__FILE__);

/* 依赖自己兜住：让本文件也能被 check.php / cron 单独引入，不依赖 index.php 的加载顺序。 */
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/Mail.php';

/**
 * 前台用户（注册 / 登录）与「资源请求」业务层
 * ==================================================================
 * 做两件事：
 *   ① 访客可以注册账号并登录（有开关、有算术验证码、有蜜罐、有 IP 限流与失败锁定）；
 *   ② 登录后可以在「资源请求」页填写想看 / 想找的资源，站长在后台逐条处理
 *      （待处理 / 已找到 / 暂无资源 / 已回复）。
 *
 * 两张表由 ensureTables() 自动创建（首次访问或保存设置时），并带「缺列自愈」：
 * 老站点覆盖升级后即使表已存在，也会把新增字段补齐，不需要手工导 SQL。
 *
 * 兼容 PHP 5.6 ~ 8.2：不用 ?? / fn() / 返回类型 / 标量类型提示 / \Throwable / ?-> / match。
 * 密码一律 password_hash() 存散列（PHP 5.5+ 自带），绝不存明文。
 */
class User
{
    /** 用户表 */
    const T_USER = 'app_users';
    /** 资源请求表 */
    const T_REQ  = 'app_requests';
    /** 密码重置令牌表（找回密码用） */
    const T_RESET = 'app_password_resets';
    /** 前台会话名（与前缀保持一致，便于自检页查看） */
    const SESS   = 'MLSESSID';

    /** 用户状态：0 禁用 / 1 正常 / 2 待审核 */
    const U_OFF  = 0;
    const U_OK   = 1;
    const U_WAIT = 2;

    /** 管理员标记：写在这个账号的 email 字段上（不占业务字段） */
    const ADMIN_TAG = 'ml-admin@local';

    /** isAdmin() 结果缓存（每请求一次） */
    private static $adminLoaded = false;
    private static $admin = false;

    /** 请求状态 → 中文（前台与后台共用一份，避免两处文案不一致） */
    public static function reqStatusText()
    {
        return array(
            'pending'  => '待处理',
            'found'    => '已找到',
            'notfound' => '暂无资源',
            'replied'  => '已回复',
            'closed'   => '已关闭',
        );
    }

    /** 用户状态 → 中文 */
    public static function userStatusText()
    {
        return array(
            self::U_OK   => '正常',
            self::U_OFF  => '已禁用',
            self::U_WAIT => '待审核',
        );
    }

    /** 一次请求 / 一天的取数上限（防止恶意刷接口） */
    const MAX_PAGE_SIZE = 100;

    /* ================================================================ */
    /* 配置（后台「站点设置 → 资源请求」控制）                            */
    /* ================================================================ */

    /** 把 `1/0/on/yes` 一律当布尔（不能直接用 empty，'0' 是合法值） */
    private static function flag($v)
    {
        if (is_bool($v)) { return $v; }
        $s = strtolower(trim((string) $v));
        return in_array($s, array('1', 'on', 'true', 'yes', 'y'), true);
    }

    /**
     * 资源请求模块的生效配置。
     * 优先级：后台 app_settings 覆盖值 > config.php 的 req 段 > 内置默认。
     * 注意 `daily` / `verify` 这类「0 是合法值」的项，一律走 Config::sub 的默认值兜底，
     * 不能用 empty() 判断 —— 否则「关闭验证码」会被当成「没设置」。
     */
    public static function cfg()
    {
        return array(
            'open'       => self::flag(Config::sub('req', 'open', 1)),        // 是否开放注册
            'logon'      => self::flag(Config::sub('req', 'logon', 1)),       // 是否必须登录才能提交（默认：注册登录后才能提交）
            'audit'      => self::flag(Config::sub('req', 'audit', 0)),       // 注册后是否需要管理员审核
            'verify'     => self::flag(Config::sub('req', 'verify', 1)),      // 注册是否出算术验证码
            'guest_view' => self::flag(Config::sub('req', 'guest_view', 1)),  // 未登录是否可浏览请求列表
            'public'     => self::flag(Config::sub('req', 'public', 1)),      // 列表是否显示他人提交的内容
            'contact'    => self::flag(Config::sub('req', 'contact', 1)),     // 是否收集联系方式
            'nav'        => self::flag(Config::sub('req', 'nav', 1)),         // 顶部导航是否显示入口
            'daily'      => max(0, (int) Config::sub('req', 'daily', 5)),     // 每人每天最多提交条数（0=不限）
            'mintitle'   => max(1, (int) Config::sub('req', 'mintitle', 2)),  // 资源名称最少字数
            'page_title' => trim((string) Config::sub('req', 'page_title', '资源请求')),
            'page_intro' => trim((string) Config::sub('req', 'page_intro', '')),
            'notice'     => trim((string) Config::sub('req', 'notice', '')),
        );
    }

    /** 页面标题（留空回退默认） */
    public static function pageTitle()
    {
        $c = self::cfg();
        return ($c['page_title'] !== '') ? $c['page_title'] : '资源请求';
    }

    /* ================================================================ */
    /* 建表（含缺列自愈）                                                 */
    /* ================================================================ */

    private static $ensured = false;
    private static $usable  = false;

    public static function ensureTables()
    {
        if (self::$ensured) { return self::$usable; }
        self::$ensured = true;

        if (!class_exists('PDO')) { self::$usable = false; return self::$usable; }

        try {
            $pdo = DB::pdo();

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::T_USER . "` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(128) NOT NULL DEFAULT '',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台注册用户'"
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::T_REQ . "` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资源请求'"
            );

            /* 老站点覆盖升级后，表已存在但可能缺列 —— 这里按列名比对后补 ALTER */
            self::syncColumns($pdo, self::T_USER, array(
                'username'   => "`username` VARCHAR(128) NOT NULL DEFAULT ''",
                'email'      => "`email` VARCHAR(128) NOT NULL DEFAULT ''",
                'status'     => "`status` TINYINT NOT NULL DEFAULT 1",
                'req_count'  => "`req_count` INT UNSIGNED NOT NULL DEFAULT 0",
                'fail_count' => "`fail_count` INT UNSIGNED NOT NULL DEFAULT 0",
                'lock_until' => "`lock_until` DATETIME DEFAULT NULL",
                'last_login' => "`last_login` DATETIME DEFAULT NULL",
            ));
            self::syncColumns($pdo, self::T_REQ, array(
                'type'       => "`type` VARCHAR(32) NOT NULL DEFAULT ''",
                'year'       => "`year` SMALLINT DEFAULT NULL",
                'contact'    => "`contact` VARCHAR(128) NOT NULL DEFAULT ''",
                'admin_note' => "`admin_note` TEXT",
                'item_id'    => "`item_id` INT UNSIGNED NOT NULL DEFAULT 0",
            ));

            /* v1.8.3 起账号一律用邮箱：老库的 username 列若还是 VARCHAR(64)，放宽到 128 容纳长邮箱 */
            try {
                $q = $pdo->query(
                    "SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS"
                    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . self::T_USER . "'"
                    . " AND COLUMN_NAME = 'username'"
                );
                $r = $q ? $q->fetch() : null;
                if ($r && (int) $r['len'] > 0 && (int) $r['len'] < 128) {
                    $pdo->exec('ALTER TABLE `' . self::T_USER . '` MODIFY `username` VARCHAR(128) NOT NULL DEFAULT \'\'');
                }
            } catch (\Exception $e) { /* 放宽失败不阻塞，超长邮箱注册时会给出明确报错 */ }

            /* 密码重置令牌（找回密码）：token 只存 sha256 散列，明文只在邮件链接里出现一次 */
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::T_RESET . "` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `token_hash` CHAR(64)     NOT NULL DEFAULT '',
  `origin`     VARCHAR(16)  NOT NULL DEFAULT 'user' COMMENT '发起入口 user/admin',
  `expires_at` DATETIME     DEFAULT NULL,
  `used_at`    DATETIME     DEFAULT NULL,
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='密码重置令牌'"
            );

            self::$usable = true;
        } catch (\Exception $e) {
            self::$usable = false;
        }
        return self::$usable;
    }

    /** 缺列补齐：SHOW COLUMNS 比对后逐列 ALTER（已存在则跳过，不产生报错噪音） */
    private static function syncColumns($pdo, $table, array $cols)
    {
        $have = array();
        try {
            foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll() as $c) {
                $have[(string) $c['Field']] = true;
            }
        } catch (\Exception $e) {
            return;
        }
        foreach ($cols as $name => $ddl) {
            if (isset($have[$name])) { continue; }
            try { $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $ddl); } catch (\Exception $e) {}
        }
    }

    /** 两张表是否都就位（供后台与自检页显示） */
    public static function tablesOk()
    {
        return self::ensureTables();
    }

    /* ================================================================ */
    /* 会话                                                              */
    /* ================================================================ */

    private static $booted = false;

    /**
     * 启动会话。
     * `$force = true` 时无条件建立会话（注册 / 登录 / 提交请求这些写操作）；
     * `$force = false` 时只有浏览器已经带着本会话的 cookie 才恢复 ——
     * 这样一个普通访客只是在首页看一眼，不会凭空多出一个会话文件。
     */
    public static function boot($force = false)
    {
        if ($force) { self::$booted = false; }
        if (self::$booted) { return self::$usable; }
        self::$booted = true;
        self::$usable = false;

        if (PHP_SAPI === 'cli') { return false; }          // 命令行（cron / 安装器）不建会话
        if (!function_exists('session_start')) { return false; }

        /* 已经有一个活跃会话（例如同一请求里被调用两次）直接复用 */
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            self::$usable = true;
            return true;
        }
        if (headers_sent()) { return false; }

        if (!$force && empty($_COOKIE[self::SESS])) { return false; }

        /* v1.8.6：统一走 core/Session.php —— 会话目录不可写时自动换目录，
           反代（宝塔 / CDN）下按 X-Forwarded-Proto 正确识别 HTTPS，
           避免 cookie 被标 Secure 后浏览器丢弃导致「登录后跳回登录页」。 */
        if (function_exists('ml_session_boot')) {
            self::$usable = ml_session_boot(self::SESS);
            return self::$usable;
        }

        /* 安全项：会话 ID 不可预测、禁止把 ID 放进 URL、cookie 拒绝 JS 读取 */
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');        // PHP 7.3+ 生效，低版本自动忽略
        @ini_set('session.gc_maxlifetime', '86400');

        $https  = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        @session_set_cookie_params(86400, '/', '', (bool) $https, true);
        @session_name(self::SESS);
        self::$usable = (bool) @session_start();
        return self::$usable;
    }

    /** 会话是否真的可用（自检页要看这个：宝塔上常见「会话目录不可写」） */
    public static function sessionUsable()
    {
        return self::boot(true);
    }

    /** 会话存储路径（自检页展示用） */
    public static function sessionPath()
    {
        $p = (string) @ini_get('session.save_path');
        if ($p === '') { return ''; }
        /* 宝塔/php 常见写法 "N;/path" 或 "N;MODE;/path" */
        if (strpos($p, ';') !== false) {
            $parts = explode(';', $p);
            $p = trim((string) end($parts));
        }
        return $p;
    }

    /** 重新生成会话 ID（登录后调用，防会话固定攻击） */
    private static function regen()
    {
        if (function_exists('session_regenerate_id') && function_exists('session_status')
            && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }

    /* ================================================================ */
    /* CSRF                                                              */
    /* ================================================================ */

    /** 取（必要时生成）当前会话的 CSRF 令牌 */
    public static function csrf()
    {
        self::boot(true);
        if (empty($_SESSION['ml_csrf'])) {
            $_SESSION['ml_csrf'] = self::rand(32);
        }
        return (string) $_SESSION['ml_csrf'];
    }

    /** 校验 CSRF：长度不同的串一律视为不匹配（恒时比较） */
    public static function csrfOk($token)
    {
        self::boot(false);
        $want = isset($_SESSION['ml_csrf']) ? (string) $_SESSION['ml_csrf'] : '';
        $got  = (is_string($token) || is_numeric($token)) ? (string) $token : '';
        if ($want === '' || $got === '') { return false; }
        return self::eq($want, $got);
    }

    /** 恒时字符串比较（PHP 5.6 没有 hash_equals，这里自己实现） */
    private static function eq($a, $b)
    {
        $a = (string) $a;
        $b = (string) $b;
        if (strlen($a) !== strlen($b)) { return false; }
        $d = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $d |= (ord($a[$i]) ^ ord($b[$i]));
        }
        return ($d === 0);
    }

    /** 随机字符串（优先 OpenSSL，回退 mt_rand） */
    private static function rand($bytes = 24)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $s = @openssl_random_pseudo_bytes($bytes);
            if (is_string($s) && strlen($s) >= $bytes) { return bin2hex($s); }
        }
        $out = '';
        for ($i = 0; $i < $bytes; $i++) { $out .= chr(mt_rand(0, 255)); }
        return bin2hex($out);
    }

    /* ================================================================ */
    /* 算术验证码（不依赖 GD，装了也能跑）                                */
    /* ================================================================ */

    /** 生成一道新的算术题，返回题面文字（答案存会话，一次有效） */
    public static function captchaNew()
    {
        self::boot(true);
        $a = mt_rand(2, 9);
        $b = mt_rand(2, 9);
        if (mt_rand(0, 1) === 0) {
            $_SESSION['ml_cap'] = (string) ($a + $b);
            $q = $a . ' + ' . $b;
        } else {
            if ($b > $a) { $t = $a; $a = $b; $b = $t; }
            $_SESSION['ml_cap'] = (string) ($a - $b);
            $q = $a . ' - ' . $b;
        }
        $_SESSION['ml_cap_at'] = time();
        return $q;
    }

    /** 校验验证码；无论对错都作废（配合每次失败重新渲染表单，体验不受影响） */
    public static function captchaCheck($value)
    {
        self::boot(false);
        $ans = isset($_SESSION['ml_cap']) ? (string) $_SESSION['ml_cap'] : '';
        $at  = isset($_SESSION['ml_cap_at']) ? (int) $_SESSION['ml_cap_at'] : 0;
        unset($_SESSION['ml_cap'], $_SESSION['ml_cap_at']);
        if ($ans === '' || (time() - $at) > 1800) { return false; }
        $v = trim((string) $value);
        if ($v === '' || !preg_match('/^-?\d+$/', $v)) { return false; }
        return ((int) $v === (int) $ans);
    }

    /* ================================================================ */
    /* 一次性提示（POST → 重定向 → GET，避免刷新重复提交）                 */
    /* ================================================================ */

    /** 写一条提示，下一次页面渲染时取走并清掉 */
    public static function flashSet($kind, $msg)
    {
        self::boot(true);
        $kind = in_array((string) $kind, array('ok', 'err', 'info'), true) ? (string) $kind : 'info';
        $_SESSION['ml_flash'] = array('kind' => $kind, 'msg' => self::clean($msg, 300));
    }

    /** 取出提示（取完即删，刷新不会重复弹） */
    public static function flashGet()
    {
        self::boot(false);
        if (empty($_SESSION['ml_flash']) || !is_array($_SESSION['ml_flash'])) { return null; }
        $f = $_SESSION['ml_flash'];
        unset($_SESSION['ml_flash']);
        if (empty($f['msg'])) { return null; }
        $kind = isset($f['kind']) ? (string) $f['kind'] : 'info';
        if (!in_array($kind, array('ok', 'err', 'info'), true)) { $kind = 'info'; }
        return array('kind' => $kind, 'msg' => (string) $f['msg']);
    }

    /* ================================================================ */
    /* 当前用户                                                          */
    /* ================================================================ */

    private static $meLoaded = false;
    private static $me       = null;

    /** 当前登录用户（未登录返回 null）。只读，不会主动建会话。 */
    public static function me()
    {
        if (self::$meLoaded) { return self::$me; }
        self::$meLoaded = true;
        self::$me = null;

        if (!self::boot(false)) { return null; }
        $uid = isset($_SESSION['ml_uid']) ? (int) $_SESSION['ml_uid'] : 0;
        if ($uid < 1) { return null; }

        $row = self::findById($uid);
        if (!$row || (int) $row['status'] !== self::U_OK) {
            /* 账号被删除或被禁用 → 当场登出，避免半个登录态 */
            unset($_SESSION['ml_uid']);
            return null;
        }
        self::$me = $row;
        return self::$me;
    }

    public static function loggedIn()
    {
        return (self::me() !== null);
    }

    /**
     * 当前登录用户是否管理员。
     * v1.7.0：后台不再用「管理令牌」卡自己，改为账号 + 密码登录；
     *   · 第一个被创建的账号（id 最小）= 管理员，即安装时那个；
     *   · 若站长把某个账号的 email 设为 ml-admin@local（或旧库里有该标记），也认。
     * 结果按请求缓存，避免每条接口都查一次库。
     */
    public static function isAdmin()
    {
        if (self::$adminLoaded) { return self::$admin; }
        self::$adminLoaded = true;
        self::$admin = false;

        $me = self::me();
        if (!$me) { return false; }

        if (isset($me['email']) && (string) $me['email'] === self::ADMIN_TAG) {
            self::$admin = true;
            return true;
        }

        if (!self::ensureTables()) { return false; }
        try {
            $st = DB::pdo()->query('SELECT MIN(`id`) AS n FROM `' . self::T_USER . '` WHERE `status`=' . self::U_OK);
            $r = $st ? $st->fetch() : null;
            if ($r && (int) $r['n'] === (int) $me['id']) { self::$admin = true; }
        } catch (\Exception $e) {
            error_log('[User::isAdmin] ' . $e->getMessage());
        }
        return self::$admin;
    }

    /** 把一个账号提升为管理员（在 email 上打标记，不依赖 id 顺序） */
    public static function markAdmin($id)
    {
        if (!self::ensureTables()) { return false; }
        try {
            $st = DB::pdo()->prepare('UPDATE `' . self::T_USER . '` SET `email`=:e WHERE `id`=:id');
            return $st->execute(array(':e' => self::ADMIN_TAG, ':id' => (int) $id));
        } catch (\Exception $e) {
            error_log('[User::markAdmin] ' . $e->getMessage());
            return false;
        }
    }

    /** 当前用户 ID（未登录 0） */
    public static function uid()
    {
        $m = self::me();
        return $m ? (int) $m['id'] : 0;
    }

    /* ================================================================ */
    /* 注册 / 登录 / 登出                                                */
    /* ================================================================ */

    public static function findById($id)
    {
        if (!self::ensureTables()) { return null; }
        try {
            $st = DB::pdo()->prepare('SELECT * FROM `' . self::T_USER . '` WHERE `id`=:id LIMIT 1');
            $st->execute(array(':id' => (int) $id));
            $r = $st->fetch();
            return $r ? $r : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function findByUsername($name)
    {
        if (!self::ensureTables()) { return null; }
        try {
            $st = DB::pdo()->prepare('SELECT * FROM `' . self::T_USER . '` WHERE `username`=:u LIMIT 1');
            $st->execute(array(':u' => (string) $name));
            $r = $st->fetch();
            return $r ? $r : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** 用户名规则：2~20 位，中文 / 字母 / 数字 / 下划线 / 减号 */
    public static function validUsername($name)
    {
        $name = trim((string) $name);
        if ($name === '') { return '请填写用户名。'; }
        $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($len < 2 || $len > 20) { return '用户名需 2 ~ 20 个字符。'; }
        $ok = @preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]+$/u', $name);
        if (!$ok) { return '用户名只能用中文、字母、数字、下划线。'; }
        return '';
    }

    /** 密码规则：6 ~ 64 位，且不能与用户名相同 */
    public static function validPassword($pass, $username = '')
    {
        $pass = (string) $pass;
        if (strlen($pass) < 6)  { return '密码至少 6 位。'; }
        if (strlen($pass) > 64) { return '密码不能超过 64 位。'; }
        if ($username !== '' && strtolower($pass) === strtolower((string) $username)) {
            return '密码不能和用户名一样。';
        }
        return '';
    }

    /** 邮箱可留空；填了就必须像邮箱 */
    public static function validEmail($mail)
    {
        $mail = trim((string) $mail);
        if ($mail === '') { return ''; }
        if (strlen($mail) > 128) { return '邮箱地址过长。'; }
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) { return '邮箱格式不正确。'; }
        return '';
    }

    /** 取访客 IP */
    public static function ip()
    {
        $keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (empty($_SERVER[$k])) { continue; }
            $v = (string) $_SERVER[$k];
            if (strpos($v, ',') !== false) {
                $parts = explode(',', $v);
                $v = trim($parts[0]);
            }
            $v = trim($v);
            if ($v !== '') { return substr($v, 0, 64); }
        }
        return '';
    }

    /**
     * 注册（v1.8.3 起账号一律用邮箱：邮箱即账号，不再填用户名/昵称）。
     * 兼容旧签名：register($email, $pass, $pass2, $captcha, $honeypot = '')
     * @return array ok / msg / uid
     */
    public static function register($email, $pass, $pass2, $captcha, $honeypot = '')
    {
        $c = self::cfg();
        if (!$c['open']) {
            return self::fail('本站暂未开放注册。');
        }
        if (!self::ensureTables()) {
            return self::fail('用户数据表创建失败：数据库用户可能缺少建表权限，请到宝塔 → 数据库 → 该库用户 → 勾选「所有权限」。');
        }
        /* 蜜罐：真人看不见这个输入框，填了就是机器人 */
        if (trim((string) $honeypot) !== '') {
            return self::fail('提交被拒绝。');
        }

        $email = trim((string) $email);
        $err   = self::validEmail($email);
        if ($err !== '') { return self::fail($err === '' ? '请填写邮箱。' : $err); }
        if ($email === '') { return self::fail('请填写邮箱。'); }
        $err = self::validPassword($pass, $email);
        if ($err !== '') { return self::fail($err); }
        if ((string) $pass !== (string) $pass2) { return self::fail('两次输入的密码不一致。'); }
        if ($c['verify'] && !self::captchaCheck($captcha)) {
            return self::fail('验证码不对，请重新计算。');
        }

        $ip = self::ip();
        /* 同 IP 一小时最多注册 3 个账号，挡住批量注册 */
        if ($ip !== '') {
            $n = self::countUsersByIp($ip, 60);
            if ($n >= 3) {
                return self::fail('同一 IP 一小时内最多注册 3 个账号，请稍后再试。');
            }
        }
        /* 邮箱即账号：username 列与 email 列都存邮箱，两处任一撞了都算重复注册 */
        if (self::findByUsername($email) !== null || self::findByEmail($email) !== null) {
            return self::fail('这个邮箱已经注册过了，直接登录或找回密码即可。');
        }

        $status = $c['audit'] ? self::U_WAIT : self::U_OK;
        $now    = date('Y-m-d H:i:s');
        try {
            $st = DB::pdo()->prepare(
                'INSERT INTO `' . self::T_USER . '`
                 (`username`,`email`,`pass_hash`,`status`,`ip`,`ua`,`created_at`)
                 VALUES (:u,:e,:p,:s,:ip,:ua,:t)'
            );
            $st->execute(array(
                ':u'  => $email,
                ':e'  => $email,
                ':p'  => password_hash((string) $pass, PASSWORD_DEFAULT),
                ':s'  => $status,
                ':ip' => $ip,
                ':ua' => substr((string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 255),
                ':t'  => $now,
            ));
            $uid = (int) DB::pdo()->lastInsertId();
        } catch (\Exception $e) {
            return self::fail('注册失败：' . $e->getMessage());
        }

        if ($status === self::U_OK) {
            self::setSession($uid);
            return array('ok' => true, 'msg' => '注册成功，已自动登录。', 'uid' => $uid);
        }
        return array('ok' => true, 'msg' => '注册成功。本站开启了注册审核，管理员通过后即可登录。', 'uid' => $uid);
    }

    /** 同 IP 在最近 N 分钟内注册了几个账号 */
    public static function countUsersByIp($ip, $minutes = 60)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) AS n FROM `' . self::T_USER . '`
                 WHERE `ip`=:ip AND `created_at` >= :t'
            );
            $st->execute(array(':ip' => (string) $ip, ':t' => date('Y-m-d H:i:s', time() - $minutes * 60)));
            $r = $st->fetch();
            return $r ? (int) $r['n'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 登录。
     * @return array ok / msg / uid
     */
    public static function login($username, $pass)
    {
        if (!self::ensureTables()) {
            return self::fail('用户数据表创建失败，请到宝塔检查数据库用户权限。');
        }
        $account = trim((string) $username);
        if ($account === '' || (string) $pass === '') {
            return self::fail('请填写邮箱和密码。');
        }

        /* v1.8.3 起账号一律是邮箱；老用户名账号也兼容：先按用户名找，再按邮箱找 */
        $row = self::findByUsername($account);
        if ($row === null) {
            $row = self::findByEmail($account);
        }
        /* 账号不存在也走一次散列计算，避免通过响应快慢判断账号是否存在 */
        if ($row === null) {
            password_verify((string) $pass, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000000');
            return self::fail('邮箱或密码不对。');
        }

        if (!empty($row['lock_until']) && strtotime((string) $row['lock_until']) > time()) {
            $left = (int) ceil((strtotime((string) $row['lock_until']) - time()) / 60);
            return self::fail('连续输错次数过多，请 ' . $left . ' 分钟后再试。');
        }

        if (!password_verify((string) $pass, (string) $row['pass_hash'])) {
            self::bumpFail((int) $row['id'], (int) $row['fail_count']);
            return self::fail('邮箱或密码不对。');
        }

        $st = (int) $row['status'];
        if ($st === self::U_WAIT) { return self::fail('账号还在等待管理员审核，通过后即可登录。'); }
        if ($st === self::U_OFF)  { return self::fail('该账号已被禁用，如有疑问请联系站长。'); }

        try {
            $s = DB::pdo()->prepare(
                'UPDATE `' . self::T_USER . '`
                 SET `fail_count`=0, `lock_until`=NULL, `last_login`=:t WHERE `id`=:id'
            );
            $s->execute(array(':t' => date('Y-m-d H:i:s'), ':id' => (int) $row['id']));
        } catch (\Exception $e) {}

        self::setSession((int) $row['id']);
        return array('ok' => true, 'msg' => '登录成功。', 'uid' => (int) $row['id']);
    }

    /** 记一次失败：满 5 次锁定 15 分钟 */
    private static function bumpFail($id, $has)
    {
        $n = $has + 1;
        try {
            if ($n >= 5) {
                $s = DB::pdo()->prepare(
                    'UPDATE `' . self::T_USER . '`
                     SET `fail_count`=0, `lock_until`=:l WHERE `id`=:id'
                );
                $s->execute(array(':l' => date('Y-m-d H:i:s', time() + 900), ':id' => (int) $id));
            } else {
                $s = DB::pdo()->prepare('UPDATE `' . self::T_USER . '` SET `fail_count`=:n WHERE `id`=:id');
                $s->execute(array(':n' => $n, ':id' => (int) $id));
            }
        } catch (\Exception $e) {}
    }

    /** 写入登录会话 */
    private static function setSession($uid)
    {
        self::boot(true);
        self::regen();
        $_SESSION['ml_uid'] = (int) $uid;
        /* 会话里换一份新的 CSRF，登录前后不共用同一个令牌 */
        $_SESSION['ml_csrf'] = self::rand(32);
        self::$meLoaded = false;
        self::$me = null;
    }

    /** 登出 */
    public static function logout()
    {
        self::boot(false);
        if (isset($_SESSION)) {
            unset($_SESSION['ml_uid'], $_SESSION['ml_csrf'], $_SESSION['ml_cap'], $_SESSION['ml_cap_at']);
        }
        self::$meLoaded = true;
        self::$me = null;
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            if (function_exists('session_regenerate_id')) { @session_regenerate_id(true); }
        }
    }

    private static function fail($msg)
    {
        return array('ok' => false, 'msg' => $msg, 'uid' => 0);
    }

    /* ================================================================ */
    /* 资源请求：前台                                                      */
    /* ================================================================ */

    /**
     * 提交一条资源请求。
     * @return array ok / msg / id
     */
    public static function createRequest(array $in, $uid)
    {
        $c = self::cfg();
        if (!self::ensureTables()) {
            return array('ok' => false, 'msg' => '数据表创建失败，请到宝塔检查数据库用户权限。', 'id' => 0);
        }
        $me = self::me();
        if ($uid > 0 && (!$me || (int) $me['id'] !== (int) $uid)) {
            return array('ok' => false, 'msg' => '登录状态已失效，请重新登录。', 'id' => 0);
        }
        /* 默认要求先注册登录；站长可在后台「站点设置 → 资源请求」里放开为游客可提交 */
        if ($c['logon'] && $uid < 1) {
            return array('ok' => false, 'msg' => '请先注册并登录，再提交资源请求。', 'id' => 0, 'need_login' => 1);
        }

        $title = self::clean(isset($in['title']) ? $in['title'] : '', 120);
        $type  = self::clean(isset($in['type']) ? $in['type'] : '', 32);
        $year  = trim((string) (isset($in['year']) ? $in['year'] : ''));
        $note  = self::clean(isset($in['note']) ? $in['note'] : '', 500);
        $cont  = self::clean(isset($in['contact']) ? $in['contact'] : '', 128);

        if ($title === '') { return array('ok' => false, 'msg' => '请填写要找的资源名称。', 'id' => 0); }
        if (self::len($title) < $c['mintitle']) {
            return array('ok' => false, 'msg' => '资源名称太短了，至少 ' . $c['mintitle'] . ' 个字。', 'id' => 0);
        }
        if ($type !== '' && !in_array($type, Categories::TYPES, true)) { $type = ''; }
        $yearN = null;
        if ($year !== '') {
            $yearN = (int) $year;
            if ($yearN < 1900 || $yearN > 2100) {
                return array('ok' => false, 'msg' => '年份请在 1900 ~ 2100 之间。', 'id' => 0);
            }
        }
        if (!$c['contact']) { $cont = ''; }
        elseif ($cont === '' && $me && !empty($me['email'])) { $cont = (string) $me['email']; }

        /* 每人每天条数上限（0 = 不限） */
        if ($c['daily'] > 0 && $uid > 0) {
            $used = self::countUserToday($uid);
            if ($used >= $c['daily']) {
                return array('ok' => false, 'msg' => '今天已经提交 ' . $used . ' 条了，每天最多 ' . $c['daily'] . ' 条，明天再来吧。', 'id' => 0);
            }
        }
        /* 同 IP 10 分钟内最多 15 条，挡住脚本刷 */
        $ip = self::ip();
        if ($ip !== '' && self::countIpRecent($ip, 10) >= 15) {
            return array('ok' => false, 'msg' => '提交太频繁了，请稍后再试。', 'id' => 0);
        }
        /* 同一个人重复提交同一条：直接提示，不写库 */
        if (self::existsSameTitle($title, $uid, $ip)) {
            return array('ok' => false, 'msg' => '你已经提交过「' . $title . '」了，站长正在处理，不用重复提交。', 'id' => 0);
        }

        $now = date('Y-m-d H:i:s');
        try {
            $st = DB::pdo()->prepare(
                'INSERT INTO `' . self::T_REQ . '`
                 (`user_id`,`username`,`title`,`type`,`year`,`note`,`contact`,`status`,`ip`,`created_at`,`updated_at`)
                 VALUES (:uid,:un,:ti,:ty,:yr,:no,:co,:st,:ip,:t,:t)'
            );
            $st->execute(array(
                ':uid' => (int) $uid,
                ':un'  => $me ? (string) $me['username'] : '',
                ':ti'  => $title,
                ':ty'  => $type,
                ':yr'  => ($yearN === null ? null : (int) $yearN),
                ':no'  => $note,
                ':co'  => $cont,
                ':st'  => 'pending',
                ':ip'  => $ip,
                ':t'   => $now,
            ));
            $id = (int) DB::pdo()->lastInsertId();
        } catch (\Exception $e) {
            return array('ok' => false, 'msg' => '提交失败：' . $e->getMessage(), 'id' => 0);
        }

        if ($uid > 0) {
            try {
                DB::pdo()->prepare('UPDATE `' . self::T_USER . '` SET `req_count`=`req_count`+1 WHERE `id`=:id')
                    ->execute(array(':id' => (int) $uid));
            } catch (\Exception $e) {}
        }
        return array('ok' => true, 'msg' => '已经提交，站长看到后会尽快处理。', 'id' => $id);
    }

    /** 今天该用户提交了几条 */
    public static function countUserToday($uid)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) AS n FROM `' . self::T_REQ . '`
                 WHERE `user_id`=:u AND `created_at` >= :t'
            );
            $st->execute(array(':u' => (int) $uid, ':t' => date('Y-m-d 00:00:00')));
            $r = $st->fetch();
            return $r ? (int) $r['n'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** 同 IP 最近 N 分钟提交了几条 */
    public static function countIpRecent($ip, $minutes = 10)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) AS n FROM `' . self::T_REQ . '`
                 WHERE `ip`=:ip AND `created_at` >= :t'
            );
            $st->execute(array(':ip' => (string) $ip, ':t' => date('Y-m-d H:i:s', time() - $minutes * 60)));
            $r = $st->fetch();
            return $r ? (int) $r['n'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** 同一个人是否已提交过同名请求（未结案的才算） */
    public static function existsSameTitle($title, $uid, $ip)
    {
        if (!self::ensureTables()) { return false; }
        try {
            if ($uid > 0) {
                $st = DB::pdo()->prepare(
                    'SELECT `id` FROM `' . self::T_REQ . '`
                     WHERE `user_id`=:u AND `title`=:t AND `status` IN (\'pending\',\'replied\') LIMIT 1'
                );
                $st->execute(array(':u' => (int) $uid, ':t' => (string) $title));
            } else {
                if ($ip === '') { return false; }
                $st = DB::pdo()->prepare(
                    'SELECT `id` FROM `' . self::T_REQ . '`
                     WHERE `ip`=:ip AND `title`=:t AND `status` IN (\'pending\',\'replied\') LIMIT 1'
                );
                $st->execute(array(':ip' => (string) $ip, ':t' => (string) $title));
            }
            return ($st->fetch() !== false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 站内是否已经有这条资源（提交前给个提示，避免重复求片）。
     * @return array 命中的条目（最多 5 条）
     */
    public static function siteMatches($title, $limit = 5)
    {
        $title = trim((string) $title);
        if ($title === '' || !self::ensureTables()) { return array(); }
        $limit = max(1, min(20, (int) $limit));
        try {
            $st = DB::pdo()->prepare(
                'SELECT `id`,`title`,`type`,`year` FROM `media_items`
                 WHERE `title` LIKE :q LIMIT ' . $limit
            );
            $st->execute(array(':q' => '%' . $title . '%'));
            $rows = $st->fetchAll();
            return is_array($rows) ? $rows : array();
        } catch (\Exception $e) {
            return array();
        }
    }

    /**
     * 请求列表。$mine 传用户 ID 时只取该用户的；$status 为空取全部。
     * @return array items / total
     */
    public static function listRequests($page, $per, $mine = 0, $status = '', $q = '')
    {
        if (!self::ensureTables()) { return array('items' => array(), 'total' => 0); }
        $per  = max(1, min(self::MAX_PAGE_SIZE, (int) $per));
        $page = max(1, (int) $page);
        $off  = ($page - 1) * $per;

        $where = array();
        $args  = array();
        if ($mine > 0) { $where[] = '`user_id`=:uid'; $args[':uid'] = (int) $mine; }
        if ($status !== '' && isset(self::reqStatusText()[$status])) {
            $where[] = '`status`=:st';
            $args[':st'] = $status;
        }
        if (trim((string) $q) !== '') {
            $where[] = '(`title` LIKE :q OR `note` LIKE :q)';
            $args[':q'] = '%' . trim((string) $q) . '%';
        }
        $w = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        try {
            $pdo = DB::pdo();
            $st = $pdo->prepare('SELECT COUNT(*) AS n FROM `' . self::T_REQ . '`' . $w);
            $st->execute($args);
            $r = $st->fetch();
            $total = $r ? (int) $r['n'] : 0;

            $st = $pdo->prepare(
                'SELECT `id`,`user_id`,`username`,`title`,`type`,`year`,`note`,`status`,`admin_note`,
                        `item_id`,`created_at`,`updated_at`
                 FROM `' . self::T_REQ . '`' . $w . '
                 ORDER BY `id` DESC LIMIT ' . $per . ' OFFSET ' . $off
            );
            $st->execute($args);
            $rows = $st->fetchAll();
            return array('items' => (is_array($rows) ? $rows : array()), 'total' => $total);
        } catch (\Exception $e) {
            return array('items' => array(), 'total' => 0);
        }
    }

    /** 用户删除自己还没被处理掉的请求 */
    public static function deleteOwnRequest($id, $uid)
    {
        if (!self::ensureTables()) { return false; }
        try {
            $st = DB::pdo()->prepare(
                'DELETE FROM `' . self::T_REQ . '` WHERE `id`=:id AND `user_id`=:u'
            );
            $st->execute(array(':id' => (int) $id, ':u' => (int) $uid));
            return ($st->rowCount() > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** 全部状态计数（前台状态条 + 后台统计共用） */
    public static function statusCounts($mine = 0)
    {
        $out = array_fill_keys(array_keys(self::reqStatusText()), 0);
        $out['total'] = 0;
        if (!self::ensureTables()) { return $out; }
        try {
            $w = ($mine > 0) ? ' WHERE `user_id`=' . (int) $mine : '';
            foreach (DB::pdo()->query('SELECT `status`,COUNT(*) AS n FROM `' . self::T_REQ . '`' . $w . ' GROUP BY `status`')->fetchAll() as $r) {
                $k = (string) $r['status'];
                if (isset($out[$k])) { $out[$k] = (int) $r['n']; }
                $out['total'] += (int) $r['n'];
            }
        } catch (\Exception $e) {
            error_log('[User::statusCounts] ' . $e->getMessage());
        }
        return $out;
    }

    /* ================================================================ */
    /* 资源请求：后台                                                    */
    /* ================================================================ */

    /** 后台改状态 / 写回复 */
    public static function saveRequest($id, $status, $note)
    {
        $map = self::reqStatusText();
        if (!isset($map[(string) $status])) { return '状态取值不合法。'; }
        if (!self::ensureTables()) { return '数据表不可用。'; }
        try {
            $st = DB::pdo()->prepare(
                'UPDATE `' . self::T_REQ . '`
                 SET `status`=:st, `admin_note`=:no, `updated_at`=:t WHERE `id`=:id'
            );
            $st->execute(array(
                ':st' => (string) $status,
                ':no' => self::clean($note, 1000),
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => (int) $id,
            ));
            return '';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public static function deleteRequest($id)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $st = DB::pdo()->prepare('DELETE FROM `' . self::T_REQ . '` WHERE `id`=:id');
            $st->execute(array(':id' => (int) $id));
            return $st->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** 批量删除（后台勾选后一次删掉） */
    public static function deleteRequests(array $ids)
    {
        $n = 0;
        foreach ($ids as $id) { $n += self::deleteRequest($id); }
        return $n;
    }

    /** 后台总览数字 */
    public static function stats()
    {
        $out = array('users' => 0, 'users_wait' => 0, 'reqs' => 0, 'today' => 0);
        if (!self::ensureTables()) { return $out; }
        try {
            $pdo = DB::pdo();
            foreach (self::statusCounts(0) as $k => $v) { $out['req_' . $k] = $v; }
            $r = $pdo->query('SELECT COUNT(*) AS n FROM `' . self::T_USER . '`')->fetch();
            $out['users'] = $r ? (int) $r['n'] : 0;
            $r = $pdo->query('SELECT COUNT(*) AS n FROM `' . self::T_USER . '` WHERE `status`=2')->fetch();
            $out['users_wait'] = $r ? (int) $r['n'] : 0;
            $out['reqs'] = isset($out['req_total']) ? (int) $out['req_total'] : 0;
            $st = $pdo->prepare('SELECT COUNT(*) AS n FROM `' . self::T_REQ . '` WHERE `created_at` >= :t');
            $st->execute(array(':t' => date('Y-m-d 00:00:00')));
            $r = $st->fetch();
            $out['today'] = $r ? (int) $r['n'] : 0;
        } catch (\Exception $e) {
            // 记录错误但不暴露给前端（避免泄露数据库结构信息）
            error_log('[User::stats] ' . $e->getMessage());
        }
        return $out;
    }

    /* ================================================================ */
    /* 用户管理（后台）                                                  */
    /* ================================================================ */

    /** @return array items / total */
    public static function listUsers($page, $per, $q = '', $status = '')
    {
        if (!self::ensureTables()) { return array('items' => array(), 'total' => 0); }
        $per  = max(1, min(self::MAX_PAGE_SIZE, (int) $per));
        $page = max(1, (int) $page);
        $off  = ($page - 1) * $per;

        $where = array();
        $args  = array();
        if (trim((string) $q) !== '') {
            $where[] = '(`username` LIKE :q OR `email` LIKE :q)';
            $args[':q'] = '%' . trim((string) $q) . '%';
        }
        if ($status !== '' && preg_match('/^\d+$/', (string) $status)) {
            $where[] = '`status`=:st';
            $args[':st'] = (int) $status;
        }
        $w = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        try {
            $pdo = DB::pdo();
            $st = $pdo->prepare('SELECT COUNT(*) AS n FROM `' . self::T_USER . '`' . $w);
            $st->execute($args);
            $r = $st->fetch();
            $total = $r ? (int) $r['n'] : 0;

            $st = $pdo->prepare(
                'SELECT `id`,`username`,`email`,`status`,`ip`,`req_count`,`created_at`,`last_login`
                 FROM `' . self::T_USER . '`' . $w . '
                 ORDER BY `id` DESC LIMIT ' . $per . ' OFFSET ' . $off
            );
            $st->execute($args);
            $rows = $st->fetchAll();
            return array('items' => (is_array($rows) ? $rows : array()), 'total' => $total);
        } catch (\Exception $e) {
            return array('items' => array(), 'total' => 0);
        }
    }

    /** 后台改用户状态（启用 / 禁用 / 待审核） */
    public static function saveUser($id, $status)
    {
        $allow = array(self::U_OK, self::U_OFF, self::U_WAIT);
        $s = (int) $status;
        if (!in_array($s, $allow, true)) { return '状态取值不合法。'; }
        if (!self::ensureTables()) { return '数据表不可用。'; }
        try {
            $st = DB::pdo()->prepare(
                'UPDATE `' . self::T_USER . '` SET `status`=:s, `fail_count`=0, `lock_until`=NULL WHERE `id`=:id'
            );
            $st->execute(array(':s' => $s, ':id' => (int) $id));
            return '';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /** 后台重置密码（留空则不改） */
    public static function resetPassword($id, $newPass)
    {
        $newPass = (string) $newPass;
        if ($newPass === '') { return ''; }
        if (strlen($newPass) < 6) { return '新密码至少 6 位。'; }
        if (!self::ensureTables()) { return '数据表不可用。'; }
        try {
            $st = DB::pdo()->prepare(
                'UPDATE `' . self::T_USER . '` SET `pass_hash`=:p, `fail_count`=0, `lock_until`=NULL WHERE `id`=:id'
            );
            $st->execute(array(':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => (int) $id));
            return '';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /* ================================================================ */
    /* 修改密码 / 找回密码（邮件重置）                                     */
    /* ================================================================ */

    /** 按邮箱找账号（邮箱未强制唯一，取最早的一个） */
    public static function findByEmail($email)
    {
        if (!self::ensureTables()) { return null; }
        $email = trim((string) $email);
        if ($email === '') { return null; }
        try {
            $st = DB::pdo()->prepare('SELECT * FROM `' . self::T_USER . '` WHERE `email`=:e ORDER BY `id` ASC LIMIT 1');
            $st->execute(array(':e' => $email));
            $r = $st->fetch();
            return $r ? $r : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 登录后修改自己的密码：需校验当前密码。
     * @return array ok / msg
     */
    public static function changePassword($uid, $oldPass, $newPass, $newPass2)
    {
        $row = self::findById($uid);
        if (!$row) { return self::fail('登录状态已失效，请重新登录。'); }
        if (!password_verify((string) $oldPass, (string) $row['pass_hash'])) {
            return self::fail('当前密码不正确。');
        }
        $err = self::validPassword($newPass, (string) $row['username']);
        if ($err !== '') { return self::fail($err); }
        if ((string) $newPass !== (string) $newPass2) { return self::fail('两次输入的新密码不一致。'); }
        if ((string) $newPass === (string) $oldPass) { return self::fail('新密码不能和当前密码相同。'); }
        try {
            $st = DB::pdo()->prepare(
                'UPDATE `' . self::T_USER . '` SET `pass_hash`=:p, `fail_count`=0, `lock_until`=NULL WHERE `id`=:id'
            );
            $st->execute(array(':p' => password_hash((string) $newPass, PASSWORD_DEFAULT), ':id' => (int) $uid));
        } catch (\Exception $e) {
            return self::fail('修改失败：' . $e->getMessage());
        }
        return array('ok' => true, 'msg' => '密码已更新，请用新密码登录。', 'uid' => (int) $uid);
    }

    /** 保存 / 修改账号邮箱（找回密码用；留空 = 清除绑定） */
    public static function saveEmail($uid, $email)
    {
        $row = self::findById($uid);
        if (!$row) { return self::fail('登录状态已失效，请重新登录。'); }
        $email = trim((string) $email);
        $err = self::validEmail($email);
        if ($err !== '') { return self::fail($err); }
        try {
            $st = DB::pdo()->prepare('UPDATE `' . self::T_USER . '` SET `email`=:e WHERE `id`=:id');
            $st->execute(array(':e' => $email, ':id' => (int) $uid));
        } catch (\Exception $e) {
            return self::fail('保存失败：' . $e->getMessage());
        }
        return array('ok' => true, 'msg' => '邮箱已保存。', 'uid' => (int) $uid);
    }

    /**
     * 发起找回密码：生成一次性令牌，并把重置链接发到账号邮箱。
     * @param string $account 用户名或邮箱
     * @param string $origin  'user'（前台）/ 'admin'（后台）—— 决定邮件里链接指向哪个重置页
     * @return array ok / msg
     */
    public static function requestReset($account, $origin = 'user')
    {
        $origin = ($origin === 'admin') ? 'admin' : 'user';
        if (!self::ensureTables()) { return self::fail('数据表不可用，请到宝塔检查数据库用户权限。'); }
        $account = trim((string) $account);
        if ($account === '') { return self::fail('请输入用户名或邮箱。'); }

        /* 前台按「不暴露账号是否存在」处理：查不到也给同样的提示 */
        $generic = '如果该账号存在且已绑定邮箱，重置链接已经发到它的邮箱，请查收（也看看垃圾箱）。';
        $row = self::findByUsername($account);
        if ($row === null) { $row = self::findByEmail($account); }

        if ($row === null || (int) $row['status'] !== self::U_OK) {
            return array('ok' => true, 'msg' => $generic);
        }

        $email = trim((string) $row['email']);
        /* 管理员占位邮箱（ml-admin@local）不算「已绑定」 */
        $bound = ($email !== '' && $email !== self::ADMIN_TAG);
        if (!$bound) {
            if ($origin === 'admin') {
                return self::fail('这个账号还没有绑定邮箱。请先用账号密码登录，在「修改密码」里填写邮箱后再找回。');
            }
            return array('ok' => true, 'msg' => $generic);
        }

        /* 限流：同一账号 60 秒内只发一封；同一 IP 1 小时最多 10 封 */
        if (self::lastResetAgo((int) $row['id']) < 60) {
            return self::fail('刚发过一封，请 1 分钟后再试。');
        }
        $ip = self::ip();
        if ($ip !== '' && self::countResetsByIp($ip, 60) >= 10) {
            return self::fail('请求太频繁了，请稍后再试。');
        }

        $raw  = self::rand(32);                       // 64 位十六进制明文令牌（只出现在邮件里）
        $hash = hash('sha256', $raw);
        $now  = date('Y-m-d H:i:s');
        $exp  = date('Y-m-d H:i:s', time() + 3600);   // 1 小时有效
        try {
            $pdo = DB::pdo();
            /* 该账号旧的未用令牌一并作废 */
            $pdo->prepare('UPDATE `' . self::T_RESET . '` SET `used_at`=:n WHERE `user_id`=:u AND `used_at` IS NULL')
                ->execute(array(':n' => $now, ':u' => (int) $row['id']));
            $st = $pdo->prepare(
                'INSERT INTO `' . self::T_RESET . '`
                 (`user_id`,`token_hash`,`origin`,`expires_at`,`ip`,`created_at`)
                 VALUES (:u,:h,:o,:e,:ip,:t)'
            );
            $st->execute(array(
                ':u' => (int) $row['id'], ':h' => $hash, ':o' => $origin,
                ':e' => $exp, ':ip' => $ip, ':t' => $now,
            ));
        } catch (\Exception $e) {
            return self::fail('生成重置链接失败：' . $e->getMessage());
        }

        $link = self::resetLink($raw, $origin);
        $site = (string) Config::sub('site', 'name', '媒资资料库');
        $tpl  = Mail::resetTpl($site, (string) $row['username'], $link, 60);
        $r    = Mail::send($email, '【' . $site . '】找回密码', $tpl['html'], $tpl['text']);
        if (empty($r['ok'])) {
            return self::fail($r['msg']);
        }
        return array('ok' => true, 'msg' => '重置链接已发送，请到邮箱查收（1 小时内有效）。');
    }

    /** 校验重置令牌，返回令牌行（含 user_id）或 null */
    public static function verifyResetToken($token)
    {
        if (!self::ensureTables()) { return null; }
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[0-9a-f]{64}$/', $token)) { return null; }
        try {
            $st = DB::pdo()->prepare(
                'SELECT * FROM `' . self::T_RESET . '`
                 WHERE `token_hash`=:h AND `used_at` IS NULL AND `expires_at` > :n LIMIT 1'
            );
            $st->execute(array(':h' => hash('sha256', $token), ':n' => date('Y-m-d H:i:s')));
            $r = $st->fetch();
            return $r ? $r : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 用令牌设置新密码（一次性）。
     * @return array ok / msg
     */
    public static function doReset($token, $newPass, $newPass2)
    {
        $row = self::verifyResetToken($token);
        if (!$row) { return self::fail('链接无效或已过期，请重新发起找回密码。'); }
        $user = self::findById((int) $row['user_id']);
        if (!$user) { return self::fail('账号不存在。'); }

        $err = self::validPassword($newPass, (string) $user['username']);
        if ($err !== '') { return self::fail($err); }
        if ((string) $newPass !== (string) $newPass2) { return self::fail('两次输入的新密码不一致。'); }

        try {
            $pdo = DB::pdo();
            $pdo->prepare(
                'UPDATE `' . self::T_USER . '` SET `pass_hash`=:p, `fail_count`=0, `lock_until`=NULL WHERE `id`=:id'
            )->execute(array(':p' => password_hash((string) $newPass, PASSWORD_DEFAULT), ':id' => (int) $user['id']));
            $pdo->prepare('UPDATE `' . self::T_RESET . '` SET `used_at`=:n WHERE `id`=:id')
                ->execute(array(':n' => date('Y-m-d H:i:s'), ':id' => (int) $row['id']));
        } catch (\Exception $e) {
            return self::fail('重置失败：' . $e->getMessage());
        }
        return array('ok' => true, 'msg' => '密码已重置，请用新密码登录。', 'uid' => (int) $user['id']);
    }

    /** 该账号最近一次发起找回距今多少秒（无记录返回一个很大的数） */
    public static function lastResetAgo($uid)
    {
        if (!self::ensureTables()) { return 999999; }
        try {
            $st = DB::pdo()->prepare('SELECT `created_at` FROM `' . self::T_RESET . '` WHERE `user_id`=:u ORDER BY `id` DESC LIMIT 1');
            $st->execute(array(':u' => (int) $uid));
            $r = $st->fetch();
            if (!$r || empty($r['created_at'])) { return 999999; }
            return max(0, time() - strtotime((string) $r['created_at']));
        } catch (\Exception $e) {
            return 999999;
        }
    }

    /** 同 IP 最近 N 分钟发起了几次找回 */
    public static function countResetsByIp($ip, $minutes = 60)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $st = DB::pdo()->prepare('SELECT COUNT(*) AS n FROM `' . self::T_RESET . '` WHERE `ip`=:ip AND `created_at` >= :t');
            $st->execute(array(':ip' => (string) $ip, ':t' => date('Y-m-d H:i:s', time() - $minutes * 60)));
            $r = $st->fetch();
            return $r ? (int) $r['n'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** 生成重置链接（按入口区分路径：后台走 /admin/?reset=，前台走 /user/reset?token=） */
    private static function resetLink($raw, $origin)
    {
        $base = self::siteUrl();
        if ($origin === 'admin') {
            $dir = trim((string) Config::sub('admin', 'dir', 'admin'));
            if ($dir === '' || strpos($dir, 'admin') !== strlen($dir) - 5) { $dir = 'admin'; }
            return $base . '/' . $dir . '/?reset=' . urlencode($raw);
        }
        return $base . '/user/reset?token=' . urlencode($raw);
    }

    /** 站点根地址：优先 config 里的 site.url，否则按当前请求推断 */
    private static function siteUrl()
    {
        $u = trim((string) Config::sub('site', 'url', ''));
        if ($u !== '') { return rtrim($u, '/'); }
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
        return ($https ? 'https://' : 'http://') . $host;
    }

    public static function deleteUser($id)
    {
        if (!self::ensureTables()) { return 0; }
        try {
            $pdo = DB::pdo();
            $st = $pdo->prepare('DELETE FROM `' . self::T_USER . '` WHERE `id`=:id');
            $st->execute(array(':id' => (int) $id));
            $n = $st->rowCount();
            /* 该用户的请求保留（标注用户名），只把 user_id 断开，站长还能看到历史记录 */
            $pdo->prepare('UPDATE `' . self::T_REQ . '` SET `user_id`=0 WHERE `user_id`=:id')
                ->execute(array(':id' => (int) $id));
            return $n;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /* ================================================================ */
    /* 小工具                                                            */
    /* ================================================================ */

    /** 去掉控制字符、多余空白并限长（防注入没必要，参数全走预处理；这里只为显示干净） */
    public static function clean($s, $max = 255)
    {
        $s = (string) $s;
        $s = str_replace(array("\r\n", "\r"), "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        $s = trim($s);
        if (self::len($s) > $max) {
            $s = function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
        }
        return $s;
    }

    /** 兼容没装 mbstring 的环境 */
    public static function len($s)
    {
        $s = (string) $s;
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    /** 页面展示用：把换行变成 <br> 并转义 */
    public static function text($s)
    {
        return nl2br(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
    }

    /** 状态 → 用于 CSS 的小写英文（前台徽章配色） */
    public static function statusClass($s)
    {
        $s = (string) $s;
        return in_array($s, array('pending', 'found', 'notfound', 'replied', 'closed'), true) ? $s : 'pending';
    }
}
