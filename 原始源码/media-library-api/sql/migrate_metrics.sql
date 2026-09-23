-- 给已部署的 media_items 表追加统计列（新装直接用 install.sql，本文件仅用于老库升级）
-- 在宝塔 phpMyAdmin 执行，或命令行：mysql media_library < migrate_metrics.sql

ALTER TABLE media_items
  ADD COLUMN api_calls INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'API 调用次数' AFTER vote_count,
  ADD COLUMN clicks    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击次数' AFTER api_calls,
  ADD KEY idx_api_calls (api_calls),
  ADD KEY idx_clicks (clicks);
