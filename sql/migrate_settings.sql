-- ==================================================================
-- 媒资资料库 · 迁移：后台「系统设置」
-- ==================================================================
-- 作用：新增 app_settings 表，用于存放「后台 → 系统设置」保存的运行时配置
--       （管理员令牌 / TMDB Key / RAWG Key / 启用数据源 / 网盘类型）。
--       表里的值优先级高于 config/config.php；留空则回退到配置文件。
--
-- 什么时候需要执行？
--   · 从 v1.2.1 及更早版本升级 → 执行一次即可。
--   · 全新安装 → 不用执行，tools/install-cli.php / sql/install.sql 已包含该表。
--   · 也完全不用手工执行：后台第一次打开「系统设置」页时会自动建表。
--
-- 注意：本文件不改动任何既有表，不影响已入库数据。
-- ==================================================================

CREATE TABLE IF NOT EXISTS app_settings (
  `k`          VARCHAR(64) NOT NULL COMMENT 'admin_token / tmdb_api_key / rawg_api_key / adapters / pan_types',
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
