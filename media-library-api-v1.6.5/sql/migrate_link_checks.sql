-- 已部署库迁移：新增「链接失效检查」结果表 link_checks
-- 在宝塔 phpMyAdmin 或命令行对 media_library 库执行：

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

-- 校验
SHOW TABLES LIKE 'link_checks';
