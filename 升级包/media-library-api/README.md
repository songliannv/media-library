# 升级包 · media-library-api

本目录只放 **增量升级包**（只含变化文件，给已经在跑的站点覆盖升级用）。
**整包**（首次安装用）在 `../../发布包/media-library-api/`；**不打包的完整源码**在 `../../发布版本/`（打开即见，可直接部署）。

## 命名规则

```
media-library-api-update-<起始版本>-to-<目标版本>.zip
```

## 清单

| 升级包 | 从 → 到 | 内容 | 大小 |
|---|---|---|---|
| `media-library-api-update-v1.7.0-to-v1.8.0.zip` | v1.7.0 → v1.8.0 | **14 个变化文件（新增 0 + 修改 14）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md`** | 16 条目 / 160.8 KB |
| `media-library-api-update-v1.6.5-to-v1.7.0.zip` | v1.6.5 → v1.7.0 | **7 个变化文件（新增 1 + 修改 6）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md`** | 9 条目 / 70.0 KB |
| `media-library-api-update-v1.6.3-to-v1.6.4.zip` | v1.6.3 → v1.6.4 | **4 个变化文件（新增 0 + 修改 4）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md`** | 6 条目 / 18.4 KB |
| `media-library-api-update-v1.6.2-to-v1.6.3.zip` | v1.6.2 → v1.6.3 | **13 个变化文件（新增 4 + 修改 9）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md`** | 15 条目 / 143.1 KB |
| `media-library-api-update-v1.6.1-to-v1.6.2.zip` | v1.6.1 → v1.6.2 | **14 个变化文件（新增 4 + 修改 10）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md`** | 16 条目 / 173.9 KB |
| `media-library-api-update-v1.6.0-to-v1.6.1.zip` | v1.6.0 → v1.6.1 | 9 个变化文件（新增 0 + 修改 9）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md` | 11 条目 / 128.9 KB |
| `media-library-api-update-v1.5.0-to-v1.6.0.zip` | v1.5.0 → v1.6.0 | 16 个变化文件（新增 3 + 修改 13）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md` | 18 条目 / 152.8 KB |
| `media-library-api-update-v1.4.1-to-v1.5.0.zip` | v1.4.1 → v1.5.0 | 17 个变化文件（新增 2 + 修改 15）+ `UPGRADE.txt`（GBK）/ `UPGRADE.md` | 19 条目 / 108.7 KB |

配套一份 `...-说明.md`（与包内 `UPGRADE.md` 同内容），不解压就能看。

## 用法

1. 解压升级包，得到变化文件（目录结构不变，另有 `UPGRADE.txt` / `UPGRADE.md` 两个说明）；
2. 宝塔 →「文件」→ 站点根目录，按同名路径**覆盖上传**；
3. 保留不动：`config/config.php`、`config/install.lock`、`uisc/`、`uisc-assets/`（包里不含它们）；
4. 确认站点根 `index.php` 里 `ML_APP_VERSION` 已变为目标版本；
5. 打开 `check.php` 看自检是否全绿（v1.8.0 为 **11 组**），再到后台点一次「同步采集」验证。

> 跨多个版本升级（例如 v1.2.0 → v1.8.0）：直接传最新**整包**覆盖，不要连续套多个增量包。
> 归档约定见 `../../资料索引.md`。

## 各升级包明细

### v1.7.0 → v1.8.0（最新）

修改：`install.php`、`core/Installer.php`、`core/User.php`、`admin/index.php`、`api/unified.php`、
`check.php`、`core/Settings.php`、`core/Config.php`、`config/config.sample.php`、
`tools/install-cli.php`、`index.php`、`preview/security.html`、`安装说明.txt`、`功能说明.txt`

> **后台不再自己控制自己**：原先后台登录靠一个「管理令牌」，等于自己给自己发密钥才有权管自己，毫无意义。
> 本版改为 **账号 + 密码**：安装时创建第一个账号 = 管理员，进后台用它登录（会话保持）。
> 后台顶部「管理 Token」输入框与「尚未配置令牌」引导块**全部移除**；「系统设置」里也去掉了「后台管理令牌」项。
> **令牌从此只属于外部调用者** —— 在「API 调用」页逐个发放（v1.7.0 已建）。
> `check.php` 的访问门槛同步改为「管理员登录态」（或在服务器本机访问）。
> **数据库无表结构变更，不用导 SQL**；旧库里残留的 `app_settings.admin_token` 不再生效（留着无害）。
> ⚠️ **升级后需有可用管理员账号**：安装时创建的那个会带 `ml-admin@local` 标记；若你没有账号，
> 见升级包内 `UPGRADE.md` 的「升级前必读」。

### v1.6.5 → v1.7.0

新增：`core/ApiToken.php`

修改：`index.php`、`admin/index.php`、`api/unified.php`、`sql/install.sql`、`安装说明.txt`、`功能说明.txt`

> **前后台令牌彻底分开**：原来的单个全局令牌同时兼任「站长登录后台」与「外部 API 调用者密钥」两件事，
> 导致所有调用者共用一个密钥、无法区分谁调了多少。本版新增 **API 调用令牌**体系：
> 后台新增一级菜单「**API 调用**」，可为**每个调用者**发独立令牌，各自设置**每日调用上限**与**IP 白名单**，
> 并记录每次调用的**接口 / 参数 / IP / 耗时**。
> 调用方式：请求头 `X-Api-Token: <令牌>` 或 URL 参数 `?token=<令牌>`。
> **公开接口依然可不带令牌调用**（老调用方不用改代码），带令牌才记用量、受限额约束。
> **数据库**：新增两张表 `app_api_tokens` / `app_api_logs`，**首次打开「API 调用」页自动创建**，一般不用手工导 SQL；
> 无建表权限时手工导入 `sql/install.sql` 末尾的两段 DDL。
> 后台登录令牌（`config.php` 的 `admin.token`）保持不变，现在只服务于站长自己。

### v1.6.3 → v1.6.4

修改：`home.php`、`index.php`、`功能说明.txt`、`安装说明.txt`

> **紧急修复首页 500**：`home.php` 是全局命名空间文件却用了短类名 `Config::sub(...)`，又没有 `use Core\Config;` → `Fatal error: Class 'Config' not found`。
> v1.6.0 引入（从 `core/Views.php` 抄代码漏了命名空间差异），**v1.6.0 ~ v1.6.3 只要站点已安装就会踩到**；此前没暴露是因为站点一直没装成。
> **只覆盖 `home.php` 一个文件即可修复**。**数据库无变更**，不用导 SQL。
> 本版起新增发布前必跑项 `phpclassscan.py`（类名解析扫描）。

### v1.6.2 → v1.6.3

新增：`core/Installer.php`、`install.php`、`安装说明.txt`、`功能说明.txt`

修改：`index.php`、`admin/index.php`、`check.php`、`config/config.sample.php`、`core/Guard.php`、
`sql/install.sql`、`tools/install-cli.php`、`DEPLOY.md`、`.htaccess`

> **恢复网页版安装器并加锁**：`install.php` 仅在"尚未安装"时可用，
> 装完写入 `config/install.lock` 后对所有人返回 **404**（与文件不存在无异），没人能借此重装站点。
> 网页与命令行安装器共用同一套内核 `core/Installer.php`。
> **升级后首页（`/`）引导页会多出一个「🚀 开始网页安装」按钮** ——
> 站点尚未安装时，访问首页不再 404，而是给出安装入口。
> **建议同步把伪静态里的 `^/(install|setup|upgrade|update)` 改成 `^/(setup|upgrade|update)`** ——
> 去掉 install，否则将来重装时安装向导会被 Nginx 拦死（**点首页按钮若仍 404，就是这个原因**）。
> **数据库**：无表结构变更，不用导任何 SQL（`sql/install.sql` 只是补齐手动安装用的两张表定义）。

### v1.6.1 → v1.6.2


新增：`core/User.php`、`core/UserViews.php`、`user.php`、`sql/migrate_users.sql`

修改：`index.php`、`admin/index.php`、`api/unified.php`、`check.php`、`config/config.sample.php`、
`core/Guard.php`、`core/Settings.php`、`core/Views.php`、`tools/install-cli.php`、`DEPLOY.md`

> **数据库**：新增两张表 `app_users` / `app_requests`，**首次访问自动创建 + 缺列自愈**，一般**不用手工导 SQL**；
> 仅当数据库账号无建表权限时，才手工导入 `sql/migrate_users.sql`。
> 回滚需注意：代码回滚后这两张表会闲置，彻底清理需手工 `DROP TABLE`。

### v1.5.0 → v1.6.0

新增：`core/PanSou.php`、`sql/migrate_site_seo.sql`、`core/Config.php` 之外的站点设置相关文件（详见包内 `UPGRADE.md`）

即：新增站点设置（含 SEO / robots / sitemap），需要执行 `sql/migrate_site_seo.sql`。

### v1.4.1 → v1.5.0

新增：`core/Sync.php`、`cron/sync_hourly.php`

修改：`index.php`、`admin/index.php`、`api/unified.php`、`core/Settings.php`、`core/Config.php`、
`core/CacheStore.php`、`core/Guard.php`、`adapters/Adapter.php`、`adapters/Tmdb.php`、
`adapters/Rawg.php`、`adapters/Bangumi.php`、`cron/sync_trending.php`、
`config/config.sample.php`、`check.php`、`DEPLOY.md`

即：**不碰数据库**（无表结构变更，无需执行 `sql/migrate_*.sql`）。
