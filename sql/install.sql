-- 媒资资料库 API - 建表脚本
-- 运行基线：PHP 8.2+ + MySQL 5.7+（genres/detail 用 TEXT 而非 JSON 列类型）
-- 在宝塔 phpMyAdmin 或命令行执行；库名与 config.php 中 db.dbname 保持一致。

CREATE DATABASE IF NOT EXISTS media_library DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE media_library;

CREATE TABLE IF NOT EXISTS media_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source        VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'tmdb / hongguoduanju / manual',
  source_id     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '外部源 ID',
  type          VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'short / movie / tv / anime / variety / game / book / music / other / person',
  title         VARCHAR(512) NOT NULL DEFAULT '',
  original_title VARCHAR(512) NOT NULL DEFAULT '',
  year          SMALLINT     DEFAULT NULL,
  rating        DECIMAL(6,2) DEFAULT NULL,
  vote_count    INT          DEFAULT NULL,
  api_calls     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'API 调用次数（ZBLOK 等消费端请求该条目详情的次数）',
  clicks        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击次数（内页 /uisc/{id}.html 被访问的次数）',
  dl_hits       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转存跳转点击次数（点下载按钮经 /dl/ 跳转到网盘的次数，与 clicks 分开统计）',
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
  status        VARCHAR(16)  NOT NULL DEFAULT 'active' COMMENT 'active=正式条目 pending=暂存（待刮削）',
  UNIQUE KEY uq_src (source, source_id, type),
  KEY idx_type (type),
  KEY idx_title (title(191)),
  KEY idx_year (year),
  KEY idx_rating (rating),
  KEY idx_clicks (clicks),
  KEY idx_updated_at (updated_at),
  KEY idx_status (status),
  KEY idx_type_year (type, year),
  KEY idx_type_rating (type, rating),
  KEY idx_type_clicks (type, clicks),
  KEY idx_type_updated (type, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ★ v4.5.8 人物索引表：人物页(/person/)据此走 name+kind 联合索引精确匹配，
--   避免对 media_items.extra（无索引的 TEXT）做 LIKE '%名字%' 全表扫描。
--   老站点覆盖升级后由 CacheStore::ensurePersonIndex() 自动补建并回填，无需手工执行。
CREATE TABLE IF NOT EXISTS person_index (
  name    VARCHAR(191) NOT NULL,
  kind    VARCHAR(16)  NOT NULL COMMENT 'director / actor',
  item_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_person (name, kind, item_id),
  KEY idx_name (name(150), kind),
  KEY idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task       VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'trending / refresh / manual',
  detail     TEXT,
  items      INT DEFAULT 0,
  created_at DATETIME DEFAULT NULL,
  KEY idx_task (task),
  KEY idx_created_at (created_at),
  KEY idx_task_created (task, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS link_checks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id    INT UNSIGNED NOT NULL,
  label      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '网盘类型，如 夸克/迅雷/光鸭',
  url        VARCHAR(2048) NOT NULL DEFAULT '',
  status     TINYINT NOT NULL DEFAULT 0 COMMENT '1=有效 0=失效',
  http_code  SMALLINT NOT NULL DEFAULT 0,
  msg        VARCHAR(200) NOT NULL DEFAULT '' COMMENT '检测原因：接口返回的中文说明',
  checked_at DATETIME DEFAULT NULL,
  KEY idx_item (item_id),
  KEY idx_item_checked (item_id, checked_at),
  KEY idx_status (status),
  KEY idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ★ v4.6.4：网盘转存记录表 —— 此前只在 PanTransfer::ensureTable() 里运行时自愈建表，
-- 新装站点首次用网盘转存会临时建表。这里补进安装脚本，让新装即完整。
-- 老站点不受影响（ensureTable 仍是 CREATE TABLE IF NOT EXISTS，幂等）。
CREATE TABLE IF NOT EXISTS pan_transfers (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  pan        VARCHAR(20)  NOT NULL DEFAULT '',
  old_url    VARCHAR(768) NOT NULL,
  new_url    VARCHAR(768) NOT NULL DEFAULT '',
  share_id   VARCHAR(64)  NOT NULL DEFAULT '',
  status     VARCHAR(10)  NOT NULL DEFAULT 'ok',
  error      VARCHAR(255) NOT NULL DEFAULT '',
  attempts   INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_old (old_url(255)),
  KEY idx_item (item_id),
  KEY idx_share (share_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ★ v4.9.7 自建访问统计（替代第三方统计，见 Site::protectThirdParty 的说明）：
--   page_views        访问明细（一次页面浏览一行），只保留 30 天，由 cron/rate_prune.php 清理
--   page_views_daily  按天聚合（PV/UV + 维度计数），保留 400 天，报表只查这张表
-- 老站点不受影响：两张表都由 ensureVisitTables() 以 CREATE TABLE IF NOT EXISTS 幂等自愈。
-- ★ 为什么要有聚合表：明细表按天增删，报表若直接 GROUP BY 明细，30 天后单次查询要扫几十万行。
--   聚合表把「今天」这一天的增量单独累加，历史行不再变动，报表查询量与保留天数无关。
CREATE TABLE IF NOT EXISTS page_views (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vid        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '匿名访客标识：优先取 HttpOnly cookie；无 cookie 时用 ip+ua 哈希',
  ip_hash    CHAR(32)     NOT NULL DEFAULT '' COMMENT 'sha1(ip+盐)，不存明文 IP（可按需加盐以满足隐私要求）',
  path       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '页面路径（不含查询串，长度上限截断）',
  referer    VARCHAR(512) NOT NULL DEFAULT '' COMMENT '来源页；站外只保留域名，站内记为空',
  src        VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '来源渠道归类：direct/search/social/cross',
  device     VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'mobile/desktop/tablet/bot/unknown',
  os         VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'Windows/iOS/Android/macOS/Linux…',
  browser    VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'Chrome/Edge/Safari/Firefox/WeChat…',
  item_id    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '详情页对应的 media_items.id，非详情页为 0',
  created_at DATETIME     NOT NULL,
  day        DATE         NOT NULL COMMENT '冗余的统计日，配合 created_at 的索引做按天范围扫描',
  PRIMARY KEY (`id`),
  KEY idx_day (`day`),
  KEY idx_vid_day (`vid`,`day`),
  KEY idx_path (`path`(191)),
  KEY idx_created (`created_at`),
  KEY idx_src_day (`src`,`day`),
  KEY idx_device_day (`device`,`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问明细（30 天滚动）';

-- 按天聚合：一行一天。PV 为累加值，UV 为当天去重后的访客数（在明细上算好后写入，不再回溯）。
CREATE TABLE IF NOT EXISTS page_views_daily (
  day        DATE         NOT NULL,
  pv         INT UNSIGNED NOT NULL DEFAULT 0,
  uv         INT UNSIGNED NOT NULL DEFAULT 0,
  mobile     INT UNSIGNED NOT NULL DEFAULT 0,
  desktop    INT UNSIGNED NOT NULL DEFAULT 0,
  tablet     INT UNSIGNED NOT NULL DEFAULT 0,
  bot        INT UNSIGNED NOT NULL DEFAULT 0,
  unknown    INT UNSIGNED NOT NULL DEFAULT 0,
  direct     INT UNSIGNED NOT NULL DEFAULT 0,
  search     INT UNSIGNED NOT NULL DEFAULT 0,
  social     INT UNSIGNED NOT NULL DEFAULT 0,
  cross      INT UNSIGNED NOT NULL DEFAULT 0,
  paths_json MEDIUMTEXT   NULL COMMENT '当日各页面 PV 的 JSON 快照，报表「热门页面」直接读它，避免回扫明细',
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问按天聚合（400 天滚动）';

-- ★ v5.0.9 自动刮削 / 资料补全的**历史记录**（后台「自动刮削」页签）
--   一次跑批 = 一行：type/scope 是筛选条件，target_total 是本次目标条数，
--   scraped/failed/skipped 是三个结果计数，detail 存**逐条明细 JSON**
--   （每条：条目 id、标题、成功/失败、补了哪些字段、失败原因）——
--   这样面板上的「数字点进去看列表」有真实数据可查，而不是现算一遍。
-- 老站点不受影响：ensureScrapeTables() 以 CREATE TABLE IF NOT EXISTS 幂等自愈。
-- ★ 只保留最近 200 次（cron/rate_prune.php 第 5 步清理），详情 JSON 单次上限 512KB。
CREATE TABLE IF NOT EXISTS scrape_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind         VARCHAR(20)  NOT NULL DEFAULT 'images' COMMENT 'images=补图（海报/背景） / enrich=补资料（简介/演职员/年份/评分）',
  type_filter  VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '本次的类型筛选，空=全部',
  scope        VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending=仅条目暂存 / all=全部条目',
  target_total INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本次目标条数（本次待处理）',
  scraped      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理成功条数',
  failed       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理失败条数',
  skipped      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳过（并发占用 / 中断）条数',
  fields       VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '本次补哪些字段（逗号分隔），enrich 用',
  duration_ms  INT UNSIGNED NOT NULL DEFAULT 0,
  detail       MEDIUMTEXT   NULL COMMENT '逐条明细 JSON 数组',
  started_at   DATETIME     NOT NULL,
  finished_at  DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY idx_kind_time (kind, id),
  KEY idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='自动刮削与资料补全的历史记录（最近 200 次）';

-- 后台「系统设置」保存的运行时设置（管理员令牌 / API Key / 数据源 / 网盘类型）
-- 优先级高于 config/config.php：表里有值就用表里的，留空则回退到配置文件。
CREATE TABLE IF NOT EXISTS app_settings (
  `k`          VARCHAR(64) NOT NULL COMMENT 'admin_token / tmdb_api_key / / adapters / pan_types',
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 前台注册用户（v1.6.2 起；程序首次使用时也会自动创建，这里给出便于手动安装）
CREATE TABLE IF NOT EXISTS `app_users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(128) NOT NULL DEFAULT '' COMMENT '登录名（v1.8.3 起即注册邮箱），全站唯一',
  `email`      VARCHAR(128) NOT NULL DEFAULT '' COMMENT '账号邮箱，与 username 一致',
  `pass_hash`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'password_hash() 加盐散列',
  `status`     TINYINT      NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用 2=待审核',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '注册 IP（风控用）',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `req_count`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计提交的资源请求数',
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  `lock_until` DATETIME     DEFAULT NULL COMMENT '锁定到期时间（满 5 次失败锁 15 分钟）',
  `created_at` DATETIME     DEFAULT NULL,
  `last_login` DATETIME     DEFAULT NULL,
  `is_admin`   TINYINT      NOT NULL DEFAULT 0 COMMENT '1=管理员',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_lock` (`status`, `fail_count`, `lock_until`),
  KEY `idx_status_created` (`status`, `created_at`)
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
  KEY `idx_title` (`title`(191)),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资源请求';

-- API 调用令牌（v1.7.0 起；程序首次使用时也会自动创建，这里给出便于手动安装）
CREATE TABLE IF NOT EXISTS `app_api_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '令牌名称/用途说明',
  `token`        VARCHAR(128) NOT NULL DEFAULT '' COMMENT '令牌值',
  `user_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联注册用户，0=未关联',
  `status`       TINYINT      NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `daily_limit`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日调用上限，0=不限',
  `ip_whitelist` TEXT COMMENT 'IP 白名单，逗号分隔，留空=不限',
  `usage_count`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计调用次数',
  `daily_count`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日已预占次数',
  `daily_date`   DATE         DEFAULT NULL COMMENT '预占计数日期',
  `last_used`    DATETIME     DEFAULT NULL COMMENT '最后调用时间',
  `created_at`   DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 调用令牌';

-- API 调用日志（v1.7.0 起）
CREATE TABLE IF NOT EXISTS `app_api_logs` (
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
  KEY `idx_created` (`created_at`),
  KEY `idx_token_created` (`token_id`, `created_at`),
  KEY `idx_ip_created` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 调用日志';
