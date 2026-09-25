-- v2.2.1 增量索引（在已有库上执行；新装库已在 install.sql 内置）
-- 用法：宝塔 phpMyAdmin 导入本文件，或命令行 mysql -u用户 -p 数据库 < upgrade_221.sql

-- sync_log 加 created_at 索引（清理任务与列表查询走它）
ALTER TABLE sync_log ADD INDEX idx_created_at (created_at);

-- app_users 加复合索引（登录锁定查询走它）
ALTER TABLE app_users ADD INDEX idx_lock (status, fail_count, lock_until);

-- 提示：app_users 加 is_admin 字段（可选，v2.2.1 已兼容旧逻辑，但建议执行以彻底消除 MIN(id) 判定）
-- ALTER TABLE app_users ADD COLUMN is_admin TINYINT NOT NULL DEFAULT 0 COMMENT '1=管理员';
-- UPDATE app_users SET is_admin=1 WHERE id=(SELECT MIN(id) FROM app_users WHERE status=1);
