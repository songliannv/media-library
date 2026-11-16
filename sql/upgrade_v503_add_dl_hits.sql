-- 升级脚本：v5.0.3 新增「转存跳转」点击统计字段 dl_hits
-- 用途：点下载按钮经 /dl/ 端点跳转到网盘的次数（与内页 clicks 分开统计）。
-- 适用：从 v5.0.2 及更早版本升级到 v5.0.3 的已有站点。
-- 用法（宝塔终端，进入站点根目录后执行其一）：
--   mysql -u<用户> -p<密码> <数据库名> < sql/upgrade_v503_add_dl_hits.sql
--   或把下面这条 ALTER 直接粘贴进 phpMyAdmin / 任意 MySQL 客户端执行。
-- 说明：IF NOT EXISTS 保护，重复执行安全；新装站点已由 install.sql 自带该列，无需执行本文件。

ALTER TABLE `media_items`
  ADD COLUMN `dl_hits` INT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '转存跳转点击次数（点下载按钮经 /dl/ 跳转到网盘的次数，与 clicks 分开统计）';
