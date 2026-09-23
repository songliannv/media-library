# media-library-api 增量升级包

> **v1.4.1 → v1.5.0**　2026-09-22
>
> 本包是**增量包**（17 个变化文件），不是整包。首次安装请用 `media-library-api-v1.5.0.zip`。

## 一、升级前（30 秒）

| 要备份的东西 | 说明 |
|---|---|
| `config/config.php` | 你的数据库配置 + 后台令牌 + API Key |
| 数据库备份 | 宝塔 → 数据库 → 备份（**本次不涉及表结构变更**，纯保险）|

站点 PHP 版本 ≥ 5.6 即可，本版仍 **5.6 ~ 8.2 全兼容**，不用切版本。

## 二、升级步骤（覆盖上传）

1. 解压本包，得到 17 个文件（目录结构不变，按同名路径放）。
2. 宝塔 →「文件」→ 进入站点根目录（例 `/www/wwwroot/cs.zyfx.wang`）。
3. 按同名路径**覆盖上传**这 17 个文件；两个新增文件（`core/Sync.php`、`cron/sync_hourly.php`）直接放进对应目录。
4. 确认版本号：站点根 `index.php` 里应看到 `define('ML_APP_VERSION', '1.5.0');`
5. 后台 →「同步采集」→ 点「立即采集」验证。

> ★ 覆盖时**不要动**：`config/config.php`、`config/install.lock`、`uisc/`（本包不含它们，正常不会被碰）。

## 三、本次「不涉及数据库」

- 无表结构变更，**不需要**执行任何 `sql/migrate_*.sql`；
- 新增的采集参数存在 `app_settings` 表（v1.3.0 起已有），后台首次保存时自动写入，无需手工建表；
- **结论：本次升级不碰数据库。**

## 四、覆盖后建议做的 3 件事

1. 后台 →「系统设置」确认令牌 / API Key / 启用数据源都还在（存数据库，升级不会丢）。
2. 后台 →「同步采集」→ 点「立即采集」，看结果表是否按「源 × 接口」列出 抓取 / 新增 / 跳过重复 / 低于票数；想先看预案不发请求就点「试运行」。
3. 宝塔 →「计划任务」→ 添加任务：

   | 项 | 值 |
   |---|---|
   | 任务类型 | **Shell 脚本** |
   | 执行周期 | **N 小时 → 1 小时** |
   | 脚本内容 | `php /www/wwwroot/你的站点/cron/sync_hourly.php` |

   后台「同步采集」页有**一键复制**按钮，粘贴即可。建议把「TMDB 热门榜时间窗口」改**今日**，配合每小时跑。

## 五、本次修好的问题

**1) 「点同步没反应」** —— 真因是后台接口封装用了 `fetch().then(r=>r.json())`：服务端返回非 JSON（PHP 致命错误页 / 502 / 504 / 404 的 HTML）时 `json()` 失败、`await` 中断，页面既不显示也不报错。
修法：前端 `api()` 改为**永不抛异常**（非 JSON 也给人话提示 + HTTP 状态码），后端同步端点整体兜底 `try/catch`，**必定返回 JSON**。

**2) PHP-FPM 下 `PHP_BINARY` 指向 php-fpm**，拼出的计划任务命令不可用 → 改为优先探测 `/www/server/php/<版本>/bin/php`。

## 六、文件清单（17 个）

**新增**

| 文件 | 说明 |
|---|---|
| `core/Sync.php` | 新建：采集引擎（限流 / 去重 / 最热口径 / 进程锁 / 日志） |
| `cron/sync_hourly.php` | 新建：每小时计划任务脚本（CLI 专用，网址访问 404） |

**修改**

| 文件 | 说明 |
|---|---|
| `index.php` | 版本号 → 1.5.0 |
| `admin/index.php` | 后台新增「同步采集」页；api() 改为永不抛异常；同步按钮忙碌态 |
| `api/unified.php` | admin/sync 走采集引擎 + 整体兜底；新增 GET admin/sync_plan |
| `core/Settings.php` | 新增 10 个采集参数键 + syncMap() + schema 分组 |
| `core/Config.php` | 配置叠加新增 sync 段 |
| `core/CacheStore.php` | 新增 exists() / existsSameTitle() 去重 |
| `core/Guard.php` | 守卫清单加入 core/Sync.php |
| `adapters/Adapter.php` | 接口扩展 trending() / trendingFetch()（window/limit/order） |
| `adapters/Tmdb.php` | 多端点：trending + movie/tv popular + 综艺；跳过 person |
| `adapters/Rawg.php` | ordering=-added / -rating；page_size 夹到 ≤40 |
| `adapters/Bangumi.php` | /calendar + /v0/subjects sort=rank；请求带 User-Agent |
| `cron/sync_trending.php` | 改用同一采集引擎（每日全量重建静态页） |
| `config/config.sample.php` | 新增 sync 示例段 |
| `check.php` | 新增第 9 组「采集与计划任务」 |
| `DEPLOY.md` | 第九节重写（采集与定时任务）+ FAQ 追加 |

**MD5（前 10 位，用于确认覆盖成功）**

```
7d08e6a1ae  core/Sync.php
82b1f3e010  cron/sync_hourly.php
6f942cbe82  index.php
c4f8d5a5b3  admin/index.php
18729c90af  api/unified.php
bfb70be103  core/Settings.php
3f2d89e34a  core/Config.php
aa52b006b9  core/CacheStore.php
a24d21a58e  core/Guard.php
aa84d3acde  adapters/Adapter.php
897bc514dc  adapters/Tmdb.php
aa98458334  adapters/Rawg.php
4c481de252  adapters/Bangumi.php
aad50975cb  cron/sync_trending.php
2d24af1ae7  config/config.sample.php
57ee949ef4  check.php
3848d61972  DEPLOY.md
```

## 七、出问题时

- 后台点按钮没反应 → 打开 `/check.php?token=你的后台令牌`，看**第 7 组**（PHP 语法）与**第 9 组**（采集与计划任务）。
- 后台接口全 404 → Nginx 伪静态没配，需要 `location / { try_files $uri $uri/ /index.php?$query_string; }`。
- 只想排错不想真采集 → 命令行 `php cron/sync_hourly.php --dry`。

**回滚**：把备份的 `config/config.php` 放回，再重新上传 v1.4.1 整包覆盖即可。
