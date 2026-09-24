# media-library-api · GitHub 推送版

> 这是给 GitHub 仓库用的门面文件。本地归档目录见 `../../资料索引.md`。

## 这是什么

一个**零框架依赖、纯 PHP** 的媒资资料库，支持短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏 / 书籍 / 音乐 / 其他 9 大分类，前台展示 + 后台管理 + 自动刮削采集 + 外部 API 调用，部署在宝塔面板上。

## 当前版本

**v1.9.4**（2026-09-24）· 兼容 **PHP 5.6 ~ 8.2**

## 主要特性

- 🎬 **9 大分类**：短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏 / 书籍 / 音乐 / 其他
- 📡 **多数据源**：TMDB（影视）、RAWG（游戏）、红果短剧代理
- 🔐 **双令牌体系**：后台管理员账号登录 + 外部 API 调用令牌（每调用者独立限额 / 白名单 / 日志）
- ⚡ **自动刮削**：定时任务补 poster / backdrop，首页热门标签云自动排列
- 📦 **开箱即用**：网页安装器 + 命令行安装器两套入口，装完自动锁死 install.php
- 🛡️ **安全防护**：Nginx 伪静态加固 + 内部 PHP 直访 404 + 环境变量保护
- 🌙 **暗色模式**：跟随系统 / 手动切换，首页与后台均适配

## 快速开始

```bash
# 克隆源码
git clone https://github.com/songliannv/media-library.git
cd media-library

# 上传到宝塔站点根目录（覆盖）
# 访问 站点地址/install.php 完成安装
# 后台：站点地址/admin
```

详细部署步骤见 [DEPLOY.md](DEPLOY.md)

## 文档

| 文档 | 用途 |
|---|---|
| [DEPLOY.md](DEPLOY.md) | 部署全流程：环境要求 → 上传 → 安装 → 伪静态 → 安全加固 → 采集 → FAQ |
| [CHANGELOG.md](CHANGELOG.md) | 完整版本变更记录（v1.1.0 起） |
| [安装说明.txt](安装说明.txt) | 中文版安装指南（给非技术用户） |
| [功能说明.txt](功能说明.txt) | 中文版功能清单与使用指南 |
| [资料索引.md](资料索引.md) | 本地五目录结构说明（原始源码 / 发布版本 / 发布包 / 升级包 / 软件素材） |

## 仓库分支

| 分支 | 内容 |
|---|---|
| `main`（默认） | 源码全树 + 本 README + 说明书（打开仓库第一眼就是代码） |
| `source` | 纯源码全树（不带任何 README / 说明书，仅源码） |

两个分支的【源码内容完全一致】。

## 目录说明

```
├── README.md          ← 你在看的这个
├── DEPLOY.md          部署文档（含 Nginx 伪静态 + 安全加固）
├── CHANGELOG.md       版本变更记录
├── 安装说明.txt        中文安装指南
├── 功能说明.txt        中文功能清单
├── 资料索引.md         本地归档目录说明
│
├── index.php          站点入口（首页重定向 + 路由）
├── home.php           首页渲染（带海报 / 轮播 / 标签云）
├── user.php           注册 / 登录 / 找回密码 / 资源请求
├── install.php        网页安装器（已安装后自动 404）
├── check.php          环境自检页（管理员登录态或本机访问）
│
├── admin/             后台单页应用
├── api/               统一 API + TMDB 兼容代理
├── core/              内核（Guard / Installer / Sync / Site / PanSou / User …）
├── adapters/          数据源适配器（Tmdb / Rawg / HongguoDuanju）
├── config/            运行时配置（部署后生成）
├── cron/              定时任务（每小时同步热门）
├── sql/               数据库建表 SQL
├── tools/             命令行工具（install-cli.php 等）
└── uploads/           用户上传目录（部署后创建）
```

## 技术栈

- **后端**：纯 PHP 5.6+（零框架、零 Composer 依赖）
- **数据库**：MySQL / MariaDB（PDO 驱动）
- **前端**：原生 HTML / CSS / JS（Vue2 + Element UI 已下线，改回 vanilla JS）
- **部署**：宝塔面板 + Nginx + PHP-FPM

## 授权

MIT License

---

本仓库是**源码快照**，开发主线代码在 `原始源码/` 目录。  
如需最新功能或报 bug，请访问 [资料索引.md](资料索引.md) 了解本地归档结构。
