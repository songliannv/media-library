-- 媒资资料库 API - 建表脚本
-- 兼容 MySQL 5.6+（genres/detail 用 TEXT 而非 JSON 列类型）
-- 在宝塔 phpMyAdmin 或命令行执行；库名与 config.php 中 db.dbname 保持一致。

CREATE DATABASE IF NOT EXISTS media_library DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE media_library;

CREATE TABLE IF NOT EXISTS media_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source        VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'tmdb / rawg / bangumi / manual',
  source_id     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '外部源 ID',
  type          VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'short / movie / tv / anime / variety / game / person',
  title         VARCHAR(512) NOT NULL DEFAULT '',
  original_title VARCHAR(512) NOT NULL DEFAULT '',
  year          SMALLINT     DEFAULT NULL,
  rating        DECIMAL(6,2) DEFAULT NULL,
  vote_count    INT          DEFAULT NULL,
  api_calls     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'API 调用次数（ZBLOK 等消费端请求该条目详情的次数）',
  clicks        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击次数（内页 /uisc/{id}.html 被访问的次数）',
  genres        TEXT,
  poster        VARCHAR(1024) NOT NULL DEFAULT '',
  backdrop      VARCHAR(1024) NOT NULL DEFAULT '',
  overview      TEXT,
  download_url  MEDIUMTEXT   NULL COMMENT '下载链接：JSON 数组 [{"label":"光鸭下载","url":"https://..."},...]；兼容旧版纯文本多行；为空不展示',
  extra         TEXT          COMMENT '分类扩展字段 JSON（如 集数/导演/制作公司/平台 等，按 Categories.EXTRA_FIELDS 存储）',
  payload       MEDIUMTEXT   COMMENT '上游原始响应，代理原样回吐',
  cached_at     DATETIME     DEFAULT NULL,
  refresh_at    DATETIME     DEFAULT NULL COMMENT '过期时间，之前代理直接返回本地',
  updated_at    DATETIME     DEFAULT NULL,
  UNIQUE KEY uq_src (source, source_id, type),
  KEY idx_type (type),
  KEY idx_title (title(191)),
  KEY idx_year (year),
  KEY idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task       VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'trending / refresh / manual',
  detail     TEXT,
  items      INT DEFAULT 0,
  created_at DATETIME DEFAULT NULL,
  KEY idx_task (task)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS link_checks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id    INT UNSIGNED NOT NULL,
  label      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '网盘类型，如 夸克/迅雷/光鸭',
  url        VARCHAR(2048) NOT NULL DEFAULT '',
  status     TINYINT NOT NULL DEFAULT 0 COMMENT '1=有效 0=失效',
  http_code  SMALLINT NOT NULL DEFAULT 0,
  checked_at DATETIME DEFAULT NULL,
  KEY idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 后台「系统设置」保存的运行时设置（管理员令牌 / API Key / 数据源 / 网盘类型）
-- 优先级高于 config/config.php：表里有值就用表里的，留空则回退到配置文件。
CREATE TABLE IF NOT EXISTS app_settings (
  `k`          VARCHAR(64) NOT NULL COMMENT 'admin_token / tmdb_api_key / rawg_api_key / adapters / pan_types',
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 前台注册用户（v1.6.2 起；程序首次使用时也会自动创建，这里给出便于手动安装）
CREATE TABLE IF NOT EXISTS `app_users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '登录名，2~20 位，支持中文',
  `email`      VARCHAR(128) NOT NULL DEFAULT '',
  `pass_hash`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'password_hash() 加盐散列',
  `status`     TINYINT      NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用 2=待审核',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '注册 IP（风控用）',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `req_count`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计提交的资源请求数',
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  `lock_until` DATETIME     DEFAULT NULL COMMENT '锁定到期时间（满 5 次失败锁 15 分钟）',
  `created_at` DATETIME     DEFAULT NULL,
  `last_login` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台注册用户';

-- 资源请求（求片区，v1.6.2 起）
CREATE TABLE IF NOT EXISTS `app_requests` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '提交人（删用户时置 0，请求保留）',
  `username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `title`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '想找的资源名称',
  `type`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'short/movie/tv/anime/variety/game',
  `year`       SMALLINT     DEFAULT NULL,
  `note`       TEXT COMMENT '补充说明',
  `contact`    VARCHAR(128) NOT NULL DEFAULT '' COMMENT '联系方式（后台可关）',
  `status`     VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending/found/notfound/replied/closed',
  `admin_note` TEXT COMMENT '站长回复',
  `item_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已找到时对应的 media_items.id',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at` DATETIME     DEFAULT NULL,
  `updated_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_title` (`title`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资源请求';
