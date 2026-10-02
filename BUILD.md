# media-library-api · v4.5.1 源码构建说明

> 当前版本：**v4.5.1** · PHP 8.2+ · MySQL 5.7+ / MariaDB 10.4+ · 发布物为 AST 混淆源码。
> **版本号规则（站长定调）**：末段 ≤9 递增；超过 9 进位到中段（4.4.9 → 4.5.0，禁止 4.4.10）。

## 一、目录与真源

- `原始源码/`：唯一明文修改来源（本目录）。
- `发布版本/`：由混淆器生成的可直接部署源码快照（每次混淆全量重建）。
- `发布包/`：全量新装 ZIP；`升级包/`：全量覆盖升级 ZIP（两者内容逐字节一致，仅文件名不同）。
- `GitHub/`：纯文档快照，供推送 GitHub 用；未经站长明确说不上传。
- `原始源码_模板_v3.1.1/`：历史冻结模板，不参与构建。

## 二、核心文件地图

- `index.php`：统一入口和路由（根路径 → home.php；/person/ 人物页；/tag/ 标签页）。
- `home.php`：主页 / 列表动态渲染；`img.php`、`pic.php`：站内图片代理（v4.5.0 起主端点为 pic.php，img.php 为兼容壳）。
- `install.php`：网页安装壳，安装完成后自锁 404；`tools/install-cli.php`：命令行安装。
- `core/`：
  - `Version.php`（版本号唯一来源）、`Guard.php`（入口守卫）、`Installer.php`、`DB.php`（连接 + 结构自愈）；
  - `Views.php`（前台全部模板：主页 / 详情页 / 卡片）、`Categories.php`（分类与扩展字段定义）；
  - `CacheStore.php`（缓存层，含 `clearAll()`）、`Cache.php`（多级缓存）；
  - `AntiBot.php`（UA 黑名单 + API 签名；前台水印已于 v4.4.8 下线，`watermark()` 返回空串）；
  - `Site.php`（站点信息 / SEO / robots / sitemap / 上传）；
  - `PanTransfer.php`（v4.3.11 网盘自动转存引擎：夸克 / UC）、`PanQr.php`（网盘扫码登录）、`PanSou.php`（网盘搜索）；
  - `PeoplePhoto.php`（v4.5.1 演职员头像百度百科补齐 + 落盘缓存）；
  - `Sync.php`（采集：入库后派生 pan_match_delayed / pan_transfer 后台任务）。
- `cron/`：`sync_hourly`、`sync_refresh`、`build_static`、`link_check`、`api_logs_prune`、`rate_prune`、`pan_match_delayed`、`pan_transfer`（v4.3.11）。
- `adapters/`：`Tmdb.php`、`HongguoDuanju.php`（当前仅此两个数据源）。

## 三、数据表

安装器一次创建 8 张核心表：

1. `media_items`
2. `sync_log`
3. `link_checks`
4. `app_settings`
5. `app_users`
6. `app_requests`
7. `app_api_tokens`
8. `app_api_logs`

`pan_transfers`（v4.3.11 网盘转存记录）由 `PanTransfer::ensureTable()` 运行时按需创建，不需要进安装器。

## 四、发布构建顺序

在项目根目录执行：

```bash
node .workbuddy/validate_php_194.mjs 原始源码
node .workbuddy/validate_js_194b.mjs 原始源码/admin/index.php
node .workbuddy/validate_contract_194.mjs 原始源码/adapters
node .workbuddy/obfuscate_194.mjs 原始源码 发布版本 '{"dirs":["preview"],"files":["check.php","__diag.php","__emergency_reset.php","__migrate_189.php","__diag_login.php","DEPLOY.md","功能说明.txt","安装说明.txt"]}'
node .workbuddy/build_pkg_431.mjs <新版本号> <旧版本号>
```

**关键点：**

- 混淆命令**必须带 excludeJson**（最后一段 JSON），否则 check.php、诊断脚本、preview/ 会混进 `发布版本/` 导致打包断言失败。
- 打包用 `build_pkg_431.mjs`（本机无 Python；`build_release_194.py` 仅留档）。打包器内置自校验：诊断文件黑名单、版本链（Version.php + install.php + admin/index.php）、混淆生效、zip 与 `发布版本/` 逐字节一致。
- 升级基线取 `发布包/` 里除本次版本外最新的旧整包；升级包与整包内容完全一致（全量覆盖式）。

发布物排除项：`preview/`、运行时配置（config.php / install.lock / sync.lock）、诊断脚本（check.php / __diag*.php 等）、全部 `*.md / *.txt / *.rst / *.adoc` 说明文档。

## 五、版本升级操作顺序

1. 改 `原始源码/core/Version.php` 的 `ML_APP_VERSION`（唯一来源；install.php / admin/index.php 均引用它）。
2. 同步改 `.workbuddy/build_release_194.py` 的 `VER`（校验脚本从这里读版本）。
3. 按第四节跑流水线；产物：`发布包/media-library-api-v<VER>.zip` + `升级包/media-library-api-update-v<OLD>-to-v<VER>.zip`。
4. 结尾向站长交付两个 zip 链接并询问是否同步 GitHub 文档；**未经明确说不上传 GitHub**。

## 六、发布前检查重点

- 版本唯一来源为 `core/Version.php`；版本号遵守「末段超 9 进位」规则。
- `发布版本/` 是混淆产物；混淆输出中的诊断文件已被 excludeJson 挡住。
- 部署文档见 `DEPLOY.md`；生产运维见 `PRODUCTION.md`；功能清单见 `功能说明.txt`。
