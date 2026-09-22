# media-library（媒资资料库 API）

**影视 / 游戏 / 动漫 资料库服务** —— 对外提供 **TMDB 兼容代理** + **统一 API**，
自带**可视化后台**，部署在宝塔面板（Nginx + PHP + MySQL）。

当前版本：**v1.6.3**　·　兼容 **PHP 5.6 ~ 8.2**　·　零框架依赖（纯 PHP）

---

## 分支说明

| 分支 | 内容 | 用途 |
|---|---|---|
| **`main`**（默认） | README.md、`安装说明.txt`、`功能说明.txt` | 说明书 / 门面，先看这里 |
| **`source`** | **源码全树**（仓库根 = 站点根，可直接部署） | 拿代码 / 部署用 |

```bash
# 取源码（推荐）
git clone -b source https://github.com/songliannv/media-library.git

# 只要说明书
git clone https://github.com/songliannv/media-library.git
```

> `source` 分支解压出来就是**可直接放进宝塔站点根**的目录结构，
> 不用再剪切子文件夹。

---

## 三步开站

1. **上传**：把 `source` 分支全部内容放到站点根目录（如 `/www/wwwroot/cs.zyfx.wang`）。
2. **配伪静态**（必做，否则后台接口全 404）：
   ```nginx
   location / { try_files $uri $uri/ /index.php?$query_string; }
   ```
3. **安装**（两种，任选其一）：
   - **网页安装（推荐，不用终端）**：浏览器打开 `https://你的域名/install.php`，按向导填数据库信息即可
     （环境自检 → 建表 → 生成 `config/config.php` → 写 `config/install.lock`）。
     **装完自动 404**，别人无法借它重装你的站。
   - **命令行**：在宝塔「终端」执行 `php tools/install-cli.php`。

装好后访问 `check.php?token=你的后台令牌` 做一次完整体检。

详细步骤见 **[安装说明.txt](安装说明.txt)**，功能清单见 **[功能说明.txt](功能说明.txt)**。

---

## 功能速览

- **API**：TMDB 兼容代理（ZBLOK 等主题零改动对接）+ 统一 API（跨源搜索 / 列表 / 详情）
- **数据源**：TMDB（影视）、RAWG（游戏）、Bangumi（动漫），可扩展
- **6 大分类**：短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏
- **前台**：卡片墙 + 4 种排序 + 分类筛选 + 分页；静态内页 `/uisc/{id}.html`
- **网盘下载**：夸克 / 迅雷 / 光鸭 / 百度 / UC，后台可视化增删；**下载区仅在填了链接时显示**
- **后台**：内容增删改 + **下载链接失效检查** + 系统设置 + 站点设置 + 安全防护 + 同步采集 + 重建静态页
- **站点设置**：基础设置 / SEO / 扫描+跳转 / 网盘链接 / 接口配置 / 资源请求（6 个二级页）
- **SEO**：动态 `/robots.txt`、`/sitemap.xml` + 三种上下文标题模板 + 统计代码
- **采集引擎**：只取「最热 / 最多人看」，按各源官方要求限流，重复自动跳过；
  支持**每小时计划任务**与 **PanSou 自动补网盘地址**
- **注册与资源请求**：注册 / 登录 / 退出 + `/request` 资源请求页，后台可处理与回复（带风控）
- **安全**：网页安装器**带自锁**、敏感目录恒 404、内部文件反直访、后台令牌、CSRF、恒时比较
- **响应式**：手机 / 平板 / 大屏六档断点，跟随系统深浅色

---

## 目录速览

```
├── index.php            入口 / 路由 / 版本号（ML_APP_VERSION）
├── install.php          网页安装器（带自锁，仅"未安装"时可用）
├── user.php             前台账号 + 资源请求控制器
├── home.php / track.php 主页 / 计数端点
├── check.php            一键环境自检（11 组，需令牌）
├── DEPLOY.md            部署文档（伪静态 / 安全加固 / 采集 / FAQ）
├── admin/               后台单页
├── api/                 统一 API + TMDB 兼容代理
├── core/                内核（Guard 守卫 / Installer 安装内核 / Sync 采集 / Site / PanSou / User …）
├── adapters/            数据源适配器（Tmdb / Rawg / Bangumi）
├── cron/                计划任务（每小时采集 / 每日趋势 / 重建静态页）
├── config/              config.sample.php、pan_types.php（运行时 config.php 由安装器生成）
├── sql/                 install.sql + migrate_*.sql
├── tools/install-cli.php 命令行安装器（与网页版共用 core/Installer.php）
└── preview/             界面静态预览页（17 页，不参与运行）
```

---

## 版本

见源码内 `index.php` 的 `ML_APP_VERSION`（当前 **v1.6.3**，2026-09-22）。
历史版本变更记录随发布包提供，源码仓库只保留最新版。
