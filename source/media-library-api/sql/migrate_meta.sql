-- 已部署库迁移：新增 download_url 与 extra 两列（数据不丢，新装库直接用 install.sql 即可）
-- 在宝塔 phpMyAdmin 或命令行对 media_library 库执行：

ALTER TABLE media_items
  ADD COLUMN download_url TEXT        NULL AFTER overview,
  ADD COLUMN extra        TEXT        NULL AFTER download_url;

-- 校验
SHOW COLUMNS FROM media_items LIKE 'download_url';
SHOW COLUMNS FROM media_items LIKE 'extra';
