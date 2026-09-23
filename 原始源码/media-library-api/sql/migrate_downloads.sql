-- 已部署库迁移：将 download_url 列扩为 MEDIUMTEXT，以便存放多条网盘下载 JSON
-- （旧数据若为纯文本多行，仍会被内页兼容解析，不会丢失）
-- 在宝塔 phpMyAdmin 或命令行对 media_library 库执行：

ALTER TABLE media_items MODIFY COLUMN download_url MEDIUMTEXT NULL;

-- 校验
SHOW COLUMNS FROM media_items LIKE 'download_url';
