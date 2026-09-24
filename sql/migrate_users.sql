-- ==================================================================
-- 媒资资料库 · 注册用户与资源请求（v1.6.2 新增）
-- ------------------------------------------------------------------
-- ★ 一般不需要手工执行本文件：
--   程序在首次打开 /user/register 或保存「资源请求」设置时会自动建表，
--   覆盖升级后缺列也会自动 ALTER 补齐。
--   这里提供 SQL 只是为了「数据库用户没有建表权限」或想提前建好的场景。
--   在宝塔 phpMyAdmin 里选中站点所用的库后执行即可。
-- 兼容 MySQL 5.6+，表名与核心代码 core/User.php 的常量保持一致。
-- ==================================================================

CREATE TABLE IF NOT EXISTS `app_users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(128) NOT NULL DEFAULT '' COMMENT '登录名（v1.8.3 起即注册邮箱），全站唯一',
  `email`      VARCHAR(128) NOT NULL DEFAULT '' COMMENT '账号邮箱，与 username 一致',
  `pass_hash`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'password_hash() 产生的散列，绝不存明文',
  `status`     TINYINT      NOT NULL DEFAULT 1  COMMENT '1=正常 0=禁用 2=待审核',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '注册来源 IP，用于「同 IP 一小时最多 3 个」限流',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `req_count`  INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '累计提交过的资源请求条数',
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '连续登录失败次数，满 5 次锁定 15 分钟',
  `lock_until` DATETIME     DEFAULT NULL        COMMENT '解封时间',
  `created_at` DATETIME     DEFAULT NULL,
  `last_login` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台注册用户';

CREATE TABLE IF NOT EXISTS `app_requests` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '提交者；用户被删除后置 0，记录仍保留',
  `username`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '提交时的用户名快照',
  `title`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '要找的资源名称',
  `type`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'short/movie/tv/anime/variety/game，空=不限',
  `year`       SMALLINT     DEFAULT NULL,
  `note`       TEXT                             COMMENT '补充说明',
  `contact`    VARCHAR(128) NOT NULL DEFAULT '' COMMENT '联系方式（后台可关掉收集）',
  `status`     VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending 待处理 / found 已找到 / notfound 暂无资源 / replied 已回复 / closed 已关闭',
  `admin_note` TEXT                             COMMENT '站长回复，只有提交者本人能看到',
  `item_id`    INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '站内已收录条目的 id，填了前台会给出详情页链接',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '提交来源 IP，用于「同 IP 十分钟最多 15 条」限流',
  `created_at` DATETIME     DEFAULT NULL,
  `updated_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_title` (`title`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资源请求';
