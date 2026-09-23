<?php
namespace Core;

/* 安全守卫：本文件只应被入口引入；直接用网址访问会 404。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * API 调用令牌管理（v1.7.0）
 *
 * 用途：把资料库 API 开放给外部系统/个人调用时，给每个调用者发一个独立令牌，
 *       以便控制调用量、限制来源 IP、并统计「谁在什么时候调用了什么」。
 *
 * 注意：这与后台登录令牌（config.php 的 admin.token）是两回事 ——
 *       后者仅用于站长自己进入后台管理，前者是发给外部调用者的。
 *
 * 两张表：
 *   app_api_tokens  令牌本身（名称 / 值 / 状态 / 每日限额 / IP 白名单 / 用量）
 *   app_api_logs    每次调用的流水（令牌 / 接口 / IP / UA / 耗时 / 时间）
 */
class ApiToken
{
    const TABLE     = 'app_api_tokens';
    const LOG_TABLE = 'app_api_logs';

    /** 表是否已就绪（首次访问自动建表，免手工导 SQL） */
    private static $ready = false;

    public static function ensureTables()
    {
        if (self::$ready) { return true; }
        try {
            $pdo = DB::pdo();

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::TABLE . "` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '令牌名称/用途说明',
  `token`        VARCHAR(128) NOT NULL DEFAULT '' COMMENT '令牌值',
  `user_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联注册用户，0=未关联',
  `status`       TINYINT      NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `daily_limit`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日调用上限，0=不限',
  `ip_whitelist` TEXT COMMENT 'IP 白名单，逗号分隔，留空=不限',
  `usage_count`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计调用次数',
  `last_used`    DATETIME     DEFAULT NULL COMMENT '最后调用时间',
  `created_at`   DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 调用令牌'"
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::LOG_TABLE . "` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id`      INT UNSIGNED  NOT NULL DEFAULT 0,
  `token_name`    VARCHAR(64)   NOT NULL DEFAULT '',
  `endpoint`      VARCHAR(128)  NOT NULL DEFAULT '',
  `method`        VARCHAR(10)   NOT NULL DEFAULT 'GET',
  `params`        TEXT,
  `ip`            VARCHAR(64)   NOT NULL DEFAULT '',
  `ua`            VARCHAR(255)  NOT NULL DEFAULT '',
  `response_code` SMALLINT      NOT NULL DEFAULT 200,
  `cost_ms`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`    DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_id`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 调用日志'"
            );

            self::$ready = true;
            return true;
        } catch (\Exception $e) {
            error_log('[ApiToken::ensureTables] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 校验令牌。
     * @param string $token
     * @return array|null  通过返回令牌行，否则 null
     */
    public static function verify($token)
    {
        $token = trim((string) $token);
        if ($token === '') { return null; }
        if (!self::ensureTables()) { return null; }

        try {
            $pdo  = DB::pdo();
            $stmt = $pdo->prepare('SELECT * FROM `' . self::TABLE . '` WHERE `token` = :token LIMIT 1');
            $stmt->execute(array(':token' => $token));
            $row = $stmt->fetch();

            if (!$row || (int) $row['status'] !== 1) { return null; }

            // IP 白名单
            if (isset($row['ip_whitelist']) && trim((string) $row['ip_whitelist']) !== '') {
                $allow = array();
                foreach (explode(',', $row['ip_whitelist']) as $one) {
                    $one = trim($one);
                    if ($one !== '') { $allow[] = $one; }
                }
                $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
                if (!empty($allow) && !in_array($ip, $allow, true)) { return null; }
            }

            // 每日限额
            if ((int) $row['daily_limit'] > 0) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS n FROM `' . self::LOG_TABLE . '`
                      WHERE `token_id` = :tid AND DATE(`created_at`) = CURDATE()'
                );
                $stmt->execute(array(':tid' => (int) $row['id']));
                $used = $stmt->fetch();
                if ($used && (int) $used['n'] >= (int) $row['daily_limit']) { return null; }
            }

            return $row;
        } catch (\Exception $e) {
            error_log('[ApiToken::verify] ' . $e->getMessage());
            return null;
        }
    }

    /** 记录一次调用并累计用量 */
    public static function log($tokenId, $tokenName, $endpoint, $method = 'GET', $params = array(), $responseCode = 200, $costMs = 0)
    {
        if (!self::ensureTables()) { return; }
        try {
            $pdo  = DB::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::LOG_TABLE . '`
                   (`token_id`,`token_name`,`endpoint`,`method`,`params`,`ip`,`ua`,`response_code`,`cost_ms`,`created_at`)
                 VALUES (:tid,:tname,:ep,:m,:p,:ip,:ua,:rc,:cost,NOW())'
            );
            $stmt->execute(array(
                ':tid'   => (int) $tokenId,
                ':tname' => (string) $tokenName,
                ':ep'    => (string) $endpoint,
                ':m'     => (string) $method,
                ':p'     => is_array($params) && !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
                ':ip'    => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                ':ua'    => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                ':rc'    => (int) $responseCode,
                ':cost'  => (int) $costMs,
            ));

            if ((int) $tokenId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::TABLE . '`
                        SET `usage_count` = `usage_count` + 1, `last_used` = NOW()
                      WHERE `id` = :id'
                );
                $stmt->execute(array(':id' => (int) $tokenId));
            }
        } catch (\Exception $e) {
            error_log('[ApiToken::log] ' . $e->getMessage());
        }
    }

    /**
     * 新建令牌。
     * @return string|null  成功返回令牌明文，失败 null
     */
    public static function create($name, $userId = 0, $dailyLimit = 0, $ipWhitelist = '')
    {
        if (!self::ensureTables()) { return null; }
        try {
            $token = self::randToken();

            $pdo  = DB::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::TABLE . '`
                   (`name`,`token`,`user_id`,`status`,`daily_limit`,`ip_whitelist`,`created_at`)
                 VALUES (:n,:t,:u,1,:lim,:ip,NOW())'
            );
            $stmt->execute(array(
                ':n'   => (string) $name,
                ':t'   => $token,
                ':u'   => (int) $userId,
                ':lim' => (int) $dailyLimit,
                ':ip'  => (string) $ipWhitelist,
            ));

            return $token;
        } catch (\Exception $e) {
            error_log('[ApiToken::create] ' . $e->getMessage());
            return null;
        }
    }

    /** 随机令牌：优先 OpenSSL，回退 mt_rand（兼容 PHP 5.6，不用 random_bytes） */
    private static function randToken()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes(24);
            if ($b !== false && strlen($b) === 24) { return bin2hex($b); }
        }
        $s = '';
        for ($i = 0; $i < 48; $i++) { $s .= dechex(mt_rand(0, 15)); }
        return $s;
    }

    /** 令牌列表 */
    public static function listTokens($page = 1, $pageSize = 20, $status = null)
    {
        $out = array('total' => 0, 'page' => (int) $page, 'items' => array());
        if (!self::ensureTables()) { return $out; }

        try {
            $pdo   = DB::pdo();
            $page  = max(1, (int) $page);
            $size  = max(1, (int) $pageSize);
            $off   = ($page - 1) * $size;
            $where = '1=1';
            $bind  = array();

            if ($status !== null) {
                $where .= ' AND `status` = :st';
                $bind[':st'] = (int) $status;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM `' . self::TABLE . '` WHERE ' . $where);
            $stmt->execute($bind);
            $row = $stmt->fetch();
            $out['total'] = $row ? (int) $row['n'] : 0;

            $sql  = 'SELECT * FROM `' . self::TABLE . '` WHERE ' . $where . ' ORDER BY `id` DESC LIMIT ' . $off . ',' . $size;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $out['items'] = $stmt->fetchAll();

            return $out;
        } catch (\Exception $e) {
            error_log('[ApiToken::listTokens] ' . $e->getMessage());
            return $out;
        }
    }

    /** 更新令牌（只改传进来的字段） */
    public static function update($id, $data)
    {
        if (!self::ensureTables()) { return false; }
        try {
            $sets = array();
            $bind = array(':id' => (int) $id);

            if (isset($data['name']) && trim((string) $data['name']) !== '') {
                $sets[] = '`name` = :n';  $bind[':n'] = trim((string) $data['name']);
            }
            if (isset($data['status'])) {
                $sets[] = '`status` = :st';  $bind[':st'] = (int) $data['status'] ? 1 : 0;
            }
            if (isset($data['daily_limit'])) {
                $sets[] = '`daily_limit` = :lim';  $bind[':lim'] = (int) $data['daily_limit'];
            }
            if (isset($data['ip_whitelist'])) {
                $sets[] = '`ip_whitelist` = :ip';  $bind[':ip'] = (string) $data['ip_whitelist'];
            }
            if (empty($sets)) { return false; }

            $stmt = $pdo = null;
            $stmt = DB::pdo()->prepare('UPDATE `' . self::TABLE . '` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
            return $stmt->execute($bind);
        } catch (\Exception $e) {
            error_log('[ApiToken::update] ' . $e->getMessage());
            return false;
        }
    }

    /** 删除令牌（同时清掉它的日志） */
    public static function delete($id)
    {
        if (!self::ensureTables()) { return false; }
        try {
            $pdo = DB::pdo();
            $pdo->prepare('DELETE FROM `' . self::LOG_TABLE . '` WHERE `token_id` = :id')->execute(array(':id' => (int) $id));
            return $pdo->prepare('DELETE FROM `' . self::TABLE . '` WHERE `id` = :id')->execute(array(':id' => (int) $id));
        } catch (\Exception $e) {
            error_log('[ApiToken::delete] ' . $e->getMessage());
            return false;
        }
    }

    /** 调用日志（可按令牌 / 日期段过滤） */
    public static function getLogs($tokenId = null, $startDate = null, $endDate = null, $page = 1, $pageSize = 50)
    {
        $out = array('total' => 0, 'page' => (int) $page, 'items' => array());
        if (!self::ensureTables()) { return $out; }

        try {
            $pdo   = DB::pdo();
            $page  = max(1, (int) $page);
            $size  = max(1, (int) $pageSize);
            $off   = ($page - 1) * $size;
            $where = '1=1';
            $bind  = array();

            if ($tokenId) {
                $where .= ' AND `token_id` = :tid';  $bind[':tid'] = (int) $tokenId;
            }
            if ($startDate) {
                $where .= ' AND `created_at` >= :sd';  $bind[':sd'] = $startDate . ' 00:00:00';
            }
            if ($endDate) {
                $where .= ' AND `created_at` <= :ed';  $bind[':ed'] = $endDate . ' 23:59:59';
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM `' . self::LOG_TABLE . '` WHERE ' . $where);
            $stmt->execute($bind);
            $row = $stmt->fetch();
            $out['total'] = $row ? (int) $row['n'] : 0;

            $sql  = 'SELECT * FROM `' . self::LOG_TABLE . '` WHERE ' . $where . ' ORDER BY `id` DESC LIMIT ' . $off . ',' . $size;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $out['items'] = $stmt->fetchAll();

            return $out;
        } catch (\Exception $e) {
            error_log('[ApiToken::getLogs] ' . $e->getMessage());
            return $out;
        }
    }

    /** 概览数字 */
    public static function getStats()
    {
        $out = array('total_tokens' => 0, 'active_tokens' => 0, 'today_calls' => 0, 'yesterday_calls' => 0, 'total_calls' => 0);
        if (!self::ensureTables()) { return $out; }

        try {
            $pdo = DB::pdo();
            foreach (array(
                'total_tokens'    => 'SELECT COUNT(*) AS n FROM `' . self::TABLE . '`',
                'active_tokens'   => 'SELECT COUNT(*) AS n FROM `' . self::TABLE . '` WHERE `status`=1',
                'today_calls'     => 'SELECT COUNT(*) AS n FROM `' . self::LOG_TABLE . '` WHERE DATE(`created_at`)=CURDATE()',
                'yesterday_calls' => 'SELECT COUNT(*) AS n FROM `' . self::LOG_TABLE . '` WHERE DATE(`created_at`)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)',
                'total_calls'     => 'SELECT COUNT(*) AS n FROM `' . self::LOG_TABLE . '`',
            ) as $k => $sql) {
                $r = $pdo->query($sql)->fetch();
                $out[$k] = $r ? (int) $r['n'] : 0;
            }
            return $out;
        } catch (\Exception $e) {
            error_log('[ApiToken::getStats] ' . $e->getMessage());
            return $out;
        }
    }

    /**
     * 按令牌汇总最近用量（后台列表页显示）
     * @return array  token_id => 调用次数
     */
    public static function todayUsageMap()
    {
        $out = array();
        if (!self::ensureTables()) { return $out; }
        try {
            $stmt = DB::pdo()->query(
                'SELECT `token_id`, COUNT(*) AS n FROM `' . self::LOG_TABLE . '`
                  WHERE DATE(`created_at`) = CURDATE() GROUP BY `token_id`'
            );
            foreach ($stmt->fetchAll() as $r) { $out[(int) $r['token_id']] = (int) $r['n']; }
        } catch (\Exception $e) {
            error_log('[ApiToken::todayUsageMap] ' . $e->getMessage());
        }
        return $out;
    }
}
