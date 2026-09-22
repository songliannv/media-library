-- ==================================================================
-- 媒资资料库 · 迁移：站点设置 / SEO / 跳转扫码 / 网盘账号 / 接口配置（v1.6.0）
-- ==================================================================
-- 作用：v1.6.0 新增了「后台 → 站点设置」下的 5 个页面（基础设置 / SEO /
--       扫描+跳转 / 网盘链接 / 接口配置），以及「采集后自动补网盘下载地址」。
--
-- ★ 需要执行本文件吗？大多数情况**不需要**：
--   · 站点设置本身只是往 app_settings 表里存 key-value，**没有新增任何表字段**；
--     该表在 v1.1.0 就有了（见 sql/migrate_settings.sql），后台打开设置页也会自动建。
--   · PanSou 用到的两张表（搜索缓存 / 待确认队列）由 core/PanSou.php 的
--     ensureTables() 在首次使用时自动创建，无需手工导入。
--   · 前台跳转 / SEO / 模板切换全部是运行时读取设置，不涉及数据库结构。
--
-- 那这个文件有什么用？
--   · 给喜欢「一次把结构对齐」的站长一个显式入口；
--   · 给不方便让 PHP 建表（数据库用户没有 CREATE 权限）的环境手工执行；
--   · 提前把 PanSou 的两张表建好，避免第一次采集时才建表。
--
-- 注意：全部是 CREATE TABLE IF NOT EXISTS，不会改动既有表、不会丢数据。
-- ==================================================================

-- 1) 站点设置的存放表（v1.1.0 起已有；这里只是兜底重建）
CREATE TABLE IF NOT EXISTS app_settings (
  `k`          VARCHAR(64) NOT NULL COMMENT 'site_* / seo_* / redirect_* / pan_* / pansou_* 等设置键',
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) PanSou 搜索结果缓存（后台「接口配置」里的「清空搜索缓存」会清这张表）
CREATE TABLE IF NOT EXISTS pan_search_cache (
  `kw`         VARCHAR(191) NOT NULL COMMENT '搜索关键词（截断到 180 字）',
  `payload`    MEDIUMTEXT            COMMENT '归一化后的搜索结果 JSON',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`kw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) PanSou 「待确认」队列（搜到但相似度没达阈值的候选，人工过一遍再写入）
CREATE TABLE IF NOT EXISTS pan_pending (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对应 media_items.id',
  `title`      VARCHAR(512) NOT NULL DEFAULT '',
  `keyword`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '实际用于搜索的关键词',
  `links`      TEXT                  COMMENT '候选下载地址 JSON',
  `score`      INT NOT NULL DEFAULT 0 COMMENT '标题相似度 0~100',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) 可选：把网盘账号相关的空设置行预置出来（不插也可以，后台保存时会自动写入）
--    留空 = 用 config/config.php 或内置默认，所以通常不需要预置。
-- INSERT IGNORE INTO app_settings (`k`,`v`,`updated_at`) VALUES ('pan_group','quark,aliyun,baidu,uc,xunlei,guangya',NOW());
