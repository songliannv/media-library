# v1.8.8 → v1.8.9 升级说明

## 新增功能
1. **书籍/音乐类型** - 扩展分类支持 book/music
2. **后台用户中心** - 新增用户管理 tab
3. **图片本地下载** - 自动下载海报/背景图到服务器

## 变更文件
- core/Categories.php - 添加 book/music 类型
- admin/index.php - 添加用户中心 tab + 图片下载复选框
- api/unified.php - 添加图片本地下载逻辑
- index.php - 版本号更新到 1.8.9
- uploads/.htaccess - 新建（限制目录访问）
- __migrate_189.php - 数据库迁移脚本（可选）

## 升级步骤
1. 解压覆盖以上文件到线上宝塔站点
2. 确保 uploads/ 目录存在且可写
3. 浏览器 Ctrl+F5 强刷后台
4. 如需迁移数据库，访问 /__migrate_189.php

## 注意事项
- 本版本不改动数据库结构
