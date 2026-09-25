-- media-library-api v3.1.0 性能索引迁移
-- 可选执行：适用于已安装旧版本；不会修改任何业务数据。
-- 如果索引已存在，跳过对应语句即可。

ALTER TABLE media_items ADD KEY idx_clicks (clicks);
ALTER TABLE media_items ADD KEY idx_updated_at (updated_at);
