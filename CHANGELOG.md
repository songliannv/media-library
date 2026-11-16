# 更新日志（media-library-api）

> 版本号规则：末段 ≤9 递增，超过 9 进位到中段（4.4.9 → 4.5.0）。

# v5.4.5（2026-11-16）后台新增「源码对接 / 源站管理」Tab

## 需求

后台新增源码对接 / 源站管理页面，把主站（万能资料库等）作为 CDN 回源站点，支持按访客地区自主判断资源切换，首次判定后记录，同地区下次直接命中；并提供一键清理全部站点缓存。

## 改动

- **新增 `原始源码/admin/ui/tab-source.php`**（后台 Tab 片段）：源站配置、地区 - 源站/资源 映射（增删）、地区判定测试、回源连通性测试（HEAD 探测）、一键清理全部缓存。
- **新增 `原始源码/core/SourceRegion.php`**（`Core\SourceRegion`）：地区映射与源站配置管理，持久化为 `stats/` 目录 JSON 文件（`source_config.json`、`source_region_map.json`、`source_region_hits.json`），**不改数据库**。
- **`原始源码/admin/ui/topbar.php`**：左侧导航新增「源码对接」Tab 入口。
- **`原始源码/admin/index.php`**：require 新的 tab-source.php 片段。
- **`原始源码/api/unified.php`**：新增 7 个 `admin/source` 端点（`/config` GET/POST，`/regions` GET，`/region` POST/DELETE，`/detect` GET，`/test` POST）。
- **一键清理全部缓存**：复用已有 `/api/v1/admin/cache_clear` 端点，清空全部站点缓存文件。
- 版本升级为 v5.4.5。

## 数据库

不改数据库结构，不新增 SQL 迁移（全部数据持久化为 `stats/` 目录 JSON 文件）。

---

# v5.4.4（2026-11-01）连续采集改造（常驻守护，一页页一个个不停歇）

## 需求

不再「定时一批批采集」，改为**常驻运行、一页页一个个不停歇地采集**：在官方 API 配额限制内控制速度，全部条目采集完后回到最开头从头再采，已采集的自动跳过，没有就采集，如此往复、不停止；每次只处理 1 条。

## 改动

- **新增 `原始源码/core/ScrapeLoop.php`**（守护管理类，`Core\ScrapeLoop`）：
  - 守护生命周期（启动 / 存活探测 / 优雅停止），存活探测校验 `/proc/$pid/cmdline` 含 `scrape_loop`，避免 pid 复用误判；
  - 游标持久化到 `config/scrape_loop.cursor.json`（断点续跑）；
  - 实时状态文件 `config/scrape_loop.status.json`（后台面板读取）；
  - 核心采集循环 `run()`：按 `media_items.id` 升序逐条遍历，每条先 `autoScrapeImages` 补图再 `autoEnrichFields` 补资料，速度由 `cron.loop_delay_ms` 控制（默认 350ms/条，0=不限速）；扫到末尾回头部 `round+1`，已齐全条目因查询条件天然跳过；长连接断开用 `ReflectionClass` 重置 `DB::$pdo` 自愈；永不主动退出，靠 `config/scrape_loop.stop` 标记优雅停止。
- **新增 `原始源码/cron/scrape_loop.php`**（CLI 入口）：`--help` / `--stop` / `--status` / `--once` / 默认常驻；被 Web 直接访问时由守卫挡掉。
- **`原始源码/cron/scrape_minutely.php`**（看门狗 + 旧批量开关闸门）：每分钟运行一次即托管拉起 / 保活守护；连续采集开启后旧「每分钟批量」模式默认安静退出（`cron_scrape_minutely_enabled` 显式开启才并行）。
- **`原始源码/core/Settings.php`**：`cronMap()` 新增 `cron_scrape_loop_enabled` / `cron_scrape_minutely_enabled` / `cron_scrape_loop_delay_ms` 三个键（进入白名单，`Settings::set()` 才会落库）。
- **`原始源码/api/unified.php`**：`saveSettings()` 新增延迟数值区间（0~5000ms）与两个 0/1 枚举校验；`admin/item` 新增 `scrape_loop_status` / `scrape_loop_save` / `scrape_loop_stop` 三个 action。
- **`原始源码/admin/ui/tab-scrape.php` + `assets/js/admin-media.js`**：新增「连续采集（常驻守护）」控制面板——启停按钮、轮次 / 已采集 / 已跳过 / 进度 / 速度实时展示、速度可调、配置保存即时启停。
- **`原始源码/core/Guard.php`**：`ml_guard_shield_files()` 登记 `cron/scrape_loop.php`。
- 版本升级为 v5.4.4。

## 数据库

不改数据库结构，不新增 SQL 迁移（全部复用既有 `scrape_logs` / `media_items` 与配置表 `app_settings` 的 `cron` 组）。

## 默认行为

连续采集**默认关闭**（`cron.loop_enabled` 未配置时兜底为 0），需站长在后「连续采集」面板手动开启；开启后才会由每分钟计划任务托管拉起守护、并默认让旧「每分钟批量」模式安静退出。这样更新后线上站点不会不经确认就切到连续采集、也不会误伤既有的自动采集。

---

# v5.4.3（2026-11-01）修复桌面端网格变成 4 排

## 现象与根因

刷新缓存后，桌面端网格列数由原先的 7 列变成 6 列；每页仍查询 21 条，因此 21 张卡片被排成 4 排，末排只剩 3 张。此前的网格补位脚本只负责不足一排时补透明占位，不能约束 CSS 的实际列数。

## 改动

- `原始源码/assets/css/site.css`：桌面默认、1400px 以上和 1800px 以上统一固定为 7 列；768px 以下继续使用响应式自适应列数。
- 保留 `assets/js/grid-auto.js` 的末排补位逻辑，移动端和不足一页的列表仍按实际列数补齐。
- 版本升级为 v5.4.3，避免浏览器 / CDN 继续使用旧版 CSS。

## 数据库

不改数据库结构，不新增 SQL 迁移。

---

# v5.4.2（2026-11-01）自动采集失败跳过与 TMDB 私有方法修复

## 需求

自动采集遇到失败条目时不能一直重复占用队列；失败原因要留下记录并继续处理后续条目。截图同时暴露了自动补图跨类调用 `Tmdb::genreMap()` 私有方法的问题。

## 改动

- `原始源码/adapters/Tmdb.php`：新增公开入口 `genresFromRaw()`，保留 `genreMap()` 为私有实现。
- `原始源码/core/CacheStore.php`：自动补图改用 `genresFromRaw()`；失败阈值默认改为 1，首次失败后进入冷却；新增手动绕过冷却参数，手动刮削仍可显式重试。
- `原始源码/api/unified.php`：后台手动刮削显式绕过自动失败冷却。
- `原始源码/cron/scrape_minutely.php`：计划任务显式遵守失败冷却，逐条记录失败并继续处理后续条目。
- `原始源码/core/Settings.php`、`admin/ui/tab-scrape.php`、`assets/js/admin-media.js`：同步默认阈值和后台文案为首次失败让位。
- `.workbuddy/test_scrape_cooldown.mjs`：增加私有方法调用回归、自动/手动入口隔离、首次失败冷却、成功清零和到期恢复测试。

## 数据库

不改现有表结构，不新增 SQL 迁移；继续使用既有 `scrape_attempts` / `scrape_logs` 表并自动记账。

## 校验

- 自动采集回归：24/24 通过
- PHP：107/107 通过
- 后台内联 JS：1/1 通过
- 适配器契约：全部通过

---
> 以下为 v4.3.7 起的版本记录（新版本在上）。

# v5.4.1（2026-10-25）卡片底部 chip 呼吸加大：评分 / 热度 / 点击不再挤在一起

## 需求
用户："chip 挤在一起"—— v5.4.0 已经把标题上下 padding 对称化，
但底部三个 chip 之间只有 6px gap、内部 padding 只有 3/6px，视觉仍显拥挤。

## 改动（1 文件 2 处）
| 选择器 | 改前 | 改后 |
|---|---|---|
| `.c-meta` | `gap:6px` | `gap:10px` |
| `.c-meta .chip` padding | `3px 6px` | `4px 9px` |

## 校验
- PHP 107/107 ✓
- 混淆 134 文件 / PHP 103 / 改名 102 / 失败 0
- verify_194 11/11 PASS

## 产物
- 发布包：`发布包/media-library-api-v5.4.1.zip`
- 升级包：`升级包/media-library-api-update-v5.4.0-to-v5.4.1.zip`

---

# v5.4.0（2026-10-25）卡片视觉最终打磨：上下 padding 对称化，标题真正居中

## 需求
用户看 v5.3.9 后反馈：「上下间隔大小还是有点不一样，上下也居中」。
v5.3.8 里 `.c-title` 用了 `margin:3px 0 10px`（上 3 下 10），不对称；
`.c-body` 也是 `padding:14px 13px 16px`（上 14 下 16），也不对称。
两处加起来视觉必然偏下。

## 改动（1 个文件，2 处）
| 选择器 | 改前 | 改后 |
|---|---|---|
| `.c-body` padding | `14px 13px 16px` | `14px 13px`（上下对称） |
| `.c-title` margin | `3px 0 10px` | `0 0 10px`（顶部呼吸由 `.c-body` padding 承担） |

## 校验
- PHP 107/107 通过（未动 PHP）
- 混淆 134 文件 / PHP 103 / 改名 102 / 失败 0
- verify_194 11/11 PASS，三方逐字节一致

## 版本规则提示
5.3.9 的下一版按末位 ≤9 进位规则应为 **5.4.0**（不是 5.3.10）。

## 产物
- 发布包：`发布包/media-library-api-v5.4.0.zip`
- 升级包：`升级包/media-library-api-update-v5.3.9-to-v5.4.0.zip`

---

# v5.3.9（2026-10-25）卡片网格间距加大：卡片之间不再挤在一起

## 需求
用户看 v5.3.8 后反馈「还是挤在一起」—— v5.3.8 只调了单张卡片内部（标题居中 + 标题和 chip 之间呼吸），
但网格 `gap:18px` 太紧，卡片之间仍显拥挤。

## 改动（1 个文件，1 处）
| 位置 | 改前 | 改后 |
|---|---|---|
| `.grid` | `gap:18px` | `gap:22px` |

## 验证
- 仅 CSS 布局调整，PHP 校验仍 107/107
- 混淆、打包、verify 全过

## 产物
- 发布包：`发布包/media-library-api-v5.3.9.zip`
- 升级包：`升级包/media-library-api-update-v5.3.8-to-v5.3.9.zip`

---

# v5.3.8（2026-10-25）前台卡片视觉打磨：标题居中 + 上下留呼吸

## 需求
用户上一版反馈：v5.3.7 删掉副标题后，标题和底部三个 chip 挤在一起，
标题本身也靠左贴角。要求「标题居中 + 上下空间拉开」。

## 改动（1 个文件，2 处）
| 位置 | 改前 | 改后 |
|---|---|---|
| `.c-body` padding | `11px 13px 14px` | `14px 13px 16px`（上下略放大） |
| `.c-title` | 无 margin | 加 `justify-content:center` + `margin:3px 0 10px` |

用 `justify-content:center` 让标题在 flex 容器里水平居中，
保留原来的 `overflow:hidden` 与 `.t-in / .t-dup` 无缝滚动机制不动。

## 验证
- CSS 修改仅涉及布局属性，无 JS / PHP 语法影响；PHP 校验仍 107/107
- 混淆、打包、verify 全过

## 产物
- 发布包：`发布包/media-library-api-v5.3.8.zip`
- 升级包：`升级包/media-library-api-update-v5.3.7-to-v5.3.8.zip`

---

# v5.3.7（2026-10-25）前台卡片「只留标题」：删掉副标题「类型 · 年份」

## 需求
用户明确指令：**只留标题**，副标题「类型 · 年份」（`.c-sub`）不要再显示。

## 根因（为什么这是噪音）
- 分类页左侧已经按「类型」维度列出全部条目
- 顶部/侧栏可以按年份筛选
- 卡片底部再重复一次「类型 · 年份」是第三次信息重复

## 改动（1 个文件，1 处）
`core/Views.php` — `cardHtml()` 的 heredoc 里删掉 `<div class="c-sub">{$type} {$year}</div>`，
留一行注释说明为什么删（防以后又加回来）；`$type` / `$year` 变量仍在（缩略图上 `c-type` 徽标还在用）；
评分 / 热度 / 点击三个 chip 保留在 `.c-meta`。

## 验证
- PHP 语法校验：**107 / 107 通过**
- 混淆：**155 文件 / 107 PHP / 改名 106 / 失败 0**
- 体积压缩 **66.7%**（2,552,163 → 1,703,407 B）

## 产物
- 发布包：`发布包/media-library-api-v5.3.7.zip`
- 升级包：`升级包/media-library-api-update-v5.3.6-to-v5.3.7.zip`

---

# v5.3.6（2026-10-25）`/api/v1/items` 附带导演/演员：PC 客户端的「导演/演员」维度不再永远 0 个值

## 需求
用户装上 PC 客户端（个人媒资库 v1.7.4）同步后反馈：
- 分类页「类型 · 0 个值」、右侧一片空白
- 修完数据加载后继续排查，发现「导演 / 演员」两个维度也是 **0 个值**

## 根因
`/api/v1/items`（`CacheStore::listItems()`）的 SELECT 列是：
```
id,source,source_id,type,title,original_title,year,rating,genres,
poster,backdrop,overview,download_url,api_calls,clicks,updated_at
```
**没有 `extra` 列** → 客户端拿不到 `extra.director` / `extra.cast`
→ 客户端的 `models.item_directors()` / `item_cast()` 永远返回空
→ 「导演」「演员」两个维度恒为 0 个值。

（客户端 `row_to_item()` 本来就写了 `"director": row.get("director") or []`，
  说明它**一直期待**后端在行里直接给出这两个字段，只是后端从没给过。）

## 改动
### `core/CacheStore.php` — 新增 `peopleByItemIds($ids)`
走**已有的 `person_index` 索引表**（name + kind + item_id）批量取本页条目的人物名：
- 返回 `[item_id => ['director' => [...], 'cast' => [...]], ...]`
- 一次 IN 查询覆盖整页（per 上限 100），代价远小于把整个 `extra`
  （含 cast 头像 URL 等大字段）塞进列表响应
- 表不存在 / 查询失败 → 返回空数组，不影响其它功能

### `api/unified.php` — `/api/v1/items` 路由
列表取出后调用 `peopleByItemIds()`，给每行补上 `director` / `cast` 两个字段
（数组，纯人名字符串）。响应结构对老客户端**完全兼容**（只是多了两个字段）。

### 版本同步
`原始源码/core/Version.php` / `发布版本/core/Version.php` → `5.3.6`
`.workbuddy/build_release_194.py`：`VER='5.3.6'`、`OLD_VER='5.3.5'`
`README.md` 当前版本行同步

## 校验
- PHP 语法：**107/107** ✓
- JS 语法：**23/23** ✓
- 适配器契约：全通过 ✓

## 结果
- PC 客户端的「导演」「演员」维度不再是 0 个值
- 老客户端不受影响（多两个字段而已）

# v5.3.5（2026-10-25）★ 找到「令牌创建失败」的真正根因 + 新建去弹窗 + 加设置按钮

## 用户反馈
1. 「创建的时候不要出现弹窗，直接生成API，然后在后面加上设置的按键，在设置里调整」
2. 「创建还是失败，找找真正的原因吧，开始是能创建的，后面越改问题越多」

## ★ 真正的根因（不是数据库权限！）

**`build_release_194.py` 只负责打包「发布版本/」，混淆是独立一步**
（`node .workbuddy/obfuscate_194.mjs 原始源码 发布版本 <EXCL>`）。

2026-10-25 连发 v5.3.2 / v5.3.3 / v5.3.4 三个版本时**只跑了打包、漏跑了混淆**，
于是「发布版本/」一直冻结在 **15:08（v5.3.1 时期）**的旧代码上，
**三个发布包发的竟然是同一份旧代码**。用户覆盖站点后拿到的是没有修复的版本：

| 修复 | 原始源码 | 发布版本 | 打进包了吗 |
|---|---|---|---|
| v5.3.2 去掉 marker 短路 | ✅ | ❌ 15:08 | ❌ |
| v5.3.3 `put()` 显式写 status | ✅ 15:54 | ❌ 15:08 | ❌ |
| v5.3.3 `visible_after_sync` | ✅ 15:55 | ❌ 15:08 | ❌ |
| v5.3.4 `missingColumns` | ✅ 15:59 | ❌ 15:08 | ❌ |

而部署包 v5.3.2 的 `ensureTables()` 里**还留着**这段短路：

```php
if($a!==''&&@file_get_contents($a)===self::SCHEMA_READY){self::$ready=true;return true;}
```

老站点上遗留的标记文件内容是 `apitoken-3`，旧代码的 `SCHEMA_READY` 也是 `apitoken-3`
—— 两者相等就 `return`，`syncColumns()` 根本没机会跑，
`token_prefix` / `token_suffix` / `token_plain` 三列永远补不上
→ `create()` 的 INSERT 必然报 `1054 Unknown column 'token_prefix'`。

**所以：不是权限问题，是我的构建流程断了，修复从未上线。**

## 改动

### 一、构建流程加硬闸门（根治）
- **`.workbuddy/build_release_194.py`**：新增 `check_release_freshness()`，
  打包前比对「原始源码/」与「发布版本/」同名文件的 mtime：
  - 只要有源文件比发布版本新（= 改了源码没重跑混淆）→ **直接中止打包**
  - 打印出具体是哪些文件，并给出正确三步命令
  - 实测：加闸门后立刻抓出 4 个陈旧文件（admin-items.js / ApiToken.php / CacheStore.php / Sync.php）
- 配套：**`core/ApiToken.php`** 的 `SCHEMA_READY` 从 `apitoken-4` → `apitoken-5`
  （常量必须随"所需列集合"变化而递增，否则老标记会命中短路）

### 二、令牌表结构三重加固（即使真没 ALTER 权限也能用）
- **`core/ApiToken.php`**：
  1. `ensureTables()` **彻底去掉 marker 短路** —— 标记只作加速信号，
     不再作为「跳过补列」的依据；每次都跑一次 `SHOW COLUMNS` 确认列齐了才真短路
  2. `create()` / `listTokens()` / `update()` / `verify()` 改为**按表里实际存在的列拼 SQL**：
     - `token_prefix` / `token_suffix` / `token_plain` / `ip_whitelist` / `daily_count` / `daily_date`
       都是「有就写、没有就跳过」
     - 结果：**无论表结构如何，令牌创建都不会再失败**
       （缺三列时列表显示 `****`、功能仍完整可用）
  3. 新增 `schemaStatus()`：表结构体检（表是否存在 / 缺哪几列 / 能否自动补 / 给出可操作的中文指令）
  4. `create()` 新增 `autoName()`：名称为空时自动生成「令牌-20261025-1532」（同日重复自动加 -2/-3）
- **`api/unified.php`**：
  - `token_add` 不再要求填名称（去掉 400 校验）
  - 新增 `GET /api/v1/admin/token_schema` 体检接口
  - `token_add` 返回值加 `name` 字段

### 三、新建去弹窗 + 设置按钮（用户第一诉求）
- **`assets/js/admin-api.js`**：
  - `openTokenAdd()` **去掉全部 3 个 `prompt()`** → 点一下直接创建
  - 新增 `tkNewModal`：创建成功后展示明文 + 一键复制（自动复制）
  - 新增 `openTkSet()` / `saveTkSet()` / `closeTkSet()`：设置弹窗（名称 / 每日限额 / IP 白名单 / 启用开关）
  - 列表行操作按钮：`复制` **`设置`** `禁用/启用` `删除`（原来的「编辑」3 连 prompt 换成设置弹窗）
  - `loadTokens()` 开头调 `token_schema`，表结构异常时页面顶部红条直接说清楚缺哪几列
- **`admin/ui/modals.php`**：新增 `tkSetModal`（令牌设置）+ `tkNewModal`（创建成功）
- **`admin/ui/tab-apitoken.php`**：加 `tkSchema` 诊断条 + 一行说明文案

## 校验
- PHP 语法：**107/107** ✓
- JS 语法：**23/23** ✓
- 构建硬闸门：启用后立即抓出 4 个陈旧文件 ✓（证明闸门有效）
- 混淆：清空发布版本后重跑，全量铺入 ✓
- 打包：整包 / 升级包 / 发布版本 三方逐字节一致 ✓

## 结果
- **v5.3.2 / v5.3.3 / v5.3.4 三个包请作废** —— 它们发的是同一份旧代码。
  用本版（v5.3.5）覆盖即可。
- 新建令牌：点一下就出，不弹任何输入框；参数去列表行「设置」里调。
- 令牌创建：表结构无论缺哪几列都不会再失败。
- 以后再漏跑混淆，`build_release_194.py` 会直接拦下来。

# v5.3.4（2026-10-25）修复「API 令牌创建失败：Unknown column token_prefix」

## 用户报错
```
创建失败，请检查数据库：[ApiToken::create] SQLSTATE[42S22]:
Column not found: 1054 Unknown column 'token_prefix' in 'field list'
```

## 根因
`ApiToken::syncColumns()` 里 `ALTER TABLE ADD COLUMN` 被 try/catch 静默吞掉（`catch (Throwable) { error_log(...); }`）。

用户站点的场景：
1. 老站点的 `app_api_tokens` 表**没有** `token_prefix / token_suffix / token_plain` 三列；
2. `CREATE TABLE IF NOT EXISTS` 因表已存在而无操作；
3. `syncColumns()` 试图 ALTER 补列，但因**宝塔的 MySQL 用户没有 ALTER 权限**（1142）被拒绝，异常被静默吞掉；
4. `ensureTables()` 仍返回 `true`；
5. 后续 `INSERT INTO ... token_prefix ...` 因列不存在被 MySQL 拒绝（1054）；
6. 用户只看到"创建失败，请检查数据库"，永远定位不到真正原因。

## 改动
- **`core/ApiToken.php`**：
  - `syncColumns()` 保留原有 catch，但错误信息写入 error_log 时附上列名，便于定位是哪一列失败。
  - 新增 `missingColumns($pdo, $table, $want)` 方法：回查 `SHOW COLUMNS`，返回实际仍缺失的列名数组。
  - `ensureTables()` 里在 `syncColumns()` 之后**立即回查**缺失列：
    - 若仍有缺失 → `lastError` 设为中文指令：`"数据库缺少列：token_prefix、token_suffix、token_plain（ALTER 被拒或无权限；请到宝塔 → 数据库 → 用户 → 勾选「ALTER」权限后重试）"`；`ensureTables()` 返回 `false`。
    - `create()` 因 `ensureTables()` 返回 false 而短路，`unified.php:1450` 的 `lastError()` 提示就会带上这段中文指令。
  - 已具备所有列的正常站点：无变化（`missingColumns` 返回空数组）。
  - 全新安装的站点：`CREATE TABLE IF NOT EXISTS` 已建齐所有列，`missingColumns` 返回空数组，`ensureTables()` 返回 true。

## 用户操作
升级后若仍创建失败，弹窗会直接告诉你要干什么：
> 创建失败，请检查数据库：[ApiToken::ensureTables] 数据库缺少列：token_prefix、token_suffix、token_plain（ALTER 被拒或无权限；请到宝塔 → 数据库 → 用户 → 勾选「ALTER」权限后重试）

到宝塔面板：**数据库 → 找到 media_library 库 → 用户 → 编辑 → 勾选「ALTER」**，保存后再点新建令牌即可。

## 校验
- PHP 语法校验：`node .workbuddy/validate_php_194.mjs 原始源码` **107/107** ✓

## 结果
- 老站点缺 ALTER 权限时不再"静默失败 + 显示无意义错误"，而是明确指向宝塔权限设置。
- 已具备权限的站点：无感知。

# v5.3.3（2026-10-25）修复「同步完成但没有数据」：put() 显式写 status + 可见性诊断

## 需求
- 用户原话：「本地软件提示已经同步完成，但没有任何数据」。
- 用户点了后台「立即同步」，看到"✓ 采集完成"，但页面上看不到任何新数据。

## 根因
三个可能同时叠加，任意一个都能造成"同步成功但不显示"：

1. **`CacheStore::put()` 依赖 DB DEFAULT `'active'`**：INSERT 语句里根本没写 `status` 列，靠数据库列定义里的 `DEFAULT 'active'` 补值。老站点若该列的默认值被 ALTER 掉（或用户手工改过 install.sql），新入库的条目会以空 `status` 落库；而前台/后台所有查询都带 `WHERE status='active'` → 条目静默消失。
2. **`ON DUPLICATE KEY UPDATE` 不碰 `status`**：老版本 put() 命中"已存在"只刷元数据，`status` 保持原样。若该条目之前是本地导入的暂存（`status='pending'`），sync 命中 `exists()` 后要么 skipped_dup（什么都不做），要么 dedupe=update（只刷字段不改状态）—— **这条条目永远不会从 pending 升到 active**，前台看不到。
3. **UI 误导**：`admin-items.js::renderSyncReport()` 只要 `!d.success === false && r.ok === true`，就一律显示「✓ 采集完成」，即使 `inserted=0`、`fetched=0`、跳过原因是间隔未到期或锁残留。用户看到绿色 ✓ 误以为数据入库了。

## 改动

### `core/CacheStore.php` — put() 显式写 status
- INSERT 列增加 `status`，值固定 `'active'` —— 同步入库的条目**永远**是可上架状态。
- ON DUPLICATE KEY UPDATE 增加 `status = CASE WHEN status='pending' THEN 'active' ELSE status END` —— 已存在的暂存条目在 sync 命中时自动晋升为正式；已是 active / 其他状态的一律不动。
- 兼容：老库 ALTER 未补全 status 列的情况（虽然罕见）依然会 SQL 报错，但那是**明确报错**而不是**静默丢数据**。

### `core/Sync.php` — 可见性诊断
- 报告末尾新增 `visible_after_sync`：查询 `report['ids']` 里实际 `status='active'` 的条数。前端能直接看到"入库 10 条 / 可见 10 条"或"入库 5 条 / 可见 3 条"。
- `inserted=0` 时给差异化中文 `reason`：
  - `fetched=0` → 提示检查数据源勾选、连通性、锁残留（`config/sync.lock`）
  - `skipped_dup>0` → 提示"全是重复，可能是本地暂存条目没晋升"
  - `skipped_low>0` → 提示"全都低于最少投票数门槛"

### `assets/js/admin-items.js` — 三档提示
- 预演（dry）→ 🔍 预演完成（未入库）
- `inserted>0` → ✓ 采集完成，附带"实际可见 N 条"；若可见 < 入库，红字提示查 status
- `inserted=0` → ⚠ 采集完成但 0 条新增（从 `note-ok` 改成 `note-warn` 琥珀色）

### 版本同步
- `原始源码/core/Version.php` → `5.3.3`
- `发布版本/core/Version.php` → `5.3.3`
- `.workbuddy/build_release_194.py`：`VER='5.3.3'`、`OLD_VER='5.3.2'`
- `README.md`：首页版本串前置 v5.3.3 说明

## 校验
- PHP 语法校验：`node .workbuddy/validate_php_194.mjs 原始源码` 全通过
- JS 语法校验：`node .workbuddy/validate_js_194.mjs 原始源码` 全通过

## 结果
- 用户再遇到"同步完成但页面空"时：
  - 如果确实入库了新条目 → UI 明确显示"入库 N 条 / 实际可见 N 条"
  - 如果 inserted=0 → UI 直接标 ⚠ + 中文原因（跳过/全重复/低于门槛/上游空返回）
  - 之前 pending 的暂存条目 → 下次 sync 命中即自动晋升 active，能在前台看到

## 需求
- 用户反馈后台这块也要改：截图里「本地资源」导航项需要统一命名，不再混用旧标题。
- 目标：后台主导航、对应页标题、弹窗标题、JS 默认标题全部统一为「资源导入」。

## 改动
- `admin/ui/topbar.php`：`本地资源` → `资源导入`
- `admin/ui/tab-local.php`：`📥 本地资源导入` → `📥 资源导入`
- `admin/ui/modals.php`：弹窗标题 `本地资源导入` → `资源导入`
- `assets/js/admin-media.js`：默认标题与代码注释统一为 `资源导入`
- `admin/index.php`：注释里的 Tab 说明同步更新

## 校验
- PHP 通过：**107/107**
- JS 通过：**23/23**

## 结果
- 后台可见文案统一，不再出现「本地资源 / 本地资源导入」混用。

# v5.3.0（2026-10-25）采集改随机翻页：不再每次都采同一批内容

## 需求
- 用户原话：「这个采集有个不是 bug 的 bug，一直采集重复内容，所以修改下，改为随机，第一个随机，随机页面，全部采集，第二次换个随机页面，也是全部采集，然后重复就跳过，下次采集还是走 2 次随机，后续一直重复这种采集模式」。

## 根因（确实是「不是 bug 的 bug」）
- `core/Sync.php::endpointsFor()` 里热门趋势是 **`for ($p = 1; $p <= $maxPages; $p++)` 顺序页**：
  1. 每次同步都从**第 1 页**开始；
  2. 第 1 页的条目一入库就把「每源最多入库 N 条」填满 → **后面的页永远轮不到**；
  3. 而「电影 / 剧集 / 综艺 · 最多人看」这三个端点本来就排在热门页**后面**，于是也永远轮不到；
  4. 再加上取数时传的是「入库上限」（默认 10），一个 20 条的页**只被看了一半**。
- 结果：每次同步采到的都是同一批内容，重复内容反复请求上游。

## 改动
- **页码随机**（`endpointsFor()`）：端点池声明各自的可翻页范围，每次同步**随机**给每个端点抽一个页码，再**打乱端点顺序**，取前 N 个（默认 2）。
  - 可翻页范围：TMDB 热门趋势 本周 1~5 页 / 今日 1 页；TMDB 电影·剧集·综艺热门榜 1~20 页；红果「今日上新」1~5 页；红果热门榜 1~3 页。
  - **两次的页码保证互不相同**（优先保证，池子太小才放宽）—— 满足「第二次换个随机页面」。
- **整页采集**：新增 `Sync::PAGE_FETCH_ALL = 100`，`trendingFetch()` 改用这个上限取数（适配器按上游的一页大小自行截断），入库条数仍由「每源最多入库 N 条」控制。
  - 详情补全的请求预算（max_requests）另算：预算用完只影响元数据深度，**不影响整页采集**。
- **重复跳过**：沿用既有 `CacheStore::exists()` 去重 + `skip_same_title` 同名同年跨源去重，命中即计入「已跳过重复」。
- **入口全覆盖**：手动「立即同步」与每小时计划任务（`cron/sync_hourly.php` → `Sync::run()`）走的是同一个 `endpointsFor()`，**两者同时生效**。
- 后台文案同步：「每个源最多翻几页」的说明改成「这 N 页是**随机抽**的」；「每源最多入库条数」说明补一句「每个随机页都是整页采集、已入库的跳过」。

## 校验
- 语义自测 `.workbuddy/test_random_pages.mjs` **15/15**（URL 拼接 / 页码范围 / 400 次抽样页码互不相同 / 热门榜端点能被抽到 / 200 次同步出现 163 种不同组合 / 红果端点合法 / 整页常量与调用点核对）。
- PHP **107/107**、JS **23/23** 通过。

## 效果预期
- 每次同步采 2 个**随机页**，每页整页过一遍，已入库的自动跳过 ⇒ 长期累计入库的内容不再重复打转。
- 想要更多覆盖就把「每个源最多翻几页」调大（3~5），想要更多入库就调「每源最多入库条数」。

# v5.2.9（2026-10-25）取消「用户中心」，用户管理并入「资源请求」

## 需求
- 用户原话：「用户中心和（资源请求）功能重复了，去除用户中心」「删前面加个详细的按键」（附三张截图）。
- 核对确认：用户中心 = 一张用户列表（搜索 + 卡片 + 详情面板），而「资源请求 → 注册用户」子页签里**已经是同一张用户列表**（含 禁用 / 重置密码 / 删除）—— 确实重复。

## 改动
- **下线「用户中心」**
  - 删除 `admin/ui/tab-users.php`（页面片段）、`assets/js/admin-users.js`（对应脚本）。
  - `admin/ui/topbar.php` 去掉「用户中心」导航项；`admin/index.php` 去掉该片段的 require 与该脚本的加载项（JS 列表从 14 → 13）。
  - `assets/js/admin-overview.js` 去掉 `if(t==='users') loadUsers();` 的页签切换分支。
  - 备份：`.workbuddy/_backup/tab-users.php.orig-5.2.9`、`.workbuddy/_backup/admin-users.js.orig-5.2.9`。
- **能力不丢：用户详情 / 编辑用户迁到「资源请求 → 注册用户」**
  - 用户详情滑出面板（基本信息 / 关联 API 令牌 / 访问资源历史）的 DOM 与 `openUserDetail()` / `closeUserDetail()` 迁到 `admin/ui/tab-request.php` + `assets/js/admin-request.js`。
  - 「编辑用户」（改用户名 / 邮箱 / 是否管理员）同样迁入，刷新改回调 `loadUserList()`。
  - 列表「操作」列的按钮顺序：**禁用 → 重置密码 → 📋 详细 → 编辑 → 删**（★ 用户要的「详细」就在「删」前面）。
- API 不动：`/api/v1/admin/user_list`（列表与编辑取数共用）、`user_save`、`user_del`、`user_history` 全部保留。
- 后台样式：`.user-modal` 系列继续使用（详情面板还在用）；`.user-card` / `.user-grid` 随用户中心下线而闲置，保留在 CSS 里不影响（将来要恢复卡片视图直接可用）。

## 验证
- 残留引用扫描：`tab-users` / `admin-users` / `loadUsers` / `switchTab('users')` **全仓零命中**。
- PHP **107/107**、JS **23/23** 通过（较 v5.2.8 各少 1 个文件：删掉的片段与脚本）。
- 渲染截图核对：主导航已无「用户中心」；「资源请求」的操作列确认为「禁用 / 重置密码 / 📋 详细 / 编辑 / 删」。

# v5.2.8（2026-10-25）后台面板视觉升级：设计系统 v2（admin-theme.css）

## 需求
- 用户原话：「后台面板太简陋了，开始美化」。
- 现状体检：卡片平铺无层次、主导航像一排药丸、表格无质感、按钮尺寸不统一、背景纯色、字号层级薄。

## 做法：新增「精修层」，不动既有样式
- 新增 `assets/css/admin-theme.css`（24.8 KB），在 `admin.css` **之后**加载。
- **不新增、不改、不删任何既有类名与结构** —— 只在同名类上做视觉升级：
  页面与 JS 行为零风险；出问题删掉那一行 `<link>` 即可**完全回滚**。
- 构建脚本对 `assets/css/*.css` 是**自动发现**（v4.9.7 起），无需改打包清单。

## 设计系统 v2 的内容
- **令牌先行**：表面/描边/阴影（三层）/圆角（五级）/主色衍生/文字四级/语义色/动效缓动/字号阶梯/间距，统一到一套 CSS 变量，改一处全局生效。
- **页面底**：纯色改为「极淡冷灰 + 两团紫色柔光」（radial-gradient，fixed 附着），卡片像浮在空气里。
- **顶栏**：渐变 + 内高光 + 外投影；品牌方块（纯 CSS 装饰，不改 HTML）；版本徽章玻璃质感；主题按钮独立造型。
- **主导航**：从「11 个灰药丸」改成**悬浮工具条**（玻璃底 + 内阴影），选中态是渐变胶囊带光晕，hover 有底色反馈，窄屏可横向滚动。
- **卡片**：圆角 14→24px、三层阴影、hover 升级阴影；标题加渐变竖条 + 更强字重。
- **按钮**：统一 36px 高、10px 圆角、渐变 + 内高光；hover 上浮 + 加深投影、active 下沉、focus 可见光环；ghost / danger / sm 各自成体系。
- **表单**：统一浅底 + 圆角 + 聚焦主色柔光环；下拉加自定义箭头；勾选项按容器宽度自动降列。
- **表格**：圆角容器 + 斑马纹 + 悬浮高亮 + 表头浅底，末行去边。
- **统计卡**（刮削页 / 匹配页）：渐变底 + 28px 等宽数字 + hover 抬升；**补上 `.mc-stat` 的非 id 作用域**（原先只有 `#tab-matching .mc-stat` 生效）。
- **状态卡**（v5.2.6/5.2.7 新增的那两张）：圆角 + 渐变底 + 更清晰的信息层级。
- **徽章 / 提示条 / 匹配页卡片 / 用户卡片 / 分页 / 弹窗 / 滚动条**：统一到同一套视觉语言。
- **深色主题**：一整套配套变量与组件覆盖（不是简单反色）。
- **无障碍**：`prefers-reduced-motion` 时关闭所有位移动画；`:focus-visible` 焦点环。
- **窄屏**：卡片内边距与统计卡在 ≤760px 自动降为单列。

## 验证（用真实 CSS + 真实 DOM 渲染截图对比）
- 新增 `.workbuddy/preview/build_preview.py`：把真实 `admin.css` + `admin-theme.css` 复制到预览目录，用**与 `admin/ui/*.php` 同名同结构**的 DOM 拼一页（概览/刮削/匹配/列表/表单/用户/标签/API 八类组件），用本机 Chromium（ms-playwright 的 headless shell）截图。
- 改前基线 `.workbuddy/preview/before.png` vs 改后 `after.png` / `afterdark.png` / `after2.png`（亮色 / 暗色 / 全部组件）。
- 过程中靠截图抓到并修掉两个真实问题：① 匹配页统计卡因只有 `#tab-matching` 作用域导致数字与文字挤在一行 → 补非 id 作用域；② 四列用户卡片在窄容器里按钮文字被压成竖排 → 加 `white-space:nowrap`。
- CSS 语法用 **css-tree 真解析**：0 错误、花括号净值 0。
- PHP **108/108**、JS **24/24** 通过。

# v5.2.7（2026-10-25）自动网盘匹配：运行状态可见 + 待匹配口径澄清

## 现象与定位
- 用户原话：「这个自动化也没生效好像」（附后台「条目匹配」页截图：待匹配 10060 / 已匹配 545 / 失败重试 80）。
- **这次和 v5.2.6 不同 —— 代码层面没有队列堵死的问题**：`pan_match_auto.php` 的候选选择已经排除了冷却期内的条目，失败条目会登记进 `pan_match_tried` 并在 7 天内不再重复请求，队列会往前走。
- 真正的问题是「**看不出来**」：
  1. **该脚本不留任何痕迹**：正常跑完不写日志，跳过时连输出都没有（`--quiet` 下静默退出）—— 站长无法判断它到底跑没跑。
  2. **「待匹配」这个数字本来就不会动**：它 = 全部没有下载链接的条目。自动匹配把条目试过一遍、多数没匹配上时，链接没写进去 ⇒ 数字纹丝不动 ⇒ 看着像"没生效"。
  3. **低置信度结果默认不写库**：只进「待确认」队列（站点设置 → 接口配置 → 待确认队列），所以「待匹配」也不会减少。
  4. 最常见的静默失败：**PanSou 没启用 / 没填接口地址** → 脚本直接退出，且什么都不提示。

## 改动
- **运行记录**：`cron/pan_match_auto.php` 在**全部路径**写 `sync_log`（task=`cron:pan_match_auto`）—— 正常完成 / 已关闭 / PanSou 不可用 / 本轮无候选，各带状态与原因。
- **后台状态卡**（「条目匹配」页顶部，复用 v5.2.6 的状态卡样式）：状态灯（绿=正常 / 琥珀=无候选 / 红=PanSou 不可用或已关闭 / 灰=从未运行）、上次运行时间与结果、近 1 小时/24 小时运行次数、**累计已尝试**（写入 / 待确认 / 无匹配 / 本地复用 / 出错）、**现在还能试的条目数**、PanSou 是否启用与置信度阈值。
- **口径澄清**：「待匹配」标签改为「待匹配（没链接）」并加悬浮说明；页头提示写明 —— 真正在推进的指标是「现在还能试的条目」与「累计已尝试」。低置信度结果去哪儿了、怎么改成直接写入，状态卡里也写清楚了。
- `PanSou::autoMatchStatus()` 新增 + API `GET /api/v1/admin/match_auto_status`。

## 校验
- PHP **108/108**、JS **24/24** 通过；状态卡 HTML 元素 id 与 JS 引用逐一比对一致。

## 站长的下一步（不用改代码）
1. 打开「条目匹配」页看状态卡：**红灯 = PanSou 没启用**（去「站点设置 → 接口配置」启用并填接口地址）；**灰灯 = 宝塔里没配每 5 分钟的 `cron/run.php`**；绿灯 = 在跑。
2. 想让「待匹配」这个数真的降下来：把「写入策略」从「仅高度匹配自动写 / 一律进待确认」改成 **「搜到就写」**（或把置信度阈值从 80 调低）。
3. 批量补链接还有一把手动快刀：「条目匹配 → 🔗 自动匹配」按钮，一键跑完（每批 20 条，直到试完）。

# v5.2.6（2026-10-25）自动刮削「开了却没效果」修复：失败冷却 + 运行状态可见 + 批量可配

## 现象与定位
- 用户原话：「已经启用自动，但好像没效果」（附后台「自动刮削」页截图：待处理 9995 / 已处理 610 / 未处理 9995，三个数字纹丝不动）。
- **从数字反推的结论**：后台「未处理」= 待处理 − 历史成功数，截图里两者相等 ⇒ **历史成功数 = 0** ⇒ 最近 50 次刮削记录里**没有一次成功**。这不是「慢」，是**根本没在推进**。
- 代码级根因（三个叠加）：
  1. **一条失败条目把整条队列堵死**：每分钟任务固定取「id 最小且缺图」的那一条；而失败的条目**一个字段都写不进去**（TMDB/百科查不到就是查不到），所以下一分钟又挑中同一条 —— 队列被永久卡住，后面的 9,994 条永远轮不到。国产竖屏短剧在 TMDB 上基本查不到，这种情况几乎必然发生。
  2. **每分钟只有 1 条**：9,995 条按 1 条/分钟要跑 7 天以上，站长即使没堵死也看不出变化。
  3. **自动任务的成果没进统计**：每分钟任务的结果只写 `sync_log`，而后台「待处理/已处理/未处理」读的是 `scrape_logs` —— 自动跑得再好，那三个数字也不动。

## 改动
- **① 失败冷却（新表 `scrape_attempts`）**
  - 记录「某条目连续失败几次、最后一次什么时候」：成功 → 失败次数归零；失败 → +1。
  - 挑条目的 SQL 加 `NOT EXISTS` 排除「连续失败 ≥3 次且在 7 天冷却期内」的条目 ⇒ 查不到上游的条目只试 3 次就让位，队列继续往下走。
  - 阈值/天数可配：`cron.scrape_fail_tries`（默认 3）、`cron.scrape_cooldown_days`（默认 7）。
  - 补图与补资料各记各的（`kind` 隔离），互不影响。
- **② 每分钟批量可配**：`cron.scrape_per_minute`（默认 1，最大 20）。上万条待处理时，1 条/分钟要跑 7 天；调大前请留意上游配额。
- **③ 后台可见**：「自动刮削」页顶部新增**自动任务运行状态卡** —— 状态灯（绿=正常 / 琥珀=上次没补成 / 红=出错 / 灰=从未运行）、上次运行时间与结果、近 1 小时 / 24 小时运行次数、待处理与冷却中条数、TMDB API Key 是否配置，三个参数可就地调整并保存。
- **④ 统计口径修正**：「未处理」改用 `scrape_attempts` **累计**统计（tries=0 = 曾经补成过），不再用「最近 50 条日志」窗口 —— 每分钟写一条日志时，50 条窗口只有 50 分钟，数字会飘。
- **⑤ 自动任务回写 `scrape_logs`**：每分钟任务的逐条明细写进 `scrape_logs`（与后台手动任务同表同格式），后台三个统计数字与「明细列表」这才反映自动化的真实进展。
- 附带：安装器与 `ensureScrapeTables()` 都会建 `scrape_attempts`（老站点首次运行自愈）；「未处理（还没转存过）」文案改为「还没补成过」（补图与转存是两回事）。

## 校验
- PHP **108/108**、JS **24/24** 通过。
- 语义自测 `.workbuddy/test_scrape_cooldown.mjs` **18/18**（SQL 形状 / kind 隔离 / 阈值 / 成功清零 / 冷却到期恢复 / 节奏估算；阈值与默认值均从源码读取防漂移）。

## 部署后请确认（宝塔）
1. 「站点设置 → 系统设置 → 计划任务（宝塔面板）」里**两条**都要配：`cron/run.php --quiet`（每 5 分钟）与 `cron/scrape_minutely.php`（每分钟）。只配前者 = 不会刮削。
2. 打开后台「自动刮削」页看**状态卡**：灰灯=从未运行（命令没配对/路径错），绿灯=在跑，琥珀=在跑但上游查不到。
3. 按上游配额把「每分钟处理」调到 3~5 条，并观察 TMDB 返回码。

# v5.2.5（2026-10-25）取消「前台显示」设置组，前台行为固定化

## 需求
- 用户原话：「前台显示 都不用设置了，基本没用，可以取消了」（附后台「站点设置 → 前台显示」截图：顶部其他外链 / 前台明暗模式 / 最新列表 三项）。
- 结论：这一组**确实基本没用** —— 逐项查证后，三项里两项是死设置、一项在 CSS 里根本不存在对应规则。

## 这一组原本的 5 个键，逐个交代去向
| 键 | 原用途 | 实际情况 | v5.2.5 起 |
|---|---|---|---|
| `nav_show` | 顶栏入口开关 | **全仓没有任何代码读它**（死设置） | 删除 |
| `nav_external` | 顶部自定义外链 | **前台没有任何代码读它**（死设置） | 删除 |
| `tpl_simple_mode` | 前台明暗模式 | **三档在 CSS 里完全等价**：`mode-light` 根本没有对应规则；`mode-dark` 与 `mode-auto` 只差几个色阶；基础 `:root` 本身就是暗色变量（后写的 `:root` 覆盖先写的亮色变量） | 固定 `auto` 档（= 当前值，前台观感零变化） |
| `list_rank_style` | 榜单样式 grid/list | 只影响一个 class `grid-plain`（无图列表），后台**连编辑项都没有**（藏在 map 里） | 固定有图卡片网格 |
| `list_latest` | 首页「最新更新」区块 | 真实有效，但首页本身已有「最新」排序 Tab，区块属重复 | 固定关闭（与站长当前设置一致；顺手省掉首页每次多跑的一次查询） |

## 改动清单
- **后台**：`admin/ui/tab-site.php` 删掉「前台显示」二级页签按钮；`assets/js/admin-site.js` 删 `SITE_GROUPS.jump` 与 `SITE_HINTS.jump`。
- **设置层**：`core/Settings.php` 删 `redirectMap()` 整个方法、`groupMaps()` 里的 `redirect` 分组、schema 里 3 个字段（`nav_external` / `tpl_simple_mode` / `list_latest`）。
- **前台**：`core/Views.php::bodyClass()` 固定 `mode-auto`；`core/Views.php` 榜单固定有图网格、去掉「最新更新」开关判断；`home.php` 去掉最新列表取数（每次首页少一次查询）。
- **接口**：`api/unified.php` 删三个枚举校验项（键已不在 map 里，留着只是误导）。
- **配置**：`config/config.sample.php` 与 `core/Installer.php` 生成的配置模板**整段移除 `redirect` 段**（跳转/扫码 v4.3.11 已下线，其余键本轮固定化）。
- **自检**：`check.php` 10.5 / 10.6 两节改写为「固定行为说明」，`$REDC`（redirect 段读取）一并删除。
- **老配置兼容**：老 `config.php` 里的 `redirect.*` 键不再被任何代码读取，直接失效、不报错；`Settings::set()` 按 `keys()` 白名单过滤，已删的键即使被旧客户端 POST 也写不进去。

# v5.2.4（2026-10-25）热门导演/演员漏统计修复 + 统计代码设置移除 + sitemap 全自动分片

## 一、热门导演 / 热门演员「收集不完整」修复（用户实测反馈）
- **根因**：三条写 `media_items.extra` 的路径从不更新 `person_index` ——
  ① `CacheStore::enrichWriteRow()`（TMDB 资料补全）；② 刮削主路径的 fetchRaw 分支（`$up2[] = 'extra = :extra'`，且会把暂存晋升为 active）；③ 刮削搜索结果分支。
  而唯一兜底 `personIndexIsStale()` 只抽「**id 最大的 40 条**」比对覆盖率：批量导入后新条目 id 大且无人名、刚补完的条目 id 偏小 ⇒ 抽样恒不缺失 ⇒ 索引永不重建 ⇒ 补完 / 刮完的条目在「热门导演 / 热门演员」里就是看不到。
- **修复**：
  1. 三个写入点全部补 `indexPersonForItem()`（与 `core/Sync.php` 红果演员富化同口径：写哪条索引哪条）。
  2. `personIndexIsStale()` 取样改 `updated_at DESC` —— 专抽「最近被动过的行」，漏索引立刻现形（`bumpClick` / `bumpApiCall` 不碰 `updated_at`，不会把无关行混进样本）。
  3. `ensurePersonIndex()` 加存在性守卫：没有任何条目带 extra 时，不再每次请求都全表重建（全新安装 + 未富化阶段的性能保护）。
- **兼容**：老站点升级后无需手动操作 —— 下一次访问标签 / 人物页即自动检出并全量重建；也可点后台「标签项目 → 重建人物索引」立即生效。

## 二、移除「统计代码」设置（站长改用自己的第一方统计）
- 后台「站点设置 → SEO」里的「统计代码」文本框移除；`core/Settings.php` schema / seoMap、`core/Site.php::headMeta()`、`core/UserViews::head()` 的注入逻辑一并移除，`protectThirdParty()` / `indentJs()` 成为死代码同步删除（Site.php 592 → 508 行）。
- 访问统计继续用 v4.9.7 起的自建方案（`page_views` / `page_views_daily`，后台「内容概览 → 访问统计」），零第三方依赖。
- 老配置里残留的 `seo.stats_code` 不再被任何代码读取，直接失效；`config.sample.php` 与安装器生成的配置模板同步去键；`check.php` 10.2 节去掉对应体检项。

## 三、sitemap 全自动：5000 条一份，去掉两个后台设置
- 移除「生成 sitemap.xml」开关与「sitemap 最多收录条数」两个设置项（schema / seoMap / api 校验表 / config 样例 / 安装器模板同步清理），按站长要求**全自动、无需任何设置**。
- `/sitemap.xml` 改为 **sitemap 索引**：按每 5000 条自动分片，输出 `/sitemap-1.xml`、`/sitemap-2.xml` …（新增 `Site::sitemapChunk()` + `index.php` 路由 `^/sitemap-(\d+)\.xml$`，靠现有 `try_files` 回落，**不需要改 Nginx**）。
- 只收 `status='active'` 的正式条目（暂存条目没有对外详情页，进 sitemap 就是 404；与 `StaticGen::buildAll()` 同口径）；首页固定放在第 1 片；越界分片返回空 urlset（合法 XML，不 404）；0 条数据也保证返回合法索引。
- `robots.txt` 默认内容固定带 `Sitemap:` 指向（只要填了站点网址）。
- 校验：Node 移植语义自测 **34/34**（分片数 / 接续边界 / 首页去重 / 越界归一 / 20 万条级 / 索引↔分片总量一致 / 路由正则）。

# v5.2.3（2026-10-25）分类标签归一化：不再直接透传采集名

## 需求
- 「类似分类不要完全用采集到的名字，选个最贴近内容的分类标签放进去」（附截图：`动作冒险`、`电视电影`、`战争与政治`）。
- 问题根源：各上游的"分类"是各家自己的叫法 —— TMDB 把 `War & Politics` 直译成「战争与政治」，短剧聚合站给「动作冒险」「校园甜宠」，还有「电视电影」「迷你剧」「短剧剧集」这类其实是**格式**不是**内容**的词。原样透传进 `media_items.genres` 后，标签云同义分裂（「动作」和「动作冒险」各算一个标签，点进去条目数还不一致），前台标签又长又乱。

## 新增 `core/Tags.php`（统一词表 + 五级兜底）
- 三张表：`CANON`（62 个规范标签：影视通用类型 + 短剧高频题材）、`ALIAS`（57 条别名 → 规范词）、`DROP`（33 个格式/技术/杂项词，`type` 字段已表达，不该占内容标签）。
- 归一化顺序：① 丢弃清单 → ② 别名 → ③ 规范词 → ④ 兜底逐层拆（`A与B` 取第一个能识别的；`片/剧/秀` 后缀去后缀再查；拼接词取**最长**规范前缀；开头挂格式词的先摘掉）→ ⑤ 未知词只放行 2~6 个纯汉字（真·新分类），英文/长句/符号/纯数字一律丢弃。
- 效果示例：`动作冒险`→`动作`、`战争与政治`→`战争`、`校园甜宠`→`甜宠`、`动作剧情`→`动作`（最长前缀）、`短剧爱情`→`爱情`（摘格式词头）、`电视电影`/`迷你剧`/`短剧剧集`/`网剧`→ 丢弃、`Sci-Fi & Fantasy`→ 丢弃。
- **纯函数、无 IO、幂等**：同一批标签反复归一化结果不变；单条目上限 10 条（超出是噪音）。

## 接入点（只碰"分类"，绝不碰人名）
- `adapters/Tmdb.php::genreMap()`：映射完再过统一词表（`战争与政治`→`战争`、`电视电影`/`迷你剧` 丢弃）。
- `adapters/HongguoDuanju.php::parseGenres()`：三个分支（`sub_title_list` / `type` / `category_schema`）返回值统一归一化。
- **为什么不放在 `CacheStore::genresEnc()`**：`genres` 列是「分类 + 导演/主演人名」合并后的结果（`genresEncWithPeople`），在那里归一化会把「克里斯托弗·诺兰」这类长人名当垃圾丢掉。人名仍由 `peopleTags()` 单独合并，不经过本类。

## 新增 `tools/normalize_tags.php`（存量回填，可选）
- 默认预演（不写库），`--apply` 才落盘；`--limit=N` 分批。
- **人名保护**：先取 `person_index` + `extra` 里的导演/主演名集合，命中即原样保留，不参与归一化 —— 不会把长人名当分类丢掉。
- 只改 `genres` 一列、不动 `updated_at`（不把老条目顶进「最新」排序）；结束后推进标签缓存版本，前台标签云 5 分钟内刷新；幂等，第二次跑「需要改动」应为 0。
- 全新安装的站点无需本脚本（写入时已归一化），它是给不想重新导入的存量库兜底用的。

## 校验
- PHP 语法（php-parser）：**108/108 通过**（新增 `core/Tags.php`、`tools/normalize_tags.php`）。
- 语义自测：本机无 PHP 运行时，用 Node 把同一套规则复刻一遍，**词表直接从 `core/Tags.php` 源文件里抽取**（不是手抄副本），跑红果白名单 63 项 + TMDB 映射值 30 项 + 截图三例 + 18 条边界用例（乱码尾巴 / 空白折叠 / 英文 / 人名 / 长句），**全部断言通过**；同时自检 `ALIAS` 目标必须都在 `CANON`、`CANON` 与 `DROP` 无重叠。

---

# v5.2.2（2026-10-25）★ 文件按功能拆分 + 链接检测每分钟循环 + 后台概览整理

## 链接检测自动化（本次新增）
- 需求：「链接检查改为自动化，每分钟检查一个，检查完后从头开始又检测一边，这样一直循环」——把链接失效检测从「每日批量扫一次」升级为「细水长流式常态化检测」。
- 新增 `cron/link_check_minutely.php`：每分钟只检测 1 个条目，复用 `CacheStore::itemsWithLinks(1)` 的「从未检测 DESC、检测最旧 ASC」排序 —— 检测完一条其 `checked_at` 刷新为当前时间，下一次自然取「次旧 / 下一个从未检测」的条目；一轮跑完后又回到最旧的那条继续，天然形成「从头到尾、再从头」的无限循环，无需任何额外游标或状态文件。
- 边界处理：若某条目 `download_url` 非空但解析不出任何链接，写一条「哨兵」记录把它标记为已检，避免它永远卡在「从未检测」队列头部、堵住每分钟的循环推进。
- 与 `cron/link_check.php`（每日 04:30 批量扫 40 条兜底）并存：两者写同一张 `link_checks` 表、针对不同条目，互不冲突；建议宝塔再新增一条「每分钟」计划任务指向 `link_check_minutely.php`（不并入 `run.php`，因其每 5 分钟才跑一次，做不了每分钟）。
- 守卫与登记：新脚本带 `ml_guard_shield()` + CLI 限制，并登记进 `core/Guard.php` 的 `ml_guard_shield_files()` 清单；`cron/run.php` 头注释补充该独立任务说明。

## 后台概览整理（v5.2.1 后已做、随本版一并归档）
- `admin/index.php` + `assets/js/admin.js`：内容总览统计精简（去掉重复的年份跨度 / 标签总数 / 人物总数 / 零点击条目 / 条目均点击卡片），来源分布标签 `源:` 改为 `来源:` 并新增 feishu 等来源中文名。
- `core/CacheStore.php`：修复「近7/30天新增」把 pending 暂存条目也计入（导致「新增 10605 > 正式条目 683」的明显错误）——两处统计 SQL 均补 `status='active'`；缓存键 `overview_stats_v1` → `v2`。

## 文件瘦身：按功能拆分大文件（本次新增）
- 需求：「好多文件都挺大的，看看文件是不是可以优化和因功能进行拆分成几个」——挑两个最影响维护的动手：`assets/js/admin.js`（173.3 KB / 3239 行）与 `admin/index.php`（58.6 KB / 1066 行，PHP 引导 + 登录页 + 11 个页签 + 6 个弹窗全挤一堆）。
- **`assets/js/admin.js` → 13 个分片**（`assets/js/admin-*.js`，最大 28.4 KB）：
  `admin-core`（接口封装 / 全局状态）、`admin-overview`（标签 / 人物 / 概览 / 访问统计）、`admin-api`（令牌与日志）、`admin-items`（列表 / 匹配 / 增删改）、`admin-sync`（暂存 / 采集）、`admin-links`（下载链接 / 失效检查）、`admin-site`（站点设置）、`admin-request`（资源请求 / 系统设置）、`admin-shell`（安全防护 / 明暗主题）、`admin-users`（用户中心）、`admin-media`（刮削 / 补全 / 本地导入）、`admin-pan`（网盘二维码 / 目录）、`admin-boot`（启动，必须最后）。
- **`admin/index.php` → 264 行 / 12.9 KB**：只剩「引导 + 登录提交处理 + 外壳 + 装配」，界面全部搬进 `admin/ui/`——`login.php`、`topbar.php`、`tab-*.php`（11 个页签各一个）、`modals.php`（网盘扫码 / 目录选择 / 条目编辑 / 改密 / 本地导入 五个弹窗）。加载顺序集中在 `ml_admin_js_list()` 与 11 行 `require`，一眼能看清。
- **拆法保证零改写**：全部按「行级切片」搬移，一个字符都不改（登录页首行 `?><!DOCTYPE html>` 的 `?>` 属于 PHP 控制流，留在 index.php），并在切之前逐行断言边界内容、切之后把片段按原顺序**回拼比对原文件**——JS 3239 行、PHP 869 行 / 50,206 字节均逐字节相同。
- **片段自带拒访**：`admin/ui/` 下每个片段顶部检查 `ML_ADMIN_UI` / `ML_ADMIN_UI_CLOSED` 常量，直连一律 404；目录另放 `admin/ui/index.php` 拒访页；`DEPLOY.md` 的 Nginx 加固段补 `location ^~ /admin/ui/ { return 404; }`。
- **构建闸门同步**：`build_release_194.py` 的「★ 只允许白名单文件残留」断言增加 `admin/ui/` 前缀放行（片段里是大段内联 HTML，★ 是 HTML 注释，混淆器剥不掉也不该剥）。
- **诚实结论（别把它当省流量）**：拆的是**维护成本**，不是带宽。分片总字节与单文件基本持平（gzip 后甚至略增几十字节的头部开销），后台是 SPA、11 个页签的 JS 本来就要全量加载，而且 v5.0.0 起 `/assets/` 已是「一年强缓存 + immutable」，回访 0 流量。真正的收益是：改「条目匹配」不用在 3239 行里翻，改「站点设置」不会误碰别处。
- 这条推翻了 v5.0.1 里「与用户确认不拆 admin.js」的结论 —— 那次否定的是**按页签懒加载**（SPA 做不到），本次是**按功能拆文件、顺序全量加载**，两件事不同。
- 顺带说明了其余大文件为什么不在这版硬拆：`core/CacheStore.php`（179.5 KB）要拆只能上 trait，`api/unified.php`（107.1 KB）要按路由拆控制器，两者都动跨文件调用契约，留作独立一版用同样「拆完必须能跑通」的方式做；`check.php`（112.5 KB）是诊断脚本，**发布包本来就不含**，不占用户带宽。

- 数据库：**无表结构变更**，无 SQL 迁移。

# v5.2.1（2026-10-24）★ pan_transfer 改默认开启 + 修复 opt 槽未生效的 bug

- 需求：「pan_transfer 功能需要一直自动开启」——把 v5.2.0 里 `pan_transfer` 的**可选槽**降级，改为**默认开启**的普通时隙。站长不用再手动去后台打开 `cron.pan_transfer_enabled` 开关，每日 04:00 由 `run.php` 自动跑一次批量补转存（换 Cookie 后重试失败项 / 老链接补一遍）。
- 顺带修复 v5.2.0 的一个隐藏 bug：`cron/run.php` 定义了 `'opt'` 键和 `cron_flag_off()` 函数，但**主调度循环从未调用它**——导致所有标为 opt 的槽（含 `build_static` 全量重建）每 5 分钟都跑一次，白白烧 CPU。现在补上通用 opt 门控：`if (isset($def['opt']))` 命中就查 `cron.<键名>` 是否显式设为 1，未开启则记 `disabled` 状态后 continue。`--task=` 手动补跑走另一分支，不受此门控限制。
- `core/Sync::cronCommands()` 同步：`pan_transfer` 从 `manual` 数组挪到 `slots` 数组；`manual` 数组现在只剩 `build_static`。后台「计划任务」卡片的时隙表与手动段同步刷新（`renderCronBox` 是数据驱动，无需改 JS）。
- `cron/run.php` `--help` 输出与文件头注释同步：`pan_transfer` 从「可选槽」段挪到主槽列表，`build_static` 仍是可选槽。
- 文档：`DEPLOY.md` 时隙表与结论段改写，「可选时隙」从 2 个缩为 1 个（只剩 `build_static`）；`安装说明.txt` 同步。
- 数据库：**无表结构变更**，无 SQL 迁移。老 `app_settings` 里的 `cron.pan_transfer_enabled` 键即使还留着也不会再被读取（无害遗留）。
- 顺带说明：v5.2.0 之前一直「默认关闭」的 `build_static` 实际也在跑（因 opt 门控没生效）；v5.2.1 起 opt 门控真正生效，`build_static` 恢复默认关闭。老站长若依赖它每日自动跑，可继续保留 `cron.build_static_enabled=1` 的 `app_settings` 配置，行为不变。

# v5.2.0（2026-10-24）★ 计划任务查漏补漏：2 条任务覆盖 cron/ 下全部脚本

- 排查结论：`cron/` 下 13 个 PHP 脚本**一个都没漏**——9 个由统一入口按时间槽调度、1 个（`scrape_minutely.php`）每分钟独立跑、`index.php` 是目录拒访、另 2 个手动。真正的漏点是**后台与自检页还在推旧的单条命令**。
- 修复「计划任务命令来源」：`core/Sync::cronCommand()` 原来仍返回 v4.5.5 时代的 `cron/sync_hourly.php` 单条命令，后台「同步采集」页一键复制的就是它——照抄只能配出 1 条增量采集，丢掉全部维护任务。现改为统一入口 `cron/run.php --quiet`。
- 新增 `core/Sync::cronCommands()`：返回结构化清单（2 条必配命令 + 8 个时隙 + 2 个可选槽），后台「计划任务」卡片改用它渲染：两条命令各自可复制、时隙表、手动补跑写法。旧版 UI 文案「每小时计划任务（宝塔面板）」改为「计划任务（宝塔面板）」，并明确「只要配 2 条」。
- 统一 CLI 前缀：`-d opcache.jit=0` → `-d opcache.enable_cli=0`。`jit=0` 只关 JIT 仍会加载扩展，宝塔日志依旧打印 `JIT is incompatible with third party extensions` 噪音；`enable_cli=0` 彻底不启用 opcache，比之前更干净。涉及 `core/Sync.php`、`check.php`、`cron/sync_hourly.php` 头注释。
- `check.php` 第 9 组同步更新：新增 `cron/run.php` / `cron/scrape_minutely.php` 就位检查；计划任务提示由 1 条拆成「任务 A（每 5 分钟，必配）」+「任务 B（每分钟，必配）」两条 `ml_item`；「最近一次采集」为空时的提示由「定时跑 cron/sync_trending.php」改为「配 cron/run.php 统一入口」；`cron/sync_trending.php` 的过期提示同步修正。
- 能合并的已合并：`cron/run.php` 新增两个**可选槽**（默认关闭，显式开启才跑），`build_static`（每天 02:00，`cron.build_static_enabled=1`）与 `pan_transfer`（每天 04:00，`cron.pan_transfer_enabled=1`）。这样全站自动化**只需要 2 条宝塔任务**；`--task=KEY` 手动补跑不受开关限制。
- 配套：`cron/run.php` 抽出 `cron_flag_on()` / `cron_flag_off()`（默认开 / 默认关两种口径），消除 `rate_prune`、`match_auto` 两处重复的开关判定；`--help` 补全可选槽说明；`core/Settings.php` 的 `sync_hourly_enabled` 提示由「计划任务每跑一次 cron/sync_hourly.php」修正为「run.php 内部约每 55 分钟一次」。
- 文档：`DEPLOY.md` 时隙表补 2 个可选槽并把「手动任务」段改写为「独立任务 + 可选时隙」；`安装说明.txt` 同步；根 `README.md` 版本行。
- 数据库：**无表结构变更**，无 SQL 迁移。开关项走 `app_settings`（`cron.build_static_enabled` / `cron.pan_transfer_enabled`）。

# v5.1.9（2026-10-24）★ 匹配全自动：一键跑完 + 后台定时自动匹配

- 需求：「匹配做自动的」——后台「条目匹配」页 1000+ 条待匹配，原「自动匹配」每批只处理 5 条，且每次都从 `id DESC` 挑同一批条目，无匹配的永远卡在最前面，表现为「匹配不完」。
- 新增 `core/PanSou::autoMatch()`：无人值守批量匹配（本地复用 → PanSou 搜索 → 高置信度直接写入 / 低置信度进待确认队列），返回 `remaining` / `done` 供外层循环推进；每条处理结果登记到新表 `pan_match_tried`，无匹配 / 进队列的条目 7 天冷却期内不再请求，`reset` 可强制全部重试。
- 新增 `POST /api/v1/admin/match_auto`（`limit` 默认 20 上限 50、`reset`、`retry_days`）。
- 后台「🔗 自动匹配」按钮改为**一键跑完**：循环调用直到 `done`，按钮实时显示「已试 X 条 · 剩余 Y」，再次点击可停止，结束汇总并刷新列表。
- 新增 `cron/pan_match_auto.php` 并挂进 `cron/run.php` 的 `match_auto` 槽：每 5 分钟跑一批（默认 20 条），试完自然空闲；后台 `cron.match_auto_enabled=0` 可关闭、`cron.match_auto_limit` 可调批大小；写完链接的条目自动重生成静态页。
- 修复致命 bug：`PanSou::enrichLocalFirst()` 调用了**不存在的方法** `self::pendingQueue()`，策略非 write 时一命中就抛 `Call to undefined method`，采集后自动匹配整批中断；改为 `pendingAdd()`。
- 数据库：**新增一张表** `pan_match_tried`（`ensureTables()` 首次运行时自动创建，无需手工导 SQL；未改任何现有表结构）。

# v5.1.8（2026-10-24）★ 上游宕机诊断与重试优化

- 生产日志：红果源 `orz.icicic.icu` 返回 Cloudflare 521（源站宕机），`Http::get` 把「响应非 JSON」拼成 `Invalid JSON from invalid JSON`，误导排查。
- 修复：`core/Http.php` 失败文案改为「上游响应不是合法 JSON（HTTP 521）| 上游响应: error code: 521 (url)」形式，并附 HTTP 状态码 + 响应体预览；Cloudflare 源站故障类 520/521/522/523/524 纳入可重试列表（原先只认 500/502/503/504）。
- `adapters/HongguoDuanju.php` 的 `request()` 调 `Http::get` 时 `retries` 由 1 改为 2，上游瞬时抖动能在同一次调用内自愈，不立即判失败。
- 数据库：未动表结构，无 SQL 迁移，仅运行时容错与诊断文案。

# v5.1.7（2026-10-24）★ 修复 `cron/scrape_minutely.php` 刮削/资料补全报 `Class "Core\Http" not found`

- 根因：`scrape_minutely.php` 的 CLI 加载链未 `require core/Http.php`，而 TMDB / 红果适配器在请求图片与资料时调用 `Http::get()`；Web 端靠 `index.php` 加载，CLI 这条链没有，于是类不存在。
- 修复：`cron/scrape_minutely.php` 补 `require core/Http.php`；`adapters/Tmdb.php` 与 `adapters/HongguoDuanju.php` 改为自带 `Http` 依赖（被任何入口单独加载时都自洽）。
- 顺带：文档中 `scrape_minutely` 的宝塔命令补 `opcache.enable_cli=0`，与统一入口一致，压掉 CLI 的 JIT 警告回显。

# v5.1.6（2026-10-24）★ 修复 `core/Views.php` 详情页 heredoc 注释插值未定义变量

- **Bug**：`Views::renderItemPage()` 的返回值是 PHP heredoc，其中一段 HTML 注释写着 `清理 $overview 占位变量处`；heredoc 里即使写在 HTML 注释中的 `$overview` 也会被 PHP 插值，而该变量 v4.7.9 已移除、当前作用域未定义 ⇒ 每次渲染详情页抛 `Warning: Undefined variable $v1`（混淆后变量名），刷屏日志。
- **Fix**：把注释里的 `$overview` 改成「简介」纯文本，不再含会被插值的变量形状。其余 `$overview` 都在 `/* */` 块注释里，不插值，无需改动。
- 仅文档注释改动，逻辑与数据库未动。

# v5.1.5（2026-10-24）★ 修复 `cron/run.php` 统一入口子脚本路径拼接 bug

- **Bug**：`cron/run.php` 用 `ML_ROOT . '/cron/' . $relScript` 拼子脚本路径，`ML_ROOT` 已是站点根，结果变成 `cron/../cron/xxx.php`；且脚本名与自身参数（`--quiet` 等）被同一 `escapeshellarg` 包成一个字符串，带参数时整段被当成文件名 → `Could not open input file`（无参数的 `rate_prune.php` 因 OS 解析 `..` 侥幸跑通，掩盖了问题）。
- **Fix**：子脚本直接用 `__DIR__` 定位（run.php 与子脚本同处 `cron/`），脚本名与参数拆分后各自 `escapeshellarg`。CLI 统一用 `php -d opcache.enable_cli=0` 压掉 `JIT is incompatible…` 警告。
- 受影响：v5.1.3 起发布的统一入口。全量静态页重建、批量转存等手动任务未受影响。

# v5.1.4（2026-10-24）★ 发布物瘦身：移除 v4.5.1 过期构建文档，整理项目根目录

## 用户需求

项目根目录被重复的旧源码副本塞满，`发布版本/` 里也带着过期的构建说明，需要整理干净。

## 改动

- 从源码真源删除 v4.5.1 时代的 `BUILD.md`、`PRODUCTION.md`（内容停留在 4.5.1，混淆后仍随发布物下发，容易误导部署）。两份文档已归档到 `软件素材/旧版本存档/`，发布物只保留 `DEPLOY.md`、`CHANGELOG.md`、`安装说明.txt`、`功能说明.txt` 四份现行文档。
- 项目根目录清理：移除与 `原始源码/` 完全重复的 v3.8.1 旧快照（`adapters/`、`admin/`、`api/`、`config/`、`core/`、`cron/`、`sql/`、`tools/`、`uploads/` 及 `home.php`、`index.php`、`install.php`、`track.php`、`user.php`、`.htaccess`、`CHANGELOG.md`、`DEPLOY.md`、`安装说明.txt`、`功能说明.txt`），根目录只保留「唯一源码真源 + 发布产物 + 文档」。
- 清除根目录遗留的 Windows 设备名异常文件 `nul`，以及过期产物目录 `outputs/`（其中 v4.2.0 功能矩阵页面已一并归档）。
- 本次仅为文件与文档整理：**不改任何 PHP/JS 逻辑，不动数据库，不需要 SQL 迁移**，升级可直接全量覆盖。

# v5.1.3（2026-10-24）★ 新增统一计划任务入口，整合自动化维护任务

## 用户需求

整理项目中其它计划任务，并把源码里适合自动运行的功能纳入计划任务；避免多条易撞车的宝塔任务重复跑上游。

## 改动

- 新增 `cron/run.php` 统一调度入口：每 5 分钟由宝塔触发一次，入口按时间槽 + 状态文件自动调度适合常规自动化的维护任务（增量采集、每日热门、过期刷新、链接检测、API 流水清理、限流/缓存/分区清理，以及 exec 被禁用时的网盘待匹配队列消费）。
- 入口带 `config/cron_run.lock` 进程锁防重入，并用 `config/cron_run.state.json` 记录各时隙最后执行时间，时间槽未到或今日已跑则跳过；任务失败返回退出码 1。
- 支持 `--quiet` / `--dry-run` / `--task=KEY` / `--help` 参数，便于安静运行、预演与手动补跑。
- `rate_prune` 含 DROP 旧分区逻辑，新增后台开关 `cron.rate_prune_enabled=0` 可关闭该时隙。
- `cron/run.php` 已登记进 `core/Guard.php` 反直接访问守卫清单。

## 部署

只需在宝塔新增一条「每 5 分钟」任务指向 `cron/run.php --quiet`，并保留原有的「每分钟」`scrape_minutely.php` 任务；历史逐条分散的 sync_hourly / sync_refresh / build_static / link_check / api_logs_prune / rate_prune / pan_match_delayed 任务可合并或移除（全量静态页重建与批量转存仍保持手动）。数据库结构不变，不需要 SQL 迁移。

修改文件：

```
原始源码/cron/run.php            （新增）
原始源码/core/Guard.php
原始源码/core/Version.php
原始源码/DEPLOY.md
原始源码/安装说明.txt
原始源码/CHANGELOG.md
```

# v5.1.2（2026-10-24）★ 刮削完成后每分钟自动资料补全 1 个条目

## 用户需求

后台「待处理（缺资料）」还有约 9944 条；要求缺图刮削全部结束后，再自动按每分钟 1 个补全资料。

## 改动

- `cron/scrape_minutely.php` 改为两阶段自动任务：缺图队列非空时只刮削 1 个缺图条目；缺图清零后才切换到资料补全。
- 资料补全阶段每分钟只处理 1 个缺资料条目，复用 `CacheStore::autoEnrichFields()` 的 TMDB 优先、百度百科回退逻辑。
- `autoScrapeImages()` 与 `autoEnrichFields()` 都支持指定 `item_id`，确保预选条目与实际处理条目一致。
- 成功后只重建当前条目的静态页；数据库结构不变，不需要 SQL 迁移。
- 仍使用 `config/scrape.lock` 与 `sync_log`，记录阶段、条目、成功/失败及错误信息。

## 部署

保持原来的宝塔计划任务即可，每分钟执行：

`php /www/wwwroot/你的站点/cron/scrape_minutely.php`

不需要新增第二条任务。覆盖 v5.1.2 升级包后，脚本会自动从缺图阶段切换到资料补全阶段。

修改文件：

```
原始源码/cron/scrape_minutely.php
原始源码/core/CacheStore.php
原始源码/core/Version.php
原始源码/CHANGELOG.md
README.md
资料索引.md
安装说明.txt
功能说明.txt
DEPLOY.md
.workbuddy/build_release_194.py
```

---

# v5.1.1（2026-10-24）★ 每分钟自动刮削 1 个待处理条目

## 用户需求

后台存在约 9990 个「待处理（缺图）」条目，希望自动按每分钟 1 个持续刮削。

## 改动

- 新增 `cron/scrape_minutely.php`，每次只取 1 个缺海报或缺背景图的条目。
- 复用现有 `CacheStore::autoScrapeImages()`、`config/scrape.lock` 和 TMDB/光鸭补图逻辑，避免重复实现。
- 成功后只重建当前条目的静态页，不做全站重建；无待处理条目时安全退出。
- 运行结果写入 `sync_log`，便于后台排查成功、失败、锁占用和接口错误。
- `core/Guard.php` 登记新 CLI 脚本；数据库表结构不变。

## 部署

宝塔计划任务：Shell 脚本，每分钟执行：

`php /www/wwwroot/你的站点/cron/scrape_minutely.php`

数据库不需要迁移。覆盖 v5.1.1 整包或升级包后，再添加该计划任务。

修改文件：

```
原始源码/cron/scrape_minutely.php
原始源码/core/Guard.php
原始源码/core/Version.php
原始源码/CHANGELOG.md
README.md
资料索引.md
安装说明.txt
功能说明.txt
DEPLOY.md
.workbuddy/build_release_194.py
```

---

# v5.1.0（2026-10-12）★ 后台网盘账号只留 6 家（隐藏 115 / 123 / 天翼 / 移动 的配置入口）

## 用户反馈

后台「站点设置 → 添加网盘」里 115、123、天翼、移动这四张配置卡用不上，去掉，只留 6 家。

经确认的两项口径：**只去掉配置入口**（不彻底停用转存能力）+ **网盘类型标签也只留 6 家**（含磁力共 7 项）。

## 改动

### ① `Settings::panAccounts()` 10 家 → 6 家

后台的 Cookie / 默认转存目录 / 临时资源目录三件套就是按这个数组生成的，删掉四项即少四张卡。

★★★ **关键：这是「隐藏配置入口」，不是「停用转存能力」** ★★★

- `PanTransfer::$pans` 转存注册表里 **pan115 / tianyi / yidong 仍然保留**，三个适配器类（`Pan115Pan` / `TianyiPan` / `YidongPan`）也都还在 `core/pan/`；
- 所以**老站点里已经填好的 Cookie 照常生效** —— 这些网盘的链接依旧能点「点我去转存」，只是管理员在后台看不到、也改不了它的账号；
- 将来想恢复入口，把那三行加回 `panAccounts()` 即可，**配置一条都不会丢**，也不用重写任何适配器代码；
- `pan123` 本来就没有转存适配器（一直是原地址直跳），去掉入口无影响。

已把这条不变量钉进守卫（`⑦-3` / `⑦-3b`）：注册表项与适配器文件哪天被顺手删掉就会翻红。

### ② 网盘类型标签 11 项 → 7 项

`config/pan_types.php`、`core/Installer.php`（`mlinst_default_pan()`）、`tools/install-cli.php`（`$panList`）三处同步减到 **夸克 / UC / 光鸭 / 百度 / 阿里 / 迅雷 / 磁力**。

- **磁力保留**：它不是网盘，是直链类型（注册表 `own=true`，不转存但前台要能显示）；
- **库里已有的条目不受影响**：它们的标签值原样留在 `download_url` 里，前台照常展示，只是后台「+」新加条目时下拉里选不到这四个了（改回 11 项即可，无需动数据）。

### ③ 未改动（有意保留）

`Settings::schema()` 的 `pansou_types`（**PanSou 搜索哪些网盘**的勾选项）保持 14 项 —— 那是控制搜索范围用的，与账号配置入口是两回事；删掉会影响站长已配置的搜索偏好。

## 升级提醒

不改表结构、不改数据。升级后后台少四张卡，行为上唯一的可见变化是「新加条目时的网盘类型下拉」少了四项。

# v5.0.9（2026-10-12）★ 自动刮削出统计 + 新增「资料补全」（简介/导演/演员）

## 用户反馈（截图标注两处）

1. 自动刮削要出统计：**待处理多少、处理了多少、未处理多少，数字点进去有详细列表**；
2. 加一个「资料补全（简介、导演、演员等）」功能，**可以用百度百科等慢慢收集补全**。

经确认的三项口径：**数据源 TMDB 优先 + 百科回退** / **范围为全部条目（正式 + 暂存）** / **历史明细落库**。

## 一、统计与明细（可点数字看列表）

### 新增 `scrape_logs` 历史表
一次跑批 = 一行（筛选条件 / 目标条数 / 成功 / 失败 / 跳过 / 耗时 / **逐条明细 JSON**）。三处一致：`sql/install.sql`、`core/Installer.php`、`CacheStore::scrapeTableDdl()`；老站点由 `ensureScrapeTables()` 幂等自愈，**不需要手工建表**。`cron/rate_prune.php` 第 5 步按「只留最近 200 次」清理（按 id 倒序删，不按时间删 —— 计划任务停过几周也不会把最近的删掉）。

### 三个口径
| 口径 | 判据 |
|---|---|
| 待处理 | 缺本次关注字段的条目数（补图看海报/背景；补资料看简介/演职员/年份/评分） |
| 已处理 | 字段齐全的条目数 |
| 未处理 | 待处理 − 历史记录里成功处理过的条数（**去重**），不是拍脑袋给的数字 |

★ `media_items` **没有 `created_at`**（最早建表时就没这列，只有 `cached_at` / `updated_at`），所以统计**只按字段是否齐全**判断，不做时间维度 —— 宁可少给一个维度，也不给错数字。

### 面板
两组统计卡（补图 / 补资料）× 三视图（待处理 / 已处理 / 未处理），共六个可点数字。点开是明细弹窗：
- **待处理** → 列出还缺什么的条目（含「缺什么」列，翻成人话：缺海报 / 缺简介…）；
- **已处理** → 明示「这些条目本来就无需处理」，**不列一份错的清单糊弄站长**；
- **未处理** → 先给「还没成功补过」的条数，再附最近 200 条待处理条目。

另：「最近一次跑批」的逐条明细（成功 / 失败 / 数据源 / 补到哪些字段 / 失败原因）也存下来了，刮削跑完后日志里给一个入口直接看。

## 二、资料补全（简介 / 导演 / 演员 / 年份 / 评分 / 类型）

`CacheStore::autoEnrichFields($type, $limit, $fields, $scope)`：

- **数据源按用户要求「两者都要」**：先查 TMDB（按 source_id → 标题搜详情，一次拿全简介 + 演职员 + 年份 + 评分 + 类型），**查不到的冷门条目（国产短剧、老片、网络电影）回退百度百科**。
- ★ **百科只补简介**：卡片接口返回的是自然语言摘要，硬抠人名会把垃圾写进库里，污染人物页与首页「热门演员」。宁可少补一个字段。
- 字段可勾选（六个），支持「只补简介」这种小批量补法。

## 三、口径变更：待处理范围扩到全部条目

`autoScrapeImages()` 与 `autoEnrichFields()` 的 `scope` 默认值改为 **`all`**（正式 + 暂存都参与），按用户要求「全部条目都算待处理」—— 以前上架的老条目缺图的、缺资料的都能补回来。传 `scope=pending` 可退回 v3.9.4 的旧行为。

★ 顺带修一个隐患：刮削成功后的「自动上架」原本无条件写 `status='active'`。全量模式下正式条目本来就是 active，无脑写虽不出错，但会掩盖状态语义 —— 改成 `CASE WHEN status='pending' THEN 'active' ELSE status END`，显式表达「只晋升暂存」这条不变量。

## API

| 端点 | 用途 |
|---|---|
| `POST item {auto_scrape:1, scope}` | 补图（默认全量） |
| `POST item {auto_enrich:1, fields, limit, scope}` | 资料补全 |
| `GET item?scrape_stats=1&kind=` | 统计 |
| `GET item?scrape_detail=1&log_id=` | 某次跑批逐条明细 |
| `GET item?scrape_pending=1&kind=` | 待处理清单 |

## 守卫

新增 `verify_scrapeenrich_509.mjs`（**69/0**，9 组，含 3 条反向自测）+ `verify_scrapeenrich_509_dyn.mjs`（**20/0**，vm 沙箱验异步链路）+ `_run.sh`+`_css.mjs`（无头 Chrome **9/0**，验六张卡回填 / `<button>` 可点 / 六字段勾选 / 表格样式）。

## 升级提醒

不改已有表结构。`scrape_logs` 由程序自动建；建表失败时数字位显示 `?` 并提示「去宝塔把该库用户权限改为所有权限」，真实报错写进 `log/php-error.log`（搜 `[scrape]`）。


# v5.0.8（2026-10-12）★ 网盘按钮文案统一为「某某网盘」

## 用户反馈（截图标注三处）

1. 按钮写成「光鸭下载网盘」—— 应该是「光鸭网盘」；
2. 按钮右边挂一个「点我去转存」小胶囊 —— 去掉；
3. 页面下方那段「标着『点我去转存』的按钮，点击后立即开始转存…」说明 —— 不要说明，和普通条目一样。

顺带明确一条不变式：**转存后的地址是网盘地址，不是站内地址**（这条 v5.0.7 已实现，本版钉进守卫防回归）。

## 改动

### ① `normalizePanLabel()` 重写（双后缀叠加的根因）

DB 里存的 label 是 PanSou 补地址时写的 **「光鸭下载」「夸克下载」**（`<网盘名>下载`），而旧规则把「2~4 个中文字符」一律补「网盘」，于是「光鸭下载」被补成 **「光鸭下载网盘」**。新规则：

1. 空 → 「下载」
2. 纯英文/数字（115、UC、PikPak）→ 原样（不拼成「115网盘」）
3. 以「下载」结尾 → **先剥掉「下载」**，剩 1~4 字再补「网盘」（光鸭下载 → 光鸭网盘）
4. **名字里含「网盘」，或以「云盘」结尾** → 原样，不再补
   （★ 第 4 条里「云盘」这一支是关键：阿里云盘 / 迅雷云盘 / 夸克云盘都是 4 字、恰好落在「2~4 字补网盘」的区间，不拦就会拼成「阿里云盘**网盘**」—— 这是本次顺手挖出来的第二个同类 bug）
5. 其余 2~4 个中文字符 → 补「网盘」
6. 更长或形态不明的 → 原样（保留站长自定义的名字）

已用 33 例期望表实测：光鸭/夸克/百度/迅雷/天翼/移动/磁力/电驴 → 「XX网盘」；阿里云盘/迅雷云盘/移动和彩云/115/UC/123/PikPak → 原样；无任何「下载网盘 / 云盘网盘 / 网盘网盘」。

### ② 按钮与说明

- **去掉 `<em>点我去转存</em>` 小胶囊**，`dl-todo` 修饰类一并删除 —— 未转存与已转存按钮现在 class 完全相同（都是 `dl-btn`），访客看不出差别。
- **删除下载区整段说明文字** `<p class="hint">`，与普通条目一致。
- `data-dl-go="1"` / `data-dl-id` / `data-dl-idx` **全部保留** —— 前者让 JS 能识别「点了要现转」并弹等待遮罩，后两者是 `dl_hits` 计数的数据来源。
- **等待期反馈改由全屏遮罩承担**（转圈 + 「正在转存到本站网盘… 网盘接口较慢，通常 5~15 秒」）；`dlclick.js` 不再改按钮文案（内部状态不该让访客操心）。
- `item.css` 删除 `.dl-todo` 三条规则，保留 `.dl-busy`（转存中禁连点）与 `.dl-mask`（遮罩）；`.dl-pending` 保留 —— 仅供尚未重生静态页的老页面正确显示。

### ③ 死变量清理

`downloadBlock()` 的 `$pending` 计数随说明文字一起取消，已移除。

## 守卫

新增 `verify_panlabel_508.mjs`（**28/0**，5 组）：33 例文案期望表 + PHP 源码与等价实现的规则一致性（含反向自测）+ 按钮模板不含胶囊/说明 + 两种按钮 class 相同 + CSS/JS 同步 + 转存后地址是我盘地址。

同时把 `verify_dlclick_507.mjs` 的三条**形态断言**改成**不变量断言**（③-1 只要求「仍是可点的 `/dl/go` 链接」、③-4 改为「不得残留旧版说明文案」、④-9 改为「`.dl-busy` 防连点仍在」）—— 外观怎么演进都不该把守卫打红，外观细节交给 508 单独钉。

## 升级提醒

不改表结构、不改配置。⚠️ **本版改了详情页模板，必须重生静态页**（后台「重新生成静态页」/ `php cron/build_static.php`），否则老静态页仍显示旧文案。


# v5.0.7（2026-10-12）★ 下载按钮改为点击转存 + 访问统计可自查

## 用户反馈两件事

1. 详情页下载按钮标着「转存中」，**点了没反应**（灰色不可点），还得等 cron + 刷新页面 → 要求改成**点击后才开始转存**。
2. 后台「访问统计」PV / UV / 来源 / 设备**全是 0**，问是不是收集不到 → 排查后加了一整套自检。

## 一、下载：改为「点击才转存」

### 改动
- **取消全部预转存**：删掉 `core/Sync.php` 里「采集入库后延迟 5 分钟调度 `cron/pan_transfer.php`」的整段。宝塔计划任务里的 `pan_transfer.php` 也**不再是必需**（删掉不影响下载）。采集链路因此还少 spawn 一个延迟子进程。
- **新门面 `PanTransfer::transferOnClick($itemId, $idx)`**：三段式 —— ① 先查 `resolveDownloadUrl()`，有我盘地址就零开销秒跳；② 没有才现场 `transfer()`（2~15 秒）；③ 成功后 `record()` 记账 + 改写 `download_url` 落库 + **重生该条静态页**，于是**第二次点同一按钮就是秒开**。
- **新端点**：
  - `GET /dl/go/{id}/{idx}` —— 302 跳转版（禁用 JS 时也能用）；
  - `GET /api/v1/dl_go?id=&idx=` —— JSON 版，给 JS 拿跳转地址；**挂在通用 `/api/v1/` 路由之前**（那里要验 API 调用令牌，访客点击没有令牌）。带 10 秒 3 次的频次闸门，防被脚本批量刷转存把网盘灌满。
  - 老路由 `/dl/{id}/{idx}` 保留（正则加了可选的 `go/`），兼容 v5.0.4~v5.0.6 生成的老静态页。
- **按钮**：`downloadBlock()` 里「转存中」灰色 `<span>` 换成真正的 `<a class="dl-btn dl-todo" href="/dl/go/{id}/{idx}" target="_blank" data-dl-go="1">…<em>点我去转存</em></a>`。**渐进增强**：无 JS 时服务端同步转存照样跳得动。
- **新前端 `assets/js/dlclick.js`**：事件委托拦 `data-dl-go` → 按钮加 `.dl-busy` 脉冲 + 弹遮罩（转圈 +「网盘接口较慢，通常 5~15 秒」）→ 成功则**新标签页**打开我盘地址并把按钮就地改成直链（下次秒开）；失败弹错误遮罩 3 秒后自动收起。**三处兜底**（fetch 不可用 / 同步抛错 / Promise reject）都回落成整页跳 `/dl/go`，保证按钮永远不会卡住。转存失败时服务端 302 回详情页带 `?dlerr=`，由同文件的第二段读出来弹提示并 `history.replaceState` 清掉 query。
- **样式 `assets/css/item.css`**：新增 `.dl-todo`（描边，视觉上比直链按钮略淡一档）、`.dl-busy`（脉冲动画 + 禁指针）、`.dl-mask`（遮罩 + `@keyframes dl-spin`）。`.dl-pending` 保留 —— 老静态页还在用它。

### 判定口径变化（`isPendingTransfer`）
含义从「等待后台转存」变成「**可以现场点着转存**」：能识别网盘 + 已配置账号 + 还不是我自己的分享，三条缺一即退化为原始直链（点了也只会失败，不如直跳）。

## 二、访问统计：全 0 可自查

### 根因排查
代码链路本身是通的（`pageview.js` → `/track?t=page` → `recordPageView()` → 两张表），**全 0 的真正原因是「静默」**：`recordPageView()` 与 `ensureVisitTables()` 的 catch 都是空注释，INSERT 失败 / 建表失败（多半是数据库用户缺 `CREATE` 权限）全都悄无声息，站长既看不到报错也查不到原因。

### 改动
- **异常必须留痕**：两处 catch 各加一行 `error_log('[ml_visit] …')`，落进 `log/php-error.log`。仍然不抛不显示（统计绝不能影响前台），但事后可查。
- **新增 `CacheStore::visitDiag()`**：把链路逐环实测出来 —— `assets/js/pageview.js` 在不在、已生成的静态页里有多少比例含埋点、两张表行数与最后写入时间、**主动写一条探针能否成功**（写完立刻按严格条件清理，不误删当天真实数据）。
- **check.php 新增第 12 组「访问统计埋点」**：脚本文件 / 静态页覆盖 / 两张表 / 写入探测 / 数据新鲜度 / `/track` 端点可达（404 判定为伪静态拦截并给修法），每项都带具体动作而不只是「失败」。
- **后台面板**：全 0 时不再只显示「暂无数据」，改成一张排查清单并指路自检页第 12 组；点名「换包后没重生静态页」这个头号原因。

### 换包后必做
本版改了详情页模板与按钮，**必须重生静态页**（后台「重新生成静态页」/ `php cron/build_static.php`）—— 不重生的话老静态页既没有「点我去转存」按钮，也没有埋点脚本，统计必然仍是 0。

## 升级提醒
不改表结构、不用重配账号。**宝塔里那条 `cron/pan_transfer.php` 计划任务可以删了**（留着也不影响，只是手动补转存通道）。


# v5.0.6（2026-10-12）★ 后台可浏览网盘目录（选择器）

## 做了什么

### 背景
v5.0.5 有了 9 家网盘的自动转存，但「默认转存目录 / 临时资源目录」只能**手填路径**——而各家的目录标识根本不一样（夸克/UC 是 fid 串、阿里/迅雷/百度用数字或字符串 ID、光鸭是 parentId），用户不知道 ID 就只能留空走网盘根目录，一层层堆在根目录里。

### 新增
- **适配器层**：`BasePan` 新增 `listChildren()` / `rootDirId()` / `getFiles()` / `resolveDir()` 四个方法；
  - `listChildren($fid)` 返回统一形态 `[['id','name','dir'], …]`，8 家各按自家 API 实现；
  - `rootDirId()` 各家根目录 ID（夸克 `0` / 阿里 `root` / 迅雷·光鸭 `''` / 百度 `/` / 天翼·移动 `-11`）；
  - `resolveDir()` 把填进来的值统一解析成目标 ID —— **路径逐级查找（不存在则创建）**，也兼容直接写裸 ID；
  - `MagnetPan` 无目录概念，`getFiles()` 直接返回 err。
- **调度器**：`core/pan/Transfer::listFiles($pan, $fid)`（实例化适配器 → `getFiles` → 归一成 `['ok','list','error']`）。
- **门面**：`PanTransfer::listFiles()` —— 注册表校验 / own 类型拦截 / `hasAccount()` 门控 / 懒加载 `\Core\Pan\Transfer` / 异常兜底。
- **API**：`POST /api/v1/admin/pan_files`（参数 `pan`、`fid`、`kind`=`dir|tmp`）。
- **前端**：后台「添加网盘」每个网盘的目录输入框下新增「📁 浏览网盘目录」按钮 → `panDirModal` 弹窗（面包屑 + 点击下钻 + 「点此目录」），选完回填输入框；`admin.css` 新增 `.pan-dir-*` 系列 14 条样式。

### 修复 / 顺带
- 阿里 / 迅雷 / 光鸭的转存目标由「直接拿配置里的值当 ID 用」改为先 `resolveDir()` 解析 —— 之前填路径会静默失效。
- 天翼 / 移动的 `toFolderId` 由硬编码 `-11`（永远根目录）改为解析用户选的目录。
- `Settings::schema()` 目录字段的 tip 补「可用右侧浏览按钮挑选」。

### 支持浏览的网盘（8 家）
夸克 / UC / 光鸭 / 百度 / 阿里 / 迅雷 / 天翼 / 移动。115、123、磁力无目录枚举能力，仍手填。

### 升级提醒
不改表结构、不用重配账号。仅新增一个后台按钮与一个管理端 API。


# v5.0.5（2026-10-12）★ 重建为 11 网盘适配器框架

## 做了什么

v5.0.4 及以前只有夸克 / UC 两家能自动转存；百度 / 阿里 / 迅雷 / 115 / 天翼 / 移动 / 光鸭的链接要么被判「不支持转存」直接放行，要么按钮永远停在「转存中」。v5.0.5 照「心悦源码优化」`extend/netdisk` 的 `Transfer + BasePan + 适配器` 三段式架构**重建转存引擎**，按本项目约定（无 autoloader、设置驱动、Guard 登记制）改造：

1. **九家网盘可自动转存**：夸克 / UC / 光鸭 / 百度 / 阿里云盘 / 迅雷 / 115 / 天翼 / 移动和彩云。磁力链接本身就是可用地址（不转存、按钮直链）；123 网盘上游同样没有适配器，链接保持原样直跳。
2. **架构分层**：`core/PanTransfer.php` 只剩门面（识别 / 记录表 / 批量调度 / 前端解析），单盘转存逻辑拆到 `core/pan/`：`Transfer.php`（域名 → 适配器调度 + 契约归一）、`BasePan.php`（配置 / HTTP / 统一 `ok/err` 契约）、10 个适配器类 + `BaiduWork`（百度网络层）/ `XunleiOAuth`（迅雷 OAuth）两个辅助类。**新增网盘只需注册表加一项 + 放一个适配器类**。
3. **账号门控（防「转存中」卡死）**：`isPendingTransfer()` 新增 `hasAccount()` 判定 —— 只有**配了账号**的网盘才显示「转存中」，没配账号的盘链接保持原样直链（与 v5.0.4「不支持转存的盘保持直链」语义一致）。
4. **磁力三重规避**：注册表 `own=true / scan=false`，使 `isOwnShare()` 对磁力判真、`isPendingTransfer()` 恒假、`run()` 全站扫描跳过、`processItem()` 不写转存记录 —— 磁力按钮永远是直链。

## 具体改动

- `core/PanTransfer.php`（重构）：
  - `$pans` 注册表扩到 **10 项**（quark/uc/guangya/baidu/aliyun/xunlei/pan115/tianyi/yidong/magnet），新增 `pans()`（调度器读注册表的唯一入口）与 `hasAccount($pan)`（账号门控）。
  - `transfer()` 改为**门面**：账号预检（own 类型跳过）→ 懒加载 `core/pan/Transfer.php` → 委托；删掉已平移进适配器的内联 `api()/ensureDir()/findOrMakeDir()` 死代码。
  - `shareId()` 兼容无捕获组正则（磁力返回 `''`，不再触发 PHP notice）；`passcode()` 补 `?password=` 分支（115 分享链接的参数名，与 `LinkChecker::pwdOf` 口径对齐）。
  - `apiErr()` 由 private 改 public（适配器共用），文案「Cookie 已失效」改「账号已失效」。
  - `isOwnShare()` 增 own 短路；`isPendingTransfer()` 加 `hasAccount()` 门控；`run()` 扫描条件改按注册表 `scan_like` 数组 OR 拼接（参数化，`scan=false` 自动排除磁力）；`processItem()` 跳过 own 类型。
- `core/pan/`（新增 14 个文件）：`Transfer` / `BasePan` / `QuarkPan` / `UcPan` / `GuangyaPan` / `BaiduPan` / `BaiduWork` / `AlipanPan` / `XunleiPan` / `XunleiOAuth` / `Pan115Pan` / `TianyiPan` / `YidongPan` / `MagnetPan`。
  - 统一契约：适配器 `ok($msg,$data)` / `err($msg)` → `['code'=>200|500,…]` → 归一为 `['ok'=>bool,'new_url'=>'','share_id'=>'','error'=>'']`；token 刷新后经 `BasePan::saveCfg()` **回写 Settings**（`pan.<代号>_cookie`），下次直接用新的。
  - 修正上游真 BUG：115 / 天翼 / 移动的 helper 返回 `code` 键而 `transfer()` 判 `status`（永远走失败分支），本移植统一改判 `code`。
- `core/Settings.php`：`panAccounts()` 从 6 家扩到 **10 家**（quark/uc/guangya/baidu/aliyun/xunlei/pan115/pan123/tianyi/yidong），`panMap()/schema()` 自动生成 cookie/dir/tmp 三件套。
- `core/Guard.php`：`ml_guard_shield_files()` 登记 `core/pan/` 下 14 个文件 + `core/PanTransfer.php`（原来漏登记）+ `cron/pan_transfer.php`。
- `config/pan_types.php` / `core/Installer.php` / `tools/install-cli.php`：网盘标签列表扩到 **11 项**（含 123 与磁力；本列表只是后台下拉，能否转存看注册表 `adapter` 字段）。
- `cron/pan_transfer.php` 文档头、`assets/js/admin.js`（`SITE_HINTS.pan`）：更新为多网盘口径。

## 升级注意

1. **覆盖源码即可**，不改表结构（`pan_transfers` 沿用 v4.6.4 结构）。
2. 各网盘账号仍在**后台「站点设置 → 添加网盘」**扫码登录配置；没配账号的网盘链接保持原样直链，不会卡「转存中」。
3. 部署后建议跑一次 `php cron/pan_transfer.php --limit=20` 观察日志；宝塔计划任务沿用 v4.3.11 起的 `cron/pan_transfer.php`（建议每 10 分钟）。

---

# v5.0.4（2026-10-12）★ 下载按钮回到「网盘直链」+ 未转存不再给原地址

## 做了什么

修正 v5.0.3「转存跳转」的两个体验问题：

1. **按钮不再套站内中转**：v5.0.3 把 `href` 写成 `/dl/{id}/{idx}`，鼠标悬停/右键复制时会暴露 `https://站点/dl/5/0` 这种中转 URL（站长想要的是访客直接看到「我的网盘地址」）。v5.0.4 改回**网盘直链**：`href` 就是该条目的下载地址本身（已转存的条目，其 `download_url` 早在转存时就被 `PanTransfer::processItem` 改写成我盘分享）。
2. **首次点击不再回退原始外链**：v5.0.3 对「尚未转存」的链接会 302 到采集来的**别人的分享**。v5.0.4 起**绝不再把访客送到原地址**——这类链接在前端直接渲染为「转存中」占位（灰化、不可点），等后台 cron 转存完成、静态页重生后自动变成我盘直链。

## 具体改动

- `core/Views.php::downloadBlock()`：`a.dl-btn` 的 `href` 恢复为真实下载地址并带 `data-dl-id/data-dl-idx`；`PanTransfer::isPendingTransfer()` 为真的链接输出 `<span class="dl-btn dl-pending">…<em>转存中</em></span>`（提示文案同步说明）。
- `core/PanTransfer.php`：
  - **修 BUG**：`recorded()` 的 SELECT 补上 `new_url` 列 —— v5.0.3 只取了 `status, attempts`，导致 `resolveDownloadUrl()` 读 `$rec['new_url']` 恒为 `undefined`，「已转存跳我盘」这条分支**实际从未生效**。
  - 新增 `isOwnShare($url)`（按 `pan_transfers.share_id` 命中，走 `idx_share` 索引）与 `isPendingTransfer($url)`（= 本引擎支持的分享地址 且 还不是自己的分享；光鸭/百度/迅雷等不支持转存的网盘返回 **false**，保持直链）。
  - `resolveDownloadUrl()` 去掉「回退原始外链」，未转存成功一律返回 `''`。
  - 新增 `refreshStaticPage()`：转存改写落库后**立即重生该条静态详情页**，让「转存中」占位马上变成可点的我盘直链（v5.0.3 漏了这一步，否则按钮永远停在旧状态）。
- `index.php` 的 `/dl/{id}[/{idx}]` 端点**保留但降级为兼容兜底**（老静态页可能还写着 `/dl/…`）：未转存成功时 302 回该条详情页（不存在则回首页），不再跳到原始外链。
- **计数**：按钮已直链，服务端没有机会数点击 → 改由 `assets/js/pageview.js` 用**事件委托 + `sendBeacon`（退回 `fetch(keepalive)` / 1×1 img）**在跳转前上报 `/track?t=dl&id=..&i=..`；`track.php` 新增 `t=dl` 分支调用 `CacheStore::bumpDlHits()`（`dl_hits` 仍与 `clicks` 独立，会话 1 秒节流）。
- `assets/css/item.css`：新增 `.dl-btn.dl-pending`（复用渐变底 + 降不透明度 + `cursor:not-allowed`，深浅主题自适应，不引入新变量）。

## 升级注意

1. 覆盖源码后**必须跑一次静态页重生**（`StaticGen::buildAll()`，或后台「重新生成静态页」，或 `php cron/build_static.php`）——详情页是预生成的 `/uisc/{id}.html`，不重生则按钮仍是旧的 `/dl/…` 中转链接。
2. `pan_transfers` 表无需改动；`dl_hits` 列沿用 v5.0.3 的 `sql/upgrade_v503_add_dl_hits.sql`。
3. 转存能力仍只覆盖**夸克 / UC**；光鸭 / 百度 / 迅雷 / 磁力等继续保持原样直链（引擎不支持自动转存，也就没有「我盘地址」可给）。

# v5.0.3（2026-10-12）★ 转存跳转（点下载按钮 → 我盘分享）

## 做了什么

把「点下载按钮直接跳别人网盘」升级为**转存跳转**闭环：

- **前端**：详情页下载按钮的 `href` 由原始外链改为站内端点 `/dl/{id}/{idx}`（`core/Views.php::downloadBlock`）。
- **端点**：`index.php` 新增 `^/dl/(\d+)(?:/(\d+))?$` 分支，复用 `core/PanTransfer.php::resolveDownloadUrl()`：
  - 该链接**已被后台转存成功**（`pan_transfers.status='ok'` 且有 `new_url`）→ 302 跳转**我盘分享**；
  - 否则 → 302 回退**原始外链**；
  - 解析失败 / 条目不存在 / 序号越界 → 友好回首页。
- **计数**：`core/CacheStore.php::bumpDlHits()` 每次跳转 `dl_hits + 1`，与详情页 `clicks` **独立统计**（`media_items` 新增 `dl_hits` 列，`install.sql` + `core/Installer.php` + `sql/upgrade_v503_add_dl_hits.sql` 三处同步）。

## 为什么这样设计

- **后台异步、不阻塞**：转存仍是 cron 驱动（`cron/pan_transfer.php`），点下载时**不现场转存**；未转存的链接首次点击先跳原始外链，后续 cron 转存完成后，再点的访客自动落到我盘分享。
- **静态详情页需重生**：`/uisc/{id}.html` 是预生成文件，改了 `downloadBlock` 后须在服务端跑 `StaticGen::buildAll()`（或等计划任务）让按钮改走 `/dl/`；在此之前按钮仍是直链，**功能不回退**（直链依然可用）。
- **已转存条目本身也被改写**：`PanTransfer::processItem` 成功后会把 `download_url` 改写成我盘分享，`resolveDownloadUrl` 即便命中改写后的链接也能正确跳我盘（双重保险）。

## 升级注意

已有站点执行 `sql/upgrade_v503_add_dl_hits.sql`（新增 `dl_hits` 列）；新装由 `install.sql` 自带。升级后建议跑一次 `StaticGen::buildAll()` 让下载按钮改走转存跳转端点。

# v5.0.2（2026-10-09）★ 前台列表查询缓存（标签页/人物页不再每次全表扫描）

## 为什么先做前台

后台是站长一个人用，前台是**每个访客每次访问都过**的路径 —— 收益面大得多。
先量了前台的真实成本：

| 项 | 实测 |
|---|---|
| 前台静态资源（6 个文件） | 54.9 KB → **gzip 19.4 KB** |
| 首页 HTML | 已有 `max-age=60` 短缓存（v4.8.7） |

静态资源已经很轻（19.4 KB 且一年强缓存），**瓶颈不在带宽，在服务端查询**。

## 定位到的真实瓶颈

### ① 标签页筛选 = 必然全表扫描

```sql
WHERE genres LIKE '%关键词%' ESCAPE '!'
```

- `genres` 是 **TEXT 且无索引**（`sql/install.sql` 实测确认）；
- **`LIKE '%x%'` 是前导通配符** —— B+ 树索引只对「前缀确定」的查询有效
  ⇒ **无论怎么加索引都用不上，必然全表扫描**。

### ② 每次请求打 2 条这样的查询，且完全没有缓存

`home.php` 里：

```php
$rows  = CacheStore::listBySort($sort, $type, $page, $per, $q, $tag);
$total = CacheStore::countItems($type, $q, $tag);
```

实测：项目里标签、人名聚合接口**都有 600 秒缓存**，
唯独 `listBySort()`（2023 行）与 `countItems()`（442 行）**一条缓存都没有** ——
偏偏这两条是最重的。⇒ 每个访客、每次翻页都要重扫一遍全表。

## 本版做法

给这两个函数加**短缓存**，SQL 一行未改：

```php
$cacheable = ($q === '');           // 搜索不缓存（见下）
if ($cacheable) {
    $ck = 'list_v1|' . $sort . '|' . $type . '|' . (int) $page . '|' . (int) $per . '|' . $tag;
    $hit = self::cacheGet($ck);
    if ($hit !== false) { return $hit; }
}
```

四个刻意的选择：

| 选择 | 理由 |
|---|---|
| **搜索（`$q` 非空）不缓存** | 搜索词是访客自己输入的，卡数多、命中率低，还会在 cache 目录堆大量一次性文件 |
| **缓存键含全部维度**（`$sort`/`$type`/`$page`/`$per`/`$tag`） | 任何一个不同就是另一条缓存，**杜绝串味** |
| **`countItems` 的键不含 `$sort`/`$page`/`$per`** | 总数与排序分页无关，**避免同一页因排序不同产生多条重复的计数缓存** |
| **TTL 60 秒** | 与首页 HTML 的 `max-age=60` 对齐 —— 两层缓存窗口一致，整体行为才自洽 |

### ★ 关键细节：命中判据必须用 `!== false`

```php
if ($hit !== false) { return $hit; }     // ✅
if ($hit)            { return $hit; }     // ❌
```

`cacheGet()` 用 `false` 表示「未命中」，而 **`0` 与 `[]` 都是有效缓存值**
（标签页正好 0 条时）。写成真值判断 ⇒ 这类页面每次都重查，缓存形同虚设。

实测复核（逐个输入验证 `cacheGet` 的返回语义）：

| 缓存内容 | `cacheGet` 返回 | 被 `=== false` 误判？ |
|---|---|---|
| `[]`（空列表） | `[]` | 否 ✓ |
| `0`（0 条计数） | `0` | 否 ✓ |
| `null` | `null` | 否 ✓ |
| `{"id":1}` | `[{"id":1}]` | 否 ✓ |

## ★ 顺带确认：人物页**不需要**优化（避免过度设计）

人物页走的是 `person_index` 表 + **`JSON_CONTAINS` 精确匹配**，
且该表有 `idx_name(name,kind)` 索引 —— **不是 LIKE 全表扫描**，
而且已有 600 秒缓存。⇒ 本版**不动它**，并在守卫里钉住这一点，
免得后人看到「genres 无索引」就顺手给人物页也加一层。

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_frontcache_502.mjs` | **31/0**（含 4 项反向自测） |
| 全量守卫 | 43 脚本绿（2 个参数型需传目录） |
| PHP / JS | 73/73、14/14 |

守卫里钉住的四条：
`①-3`/`②-3` 命中判据必须是 `!== false`、
`①-5`/`②-6` 缓存键维度完整、`①-7` 搜索不走缓存、
`①-9`/`①-10` 排序与分页 SQL 未被改动（v4.5.7 的 NULL 沉底修复仍在）。

# v5.0.1（2026-10-09）★ 后台概览页统计缓存（并说明为何「不拆 admin.js」）

## ★★ 先说两件被我推翻的判断（本版最有价值的部分）

### ① 「按页签加载 admin.js」—— 做不到：后台是单页应用

查证 `admin/index.php`：**11 个 `class="tabpane"` 全在同一个 HTML 里**，
靠 `switchTab()` 切 `display` 显隐 ⇒ **后台是 SPA**。
用户只要看一眼后台，11 个页签的 JS 就**全下载了**，按页签加载无从谈起。

（顺带纠正一个我先前的误解：我一度以为这 11 个页签是「每次只显示一个」，
实际上 `display:none` 的页签**内容仍在 DOM 里**，JS 无论如何都会被下载。）

### ② 拆分收益远小于预期 —— 已与用户确认**不拆**

实测体积与压缩率：

| 文件 | 原始 | gzip |
|---|---|---|
| `assets/js/admin.js` | 151.2 KB | **46.9 KB**（31%） |
| `assets/css/admin.css` | 56.0 KB | **12.3 KB**（22%） |

而 v5.0.0 刚给 `/assets/` 配了**一年强缓存 + `immutable`** ⇒
**回访时这两个文件本来就是 0 流量**。
拆成真多页只能省「首次打开后台那一次」的几十 KB，
代价却是改 2860 行 JS + 11 个页签的路由 + 弹窗/分页/批量操作的页签耦合
（`switchTab('overview')` 就同时触发 4 个接口，说明这些逻辑是交织的）。

⇒ **结论：投入产出不成比例，本版不拆。**
真正值得做的是**降低每次都要重算的成本**（下面就是）。

## 本版做的：概览页统计缓存

### 问题定位

概览页进入时 **4 个接口并发**发出（实测 `switchTab` 的 overview 分支）：

```js
loadStats(); loadTopItems(); loadOverviewStats(); loadVisitStats();
```

其中 `overviewStats()` 一次要跑 **11 条聚合查询**（全表 `SUM` / `COUNT` / `GROUP BY`）
—— 条目上万后，**每次打开后台都要把表扫一遍**，概览页明显发顿。

项目里本来就有完整的文件缓存机制（`cacheGet` / `cacheSet`，
原子写 + TTL 持久化 + 版本失效），但**只用在标签上，统计接口完全没用**。

### 做法

`overviewStats()` 拆成「**外层缓存 + 内层口径本体**」，`stats()` 同理：

```php
public static function overviewStats()
{
    $ck = 'overview_stats_v1';
    $cached = self::cacheGet($ck);
    if ($cached !== false) { return $cached; }      // 命中即返回
    $out = self::overviewStatsUncached();            // 口径本体：SQL 一行未改
    self::cacheSet($ck, $out, 120);
    return $out;
}
```

三个刻意的设计选择：

| 选择 | 理由 |
|---|---|
| **只在外层加缓存，函数体一行动不动** | 里面的 SQL 是纯只读聚合，短期不变；口径 SQL 保持原样，零回归风险 |
| **缓存键带版本号 `_v1`** | 将来口径变了递增即可失效，**不必记得手动清缓存**（v4.7.1 踩过这个坑） |
| **不写「精准失效」而用「短 TTL + 手动强刷」** | 写库入口有 8 处以上（同步/保存/删除/导入/转存…），**漏一处就出现「改了数据统计还是旧值」**，比多扫几秒表更麻烦 |

TTL 取 **120 秒**：既让连续开后台不再重复扫表，又能保证「改完数据最多两分钟就反映到统计」。

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_statcache_501.mjs` | **31/0**（含 4 项反向自测） |
| `verify_ovstats_463`（口径断言） | 改成读「本体 + 缓存层」合并串，**60/0** |
| 全量守卫 | 42 脚本绿（2 个参数型需传目录） |
| PHP / JS | 73/73、14/14 |

★ 守卫里把**两条「不拆」的决定**钉成了断言：
`③-1` 后台仍是单页应用（若将来有人真拆成多页，断言会提示重新评估按需加载）、
`③-4` 无 `admin-*.js` 半途而废的拆分残留。

## ★ 顺带修的：守卫被打断后的连带修复

`overviewStats` 拆成两个函数后，`verify_ovstats_463` 立刻全线假 FAIL
（它用 `fnBody('overviewStats')` 只截到 3 行的缓存壳，所有口径断言全部失效）。
⇒ 改为读「本体 + 缓存层」合并串，并**新增对缓存层本身的断言**
（是否命中即返回、键是否带版本号、TTL 是否合理、本体是否被调用而非复制 SQL）。

⇒ 提炼成通用规则：**凡把一个函数拆成两个，任何只按函数名取体的守卫都会漏掉另一半** ——
这与 v4.9.9「CSS 拆分导致 6 个守卫假 FAIL」是同一类问题的不同表现形式。

# v5.0.0（2026-10-09）★ 补上「外链化」的另一半：静态资源缓存响应头 + 部署自检

- **用户诉求**：「我把能想到的 CSS 和 JS 分离，三重缓存，你结合所有知道的源码帮我做进一步优化」。

## ★ 先说两个我判断错、被源码实证推翻的事（本版最有价值的部分）

### ① 「在 PHP 里给静态资源加 ETag / Last-Modified」—— **错，不会生效**

- `index.php` 与 `home.php` **都不接管 `/assets/`** ⇒ 静态资源由 Nginx/Apache **直接从磁盘吐出，不经 PHP**。
  PHP 端写的响应头**不在请求路径上**，加了也白加。
- 而且 **Nginx 默认 `etag on` 就已经提供 ETag + 304**，本来不缺。

⇒ 真正的缺口**只有一个**：**没有 `Cache-Control` 响应头**。
浏览器因此每次回访都要重新校验、校验不过就重传整个文件 ——
**这就是「CSS/JS 都分离了却没感觉到快」的真正根因**（外链只解决了一半，缓存头是另一半）。
同理，默认配置往往只压 HTML，**CSS/JS 的 gzip 常被漏掉**。

### ② 「给图片加尺寸占位防 CLS」—— **也错，早就做完了**

`.c-thumb{aspect-ratio:2/3}` + `.c-thumb img{width:100%;height:100%;object-fit:cover}`、
`.hero .poster{aspect-ratio:2/3}`、`.cast .p img{width:110px;height:150px}`，
且 `loading="lazy"` 四处齐全 ⇒ **CLS 防护本就完整**，本版不新增任何图片属性，
只把「不许退化」钉成守卫断言（防止后人误以为「还没做」而重复加一遍）。

## 本版真正做的四件事

### ① `软件素材/nginx-static-assets.conf`（完整版，直接粘贴用）

```nginx
location ^~ /assets/ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
    etag on;
    location ~ /\.    { return 404; }
    location ~ \.php$ { return 404; }   # assets/ 里绝不允许执行脚本
}
location ~* ^/uisc/.*\.html$ { expires 5m; add_header Cache-Control "public, max-age=300, must-revalidate"; etag on; }
gzip on; gzip_vary on; gzip_comp_level 6; gzip_min_length 1024;
gzip_types text/css text/javascript application/javascript application/json application/xml image/svg+xml;
```

- **`^~` 前缀符不可省** —— 让 `/assets/` 优先于后面的 `location ~* \.(sql|md|…)$` 匹配。
- **`immutable` 为什么安全**：URL 带 `?v=版本号`，内容变了必然换版本号 ⇒ 同 URL 内容永不变。
- `/uisc/*.html` **不能用一年强缓存**（内容随条目变更），用 5 分钟 + 每次校验。
- 附 MIME 类型、HTTP/2、独立静态域名三段**可选**配置，各写了启用前的前提与风险
  （⚠️ `types` 块重复声明会导致 nginx **启动失败、站点全挂**，已明确警告）。

### ② `.htaccess` 补 Apache 等价配置（项目根自带，Apache 环境自动生效）

- ★ **`mod_expires` 只发已废弃的 `Expires` 头，主流浏览器不认** ——
  必须自己再写一遍 `Cache-Control`，否则等于白设。
- ★ **`SetEnvIf` 必须写在 `Header set … env=` 之前** —— 反了的话 env 变量还不存在，
  规则**静默失效**（不报错、也不生效）。

### ③ `DEPLOY.md` 新增「四·二」章节

含粘贴内容、`curl -I` 校验命令、**304 验证方法**，以及**「常见坑」表**：

| 现象 | 原因 |
|---|---|
| 没看到 `Cache-Control` | 伪静态没保存，或配置放在 `location / {}` **之后**被覆盖 |
| 没看到 `Content-Encoding` | 测的时候**没带 `Accept-Encoding: gzip`**（多数检测工具默认不带，会误判「未启用 gzip」，v4.8.7 已踩过） |
| 样式不生效 | `location ~* \.(…)$` 抢先命中 —— 必须用 `^~` |
| Nginx 启动失败 | 重复声明 `types` 块 |

### ④ ★ `check.php` 新增「9b · 静态资源缓存与压缩」自检段

**看得见才可运维** —— 之前自检页完全看不到这件事。新增两个函数（自检页刻意不依赖业务类）：

- `ml_probe_head()`：原生 cURL 发 **HEAD** 请求取响应头。
  ★ **必须带 `Accept-Encoding: gzip`**，否则服务器不返回 `Content-Encoding` → 误报「未启用压缩」。
  ★ 无 cURL 时如实显示「探测失败」并给出手动 `curl -I` 命令，**不静默跳过**
  （静默跳过会让用户误以为「测过了没问题」）。
- `ml_self_base_url()`：推断 scheme + host，推断不出时显示「未测」而不是给误导性结论。

判据分三级：

| 情况 | 判定 | 说明 |
|---|---|---|
| 有 `Cache-Control` 且 `max-age ≥ 604800` | ✅ ok | — |
| 有但 `max-age < 604800` | ⚠️ warn | 建议改一年 |
| **完全没有** | ⚠️ warn | 「这就是外链了却没感觉到快的根因」 |
| 设成 `no-store` / `no-cache` / `max-age=0` | ❌ **fail** | 等于完全没缓存 |

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_perf_500.mjs` | **53/0**（含 5 项反向自测） |
| PHP / JS 语法 | 73/73、14/14 全通过 |
| 两条「错判」钉成防退化断言 | ⑤-1 静态资源不经 PHP、⑤-3 图片尺寸在位 |

★ 守卫里有两条**专门防我自己重犯**的断言：
`⑤-1`「静态资源不经 PHP」（别为了加 ETag 把它写进 index.php，性能会立刻退化）、
`⑤-3a~g`「图片尺寸占位不许退化」（防止后人误以为「还没做」而重复加）。

## ★ 下一版计划：admin.js 按页签拆分

`assets/js/admin.js` **155 KB、172 个函数**挤在一个文件，后台**每个页面都全量加载**。
概览页其实只用其中一小部分。这是全站最大单文件，也是下一笔最值得的优化。
已确认用户同意「拆，但分两个版本发」。

---

# v4.9.9（2026-10-09）★ CSS 分离第三阶段：内页专属样式拆出 item.css（三项分离的收尾）

- **用户诉求**：「如果三项全做」—— 这是三项分离的最后一项。
- **收益**：`site.css` **48.6 KB → 36.2 KB（少 25%）**，首页/列表/标签/人物页都少传这一份。

| 项 | 改前 | 改后 |
|---|---|---|
| `site.css` | 48.6 KB（首页 + 内页混在一起） | **36.2 KB**（只剩首页 / 列表 / 标签 / 人物共用） |
| `item.css` | — | **20.4 KB**（详情页专属，71 条顶层 + 39 条 @media 规则） |
| 详情页带宽 | 36.2 KB | 36.2 + 20.4 = 56.6 KB（多付，但本来就要下这些规则） |
| 其它页面带宽 | 48.6 KB | **36.2 KB**（省 25%） |

- 详情页通过 `cssLinks(false, '', '', array('assets/css/item.css'))` 追加，
  输出点在 `site.css` **之后**（同源规则靠后者覆盖前者，顺序反了会失效）。
- 静态详情页（`/uisc/*.html`）复用 `Views::renderItemPage()`，**自动带上**，无需另改。

## ★★ 拆分最难的三点（都实测踩过）

1. **内页的响应式规则混在与首页共享的 @media 块里** ——
   例如 `.hero h1{flex:0 0 100%}` 在 `max-width:560px` 内。
   **只搬顶层规则 ⇒ 窄屏 hero 布局塌掉**（v4.8.2 已踩过：390px 下 h1 只剩 81px、标题竖排成一行一个字）。
   ⇒ 每条规则都记录「所处 @media 条件」，搬时原样保留。实测搬走了 **7 个 @media 块**。
2. **逗号混合规则** —— 形如 `.mainnav .navin,.cast,.toolbar{…}`，
   整条搬走会让其中一个文件缺样式 ⇒ **按逗号拆开**，共享部分留 `site.css`、内页部分进 `item.css`。
   本项目共 3 条（滚动条、`focus-visible`、`-webkit-overflow-scrolling`）。
3. **at-rule（`@keyframes`）会被结构化解析漏掉** ——
   第一版解析器只输出「选择器 + 声明块」，at-rule 没有选择器 ⇒ **被静默丢弃**：
   实测丢了 `bannerFloat` 与 `skeleton-shimmer` 两个动画定义。
   ⇒ 输出函数必须先判 `sel === undefined` 走 `raw` 分支。
   ★ 这类「解析后输出时漏掉某一类节点」的 bug 最危险：**自检若只查普通选择器就发现不了**。

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_itemcss_499.mjs` | **23/0**（含 3 项反向自测） |
| 内容零丢失 | 拆分前后「选择器 + @media 条件」集合**完全一致**（415 条，零丢失零新增） |
| 双向归属 | `site.css` 残留内页规则 **0**；`item.css` 混入首页选择器 **0** |
| 无头 Chrome 四视口对比 | 1440 / 820 / 680 **与拆分前逐属性一致**；390px 仅横向 padding 差 5px（见下） |

★ **390px 的 5px 差异已查明且可接受**：那条
`@supports` 刘海屏安全区规则
`.topbar .in,.banner .in,.wrap,.hero .in,.detail,…{padding-left/right:max(...)}`
原本是**跨首页与内页的逗号混合规则**，拆分后成两份、相对顺序变了，
`max()` 与基础 `padding:34px 18px` 的叠加结果随之微变。
横向内边距 13px vs 18px 视觉无感知，且**拆分后取的 18px 才是基础声明值**（更符合预期）。

## ★ 连带更新的守卫（拆分的必然代价）

拆走 CSS 后，**所有断言「内页样式」的守卫都必须改读 `site.css + item.css`**：

| 守卫 | 改动 |
|---|---|
| `castfit_481` / `notypetag_492` / `ovfit_482` / `taguni_480` | 改为读两个文件合并串（顺序固定 site+item，与线上一致） |
| `castfit_481` S7 / `ovfit_482` S8 | 配平检查改为**逐文件**校验 —— 两文件各自配平，合并串必然 183/182 |
| `csssplit_476` S1 / S12 | 体积阈值 40000 → 15000 / 判总量（site+item+user） |
| `csssplit_476` S9b / `slimsearch_475` S5 | 调用签名放宽为「首参是 false」，兼容新增的 `item.css` 参数 |
| `nooverview_479` | 读两个文件；E3 反向自测的样本 `.detail{` 也随之找到 |

## ★ 拆分工具本身的教训（写脚本时连栽两次）

我写的第一个拆分解析器**把「注释处理」与「规则切分」耦合**：
一版用「往回 `lastIndexOf` 找闭合标记」被多行块注释坑掉、漏搬 2 条；
另一版改用「注释替换成占位 token」，结果 token 粘进选择器、识别全部失效。

⇒ 第三版改成**两段式**：① 先把注释整段抠出来、记「它后面那个选择器」；
② 切分只用规则本体；③ 输出时按选择器把注释找回来。**注释与规则不再互相影响**。
★ 另：**解析 CSS 时必须先 `if (src.startsWith('/*', i))` 跳过注释** ——
不跳的话 `/* ===== 内页 ===== */` 后面的第一个 `{` 是**下一条规则**的花括号，
这段注释与 `.hero` 的选择器被拼成一个字符串 ⇒ 内页规则全部滞留 `site.css`
（而自检会显示「丢失」，两边都对不上，极难定位）。

⚠️ **item.css 头部注释里别写 `（/uisc/*.html）`** ——
其中的 `/uisc/*` 会被当成注释起始符，导致「注释 53/52」不配对（浏览器会吞掉后面的样式）。
已改成文字描述「详情页静态页，路径形如 /uisc 加数字加 .html」。

# v4.9.8（2026-10-09）★ CSS 分离第二阶段：后台 CSS 外链 + 打印样式拆出

- **用户诉求**：「限制 CSS 和 JS 已经分出来了，看下现有的结构是否还有什么要做分离，
  因为开始设计的时候没想到做分离进行加速」→「**三项全做**」。
- **★ 为什么分两个版本做**（版本号硬约束）：
  当前 4.9.7，而**三段式第三位不得为两位数**（本项目已因此作废过 3 个包）⇒ 只剩 4.9.8 / 4.9.9。
  故：**v4.9.8 做本两项**（收益最大、风险最低），
  **v4.9.9 做「内页 item.css」**（要连带搬媒体查询，风险最高）。
  ★ 订正一条容易误推的事：**4.9.9 之后就是 5.0.0，不存在「版本空间枯竭」**。
  递增是**任意位满 10 进 1 并归零**，`next()` 两级判断齐全，实测
  `4.9.8 → 4.9.9 → 5.0.0 → 5.0.1 → … → 5.0.9 → 5.1.0` 全部合法、**永不经过 4.10.0**。
  （v4.9.5 那次闸门拦下 4.10.0 是真的 —— 当时是**手写**了 `4.10.0`，
  不是 `next()` 推出来的。「某段不得超过 9」≠「进位时该段会变两位数」，别混为一谈。）

## ① 后台 CSS 外链（最大收益）

| 项 | 改前 | 改后 |
|---|---|---|
| `admin/index.php` | **113 KB**（其中 58 KB 是两段内联 `<style>`） | **53 KB** |
| 后台样式 | 每开一次后台重传 58 KB，且**整页 HTML 不可缓存** | 两次外链，可缓存 + 可 gzip |
| 新增文件 | — | `assets/css/admin.css`（57 KB）、`assets/css/admin-login.css`（2.6 KB） |

- 两段内联样式本来是**互斥**的（登录页 / 后台主页面），所以拆成两个文件各自按需加载。
- 走 `ml_admin_asset()`（后台不 require 前台 `Views.php`，子目录部署要自己拼 URL）。
- 抽取是**脚本化**的：正则切出 `<style>` 内容逐字落盘，并核对**文件字节数与原块完全一致**
  （2625 / 57364 B），避免手工复制出错或截断。
- ★ 注意：`admin.js` 早在 v4.7.8 就外链了，**CSS 一直漏着** —— 这就是「只做了一半」的典型。

## ② 打印样式拆出

- `site.css` 里的 `@media print`（约 0.7 KB）→ `assets/css/print.css`，
  由 `<link rel="stylesheet" media="print">` 加载。
- ★ **屏幕浏览时浏览器根本不下载它** —— media 不匹配的 `<link>` 会被跳过，连请求都不发
  （不是「下载了不用」）。同一文件不能既「全屏适用」又「只打印适用」，**必须分文件**。
- ★ print link 写在 `cssLinks()` 的 `$slim` 分支**之外** ——
  否则搜索页（动态裁剪 + `no-store`）会丢打印样式。

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_csssplit_498.mjs` | **26/0**（含 3 项反向自测） |
| 无头 Chrome 多视口实测 | 1440/820/680/390 **全部无横向溢出**；列数 6/4/3/3 正常 |
| 屏幕态 | `.topbar` 仍 `display:block` ⇒ **打印规则未污染屏幕** |
| 打印态（等效） | `.topbar:none`、背景变白、网格变 3 列 ⇒ 规则确实生效 |
| 后台 / 登录页 | 卡片圆角、按钮圆角、网格列数全部正确 ⇒ 外链 CSS 真的加载上了 |
| CSS 大括号配平 | 4 个文件全部配平 |

## ★ 顺带修掉的两处「分离特有的隐患」

1. **`verify_cssguard_485` 的 ②-4 曾硬编码 8 个静态资源名** ——
   v4.9.7 把 build 的 `need_assets` 改成自动扫目录后，这里 8 个名字全都不出现 ⇒ 假 FAIL。
   改为断言「自动发现逻辑本身在闸门里」+ **产物里的资源与源码一一对应（无孤儿）**，现 31/0。
2. **`verify_csssplit_476` 的 S14 反向自测只替换了第一个 `rel="stylesheet"`** ——
   `cssLinks()` 现有三处（site.css / user.css / print.css），
   非全局替换正好改中 print.css 那处 ⇒ **S6 仍 PASS ⇒ 自测形同虚设**。改全局后 18/0。

★★★ **新写守卫时自己踩的三个坑**（都记在脚本注释里）：
- 断言 admin.css 含 `.topbar` —— **后台根本没这个 class**（那是前台的），凭印象列选择器必假 FAIL。
  改为「从 admin/index.php 真实提取用到的 class，再算覆盖率」（现 79%）。
- 「print.css 的 link 带 `?v=`」用 `f.split('print.css')[1]` 判断 ——
  `?v=` 恰在**下一行**，取到的片段里没有 ⇒ 假 FAIL。改为取「该处之后 400 字符」。
- 反向自测 `replace('media="print"', ...)` —— 该串在文件里出现**两次**（一次在注释），
  非全局替换只改注释 ⇒ 断言恒真。改 `/g`。

# v4.9.7（2026-10-09）★ 自建第一方访问统计（PV / UV / 来源 / 设备 / 入口页），替代第三方统计代码

- **用户诉求**：把第三方统计代码（51.la / 百度统计 / GA）能做到的功能做进自己源码。
  按推荐方案实施：**方案 2**（cookie 精确 UV + 30 天自动清理 + 按天聚合表）
  + **方式 3**（fetch/img 双通道，cookie 优先、回退 IP+UA 哈希）
  + 报表放**后台概览页新增区块**。
- **★ 为什么值得做**：站长粘的统计代码是**同步外链**，第三方慢实测 1.03s、
  挂了 TCP 超时 10~30 秒 ⇒ **整页白屏**（v4.9.1 的 `protectThirdParty` 就是为此而生）；
  而且数据捏在别人手里。本版把同等能力做进自己项目，**零第三方依赖**。

## 数据结构：两张表，两种用途（不可混用）

| 表 | 用途 | 保留 | 谁在查 |
|---|---|---|---|
| `page_views` | 明细，一次浏览一行 | **30 天** | 实时在线、当天去重、近 7 天 Top |
| `page_views_daily` | 按天聚合，一天一行 | **400 天** | 趋势 / UV / 设备 / 渠道 |

★ 报表**只查聚合表** —— 否则 30 天后一次「近 30 天趋势」要扫几十万行。
DDL 三处同步：`sql/install.sql` ↔ `core/Installer.php` ↔ `CacheStore::visitTableDdl()`；
老站点由 `ensureVisitTables()` 幂等自愈（`CREATE TABLE IF NOT EXISTS`）。

## UV 口径（用户要求「一开始就定」，上线后无法回溯补齐）

1. **首选**：服务端签发的 HttpOnly 匿名 cookie `ml_vid`（32 位 hex，一年期，`SameSite=Lax`）—— 精确；
2. **回退**：`sha1(ip + UA + 当天)` —— 无 cookie 时用（多为爬虫 / 禁用 cookie）。
   ★ 哈希**带当天日期**，否则跨天会串成同一访客。
   ★ 明细表**只存 `ip_hash`，不存明文 IP**；查询串在服务端剥掉（不采集搜索词等隐私数据）。
- **已知取舍**：无 cookie 时同一办公室共用出口 IP 会被算成 1 个访客。
  有 cookie 的访客是精确的，没 cookie 的才走近似 —— 这是「方式 3」的固有代价。

## 采集：`track.php` 加 `t=page` 分支

- 点击埋点（`t=click`）**逻辑一字未动**，仍自增 `media_items.clicks`。
- PV 分支：`recordPageView()` 写明细 + `INSERT … ON DUPLICATE KEY UPDATE` 累加聚合。
  UV 判定 = 「当天该 `vid` 的明细数 ≤ 1」才 +1 ⇒ 同一人当天反复浏览只算 1 个 UV。
- ★ **3 秒会话节流**：前端是 fetch + img 双通道，不节流会让 PV 翻倍。
- 无 cookie 时下发 `Set-Cookie`；整个分支整体 `try/catch`，任何异常都仍返回 204。

## 前端埋点：`assets/js/pageview.js`（外链 defer，可缓存）

- **fetch 优先 + img 兜底**：企业代理/老 webview 会拦 fetch，img 仍能上报。
- `keepalive: true` —— 页面跳转时请求不丢；`credentials: 'same-origin'` —— 否则服务端签的 cookie 收不到。
- 完全静默：失败不弹错、不 `console.error`、不重试（重试会把 1 个访客放大成 N 个请求）。
- ★ **`item_id` 走 `<body data-item-id>`，绝不进 `script src` 的查询串** ——
  否则每个条目页的脚本 URL 都不同，**缓存 100% 失效**。

## 后端接口与报表

- `GET /api/v1/admin/visit_stats?days=30`（**位于 admin 分支内**，继承既有管理员鉴权）。
- `CacheStore::visitStats()`：今日/昨日 PV·UV、30 天趋势（**补齐无数据的日子**，
  否则图表跳日期看起来像掉量）、来源渠道、设备/系统/浏览器、热门页面、入口页、实时在线（近 5 分钟）。
- **纯聚合只读**，整体 + 分步 `try/catch`；有 `table_ok` 标志 ——
  老站首次部署 / DB 用户无建表权限时**明确提示**，而不是显示一堆 0 让人误以为「没人访问」。

## 后台概览页「访问统计」区块（不新增菜单）

今日 PV / 今日 UV（带环比）/ 30 天 PV·UV / 当前在线 + 近 30 天趋势（**纯 CSS 柱状**，深色=PV、浅色=UV）
+ 来源渠道 + 设备与系统 + 热门页面 + 入口页。
★ 与既有「流量与热度」的区分：那块是**条目维度**（多少条目有点击），这块是**站点维度**（多少人来过、从哪来）。

## 清理

`cron/rate_prune.php` 新增第 4 步：`pruneVisitData(30, 400)`。
★ 不清理的话 `page_views` 每次浏览插一行、无上限增长，半年后会把后台卡死。幂等、独立 try/catch。

## 验证

| 项 | 结果 |
|---|---|
| 新守卫 `verify_visit_497.mjs` | **68/0**（静态 8 组，含 5 项反向自测） |
| 新守卫 `verify_visit_497_dyn.mjs` | **16/0**（Node vm 实跑 pageview.js，含 2 项反向自测） |
| PHP / JS / 契约 | 73/73、**14/14**（见下）、全通过 |

**动态验证实测**（Node 造浏览器环境实跑埋点脚本）：
一次浏览**只发 1 个请求**（双通道没重复）；URL = `/track?t=page&p=%2Fuisc%2F123.html&id=123&r=…`，
**不含 `location.search`**（不采集搜索词）；fetch 带 `keepalive` + `credentials: 'same-origin'`；
**fetch 被 reject 时 img 兜底顶上，合计 2 个请求、无重试风暴**；完全无 console 输出。

## ★ 本版修掉的三个自身缺陷（都是先写错、再被验证抓出来）

1. **`VALUES(vid)`** —— 判 UV 的子查询里用了它，但 `page_views_daily` **根本没有 `vid` 列**
   （那是明细表的列）⇒ 执行必报 `Unknown column`。**静态校验完全查不出，只有真连库才炸。**
   改为 `?` 占位符传参（参数顺序：`day` → 10 个维度 → `vid`，`vid` 必须在最后）。
2. **`window.fetch`** —— 初版用 `window.fetch` 判断是否支持 fetch，
   但少数环境里 `window` 与全局作用域不等价 ⇒ 误判「不支持」而白走 img 兜底。
   改为 `typeof fetch === 'function'` + 直接调全局。**这个只有实跑 vm 才暴露。**
3. **`validate_js_194.mjs` 只扫 PHP 内联 `<script>`，从不扫 `assets/js/*.js`** ——
   v4.7.8 起前台 JS 全部外链，等于**外链脚本一个都没被校验过**。
   本版补上（脚本块 6 → **14**）。

★ 顺带修了 `verify_ovstats_463.mjs` 的 `fnBody()`：它用 `src.indexOf('\n}', i)` 截断，
函数体内有任何嵌套 `}` 就会**一路吃到下一个顶层 }** —— overviewStats 的窗口因此从真实的 4.7 KB
膨胀到 **64 KB**，把本版新加的 `created_at` 语句误判成「违规」。改为花括号计数配对后 **53/0**。

# v4.9.6（2026-10-09）后台「同步采集」也移进「站点设置」

- **用户诉求**（截图，紧接 v4.9.5）：顶层「同步采集」与「站点设置」并列，要求也移进去。
- **改法**：删顶层 `data-tab="sync"` 与 `#tab-sync` 面板，5 张卡片**原样**搬进 `#tab-site`，
  降级为二级页签 `data-stab="sync"`，排在「系统设置」之后。
  页签栏现为：`系统设置｜同步采集｜基础设置｜SEO 设置｜前台显示｜添加网盘·Cookie获取地址｜接口配置｜资源请求｜邮件服务`
- ★ **站点设置区现在是三个互斥区域**，各自一套加载/保存语义，**不能合并**：

  | 区域 | 容器 | 加载 | 保存 | 草稿变量 |
  |---|---|---|---|---|
  | 系统设置 | `#sysBox` | `loadSettings()` | `saveSettings()` | `TXT_DRAFT` / `PAN_DRAFT` / `AD_DRAFT` |
  | 同步采集 | `#syncPane` | `loadSync()` | `saveSyncParams()` | `SYNC_DRAFT` |
  | 其余 7 个页签 | `#sitePane` | `renderSite()` | `saveSite()` | `SITE_DRAFT` |

  ⇒ 抽成 **`SITE_PANES` 表 + `showSitePane()`** 统一承担互斥，
  而不是继续在 `switchSiteTab` 里堆 `if` —— **加页签时只改这张表**，
  漏一处就会出现「两个区域同时显示」或「点了没反应且不报错」。
- ★ `switchTab()` 里的 `if(t==='sync') loadSync();` 一并删除（同步采集不再是顶层页）。
- ★ `renderSite()` 的守卫从只挡 `sys` 扩成 **挡 `sys` 与 `sync`**，
  否则 sync 会掉进末尾 else 通用分支、拿站点字段清单渲染出一堆空卡片。
- ★ `collectSiteDraft()` 仍是**无条件调用**（沿用 v4.9.5 的结论）：
  它只读 `siteKeys()` 的元素，在 sys / sync 两页调用都是空转无害；
  加条件反而会让「站点页改值 → 切到这两页 → 切回」时草稿被静默丢弃。

- **动态实测**（真实 PHP 片段 + admin.js 真实函数，无头 Chrome 1440，控制台零错误）：

  | 步骤 | 结果 |
  |---|---|
  | 顶层 tab（11 个） | 已无「同步采集」「系统设置」 |
  | `#tab-sync` / `#tab-settings` 面板 | 均已不存在 |
  | 采集面板 7 个关键 id | 齐全（5 张卡片内容未丢） |
  | 点「同步采集」 | syncPane 显示 / sysBox 隐藏 / sitePane 隐藏 / 调用链 `collectSiteDraft → loadSync` |
  | **三区域轮转 7 次** `basic→sys→sync→basic→sync→sys→basic` | **每一步都恰好 1 个可见**（`basic:1 sys:1 sync:1 …`） |
  | sync 下调 `renderSite()` | 提前 return，siteBox 零改动 |
  | `switchTab()` | 不再调 `loadSync` / `loadSettings` |

- **守卫** `.workbuddy/verify_admintab_496.mjs`（**24/0**，含 3 项反向自测）：
  在 v4.9.5 的 13 项基础上扩展到三区域，新增「采集面板 7 个 id 齐全」「SITE_PANES 登记三区」
  「HTML 区域数与 SITE_PANES 登记数**双向一致**（⑩c，加页签不会漏一处）」「sync 下 renderSite 提前 return」。
- ⚠️ **写守卫时又踩一次正则跨界**：`/const SITE_GROUPS\s*=\s*\{[\s\S]*?\bsync\s*:/`
  会一路跨到后面的 `SITE_PANES`（同一对象里含 `sync:`）⇒ **假 FAIL**。
  修法 = 用花括号配对精确切块后再判断（与第七节·4「窗口要短」同源）。

# v4.9.5（2026-10-09）后台「系统设置」移进「站点设置」

- **用户诉求**（截图）：后台顶层的「系统设置」与「站点设置」两个 tab 并列，
  要求**把「系统设置」移动到「站点设置」里**。
- **改法**：顶层 `data-tab="settings"` 整个删除，原 `tab-settings` 面板内容
  搬进 `tab-site`，降级为**第一个二级页签** `data-stab="sys"`；
  两块区域用 `#sysBox`（系统设置）与 `#sitePane`（站点设置其余 7 个页签）**互斥显示**。
  ```html
  <div class="subtabs" id="siteSubtabs">
    <button class="stab" data-stab="sys">系统设置</button>
    <button class="stab active" data-stab="basic">基础设置</button>
    …
  </div>
  <div id="sysBox" style="display:none"> … setBox / saveSettings / resetAllSettings … </div>
  <div id="sitePane"> … siteBox / saveSite / resetSite … </div>
  ```
- ★ **两套保存逻辑刻意保持独立、没有合并**：
  - 系统设置走 `loadSettings()/saveSettings()/resetAllSettings()`，提交 `tmdb_api_key / adapters / pan_types`；
  - 站点设置走 `loadSite()/saveSite()/resetSite()`，按 `SITE_GROUPS` 分组**增量**提交 `SITE_DRAFT`，
    草稿收集靠 `collectSiteDraft()`（只认 `siteKeys()` 的元素）。
  合并会导致 Key / 网盘类型被空值覆盖。
  ⇒ `SITE_GROUPS` 里**故意不登记 `sys`**，并在代码里留了成因注释。
- ★ **`renderSite()` 加了 sys 提前 return**：否则 `sys` 会掉进末尾的 else 通用分支，
  拿「站点字段清单」去渲染系统设置页，渲染出一堆空卡片。
- ★★ **修掉首版自己引入的一个丢数据 bug**：`switchSiteTab()` 我先写成
  `if(SITE_TAB!=='sys') collectSiteDraft();`，想着「sys 页不该收站点草稿」。
  但这会让「基础设置改了值 → 点系统设置 → 点回基础设置」这条路径上草稿**被静默丢弃**
  （此时 `SITE_TAB==='sys'`，收集被跳过）。
  实际 `collectSiteDraft()` 只读 `siteKeys()` 的元素，在 sys 页调用它是**读不到任何元素 ⇒ 空转，无害**
  ⇒ 改为**无条件调用**。动态验证第 4 步的调用日志就是钉这条的。
- **动态实测**（真实 PHP 片段 + admin.js 真实函数，无头 Chrome 1440px，控制台零错误）：

  | 步骤 | 结果 |
  |---|---|
  | 顶层 tab 列表（12 个） | 已无「系统设置」 |
  | `#tab-settings` 面板 | 已不存在 |
  | 页签栏（8 个） | `系统设置｜基础设置｜SEO 设置｜前台显示｜添加网盘·Cookie获取地址｜接口配置｜资源请求｜邮件服务` |
  | 点「系统设置」 | sysBox 显示 / sitePane 隐藏 / setBox 已填充 / 调用链 `collectSiteDraft → renderSettings` |
  | 点回「基础设置」 | sysBox 隐藏 / sitePane 显示 / 调用链 `collectSiteDraft` |
  | sys 下调 `renderSite()` | 提前 return，siteBox 内容零改动 |
  | `switchTab('site')` | 不再调 `loadSettings` |
  | sys↔basic 往返两次 | 互斥显示均正确 |

- **新增守卫** `.workbuddy/verify_admintab_495.mjs`（**13/0**，静态 11 + 2 项反向自测）：
  钉住顶层 tab 已删、面板已搬、sys 排第一、sysBox 默认隐藏、switchSiteTab 的 sys 分支、
  `switchTab` 不再调 `loadSettings`、`collectSiteDraft` 无条件调用、`renderSite` 的 sys 守卫、
  `SITE_GROUPS` 不含 sys。
- ⚠️ **版本号**：本该是 4.10.0，但**三段式第三位不得为两位数**（本项目已因此作废过 3 个包），
  闸门直接拦下 ⇒ 改为 **4.9.5**。

# v4.9.4（2026-10-09）修 v4.9.3 漏掉的场景：**分类标签为空**的条目，指标会贴回标题右边

- **用户反馈**（截图，条目「逗鸟外传：萌宝满天飞（2016）」）：
  hero 里「评分 4.7 / 热度 0 / 点击 7」没有像其它条目那样贴到最右侧，
  而是紧跟在标题后面 —— 也就是没实现 v4.9.3 定稿的「标签+指标整组靠右」。
- ★ **根因（v4.9.3 自己挖的坑）**：v4.9.3 把 `margin-left:auto` 只挂在 `.hero .tags` 上，
  指望它「把整组推向右侧、指标紧跟其后」。但**分类标签为空时整块 `.tags` 根本不输出**
  （`.tags` 是 `{CLASSTAGS}` 占位符，`$genresHtml === ''` 时整块跳过）——
  于是 `.stats` 失去了那个 `auto`，被 flex 排回标题右边。截图那条就是 genres 为空的条目。
- **修法（结构性，与数据无关）**：把两组包进同一个容器，`auto` 由**容器**承担：
  ```html
  <div class="topline">
    <h1>{$title}</h1>
    <div class="hero-right">
      {CLASSTAGS}          <!-- 为空则整块不输出 -->
      <div class="stats">评分 / 热度 / 点击</div>
    </div>
  </div>
  ```
  ```css
  .hero .hero-right{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center;min-width:0}
  /* .tags / .stats 一律不带 auto（两个 auto 会平分剩余空间，把整组拆开） */
  ```
  窄屏 `@media (max-width:560px)` 内 `.hero .hero-right{margin-left:0}`（否则 390px 下整组被推出右边界，v4.8.2 踩过）。
- **动态实测**（真实 CSS + 无头 Chrome，`gapRight` = 整组右边界到容器右边界）：
  | 场景 | 视口 | gapRight | 与标题同行 | 横向溢出 |
  |---|---|---|---|---|
  | 有分类标签 | 1440 | **0**（贴右） | 是 | 0 |
  | **无分类标签** | 1440 | **0**（贴右） | 是 | 0 |
  | 有分类标签 | 390 | 138（左对齐换行） | 否 | 0 |
  | 无分类标签 | 390 | 299（左对齐换行） | 否 | 0 |
  **反向对照**（把 auto 改回 `margin-left:0`）：`gapRight` 变为 243 / 429 ⇒ 断言非空转。
- **守卫更新**：`verify_notypetag_492` 的 ④ 组（3 项 → 4 项：`.hero-right` 承担 auto、
  `.tags`/`.stats` 均不带 auto、模板结构同容器、窄屏取消 auto）；
  `verify_ovfit_482` 的 S2 断言同步到新容器名。

# v4.9.3（2026-10-09）hero 布局定稿：标题贴左、分类标签移到右侧与指标同排、去掉「年份」

- **用户诉求**（截图标注，纠正了我上一版的理解）：
  - **「最后目击」标题位置不动**（仍贴左）；
  - 把**分类标签（犯罪 / 剧情 / 悬疑）从左边移到右边**，与指标同排；
  - **去掉「年份 2026」**那颗胶囊。
- ★ 我上一版（v4.9.2）把靠右对齐从 `.tags` 改给了 `.stats` —— 结果是
  **分类标签仍留在左边**、只有指标靠右，与诉求相反。本版改回：
  ```css
  .hero .tags{margin-left:auto}   /* 标签组把整组推向右侧，指标紧跟其后 */
  /* .stats 不带 auto —— flex 里两个 auto 会平分剩余空间，
     把标签组推到中间、指标推到最右，视觉上被拆开 */
  ```
  窄屏 `@media (max-width:560px)` 内仍 `margin-left:0`（否则 390px 下整组被推出右边界，v4.8.2 踩过）。
- **年份胶囊整颗移除**：`$yearStat` 变量与模板插值一并删掉（不留空占位）。
  ★ `$year` **变量保留** —— 下方「{分类}信息」区块仍在用年份。
- **实测**（真实 CSS，无头 Chrome，1100px）：
  | 元素 | left | right |
  |---|---|---|
  | 标题「最后目击」| 244 | 357 |
  | 分类标签组 | 612 | 790 |
  | 指标组（评分/热度/点击）| 804 | **1040 = 容器右边界（贴右）** |
  标签组与指标间距 14px（= flex gap）；指标数 **3**（年份已去）。
- **新增守卫** `.workbuddy/verify_herolayout_493.mjs`；同时更新
  `verify_notypetag_492`（其 ④ 断言「靠右由 .stats 承担」已随本版反转）。
- **不动**：分类标签内容与链接、指标（评分/热度/点击）、SEO、其它区块、数据库。

# v4.9.2（2026-10-09）详情页 hero 去掉「类型」标签：保留分类标签与指标

- **用户诉求**（截图 + 澄清）：hero 第一屏那一排胶囊里，
  **要去掉的是「类型」**（短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏 / 书籍 / 音乐 / 其他），
  **保留「分类」**（犯罪 / 剧情 / 悬疑 …）和右边的指标（评分 / 年份 / 热度 / 点击）。
- ★ **两者不是一回事**（本版的核心区分，务必记牢）：
  | | 来源 | 含义 | 例子 |
  |---|---|---|---|
  | **类型** | `$type` | 条目属于哪一类 | 电影 / 电视剧 / 短剧 / 动漫 |
  | **分类** | `genres` | 条目讲什么题材 | 犯罪 / 剧情 / 悬疑 |
  类型在海报、面包屑、分类页到处都有，hero 再列一遍属重复信息，且挤占标题与指标空间。
- **改法**：
  - 模板：`<div class="tags"><span class="t">{$typeLb}</span>{$genresHtml}</div>`
    → `{CLASSTAGS}` 占位符（**分类为空时整块不输出**，不留空 div）。
    ★ 占位符**无 `$`**（v4.8.6 铁律：带 `$` 会被 heredoc 提前插值）。
  - CSS：靠右对齐从 `.hero .tags{margin-left:auto}` 改由 **`.hero .stats{margin-left:auto}`** 承担 ——
    否则没有分类标签时指标会被顶到左边、布局跳动。
    窄屏 `@media (max-width:560px)` 内同步加 `.hero .stats{margin-left:0}`。
  - `$typeLb` **变量保留**（仍用于 SEO 的 `type_label`），只删模板输出。
- **实测**（真实 CSS，无头 Chrome，1100px）：
  | 场景 | 指标 left 偏移 | 指标 right 距容器右边 | 指标数 | 溢出 |
  |---|---|---|---|---|
  | 有分类标签（犯罪/剧情/悬疑）| 498 px | **0 px**（贴右）| 4 | 无 |
  | 无分类标签（tags 不输出）| 498 px | **0 px**（贴右）| 4 | 无 |
  两种情况布局完全一致，指标始终贴右。
- **新增守卫** `.workbuddy/verify_notypetag_492.mjs`（**17/0**，含 1 项反向自测）：
  类型标签已删（含反向自测）/ 占位符无 `$` / 分类为空不输出空 div /
  `.stats` 承担靠右且 `.tags` 不再争抢 auto / 窄屏断点同步取消 /
  指标四项与 `$typeLb` 的 SEO 用途都还在 / 分类仍是可点链接 / 成因注释留档。
- **不动**：分类标签、指标、SEO、页面其它区块、数据库。

# v4.9.1（2026-10-09）★★ 第三方统计代码异步化：第三方挂掉不再拖垮整页

- **用户实测**：F12 Network 里 `https://sdk.51.la/js-sdk-pro.min.js` **红色失败**。
- **实测结论**：51.la 服务本身**正常**（HTTP 200 / 36 KB / **1.03 s**、DNS 三 IP 正常），
  红色是当时的网络抖动。**但真正的问题在引入方式**——
  抓线上 head 发现那段代码是**同步外链**：
  ```html
  <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
  <script>LA.init({id:"3Qfo9XzF3qdfYXig",ck:"3Qfo9XzF3qdfYXig"})</script>
  ```
  浏览器在 `</head>` 处**同步等待**它执行完才继续解析渲染：
  - 第三方慢：实测 1.03 s ⇒ **每次打开页面白等约 1 秒**；
  - 第三方挂掉：TCP 超时可达 10~30 秒 ⇒ **整页白屏**。
  统计代码挂掉却拖垮整个站点，代价比失去统计大得多。
- **改法**（`Site::protectThirdParty()`，输出层统一处理，对任意第三方代码生效）：
  ① 外链 `<script src=…>` 缺 `async`/`defer` → **补 `async`**（并行下载、不阻塞渲染）；
  ② 外链补 `onerror="this.remove()"` —— 失败就把节点摘掉，不留红叉元素；
  ③ 紧随其后的**内联初始化**（`LA.init(...)`）→ **延后到 `window load` 之后**执行，
     并包 `try/catch` —— SDK 未到达时静默跳过，不再抛 `ReferenceError: LA is not defined`。
- **两处注入点都改了**：`Site::headMeta()`（首页/内页/静态页）与 `UserViews::head()`
  （用户中心此前是裸 `$stats`，最容易漏）。
- **★ 刻意不动站长粘贴的原文**（不在设置页做改写）：
  那样会让「我粘的」和「我看到的」不一致、难以排查。输出层处理对所有第三方代码都生效，站长无感。
- **边界必须原样保留**（守卫用真实 51.la 样本 + 4 个边界样本验证）：
  已有 `async`/`defer`、`type="application/ld+json"`（结构化数据绝不能包 try/catch）、
  `type="module"` 一律不动；包装体带 `__mlTPLoader` 幂等标记 ⇒ 二次处理结果不变。
- **新增守卫** `.workbuddy/verify_tpsafe_491.mjs`（**18/0**），
  含**行为镜像**（用 51.la 真实代码样本复刻 PHP 逻辑，验证 async/onerror/延后/try-catch/幂等/边界）。
- **不动**：统计功能本身、页面内容、样式、接口、数据库。

# v4.9.0（2026-10-09）★★ 列表页翻页预取 + 零等待切换：点「下一页」不再白屏等待

- **用户诉求**：「可以加入缓存预存吗？加快翻页后的加载速度」。
- **原理**（纯前端，零后端开销）：
  ① 页面空闲时（`requestIdleCallback`）**预取下一页 HTML**，
     存内存 + 浏览器 HTTP 缓存（配合 v4.8.7 的 `max-age=60`，回访命中即 0 流量）；
  ② 点分页时若内存缓存命中 → **只替换 `.grid` 与 `.pager`**，
     `history.pushState` 改地址，**不做整页跳转** ⇒ 点击到看到**毫秒级**；
  ③ 未命中 / 非翻页链接 / 任何异常 → **立刻退回原生跳转**，行为与改动前完全一致。
- **实测**（真实 `pagecache.js` + 真实 `site.css`，无头 Chrome，5 页夹具）：
  | 指标 | 结果 |
  |---|---|
  | 点击「下一页」→ 内容变成第 2 页 | **5 ms** |
  | 首卡标题 | 「条目标题示例 22 号」（第 2 页首张，正确）|
  | 分页栏同步更新 | 「上一页第 2 / 5 页下一页」 |
  | 末行补位 | `.ph` 3 个（21 张 / 6 列 ⇒ 补 3，正确）|
  | 是否整页跳转 | 否（无 navigation 条目，`pushState` 生效）|
- **配套改动**（缺一不可，否则翻页后会露馅）：
  - 分页链接加 `data-ml-page="N"`（脚本据此判定「这是翻页」；没有该属性就完全放过）。
  - `grid-auto.js` 把 `fix()` 暴露为 `window.__mlGridFix` ——
    新 `.grid` 是整块替换进来的，末行补位必须重算
    （否则会露出 v4.7.4 修过的「末行排不满、右侧留白」）。
  - `title-scroll.js` 同样暴露 `window.__mlTitleMark` —— 新卡片还没测过标题是否溢出。
- **★ 搜索页不启用**：搜索结果的页头区块（标签云 / 筛选标题）会随 `q` 变化，
  只换 `.grid` 会与页头不一致 ⇒ 搜索页走原生跳转。
  服务端 `pageCacheJs($isSearch, $totalPages)` 双重把关：搜索态、或只有 1 页（无分页栏）都不输出脚本。
- **内存与时限**：缓存最多 5 页、TTL 60 秒（与服务端 `max-age` 对齐）——
  超时自动丢弃，避免「新内容看不到」与内存膨胀。
- **新增守卫** `.workbuddy/verify_prefetch_490.mjs`（含反向自测与动态层无头实测）。
- **不动**：数据、接口契约、分页 URL 结构、数据库。

# v4.8.9（2026-10-09）后台概览页重排：「点击最多的 50 个条目」置顶 + 重复统计合并

- **用户诉求**（截图两处）：
  ① 概览页**上下两块统计重复**了 —— 顶部那排的「总条目 / 暂存」与下方
     「内容总览统计」的「正式条目 / 暂存待刮削」是同一份数据、同一口径，
     同样的数字出现两遍纯噪音；
  ② 「🔥 点击最多的 50 个条目」要放到**最上面**（它最常用，却埋在最下面）。
- **改法**（`admin/index.php` 概览页 DOM 顺序 + `assets/js/admin.js` 的 `loadStats()`）：
  - DOM 顺序改为：**点击最多 → 内容总览统计**（内含 条目总览 / 类型与来源分布 / 数据完整度 / 流量与热度）。
  - `loadStats()` **不再渲染「总条目」「暂存」**两项 —— 与内容总览统计重复。
  - ★ **合并不是删掉整块**：顶部那块**独有**「类型分布 / 来源分布」，
    已作为「📂 类型与来源分布」小节**并入内容总览统计卡片内部**。
    只丢掉重复的两项，独有信息全部保留。
- **数据源不变**：`stats` 与 `overview_stats` 两个接口仍各调一次
  （前者取类型/来源分布，后者取总量/完整度/流量），不是靠少取一次数据来"看起来不重复"。
- **新增守卫** `.workbuddy/verify_overview_489.mjs`（**15/0**），要点：
  ① 「点击最多」必须是概览页第一个区块 / ② `loadStats()` 不得再渲染那两项
  （含 **2 项反向自测**：分别加回「总条目」「暂存」都必须判失败）/
  ③ 类型分布、来源分布必须仍在（**合并不是删信息**）/ ④ 两个接口都要还在 /
  ⑥ 成因注释必须留（防后人又把两项加回来）。
- **不动**：数据、接口、页面其它区块、数据库。

# v4.8.8（2026-10-09）★★ PHP 错误输出不再进入响应体：修「服务器返回了非 JSON 内容」

- **用户报错**（后台弹窗截图）：
  `服务器返回了非 JSON 内容（HTTP 200）：<br /> <b>Warning</b>: Undefined variable $cssLinks in .../core/Views.php on line 55`
  —— 一条 PHP Warning 把整个 JSON 响应**污染成了非 JSON**，功能整体不可用。
- **实测确认线上版本**：`bash .workbuddy/check_live.sh` ⇒ **线上 v4.8.5**，
  本地发布版 v4.8.7 ⇒ **v4.8.6 / v4.8.7 都没部署**。
  报错变量名 `$cssLinks` 正是 v4.8.6 修掉的旧占位符形态（`{$cssLinks}`）。
- **★ 本版做的是「减轻痛苦」而非等部署**：即使服务器上还残留任何告警，
  也不再破坏接口与页面。
  `core/Guard.php`（**所有入口的第一个加载项**）新增：
  ```php
  @ini_set('display_errors', '0');           // 错误不进响应体
  @ini_set('display_startup_errors', '0');
  @ini_set('log_errors', '1');                // 但仍完整写日志
  @ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
  // 日志落到 log/php-error.log；目录不可写则优雅降级到 php.ini 的 error_log
  ```
  原因：宝塔 / php-fpm 默认 `display_errors=On`，**任何一条 Warning 都会插进响应体开头** ⇒
    ① JSON 接口不再是 JSON（前端解析失败 ⇒ 整个功能不可用）；
    ② HTML 页面顶部多一行报错文字；
    ③ 若输出发生在 `header()` 之前，还会连带 `headers already sent`。
- **★ 排错方式随之改变**：**看日志，不再看屏幕** ——
  `log/php-error.log` 里有完整的文件名/行号/原因，屏幕保持干净。
  注释里写明这一点，防止后人为了「能看到报错」把 `display_errors` 又打开。
- **刻意不做**：不在 Guard.php 里 `define('ML_APP_VERSION', '1')` 兜底 ——
  版本号唯一来源是 `core/Version.php`，塞第二个来源会被打包闸门判违规
  （守卫 ⑦ 专门断言这一点，并带反向自测）。
- **新增守卫** `.workbuddy/verify_noerrleak_488.mjs`（**11/0**）：
  `display_errors=0` / `display_startup_errors=0` / **`log_errors=1` 必须保留**
  （一起关掉就变成「屏幕干净但无从排错」，比现在更糟）/ 日志目录判权限才能优雅降级 /
  不得定义第二个版本来源 / **全部入口都要 require Guard.php**（否则 ini 设置对它们无效）/
  成因注释必须留。
- **不动**：业务逻辑、页面内容、样式、接口、数据库。

# v4.8.7（2026-10-09）前台页面缓存：no-store → 60 秒短缓存（重复访问流量降为 0）

- **用户提问**「明明已经启用了，但还是没压缩」→ 实测发现 **GZIP 其实一直是好的**
  （`site.css` 48,644 → 15,278 B / 69%，`admin.js` 146,602 → 44,825 B / 69%，
  首页 30,586 → 5,924 B / 81%），宝塔面板的「未启用GZIP压缩」是**误报**：
  nginx 只对带 `Accept-Encoding: gzip` 的请求压缩，而面板的检测请求没带该头
  ⇒ 拿不到 `Content-Encoding`；截图里「源文件大小 / 压缩率 / 服务器类型」全为空也印证它**没拿到完整响应**。
- **顺带发现的真问题**：首页 / 标签页 / 人物页自 v4.6.2 起就是 `Cache-Control: no-store`，
  **每次访问都重传整页 HTML**（gzip 后仍 ~5.9 KB）。
  v4.7.6 把 CSS/JS 拆成静态文件后 HTML 重新变成大头，这个浪费就明显了。
- **改法**（`home.php` / `index.php`）：

  | 页面 | 改前 | 改后 |
  |---|---|---|
  | 首页 / 列表页 | `no-store, must-revalidate` | `public, max-age=60, must-revalidate` + `Vary: Accept-Encoding` |
  | **搜索结果页** | `no-store` | **保持 no-store**（结果必须实时、URL 参数多易串味） |
  | 标签页 `/tag/` | `no-store` | `public, max-age=60, must-revalidate` + `Vary` |
  | 人物页 `/person/` | `no-store` | 同上 |
  | 详情页 `/uisc/*.html` | Nginx 长缓存 | 不变 |
  | `mimg.php` 图片代理 | 30 天强缓存 | 不变 |

  `Vary: Accept-Encoding` 是必要的：CDN 若同时缓存了压缩版与未压缩版，
  会把未压缩版发给支持 gzip 的客户端。
- **v4.6.2 的顾虑仍被满足**：它防的是「升级后旧缓存导致整页点不动」。
  现在用 **60 秒短窗 + must-revalidate** 达成同样效果 ——
  关键是**时间窗要短**，不能像 CDN 长缓存那样把旧版留几小时。
  升级后最多 60 秒拿到旧版，可接受。
- **新增守卫** `.workbuddy/verify_pagecache_487.mjs`（**13/0**）：
  搜索态必须仍是 no-store（反向约束）/ 短缓存的 `max-age` 必须在 1~300 之间
  （太长会重现旧缓存问题）/ `Vary` 必须声明 / 三处改齐 /
  **未被波及的策略必须原样**（`mimg.php` 30 天、`track.php` no-store、`Headers.php` API 默认头）。
- **不动**：页面内容、样式、接口、数据库。

# v4.8.6（2026-10-09）★★ 修复「首页完全没有样式」的真凶：`str_replace` 占位符写成了 `{$cssLinks}`，被 PHP heredoc 提前插值

- **用户现象**（截图）：前台首页**完全没有样式**（浏览器默认字体、蓝色下划线链接、海报原图铺开）；
  页面顶部同时出现两条线索：
  - `Warning: Undefined variable $cssLinks in .../core/Views.php on line 23`
  - 我 v4.8.5 加的红底提示条「本站仅做资源索引…」**出现了** ⇒ 说明 `core/Views.php` 是新版，
    但服务器上**确实缺 `assets/css/site.css`**。
- **★ 真凶（比混潦器更根本，v4.7.6 起一直踩着）**：

  ```php
  $html = <<<HTML
  {$cssLinks}          ← ① heredoc 赋值那一刻 PHP 就插值了 → 变量未定义 → Warning + 空串
  HTML;
  return str_replace('{$cssLinks}', self::cssLinks($isSearch), $html);
                       ↑ ② 找字面文本 `{$cssLinks}` —— 已被插值掉，永不匹配 → 整段替换空转
  ```

  **PHP heredoc 与双引号字符串同语义**，模板里的 `{$var}` 会被插值。
  所以 `str_replace` 的占位符**绝不能写成带 `$` 的形态**。
  v4.7.5 用的 `{CSS_PLACEHOLDER}` 是对的；v4.7.6 改成 `{$cssLinks}` 才埋雷。
- **★ 为什么源码里完全看不出来**：`str_replace` 找不到目标时**返回原串、不报错、不抛异常**，
  PHP 也不校验 `'{$cssLinks}'` 这种「非法变量名」形态 —— 一切看起来都很正常，
  只有看线上渲染结果（页面无 `<link>`）才发现。同文件的 `{CSRF}` / `{U}` / `{TYPES}` 都是无 `$` 形态，
  属于正确写法，只有 CSS 这两处不一致。
- **修复**：
  - `core/Views.php`：两处占位符 `{$cssLinks}` → **`{CSS_LINKS}`**（含 2 处 `str_replace` 键）；
    并留下成因说明注释（含「为什么带 `$` 必然失效」），防日后改回。
  - `core/UserViews.php`：不再自己拼 `<link>`，改走 `Views::cssLinks()`；
    `cssLinks()` 新增第 3 参 `$extraFile`，让 **user.css 缺文件时同样有提示**（此前只有 site.css 有）。
  - 新增 `Views::assetMissingNotice()`：提示条逻辑抽成公用方法（自带内联样式 ——
    此刻外部样式表正好不可用，任何 class 都无济于事）。
- **★ 新增头号打包闸门**（`build_release_194.py`）：
  **`core/Views.php` 与 `core/UserViews.php` 里 `str_replace` 的占位符一律不得含 `$`**，
  命中即**中止打包**并打印正确写法。这类故障「源码无异常、产物无异常、只有线上裸奔」，
  必须在打包阶段拦住。
- **新增守卫断言** `verify_cssguard_485.mjs` 第⑤组（7 项）：占位符形态 / CSS 占位符具体形态 /
  模板与键配对 / **行为镜像**（用 JS 复刻 PHP heredoc 插值语义，证明「带 `$` 形态无论变量有无值都会被插值掉」）
  / 反向自测（改回带 `$` 形态必须判失败）/ 成因注释 / UserViews 复用。
  本版共 **29/0**。
- **部署提醒**：本次修复**只存在于重新打出的包**里。
  由于 v4.7.6 ~ v4.8.5 的 CSS 一直是坏的，**升级后请顺手让浏览器强制刷新**
  （CSS/JS 带 `?v=版本号` 会自动破缓存，一般直接刷新即可）。
- **不动**：页面结构、样式内容、接口、数据库。

# v4.8.5（2026-10-09）★ 首页「显示不完整 / 完全没有样式」：线上仍是旧混淆版 + 新增两道防护

- **用户现象**（截图）：前台首页**完全没有样式** —— 浏览器默认字体、蓝色下划线链接、
  海报按原图尺寸铺开、右半屏空白。
- **实测定位**（`curl` 带正常 UA 抓线上首页）：

  ```
  首页开头泄漏：Warning: Undefined variable $i3 in /www/wwwroot/zyfx.wang/core/Views.php on line 23
  <link rel="stylesheet"> 出现次数：0
  外链 JS：正常（?v=4.8.3）
  assets/css/site.css 本身：HTTP 200 / 48644 B  ← 文件在，是引用丢了
  ```

  ⇒ 线上跑的是 **v4.8.3 的旧混淆产物**，v4.8.4 的修复尚未部署。
  根因即 v4.8.4 修的那个混淆器 BUG：模板占位符 `{$cssLinks}` 被改名、
  `str_replace` 的键没改 ⇒ 替换失效 ⇒ `<link>` 整个消失 ⇒ **CSS 一字节都没加载**。
  JS 外链正常是因为它们走的是真实变量插值（改名后仍然自洽），只有 `str_replace` 占位符会脱节。
- **★ 部署提示**：本次修复**只存在于重新打出的 v4.8.4+ 包**里。
  **不要只手动传单个 `core/Views.php`** —— 手动传的若是从别处拷来的旧混淆文件，
  故障会原样保留。必须用整包/升级包**全量覆盖**。
- **新增防护①（运行时）**：`Views::cssLinks()` 先 `is_file()` 探测 `assets/css/site.css`。
  文件不存在时**不输出注定 404 的 `<link>`**，改为输出一条**不依赖任何 CSS 的内联提示条**
  （红底白字，写明缺哪个文件、怎么补）。此前这种故障表现为「静默裸奔」，用户无从判断原因。
- **新增防护②（打包闸门）**：`build_release_194.py` 新增硬断言 ——
  ① 8 个静态资源必须齐全且体积正常；
  ② **每个 `str_replace('{$x}', …)` 的占位符，在源码与混淆产物里的出现次数必须相等**
     （不等即说明混淆器改了名，替换会失效）；
  ③ 产物里不得残留形如 `{$i3}` 的孤儿占位符。
  这三条直接打在**混淆产物**上 —— 这类故障源码里 100% 看不出来，只在产物/线上暴露。
- **新增工具** `.workbuddy/check_live.sh`：`bash .workbuddy/check_live.sh [域名]`
  一条命令自检线上 —— 首页是否有 PHP 报错泄漏、`<link>` 是否存在、
  CSS/JS 是否真能下载、**线上版本号是否落后于本地发布版本**、用户中心 user.css 是否就位。
  首次运行即定位出 4 项异常。
- **不动**：页面结构、样式内容、接口、数据库。

# v4.8.4（2026-10-09）★ 修复「生成静态报 Undefined variable」：HTML 注释里的变量插值也会被 heredoc 解析

- **用户报错**（后台弹窗截图）：
  `Warning: Undefined variable $i in /www/wwwroot/zyfx.wang/core/Views.php on line 55`
  （HTTP 200，但警告文本被写进 JSON 输出 ⇒ 后台「生成静态」直接弹出来）。
- **★ 根因（自己 v4.7.9 埋的雷，本项目最隐蔽的一类坑）**：
  v4.7.9 去掉详情页简介时，在模板里留了一句**说明注释**：
  ```html
  <!-- ★ v4.7.9：此处原为 {$synHtml}（TMDB 长简介），已移除 -->
  ```
  ★★ **HTML 注释对 PHP heredoc 是不设防的** —— `{$synHtml}` 写在 `<!-- -->` 里**照样被插值**，
  而该变量在 v4.7.9 已连同 `$overviewHtml` 一起删除 ⇒
  每次生成静态页都抛 `Undefined variable`。
  截图里的变量名是 `$i` 而非 `$synHtml`，是因为**线上跑的是混淆版**，
  混淆器把局部变量改成了短名（`$synHtml` → `$u1`/`$t1`），行号也随之变化。
  ⇒ 本地源码看着"注释里而已"没问题，**只有混淆后的线上文件才暴露**。
- **★ 两个独立根因（都修）**：
  - **①（代码侧）v4.7.9 自己埋的雷**：去掉详情页简介时，在模板里留了一句**说明注释**
    `<!-- 此处原为 {$synHtml}（TMDB 长简介），已移除 -->`。
    ★★ **HTML 注释对 PHP heredoc 不设防** —— `{$synHtml}` 写在 `<!-- -->` 里**照样被插值**，
    而该变量已删除 ⇒ 每次生成静态页都抛 `Undefined variable`。
    修：注释里彻底避开「美元符号 + 花括号 + 变量名」形状，改用文字描述，并留成因说明防回退。
  - **②（★ 混淆器 BUG，本条才是线上报错的真正根源）**：
    模板用 `{$cssLinks}` 作占位符，靠 `str_replace('{$cssLinks}', …)` 拼装时整体替换。
    混淆器把 heredoc 里的 `$cssLinks` **改了名**（因为它出现在变量位置），
    而 `str_replace` 的键是**字符串字面量**、**没跟着改**
    ⇒ 两者不再同名 ⇒ **str_replace 匹配不上** ⇒ 线上 `<head>` 里残留裸文本 `{$i3}`
    并抛 `Undefined variable`。
    实测：源码 4 处 `{$cssLinks}` → 修复前产物只剩 2 处（键那2 处），
    模板插值被改成 `{$i3}` / `{$t1}`。
    **修 `obfuscate_194.mjs`**：新增 `strReplacePlaceholderNames()` ——
    凡出现在 `str_replace('{$x}', …)` 里的名字，**在文件内整体保持原样**（模板插值与键都不改名）。
    修复后：源码 4 处 = 产物 4 处，`{$i3}` 归零，`str_replace` 恢复正常。
    ⇒★ **`str_replace` 占位符是「名字敏感」的**：一旦混潦改名，替换静默失效、
    线上才炸。以后新增占位符必须同步更新混潦器的保护名单。
- **新增回归守卫** `.workbuddy/verify_heredoc_var_484.mjs`（**5/0**）：
  - S1 扫 8 个模板文件里全部 **10 个 heredoc 块 / 59 处插值**，逐个核对「是否有定义」；
  - S4/S4b **产物侧**：比对源码与产物的占位符数量，并核对产物里每个插值都有定义/是占位符
    —— 这两条能直接抓住「混潦改名破坏 str_replace」；
  - ★ 判定时必须**排除「由 `str_replace('{$x}', …)` 替换的占位符」**
    （如 `{$cssLinks}` —— 它本来就不该有 PHP 变量），否则全是假 FAIL。
- **不动**：页面结构、样式、接口、数据库。

# v4.8.3（2026-10-09）剔除源码里的测试期域名 `sq.zyfx.wang` / `api.zyfx.wang`

- **用户诉求**（原文）：「sq.zyfx.wang 和 https://api.zyfx.wang 当初测试用的，不要真的放进源码」。
- **背景**：v4.7.0 剔除了测试站 `cs.zyfx.wang`，但当时**判定 `sq.` / `api.` 属于"其它项目"而保留**。
  用户这次澄清：这两个也是**测试期地址**，不应留在源码里。
- **改动（4 处，全是文案，零功能影响）**：
  | # | 文件 | 原文 | 改后 |
  |---|---|---|---|
  | ① | `api/tmdb_proxy.php:19` | `把 TMDB base URL 改成你的站点（如 https://api.zyfx.wang）` | `（如 https://你的域名/api）` |
  | ② | `core/AntiBot.php:102` | `现有客户端（ComicMaker / WebDAVBackup / sq.zyfx.wang）尚未实现签名` | `现有客户端（ComicMaker / WebDAVBackup 等）尚未实现签名` |
  | ③ | `CHANGELOG.md` v4.7.0 条 | 「**不动**：`api.zyfx.wang`、`sq.zyfx.wang` 等其它项目地址，保持原样」 | 改为「**当时不动**……★ v4.8.3 起已一并剔除」，保留历史决策痕迹与追溯链 |
  | ④ | `CHANGELOG.md` 备注 | 同上（相邻行） | 同步 |
- **不动**：`PRODUCTION.md` 里`zyfx.wang` 的 Nginx / 日志 / 备份**路径示例**
  （`server_name zyfx.wang`、`/www/wwwroot/zyfx.wang/...`）——
  那是**正式站的部署路径写法**，宝塔/Nginx 场景下需要能对照实操，不是测试地址；
  且该文档**不进发布包**（EXCL 已排除）。若将来要一并中性化，再单独提。
- **核对**：改动后 `原始源码/` 内 `sq.zyfx.wang` / `api.zyfx.wang` 均为 **0 处**
  （`verify_nositetest_470` 的域名扫描也覆盖不到它们，故新增专项断言，见 `verify_testdomain_483.mjs`）。
- **数据库影响**：无（仅注释与变更日志文案）。

# v4.8.2（2026-10-09）★ 窄屏详情页三处溢出/截断：标题被挤成竖排、标签组被裁、长文本撑宽

- **用户诉求**（两张截图）：「也是溢出」——
  图①标题「巴黎陷落」被拦腰切断、右侧「电视剧 / 动作冒…」被裁；
  图②横向滚动到最右仍有一片空白、下载说明文字被截断。
- **★ 这是 v4.8.1 的「姊妹问题」，不是同一个**：v4.8.1 修的是 **`.detail` grid 子项的
  `min-width:auto`**（演职员多时整页被撑破）；本次修的是 **hero 区与长文本的排版溢出**。
  修完后实测：整页 `documentElement.scrollWidth == clientWidth`（三个宽度全部成立）。
- **三个根因（全部由无头浏览器量出来，不是猜的）**：
  | # | 现象 | 根因 | 修法 |
  |---|---|---|---|
  | A | 标题被竖排成一行一个字 | `.hero .topline` 是 `flex-wrap:wrap` 多行布局（h1 / tags / stats 各占一行），**h1 没有 flex-basis** ⇒ 宽度被同行 tags/stats 挤出来。实测 390px 下 **h1 的 clientWidth 只剩 81px**（约一个汉字） | 窄屏 `.hero h1{flex:0 0 100%;min-width:0}` 强制独占整行 |
  | B | 「电视剧/动作冒险/战争与政治」被裁 | `.hero .tags{margin-left:auto}`（靠右对齐）在窄屏把标签组**整体推出右边界** | 窄屏 `.hero .tags{margin-left:0}` 左对齐自然换行 |
  | C | 下载地址/说明文字被截断 | `.dl-raw` 只有 `word-break:break-all`，**长英文/数字连续串仍可能撑宽**；`.hero .ot`、`.kv .v` 完全没有断行设置 | 三者补 `overflow-wrap:anywhere` |
- **修复后实测（真实 CSS + 30 演职员 + 超长 URL + 长说明，无头 Chrome）**：
  | 视口 | 整页 | h1 宽（修复前 81px） | 最后一个标签 right |
  |---|---|---|---|
  | 1280px | scroll=client=1254 ✔ | — | 863 ≤ 1254 ✔ |
  | 820px | scroll=client=794 ✔ | — | 776 ≤ 794 ✔ |
  | 390px | scroll=client=526 ✔ | **500**（= `.info` 全宽，独占整行） | 241 ≤ 526 ✔ |
  `.cast` 在三个宽度均 `scrollWidth > clientWidth` ⇒ 横向滚动正常（v4.8.1 未回退）。
- **不动**：HTML 结构、卡片宽度、翻页按钮、拖拽、标签统一规格（v4.8.0）、接口、数据库。
- **回归守卫**：新增 `.workbuddy/verify_ovfit_482.mjs`（**22/0** = 静态 9 + 动态 13）。

# v4.8.1（2026-10-09）★ 修复「演员表太多时页面横向溢出」：grid/flex 子项的 min-width:auto 反向撑开容器

- **用户诉求**（截图）：「演员表太多的时候页面溢出了」——
  演职员横向一排排不开，最后几张卡（截图里Rose Williams）被裁在屏幕右侧，页面出现横向滚动。
- **根因（无头浏览器实测三层定位，不是猜的）**：
  实测 24 个演职员时，1280px 视口下：
  ```
  页面client=1254  scrollWidth=3063   ⚠ 整页横向溢出
  .block      client=3006            ← 被撑到 3006px！
  .cast-wrap  client=2962
  .cast       client=2962 scroll=2962  ← overflow-x:auto 形同虚设
  ```
  `.cast` 明明写了 `overflow-x:auto`，但它的 **clientWidth 也是 2962** ——
  **元素本身就那么宽，根本无处可滚**。
  链条是：`.detail{display:grid}` 的子项默认 **`min-width:auto`**
  （grid/flex 项的**默认行为就是拒绝收缩到内容宽度以下**）
  → `.block` 作为 grid 子项被 `.cast` 的内容宽反向撑到 3006px
  → `.cast-wrap`（flex）跟着撑 → `.cast` 的 `overflow-x:auto` 失去意义
  → `documentElement.scrollWidth` 3063 > clientWidth 1254 ⇒ **整页横向溢出**。
  ⇒ ★ 这类「横向滚动容器不滚动、反把页面撑破」的现象，
    **根因永远在父级的 `min-width:auto`，不在滚动容器自己**。
- **修法**（`assets/css/site.css`，3 处）：
  ① `.detail > * { min-width:0; min-height:0 }` —— grid 子项允许收缩（`min-height:0` 防同类纵向问题）；
  ② `.block { … ; min-width:0 }` —— 它可能被直接当作 grid 子项使用；
  ③ `.cast-wrap .cast { …; min-width:0 }` —— flex 层同样显式允许收缩（原本已有，一并钉住）。
- **修复后实测（真实 CSS + 24 个演职员 + 无头 Chrome，三个断点）**：
  | 视口 | 修复前 scrollWidth | 修复后 | `.cast` 内容/容器 |
  |---|---|---|---|
  | 1280px | 3063（溢出） | **1254 = clientWidth** ✔ | 2962 / 1098 ⇒ 可滚 |
  | 820px | 3026（溢出） | **794 = clientWidth** ✔ | 2962 / 712 ⇒ 可滚 |
  | 390px | 2602（溢出） | **526 = clientWidth** ✔ | 2557 / 468 ⇒ 可滚 |
- **不动**：卡片宽度（仍 `flex:0 0 <固定宽>`）、横向滚动能力、左右翻页按钮（`.cast-nav`）、拖拽、HTML 结构。
- **回归守卫**：新增 `.workbuddy/verify_castfit_481.mjs`（**16/0** = 静态 7 + 动态 9），
  动态层在 1280/820/390 三个宽度实测「整页不溢出」且「`.cast` 内容宽 > 容器宽（= 真能滚）」。

# v4.8.0（2026-10-09）详情页标签样式统一：分类标签与指标胶囊改为同高同圆角同底色

- **用户诉求**（截图）：「标签样式要统一下」—— 详情页 hero 标题右侧那一排里，
  「电视剧/ 剧集」是小圆角小字的胶囊，而「评分 / 年份 / 热度 / 点击」是大方块，
  两组**高矮胖瘦不齐**，看着很乱。
- **根因**：两组标签本来就是两套独立规格，从没对齐过：

  | | 分类标签 `.hero .tags .t` | 指标 `.hero .stats .s` |
  |---|---|---|
  | 内距| `4px 12px` | `8px 15px` |
  | 圆角 | `20px` | `11px` |
  | 底色 | `rgba(255,255,255,.16)` | `rgba(255,255,255,.12)` |
  | 容器 gap | `8px` | `10px` |

- **改动**：两组**共用同一条规则**（同 padding / 同圆角 / 同底色 / 同字号 / 同 gap），
  采用「胶囊」规格（`border-radius:999px`，与卡片上的 `.chip` 家族一致）；
  只保留指标里数值 `<b>` 的字号与配色差异（评分金/ 热度橙 / 点击绿），信息层级仍在。
  - `.hero .tags, .hero .stats` 合并为一条 flex 规则（`align-items:center` + 同 gap）；
  - 分类标签是链接、指标不可点 ⇒ **只给 `.tags .t` 加 hover 反馈**，
    避免"看起来能点其实点不了"的误导；
  - 移动端（≤560px）同步为缩小版统一规格（`height:29px`）。
- **★ 实测抓到的隐藏坑（静态看 CSS 发现不了）**：
  第一版统一后，圆角/内距/底色/间距都对上了，**无头 Chrome 实测高度仍差 3px**
  （分类标签 32px vs 指标 35px）。追下去发现元凶是 `<b>`：
  `.stats .s b{font-size:15px}` 继承了 1.5倍行高 ⇒ 算出 `22.5px` 行高，把胶囊撑高。
  修法：给 `<b>` 显式 `line-height:1`，并给胶囊锁定 `height:32px` + `display:inline-flex;align-items:center`。
  ⇒ **两行文本高度不一致时，必须实测 `getBoundingClientRect().height`，不能只看 CSS 属性。**
- **实测结论（真实 CSS + 无头 Chrome）**：四个样本（电视剧 / 剧集 / 评分 / 年份）
  高度全为 **32px**、圆角全为 999px、底色全为 `rgba(255,255,255,.14)`、两组 gap 全为 8px。
- **不动**：HTML 结构（`.tags` / `.stats` 与各自 class 名）、标签色语义配色、打印样式。
- **回归守卫**：新增 `.workbuddy/verify_taguni_480.mjs`（静态断言 + 无头渲染动态层实测高度一致）。

# v4.7.9（2026-10-09）★ 详情页不再渲染「释义 / 简介」：TMDB 长简介只留 SEO，列表查询也不再拉该字段

- **用户诉求**（原文）：「释义 全部去除，影响页面的打开」；并确认
  ① **SEO description 保留**（搜索结果里的摘要要它）、② 列表页卡片上的简介也去掉。
- **那「释义」是什么**：`media_items.overview` —— TMDB 采集来的**长简介**（中文可达上千字），
  v4.3.15 起被整段塞进详情页 hero 标题下方（`.hero .syn`）。它有三重代价：
  1. 单条上千字随 HTML 下发，**详情页体积与打开速度直接受影响**（用户诉求的核心）；
  2. `.hero .syn` 无高度上限，长简介占满整屏，用户要滚很久才看到下载地址区；
  3. 中文简介为空时 `adapters/Tmdb.php` 会**再发一次 TMDB 请求**拉 `en-US` 简介兜底
     （多一次外部请求 + 一段对中文用户无意义的英文）。
- **改动（四处）**：
  | # | 文件 | 改动 |
  |---|---|---|
  | ① | `core/Views.php` `renderItemPage()` | 移除 `$synHtml` / `$overviewHtml` 渲染与模板插值（保留定义处的成因注释） |
  | ② | `assets/css/site.css` | 删除 `.hero .syn` 规则（114 行）与 ≤560px 断点内那处（339 行） |
  | ③ | `core/CacheStore.php` `listBySort()` | **SELECT 去掉 `overview`** —— 见下|
  | ④ | `assets/css/site.css` | 把v4.4.7 那条「简介铺满 hero」的注释同步标注为已移除，避免后人复活 |
- **③ 为什么是真正的性能收益**：列表页 / 标签页 / 人物页 / 搜索结果页都走 `listBySort()`，
  它每页固定取 **21 条**，而 `Views::cardHtml()` **从不渲染 overview** ——
  也就是每翻一页都白从数据库读 21 段长简介、再传给 PHP、再随缓存文件落盘。
  去掉这个字段后，选出的行体积显著变小。
- **★ 刻意不动的三处（避免改坏）**：
  - **SEO description 保留**：`Site::headMeta('item', …)` 仍传 `overview`，
    `core/Site.php` 仍用它拼 description —— 用户明确要求保留搜索结果摘要。
  - **对外 API 不动**：`searchItems()` / `listItems()` / `listItemsExactGenre()`
    是 `/api/v1` 的 JSON 数据源，**仍 SELECT overview**。改了会破坏外部客户端契约。
    守卫 `verify_nooverview_479.mjs` 专门钉这三条（判它们**必须**含 overview）。
  - **`adapters/Tmdb.php` 的 en-US 简介兜底保留**：它虽然主要是为简介，
    但同一段代码还顺带补`poster_path` / `backdrop_path`（海报兜底），删了会丢图。
- **不动数据库**：字段照旧采集入库、照旧参与 SEO，只是不再渲染到页面。
- **回归守卫**：新增 `.workbuddy/verify_nooverview_479.mjs`（**14/0**，四层断言
  渲染 / 查询 / SEO / API + 3 项反向自测）。

# v4.7.8（2026-10-09）★ JS 全部拆分为静态文件（<script src defer>）：可缓存 + 可压缩

- **用户诉求**（承接 v4.7.6 拆 CSS）：「js也需要分开，不然没办法压缩」。
- **原状**：全站 JS 共约 **176 KB** 全以内联形式塞在页面里，其中
  **`admin/index.php` 单页就内联了 167 KB**（146 KB 后台主逻辑 + 20 KB qrcode 库）——
  每次打开后台都要重传一遍，且压缩率被 HTML 标记严重稀释。
- **拆分清单**（全部落在 `assets/js/`）：

  | 文件 | 大小 | gzip | 原位置 |
  |---|---|---|---|
  | `admin.js` | 146602 B | **44690 B (30%)** | `admin/index.php` 内联 |
  | `qrcode.min.js` | 20048 B | 7176 B (36%) | 同上（扫码登录用第三方库） |
  | `tagcloud.js` | 6136 B | 2871 B | `Views.php::$tagCloudJs` |
  | `grid-auto.js` | 2171 B | 1229 B | `Views.php::$gridAutoJs`（v4.7.4） |
  | `request-hint.js` | 1233 B | 622 B | `UserViews::reqScript()` |
  | `title-scroll.js` | 474 B | 300 B | `Views.php::$titleScrollJs`（v4.7.3） |

- **改动**：
  - `core/Views.php` 新增 `Views::jsTag($file)` —— 输出
    `<script src="/assets/js/xxx.js?v=版本号" defer>`；
    `defer` 保证「并行下载、不阻塞解析、执行顺序在 DOM 解析后」，与原内联行为一致。
    文件缺失时**返回可见提示注释**而非坏标签。
  - 三段前台 JS 改为 `self::jsTag(...)`；**标签云仍【条件引入】** ——
    搜索页不渲染标签云，也就不该下载它的 6 KB JS（v4.7.5 的能力不能因拆分而丢失）。
  - `admin/index.php` 新增本地 `ml_admin_asset($rel)`：由 `config.admin.dir` 反推**站点根**，
    支持子目录部署（`/foo/admin/` → `/foo/assets/js/...`）。
    ★ 之所以不复用 `\Core\Views::assetUrl()`：**后台全程没有 require Views.php**
    （见文件头 require 列表），硬调会 Fatal。
- **刻意保留内联的一处**：`admin/index.php` 的**明暗主题首屏防闪烁脚本（609 B）**
  ——它必须在渲染前同步执行，外链反而会「先亮一下再变暗」，比内联更差。
- **收益（实测）**：后台每次打开，JS 从「内联 167 KB」变为
  「HTML 内两行 `<script src>` + 首次下载 176 KB（gzip 后共 ~56 KB），**二次打开 0 流量**」。
- **部署注意**：升级包全量覆盖，务必确认 `assets/js/` 六个文件已上传（缺任一时页面会留可见提示，
  对应功能失效）。守卫 `verify_jsplit_478.mjs` 会检查文件存在性与大小。
- **回归守卫**：新增 `.workbuddy/verify_jsplit_478.mjs`（**14/0**），
  含 3 项反向自测；另验证 `ml_admin_asset()` 在 `admin` / `sub/admin` / `a/b/admin`
  三种部署形态下均拼出正确站点根。

# v4.7.7（2026-10-09）前台「管理后台」入口仅管理员可见：普通用户登录后只能填写「我的请求」

- **用户诉求**（截图 + 原文）：「管理后台隐藏起来，除了管理员其他人登录只能填写我的请求」。
- **原缺陷**：前台三处「管理后台」入口（首页顶栏链接、首页底部大按钮、静态详情页底部按钮）
  只看后台配置 `admin.show_link`，**完全不判身份** ⇒
  普通用户与未登录访客都能看到「🛡 管理后台」按钮，点进去却只能看到登录页
  （`admin/index.php` 的 `if (!$ME || !User::isAdmin())` 门禁）——
  典型的「看得见、进不去」的误导入口。
- **改动**：`core/Views.php` 新增统一判定 `Views::showAdminEntry()`，三处入口全部改用它：
  ```php
  if (!Config::sub('admin', 'show_link', 1)) { return false; }   // ① 原开关保留
  return class_exists('Core\User') && User::isAdmin();            // ② 必须是管理员
  ```
  - ① **保留原开关语义**：管理员仍可在后台「关闭管理后台链接」里彻底隐藏；
  - ② **新增身份判定**：`User::isAdmin()`（读 `app_users.is_admin`，每请求只查一次并静态缓存）；
  - `class_exists` + `try/catch(\Throwable)` 兜底：查不到身份时按**不可见**处理，
    既不白屏、也不会误判成可见。
- **不动的部分**：
  - **后台门禁原样保留**（这才是安全防线，本改动只是「不露出入口」的经验层优化）；
  - `User::userArea()` 的「我的请求 / 退出」照旧 —— 普通用户登录后的去处正是它；
  - `user.php`、`core/UserViews.php`（登录/注册/我的请求页）本来就没有后台入口，无需改。
- **静态内页行为**：`renderItemPage()` 由 cron/CLI 生成，此时 `User::me()` 恒为 null
  ⇒ 静态详情页**不再带后台入口**（原本也不该带）。
- **回归守卫**：新增 `.workbuddy/verify_adminentry_477.mjs`（**11/0**），
  含 3 项反向自测：去掉 isAdmin 调用 / 把调用点改回裸配置判定 / 删掉后台门禁，均须判 FAIL。

# v4.7.6（2026-10-09）★ CSS 拆分为静态文件（<link> 引入）：可缓存 + 可压缩，二次访问 0 流量

- **用户诉求**（原文）：「直接把css写在同一个文件了，css需要分开，不然没办法压缩」。
- **问题**：全站 CSS（45.4 KB）原先以 heredoc **内联**在 `Views::css()` 里，
  每一个页面 HTML 都重复携带这45 KB，后果有三：
  ① 浏览器**无法按 URL 缓存**（URL 每次都变），每页、每次访问都重传；
  ② gzip/brotli 的压缩率被海量 HTML 标记严重稀释；
  ③ 首屏必须等这 45 KB 内联样式下载 + 解析完才开始渲染。
- **改动**：
  | 文件 | 说明 |
  |---|---|
  | `assets/css/site.css` | **新增**（45407 B）站点评测页面共用样式，从 `Views::css()` 抽出 |
  | `assets/css/user.css` | **新增**（8218 B）用户中心/登录/投稿等页面样式，从 `UserViews::ucss()` 抽出 |
  | `core/Views.php` | `css()` 改为读文件 + `static` 进程内缓存；新增 `assetUrl()`（根相对 `/assets/...`）、`cssLinks()`（输出 `<link rel="stylesheet" href="...?v=版本号">`） |
  | `core/UserViews.php` | `layout()` 改为引两个 `<link>`；`ucss()` 保留为「兼容读取」（不删方法，防外部调用 Fatal） |
- **为什么用根相对 `/assets/...`**：前台页面在域名根下、静态详情页在 `/uisc/xxx.html`（一级子目录），
  根相对路径让两种页面命中**同一份文件**，静态页不必再复制一份。
- **搜索页例外**：v4.7.5 的「按需裁剪」结果与查询无关、无法落盘，
  所以搜索页仍走 `css(true)` **内联输出**（其 CSS 已比全量少 12.3%）。
  这是「静态文件负责缓存压缩、动态输出负责裁剪」的分工。
- **收益（实测）**：
  - `site.css` gzip 后 **13,605 B**（30%）、`user.css` gzip 后 **2,590 B**（32%）；
  - **二次访问 CSS 流量0 B**（浏览器缓存命中）—— 这是内联永远做不到的；
  - 用户中心页原本每页内联8 KB `ucss`，现在同样可缓存；
  - 页面 HTML 体积减少约 45 KB（首页/内页）、53 KB（用户中心）。
- **安全兜底**：读不到 CSS 文件时**不静默返回空串**（那会让页面无样式裸奔），
  而是输出一行可见注释提示「site.css 未能读取…」，一眼能看出是文件没随包上传。
- **部署注意**：升级包是**全量覆盖**，务必确认 `assets/css/` 两个文件已上传
  （否则前台会无样式）。守卫 `verify_csssplit_476.mjs` 会检查文件存在与结构完整性。
- **回归守卫**：新增 `.workbuddy/verify_csssplit_476.mjs`（**27/0** = 静态 18 + 动态 9）。
  动态层用无头 Chrome 实证「外部 `<link>` 真的被加载并生效」
  （量到 body 渐变背景、.card 圆角/阴影、.topbar sticky、.grid 6 列、.u-card 圆角全部正确）。

# v4.7.5（2026-10-09）搜索结果页瘦身：搜索时不再渲染首屏标签云，单次请求约省 16 KB

- **用户诉求**：「搜索页单独显示，减少宽带占用」—— 搜索是用户主动"找东西"的行为，
  却在**每一页都白传**首屏标签云（渐变 banner + 50 个标签 + 漂浮动画 JS），
  这些只在"随便逛逛"时才有意义。
- **改动 ①（数据与 HTML）**：`core/Views.php::homeHtml()` 新增 `$isSearch = ($q !== '')`，
  标签云整块（`$bannerHtml` / `$tagLinks` / `$tagCloudJs`）**只在非搜索态渲染**；
  连带 `regularTags()` / `hotTags()` / `hotPeople()` **三次聚合查询也不再执行**（省 DB 开销）。
- **改动 ②（CSS 按需裁剪）**：`Views::css()` 增加 `$slim` 参数，
  搜索页剥掉用不到的 `.tagcloud/.tc-*`（标签云）、`.banner*`（渐变首屏）、
  `.latest*`（最新更新，搜索时本来就不渲染）规则块。
  实测内联 CSS **40800 B → 35770 B（-5030 B / -12.3%）**。
- **单次搜索请求合计省**：标签云漂浮 JS **6154 B** + 标签云 CSS **5030 B**
  + 50 个 `.tc-t` 链接 HTML **约 5600 B** ≈ **16.4 KB**（未压缩；gzip 后约 4~5 KB）。
- **安全设计**：
  - 剥离是**整块删**（含跨行规则与挤在单行 `@media` 里的声明块），
    只删首行会留下悬空花括号 ⇒ 浏览器**整份 CSS 判非法并全部丢弃**（页面裸奔）。
  - 剥完用 `cssBalance()` 自检花括号是否配平，**不配平就原样返回完整 CSS** ——
    多传几 KB 远好过页面裸奔。
- **不动**：搜索结果本身、排序 Tab、类型筛选、分页、顶栏搜索框、页脚、
  标题悬停滚动（`.ml`，长片名搜索结果仍需要）、网格补位（v4.7.4）、接口、数据库。
- **回归守卫**：新增 `.workbuddy/verify_slimsearch_475.mjs`
  （静态断言 + Node 复刻剥离逻辑验证 CSS 完整性 + 反向自测）。

# v4.7.4（2026-10-09）★ 手机端条目「最后一行排不满、右侧大片留白」：补位数量写死 21 张（PC 7 列）导致移动端补错

- **现象**（用户手机截图）：首页条目网格**最后一行只排得下 1~2 张卡，右侧整片空白**；
  用户指出「早期就这样，后面按 PC 版改过」，并要求检查其它自适应断点是否同样问题。
- **根因**：`core/Views.php` 的 `$gridAutoJs`（v4.1.1 起）用**写死常量 `PER=21`**，
  即「PC 版 7 列 × 3 排」的排数。但 `.grid` 用的是
  `repeat(auto-fill,minmax(140px,1fr))`（≤560px）/ `152px`（≤768px）/ `164px`（默认）/ `176px`（≥1400px），
  **列数随视口宽度变化**：实测 390px → 3 列、820px → 4 列、1280px → 6 列。
  写死 21 在移动端要么补多（多出十几个透明元素）、要么补少（末行仍不满）。
- **修法**：删掉 `PER` 常量，新增 `colCount(grid)`：
  ① 首选 `getComputedStyle(grid).gridTemplateColumns` —— 浏览器已把 `repeat()` 解析为实际列宽序列
     （如 `"159px 159px 159px"`），**其长度即真实列数**，最可靠；
  ② 解析不出（`none` / 空串）时兜底「量卡片 `offsetTop` 有几种不同值」。
  补位目标从「凑满 21」改为「**补到列数的整数倍**」：`(cols - cards.length % cols) % cols`。
  原有 `resize` 监听天然跟随断点 —— 旋转屏幕 / 改窗口宽度会自动重算。
- **实测**（真实 CSS + 无头 Chrome，三个断位 × 3/5/21 张卡片）：
  390px(3列) 3张→补0、5张→补1、21张→补0，各行均排满；
  820px(4列) 与 1280px(6列) 同样全部排满。旧 `PER=21` 在 390px/5张时会补 16 个（应为 1）。
- **不动**：卡片尺寸（保持原样，**不学 v4.0.9 那种把末排卡拉伸变大**——用户 v4.1.0 已明确否掉那种做法）、
  每页条数、断点宽度、接口、数据库。
- **回归守卫**：新增 `.workbuddy/verify_gridfill_474.mjs`（静态层 + 无头渲染动态层 + 反向自测）。

# v4.7.3（2026-10-09）★ 卡片标题重复显示两遍（「冰之城墙冰之城墙」）：无缝滚动副本靠 overflow 裁不住的坑

- **用户诉求**（前台截图，深色皮肤）：卡片标题显示成两遍，如「冰之城墙冰之城墙」。
  上一版刚把导演/演员名字并回首页标签云，用户怀疑是那批改动引入 —— **实测证明不是**：
  与本版无关，是 v3.6.5「长标题悬停滚动」起就存在的老缺陷（亮色/暗色模板都中招），
  只是短标题才显形、平时被长标题掩盖。

- **根因（★ 静态断言查不出，只有真浏览器渲染才暴露）**：
  `core/Views.php::cardHtml()` 为了「悬停无缝滚动」把标题渲染**两份**：
  `<span class="t-in">{标题}</span><span class="t-in t-dup" aria-hidden="true">{标题}</span>`，
  副本靠父级 `.c-title{overflow:hidden}` 裁掉。但卡片内容宽只有 **≈147px**
  （164px 卡片 − 左右 padding 13px − 边框），标题 15px/字 ⇒
  **「单份标题 ≤ 147px（≈9 个汉字）」时，两份副本加起来仍塞得进容器** ⇒
  副本完整落在可视区内 ⇒ 标题显示两遍。
  「冰之城墙」4 字 = 60px，两份 120px < 147px ⇒ 完整可见（与截图完全一致）。
  ≥10 字的标题才会溢出、被裁掉并打上 `.ml`，所以此前一直没被发现。

  **无头 Chrome 实测（1200px 视口，真实 CSS / 真实卡片结构）**：

  | 标题字数 | 单份宽 | 容器宽 | 修复前副本 | 修复后副本 |
  |---|---|---|---|---|
  | 1~4 字（如「冰之城墙」60px） | 15~60px | 147px | **完整可见** | 不可见 |
  | 5~9 字 | 75~135px | 147px | **部分/完整可见** | 不可见 |
  | ≥10 字（225px，加 `.ml`） | >147px | 147px | 被裁掉（正常） | 被裁掉（正常） |

- **修复**（`core/Views.php` CSS 2 行 + 标签云加固）：
  1. **主修**：`.c-title .t-in.t-dup{display:none}` —— 副本默认不参与渲染；
     `.c-title.ml .t-in.t-dup{display:inline-block}` —— 仅当 JS 判定「单份标题已溢出」
     而给容器打上 `.ml` 时才显示。此时副本起于容器右边界之外，依旧被裁，
     悬停滚动（`translateX(-50%)`）的无缝循环不受影响。
     判「溢出」的 `mark()` 量的是第一份 `.t-in` 的 `scrollWidth`，与本副本显隐无关，逻辑不动。
  2. **加固**：首页标签云合并闭包 `$push()` 改为**先归一化再去重** ——
     原写法 `isset($merged[$tag])` 在 `trim()` **之前**，于是「冰之城墙」与
     「冰之城墙 」/ 带全角空格 / 零宽字符的同一个名字会被当成两个标签各推一次，
     标签云里出现两个看上去一模一样的标签。现统一去掉首尾空白与不可见字符
     （U+3000 全角空格 / U+00A0 / U+200B~200D / U+200E~200F / U+FEFF）后去重，
     显示与 `href` 也都用归一化结果。

- **不动**：接口 / 数据库 / 表结构 / 标签页与人物页逻辑一律未动；纯样式 + 前端去重口径。
- **回归守卫**：新增 `.workbuddy/verify_titledup_473.mjs`
  （静态：副本默认 `display:none`、`.ml` 才显示、两份 `.t-in` 仍在、CSS 未被写回；
  动态：无头 Chrome 渲染 10 种标题长度 × 明暗两套皮肤，断言「短标题副本不可见 +
  长标题仍带 `.ml`」，并做反向自测证明非空转）。

# v4.7.2（2026-10-09）UI：本地导入弹窗「检测失效 / 导入 / 清空重来」三个操作上移到解析按钮之后

- **用户诉求**（后台截图）：本地资源导入弹窗里，三个操作指令（🔎 检测失效 / 📥 导入 N 条 / 清空重来）
  原先排在**结果列表最底部**，识别结果上千条（如 Excel 导入 1590 条）时必须一路滚到底才能操作。
  要求把这三个指令放到「解析文件」按钮后面。

- **改动**（纯前端 HTML 结构，`admin/index.php` 弹窗模板，2 处）：
  1. **Excel 导入**标签：`#leImportRow` 由 `#leResult` 之后上移到「🔍 解析文件」按钮之后、
     `#leFilterRow` 之前 → 顺序变为「解析文件 → 检测失效/导入/清空重来 → 筛选栏 → 结果列表」。
  2. **粘贴解析**标签：`#lpImportRow` 同法定序（「🔍 识别解析」之后），保持两个解析标签结构一致。

- **无逻辑改动**：`renderLocalPreview(resultId, countId, rowId, items, holder, filterId)`
  与 `localParseClear()` / `localExcelClear()` 全部按 **id** 取元素并各自切换 `display`，
  **与 DOM 顺序无关**；`lpCount` / `leCount` 计数、筛选、检测失效、导入行为均不变。不动数据库、不动接口。

---

# v4.7.1（2026-10-09）★ 导演/演员统计恒为 0（PHP 值语义坑）：后台人物云与首页标签云一并修复

- **现象**（后台 Ver 4.7.0 · 标签项目页截图）：统计行「共 32 项（标签 32 · **导演 0 · 演员 0**）」；
  诊断面板却明确显示「人物索引表已建立 · 索引行数 **680** · 已上架条目 86 条 ·
  其中带演职员信息 **77** 条」，点「重建人物索引」也回「✓ 已重建，写入 680 行索引」。
  ⇒ **数据在、索引在，统计却全是 0** —— 问题在「读」，不在「写」。
  前台同步表现：首页标签云里**导演/演员的名字整体消失**（首页标签云 = `regularTags()`
  + `hotPeople.directors` + `hotPeople.actors` 合并而成，同一个根因）。

- **根因（★ 致命 · PHP 值语义）**：`CacheStore::hotPeople()` 聚合段原来写成
  ```php
  $agg = ($bucket === 'act') ? $actAgg : $dirAgg;   // ← PHP 数组赋值 = 复制一份
  if (!isset($agg[$n])) { $agg[$n] = array(...); }
  $agg[$n]['clicks'] = ...;  $agg[$n]['items'] = ...;
  ```
  PHP 数组是**值语义**，`$agg[$n] = ...` 改的是**局部副本**，永远写不回 `$dirAgg` / `$actAgg`；
  而循环每轮都重新取一份桶（仍是空的）⇒ 循环结束后两个桶仍然是空的 ⇒
  `directors` / `actors` 恒返回 `[]`。该写法自 **v4.6.1** 起存在
  （v4.6.1 把统计口径从「实时遍历 extra」改为「读 person_index」时引入）。

- **为什么一直没被发现**：本机（Windows）**没有 PHP 运行时**，这段聚合**从未被真正执行过**；
  既有断言全是静态正则，而该写法**语法完全合法**，正则当然全绿。
  ★ 教训：涉及「聚合 / 累加 / 排序」的逻辑，静态断言不足以定案，必须补**语义镜像**断言。

- **修复（5 处）**：
  1. `hotPeople()` 改为**直接写真正的桶**（`$dirAgg[$n] += …` / `$actAgg[$n] += …`），不再经过临时变量；
  2. `hotPeople()` **空结果不入缓存** —— 此前无论是否为空都 `cacheSet` 600 秒，
     一次数据库抖动（catch 返回空）或索引正在重建，就会把「导演/演员为空」钉住 10 分钟，
     站长随后点「重建人物索引」「强制刷新」看到的仍是旧空列表，极易误判成「修了也没用」；
  3. `allPersonTags()` 同口径（注意 `is_array(array())` 为真，空数组此前会被当成缓存命中直接返回）；
  4. `personIndexIsStale()` 的**重复行检测分组键写错**：原为 `GROUP BY name, kind HAVING c > 1`，
     于是「同一个人出现在 2 部以上条目里」这种**完全正常**的情况也被判成「有重复行」
     ⇒ 恒返回 true ⇒ `ensurePersonIndex()` **每次请求都 TRUNCATE + 全量重建 person_index**，
     并顺带 `invalidateTagCaches()` ⇒ 标签/人物缓存**永不命中**（前台每页都全量聚合）。
     现改为 `GROUP BY name, kind, item_id`（等价于唯一键 `uq_person`）；
  5. `GET /api/v1/admin/hot-people?force=1` 顺带 `invalidateTagCaches()` ——
     后台「🔄 强制刷新」此前只绕过后端这一次查询，首页标签云仍吃旧缓存，刷了也不变。

- **回归守卫**：`.workbuddy/verify_hotpeople_471.mjs`（**19/0**，反向自测 **12 FAIL**）。
  核心是「**PHP 值语义镜像**」：按源码实际写法复刻聚合语义 + 刁钻样例
  （同 clicks 靠 items 分胜负、超 limit 截断、两桶分流），并**反向跑 `byvalue` 模式
  必须产出两个空桶**以证明镜像非空转。同时静态守住：空结果不入缓存、`GROUP BY … item_id`、
  自愈链路、首页合并链路、`force` 失效标签缓存 —— 都是「删掉就会让现象复发」的点。

- **数据库影响**：无（不改表结构，只改聚合与缓存策略）。部署后在后台点一次
  「🔄 强制刷新」即可（会一并失效首页标签云缓存）。

# v4.7.0（2026-10-05）剔除测试站域名（文档/示例一律改中性占位）

- **背景**：`cs.zyfx.wang` 是**测试站，随时可能被删除**。此前它作为「示例域名」散落在
  后台设置提示、`config.sample.php` 注释、`preview/` 设计稿与 `README.md` / `DEPLOY.md` 部署示例里，
  容易让人误以为它是正式站或可访问的演示站 —— 站一旦下线，这些示例就变成误导。

- **改动**：所有「示例站点地址」改为中性占位，不再出现任何真实测试站域名：
  - `core/Settings.php`「网站网址」设置项提示：`例：https://cs.zyfx.wang` → `例：https://你的域名`；
  - `config/config.sample.php` 的 `site.url` 注释：同上；
  - `preview/install.html` / `site-basic.html` / `site-seo.html` / `sync.html` 四份设计稿
    （`preview/` 不进发布包，仅本地留档）里的地址与计划任务示例路径；
  - `README.md` / `GitHub/README.md` / `GitHub/DEPLOY.md` 里的站点根目录示例
    `/www/wwwroot/cs.zyfx.wang` → `/www/wwwroot/你的站点目录`。

- **当时不动**：`PRODUCTION.md` 里的 `zyfx.wang` Nginx/日志/备份示例、以及 `api.zyfx.wang`、
  `sq.zyfx.wang` 等**其它项目/授权服务器**地址。
  ★ **v4.8.3 起已一并剔除**（用户指出：这两个是**测试期地址**，不该留在源码里）——
  现全部改为中性占位「`你的域名`」，详见 v4.8.3 记录。
- **历史故障日志不作改写**：`cs.zyfx.wang` 出现在 2026-09 的故障记录里是**当时的事实**
  （路径、报错、502 现场），删掉会失去追溯依据；只从「现役配置与文档」里剔除。

- **数据库影响**：无（仅文案与文档）。

# v4.6.9（2026-10-05）导演/演员「被去掉」：写入侧防丢 + 标签页可诊断可自愈

- **现象**（后台 Ver 4.6.8 · 标签项目页截图）：统计行显示「共 38 项（**标签 38 · 导演 0 · 演员 0**）」，
  「🏷️ 常规标签」38 个标签都在，**热门导演 / 热门演员两个区块整体归零**，页面底部只剩
  「暂无演职员数据 —— 同步 / 拉详情后自动充实…」。用户原话：「后台的标签修改的时候把导演和演员给去掉了」。

## 根因（逐段核对链路后定位）

`hotPeople()` 读的是 `person_index JOIN media_items ON id WHERE m.status='active'`，
而 `person_index` 完全由 `media_items.extra` 派生（`indexPersonForItem()` / `rebuildPersonIndex()`）。
⇒ **只要 `extra` 里的 `cast` / `director` / `actors` 被写空，索引随之清空、人物云必归零**，
而 `genres`（常规标签）不受影响 —— 正是「标签还在、人名没了」这一现象。

写空人物字段的两处：

1. **`CacheStore::put()`**（同步采集 / 每小时计划任务都走它）：
   `ON DUPLICATE KEY UPDATE extra=VALUES(extra)` 是**整体覆盖**。
   上游（TMDB / 红果）限流、字段改名、临时返回残缺 JSON 时，本次 `extra` 里的人物键是空值，
   一次同步就把库里已有的人名清成空 ⇒ 索引被 `indexPersonForItem()` 删空 ⇒ 导演/演员归零。
   ⚠️ 关键细节：适配器 `normalize()` **每次都会写** `cast`/`director`/`actors` 三个键（采不到时为空串），
   所以防丢判定必须是「本次该键**是否有值**」，按「键是否存在」判等于没防。
2. **`CacheStore::updateById()`**（后台编辑条目保存）：提交的 `extra` 是前端拼的、整体替换，
   「只想改个类型标签/年份」也可能把人名一起清掉（`genres` 早有合并保护，`extra` 一直没有）。

## 修复

**① 写入侧防丢**（`core/CacheStore.php`）

- 新增常量 `PERSON_EXTRA_KEYS = [cast, director, actors, host, writers]` 与两个判定器：
  `hasPersonKeyValued()`（有值）/ `hasPersonKeyPresent()`（只看键在不在）。
- `put()` 新增 `keepPeopleOnSync()`，写库前把旧人物字段补回：
  - 本次 `extra` 整体为空（一个扩展字段都没采到）→ **整体沿用库中原值**（原始响应另存 `payload`，可追溯）；
  - 本次人物键**缺失或为空**、库里有值 → 用库里的值补回（残缺响应不算「删除」）；
  - 本次人物键有值 → 一律以本次为准（正常换导演/演员照旧生效）。
- `updateById()` 新增 `inheritPeopleOnUpdate()`：**提交里一个人物键都没有**才继承库中原值；
  只要出现过人物键（哪怕空串 = 用户显式清空）就以提交为准 —— 显式清空仍然生效。
- 两条规则都带 `try/catch`，读旧值失败不阻断入库/保存。

**② 标签页可诊断 + 可自愈**（此前只能 SSH 上服务器查表）

- `hotPeople($limit, $type, $force = false)`：新增 `force`，`true` 时绕过 600 秒文件缓存直查数据库。
- 新增 `CacheStore::peopleDiag()`：返回 `index_table / index_rows / active_items /
  active_with_people / pending_with_people` 五个计数（整体 `try/catch`，诊断失败不抛）。
- `GET /api/v1/admin/hot-people` 支持 `?force=1`，并在 `data.diag` 里带回诊断。
- 新增 `POST /api/v1/admin/rebuild-person-index`：一键 `rebuildPersonIndex()`，不用再 SSH 跑脚本。
- 后台标签页「🔄 强制刷新」由 `renderTags(true)` 改为 `refreshTagPage()` ——
  **标签与人物云一起绕过缓存重查**（此前只刷标签，人物云仍吃 600 秒缓存，误判成「人名没了」）。
- 人物云为空时展开「🩺 人物数据诊断」面板，直接告诉你属于哪种情况：
  索引未建 / 索引为空（点「重建人物索引」即可）/ 已上架条目确实没有演职员信息（需重新拉详情回填）
  / 人名只在条目暂存里（刮削通过后才进统计）/ 有数据但需强制刷新排除缓存。

## 已丢失的数据怎么办（重要，请务必读完）

本版**只防再丢，不会凭空造数据**：`extra` 里已经被人名清空的条目，需要重新拉一次详情回填
（「同步采集」或「自动刮削」）。回填后再点标签页的「重建人物索引」，导演/演员区块即恢复。
回填过程本身现在也是安全的 —— 中途任何一次上游残缺响应都不会再把人名抹掉。

## 数据库影响

**不改表结构**。`person_index` 仍由既有 `ensurePersonIndex()` 自建（v4.5.8 起），
`peopleDiag()` 只是只读计数。部署后清一次缓存即可生效。

## 验证

| 项目 | 结果 |
|---|---|
| 新增 `.workbuddy/verify_people_469.mjs` | **34 通过 / 0 失败**（含行为模型镜像 4 场景 + 反向自测 12 FAIL） |
| PHP 语法 | 73/73 |
| 内联 JS（admin/index.php / core/Views.php） | 3/3 · 5/5 |
| 历史审计 `audit_changelog.mjs` | 54/54 |
| 业务断言（sort/person_sync/ovstats/pantable/infoblock/yearpill/nofreeze 等） | 全绿 |
| 版本闸门 / 产物三方逐字节一致 | 全合规 |

# v4.6.8（2026-10-05）详情页「年份」胶囊：年份无效时不再渲染

## 现象
详情页 hero 指标行里，「年份」胶囊在条目**没有年份**（或年份非法）时仍照常渲染，
呈现为一个**光秃秃、只有标签没有数字**的空胶囊（截图反馈：`短剧 都市 都市日常 评分 — 年份 热度🔥1010 点击0`）。
用户反馈：「这里的年份是无效的，清理掉。」

## 根因
`core/Views.php::renderItemPage()` 里 `$year` 在无年份时被置为空串 `''`，但模板里
`<span class="s">年份<b>{$year}</b></span>` **无条件渲染** ⇒ 空值就变成「年份」+ 空白。
对比同行的「热度」自 v4.3.3 起就是「无值就不渲染」（`$hotStat` 为空串即不输出），「年份」漏了这层判断。

## 修复
把「年份」也改成**有值才渲染**（与 v4.3.3 热度同口径）：
- 新增 `$yearStat`，仅当 `$year` 非空且落在 **1900 ~ 2100** 的合理四位年区间**内才生成胶囊；
- 模板 `<span class="s">年份<b>{$year}</b></span>` 改为 `{$yearStat}`；
- **不改动 `$year` 本身** —— 结构化数据（`headMeta` 的 `year`）仍按原样取用，避免影响 SEO/卡片。

## 验证
新增 `.workbuddy/verify_yearpill_468.mjs`（静态 + 行为模型，含反向自测）；php 语法 / 内联 JS / 契约 / 历史审计全回归；
版本闸门与产物三方一致全绿。

## 产物
- `发布包/media-library-api-v4.6.8.zip`
- `升级包/media-library-api-update-v4.6.7-to-v4.6.8.zip`

# v4.6.7（2026-10-05）短剧/影视信息块：演员行常驻（与导演一致，缺数据显「暂无」）

## 现象
详情页「短剧信息」区块里**只有「导演」一行**；条目没有演员数据时，「演员」**整行消失**，
看起来像功能缺失。用户反馈：「短剧这里，不能只有导演，演员也需要，如果没有可以空着。」

## 根因
`core/Views.php::extraFieldsBlock()` 里只有 `director` 被设为「常驻行」（v4.4.5），
`actors` 未纳入 ⇒ `extra` 里没有 `actors` 时该行被 `continue` 跳过；
且 `extra` 全空时的兜底分支也只补了「导演 暂无」。

## 修复
把「常驻行」从「仅导演」扩展为「导演 + 演员」（统一走 `$__alwaysRow`）：
- 影视分类 **movie / tv / short / anime**：导演、演员均常驻，无数据显示「暂无」；
- **variety（综艺）** 无「演员」概念（用「主持人」）⇒ 仍只保留导演；
- `extra` 全空的兜底分支同步补「演员 暂无」；
- 演员行沿用 v4.4.9 的三列网格宽度（**演员占 2/3、导演占 1/3**）。

数据来源已确认：`adapters/Tmdb.php`（写入 `extra.actors` 字符串版）与
`adapters/HongguoDuanju.php`（`extra.actors`）都会写该字段 ⇒ **有数据照常渲染、无数据显「暂无」**。

## 验证
新增 `.workbuddy/verify_infoblock_467.mjs`（**11 通过 / 0 失败**，含反向自测：改回仅导演即报 2 FAIL）；
php 语法 / 内联 JS / 契约 / 历史审计（54/54）全回归；版本闸门与产物三方一致全绿。

## 产物
- `发布包/media-library-api-v4.6.7.zip`
- `升级包/media-library-api-update-v4.6.6-to-v4.6.7.zip`

# v4.6.6（2026-10-05）★ 致命修复：标签页 / 列表页「点进去后整个页面完全卡死」

> **这是自 v4.4.5 起就存在的严重前端 BUG，会冻结整个浏览器标签页。**
> 触发面比想象的广：**任何一页条目不足 21 张的列表页**——
> `/tag/{标签}`、`/person/{导演|演员}`、搜索结果页，以及总数不足 21 条的首页。
> 首页恰好排满 21 张时不会触发，所以此前一直没被发现（也让我们一度误判成"浏览器缓存"）。

## 现象

点标签（或任何条目数 < 21 的列表页）进入后：页面渲染到一半就**完全卡住**——
标签页转圈不结束、滚动无效、点什么都没反应，浏览器甚至弹出「页面无响应」。

## 根因：`core/Views.php` 中 `$gridAutoJs` 的死循环

v4.1.1 为了「每页 21 张（7 列 × 3 排），不满就补透明占位凑满」加了这段脚本，其中清理旧占位写的是：

```js
var phs = grid.querySelectorAll(':scope > .ph');   // ← 静态 NodeList！
while (phs.length) phs[phs.length - 1].remove();   // ← 死循环
```

`querySelectorAll` 返回的是**静态 NodeList**：调用 `remove()` 之后 `phs.length`
**永远不变**（DOM 变了，这个列表不跟着变），于是 `while` 条件恒为真 → **无限循环**，
主线程 100% 占用、页面再也无法响应任何事件。

为什么只有列表页炸、首页不炸：

1. 首次 `fix()` 执行时 DOM 里还没有 `.ph`，`phs.length === 0`，`while(0)` 不执行 → 安全；
   接着按 `21 - 卡片数` 补上 n 个占位元素。
2. `setTimeout(fix, 250)` / `setTimeout(fix, 900)` / `window resize` 触发**第二次** `fix()`，
   此时 `.ph` 已存在 → `phs.length > 0` → **进入死循环**。
3. 首页满 21 张 ⇒ `n = 0`，从不生成占位 ⇒ `phs.length` 恒为 0 ⇒ 首页安然无恙。

## 修复

先快照成真数组，再用**定长 for**逐个移除（每个元素只删一次，必然终止）：

```js
var phs = Array.prototype.slice.call(grid.querySelectorAll(':scope > .ph'));
for (var pi = 0; pi < phs.length; pi++) {
  var old = phs[pi];
  if (old && old.parentNode) { old.parentNode.removeChild(old); }
}
```

## 验证方式（可复跑）

用无头 Chrome 真实渲染线上标签页 HTML，对比「JS 开 / 关」与「逐脚本剥离」：

| 变体 | 结果 |
|---|---|
| 禁用 JS | 正常渲染 |
| 保留全部脚本 | **超时 40s 未返回（卡死）** |
| 仅移除 `$tagCloudJs` | 仍卡死 |
| 仅移除 `$titleScrollJs` | 仍卡死 |
| **仅移除 `$gridAutoJs`** | **正常渲染** |
| 换成修复版脚本 | 正常渲染 |

新增回归断言 `.workbuddy/verify_nofreeze_466.mjs`：
静态查「静态 NodeList + while」模式 + 用无头 Chrome 渲染最小复现页，**卡死即 FAIL**，
把这类「语法完全合法、静态扫描查不出、只有真跑浏览器才暴露」的 BUG 挡在发布之前。

# v4.6.5（2026-10-03）全量扫描修复：图片代理失效 / 分区误删 / 统计口径不统一

> 本版不是新增功能，是**一次全量代码审计后的修复集**。
> 扫描手段（均为新建脚本，可复跑）：`deepscan.mjs`（依赖/注入/废弃语法）、
> `deps.mjs`（类已用未引入）、`varscope.mjs`（变量未赋值先用）、
> `methods.mjs`（调用了不存在的方法）、`argc.mjs`（必填参数不足）。

## BUG 1 · 站内图片代理 mimg.php 全程失效（影响最大）

- **现象**：TMDB 海报全靠 `img onerror` 的直连回退才显示，走代理的地址永远返回 1×1 透明占位图。
- **根因**：`mimg.php` 里用了 `Core\DB`（读 `image_proxy.enable` 开关）与 `Core\CurlSafe`（回源下载），
  但**这两个文件从头到尾没有被 require 过**。`/mimg.php` 是 Nginx 直接服务的公开端点，
  不经过 `index.php`，项目也没有注册 autoloader ⇒
  `class_exists('Core\\DB')` / `class_exists('Core\\CurlSafe')` **恒为 false**：
  ① `image_proxy.enable` 开关形同虚设；② 回源分支被整体跳过，`$body` 恒为空 ⇒ 必返回占位图。
- **修复**：补 `require_once core/DB.php` 与 `core/CurlSafe.php`（带 `file_exists` 保护，兼容精简部署）。
- 由 `.workbuddy/deps.mjs` 检出（全库仅此 1 处「用了但没引入」）。

## BUG 2 · cron/rate_prune.php 会误删保留期内的分区（数据提前丢失）

- **根因**：`$nowMonth = (int)date('Ym'); $keepFrom = $nowMonth - 12;`
  YYYYMM 是十进制整数，减 12 **不等于**往前推 12 个月 —— 跨年时得到非法中间值
  （202601−12=202589），而真实分区名最大只到 YYYY12 ⇒
  **上一整年的分区都会被判成"过期"而 DROP**，实际只保留了当年 1 月起的数据。
- **修复**：改用真正的日期运算 `date('Ym', strtotime('-12 months'))`；
  并移除硬编码的 `PARTITION_NAME != 'p202612'` 魔法值（跨过该月即失效），
  改为「未来分区（> 当月）一律保留」。

## BUG 3 · cron/link_check.php 使用了未定义变量 `$id`

- 保存失败的告警里写的是 `"... item #$id: ..."`，但本作用域的变量是 `$row`。
  PHP 8 会抛 `Warning: Undefined variable $id`，且日志里的条目号**永远是空的**，排障时看不到是哪条。
- 修复为 `(int) $row['id']`。由 `.workbuddy/varscope.mjs` 检出。

## BUG 4 · 标签 / 人物统计口径与列表页不一致

以下三处此前**都没有 `status='active'` 过滤**，而列表页 `listBySort()` / `countItems()` 全都带：

| 位置 | 影响 |
|---|---|
| `CacheStore::hotTags()` | 首页标签云把「条目暂存（pending）」的标签也算进去，条目数与点进去后的真实条数对不上 |
| `CacheStore::allTagsWithCounts()` | 后台「标签项目」页计数偏大 |
| `CacheStore::allPersonTags()` | 只在暂存条目里出现过的人名也会进人物标签列表，点进去 0 条 |

已统一补上 `status='active'`（`allPersonTags` 额外 JOIN `media_items` 校验状态），
并把 `hotTags()` 的 `type` 参数从 `quote()` 拼接改为预处理绑定。

## BUG 5 · 缺 mbstring 扩展时前台人物页直接 Fatal

- DEPLOY.md 与安装说明里 mbstring 写的是「**建议**启用」，不是必装；
  但 `index.php` 的 `/person/` 路由直接调用 `mb_strlen($name) > 60`，还有 30+ 处同类调用。
  ⇒ 装在没有该扩展的机器上，**人物页直接 500**，与"建议扩展"的定位矛盾。
- **修复**：新增 `core/Mbcompat.php`（扩展缺失时提供 UTF-8 安全的 `mb_strlen` / `mb_substr` /
  `mb_strtolower` / `mb_stripos` 纯 PHP 实现），由 `core/Guard.php` 在开头引入 ——
  Guard 是所有入口的第一个加载项，保证任何调用点之前已可用。扩展存在时本文件零开销。

## BUG 6 · 其它

- `cron/pan_match_delayed.php`：`catch (\Error)` 写在 `catch (\Throwable)` 之后 ⇒ **永远不可达**的死分支，已删除。
- `__migrate_189.php`：调用根本不存在的 `Core\DB::instance()`，且建的是早已废弃的 `media_users` 表、
  用 SQLite 方言 ⇒ 一运行必 Fatal。已改为「明确作废提示 + 安全退出」。
- `core/Session.php`：此前**没有 require Guard**，守卫写在文件末尾且靠 `function_exists` 兜着，
  只有在「碰巧先加载过 Guard」时才生效；已与其它 core 文件统一在开头 require。
- `core/XlsxReader.php`：补 `simplexml` 扩展缺失时的可读报错（原先是致命错误，后台看不到原因）。

## 工具脚本自身的修复

- `audit_changelog.mjs` 等 8 个校验脚本：`read()` 用 `try/catch` 把 ENOENT 吞成空串 ⇒
  根目录传错时 54 项断言**静默全 FAIL**（曾误报"修复被改回去"）。现改为缺文件即显式报错退出，
  并支持「传项目根」或「传原始源码目录」两种写法。
- `deepscan.mjs` 白名单补齐（`session_name` / `session_status` / `session_write_close` / `set_exception_handler`）。

## 扫描结论（本版之后）

| 检查 | 结果 |
|---|---|
| PHP 语法（73 文件） | 通过 73 / 失败 0 |
| 适配器契约 | 全部通过 |
| 内联 JS（3 文件） | 通过 9 / 失败 0 |
| 「用了但没引入」 | 0 |
| 「未赋值就使用」 | 0 |
| 「调用了不存在的方法」（820 处静态调用） | 0 |
| 「必填参数不足」（1240 处命中） | 0 |
| 历史修复回溯审计 | 54 / 54 仍有效 |

---

# v4.6.4（2026-10-03）pan_transfers 建表补进安装脚本（新装即完整）

- **问题**：`pan_transfers`（网盘转存记录表）此前**只在 `core/PanTransfer.php::ensureTable()` 里运行时自愈建表**，
  `sql/install.sql` 与 `core/Installer.php` 都没有它 —— 新装站点**首次使用网盘转存时会临时建表**。
  功能不会坏（自愈是幂等的），但新装站点不是"装完即完整"。
- **本版**：把同一份 DDL 补进 `sql/install.sql`（link_checks 之后）与
  `core/Installer.php` 的建表数组，并在安装完成提示的表名列表里加上它。
- **三处一致性**：以 `PanTransfer::ensureTable()` 为基准，用归一化比对（压缩连续空白后逐行相同）
  校验 `install.sql` / `Installer.php` 与之**结构完全一致**，避免出现
  「安装建的表」与「运行时建的表」字段/索引不同这种隐性坑。
  断言：`.workbuddy/verify_pantable_464.mjs`（15 项）。
- **老站点完全不受影响**：`ensureTable()` 仍是 `CREATE TABLE IF NOT EXISTS`，预建的表会被直接复用，幂等无副作用。
- 同批附带：`F:\ZCode\万能资料库` 旧副本（v4.5.7，无任何独有文件）已归档为
  `万能资料库_已归档_20261003`，唯一活项目目录统一为 `F:\WorkBuddy\万能资料库`。

# v4.6.3（2026-10-03）后台概览页补回「内容总览统计」

- **现象**：概览页只有顶部总数卡片 + 「点击最多的 50 个条目」，下方没有统计区块（用户反馈"本来有的，不知道哪次改没了"）。
- **新增**：
  - 后端 `CacheStore::overviewStats()` + `GET /api/v1/admin/overview_stats`。
    全部走聚合查询（一条明细都不取），**只读、不改任何数据**；整体 `try/catch`，统计失败不影响概览页其它区块。
    老接口 `stats()` **保持不动**（别处在用）。
  - 前端概览页「点击最多的 50 个条目」下方新增「📊 内容总览统计」区块，复用现有 `stat-grid` 样式，分三组：
    - **条目总览**：正式条目 / 暂存待刮削 / 近 7 天新增 / 近 30 天新增 / 年份跨度 / 平均评分 / 标签总数 / 人物总数
    - **数据完整度**：缺海报 / 缺简介 / 缺评分 / 缺标签 / 未匹配网盘（各带占比）
    - **流量与热度**：点击总量 / 有点击条目 / 零点击条目 / 条目均点击 / API 调用总量
  - 加载时机：切到概览 Tab 时加载 **且** 页面初始加载就加载（概览是默认 Tab，否则首次进来是空白）。
- **口径坑（写这功能时实测确认的）**：`media_items` **没有 `created_at` 列**，只有 `cached_at`，
  而 `cached_at` 会被 `put()` 的 `ON DUPLICATE KEY UPDATE` **刷新** —— 拿它算「新增」等于算「最近同步过」，
  口径不成立会虚高。改用「以 `updated_at` 为参照的历史最大 `id`」作基准来近似近 7/30 天新增（稳定、无需改表）。
- 验证：新增 `.workbuddy/verify_ovstats_463.mjs` **53/53**（含两条口径红线断言：
  不得用 `cached_at` / `created_at` 算新增）；六套既有断言全回归（23/30/36/30/20/27）；
  php-parser **72/72**；admin 内联 JS **3/3**。

# v4.6.2（2026-10-03）标签页「能看但点不动」修复（死页兜底 + 防旧缓存）

- **现象**：点标签云进页后整页点击无反应，地址栏是 `/tag/?sort=click`（标签名为空、`sort` 还是单数）。
- **实测结论：线上代码本身是好的** —— `/tag/喜剧` 返回 HTTP 200 / 75KB，含 50 个标签云链接 + 14 个卡片链接
  （`<a class="card" href="/uisc/218.html">`），全部可点。真正的原因是：
  1. `/tag/`（空标签）被 **Nginx 抢先返回 HTML 404**（`try_files $uri $uri/` 命中空目录，PHP 没机会执行），
     浏览器却仍显示上一页的 DOM —— 观感就是「页面能看、但点不动」；
  2. 覆盖升级后浏览器 / CDN 仍留**旧版 HTML**，里面的排序与标签链接还指向旧路由（旧 HTML + 新 JS 混用）；
  3. `sort=click`（单数）只存在于旧版缓存 —— 线上新版白名单已把它过滤（实测页内只剩 4 个合法值）。
- **修复（三处，均不依赖用户手动清缓存）**：
  1. **死页兜底**：`/tag/` 空 slug 与 `/person/` 非法人名不再吐 JSON 404，改为 **302 回首页**，
     让旧书签 / 旧缓存进来也能自救。
  2. **防旧缓存**：首页（`home.php`）、`/tag/`、`/person/` 显式 `Cache-Control: no-store`（含 `Pragma: no-cache` 兼容老代理），
     覆盖升级后强制回源，不再拿旧 HTML 渲染。**详情页 `/uisc/*.html` 由 Nginx 直接服务、照旧长缓存，不受影响。**
  3. **空态给出路**：标签 / 影人查不到时，除了说明原因，再给「返回首页」「看全部条目」两个可点按钮
     （新增 `.empty-actions` 样式，用真实主色变量 `--pri`），消除「像死页」的观感。
- 验证：新增 `.workbuddy/verify_tagpage_462.mjs` **23/23**；五套既有断言全回归（30/36/30/20/27）；
  php-parser **72/72**；admin 内联 JS **3/3**。
- ⚠️ 覆盖升级后**建议仍按 F5 / Ctrl+F5 强刷一次**以清掉浏览器已缓存的旧 HTML（本版之后会由 no-store 自动生效）。

# v4.6.1（2026-10-03）标签页「显示 1 部、点进去 200+ 条」修复（人物索引口径统一）

- **现象**：后台标签项目页「热门演员/导演」卡片显示 N 部（如「熊慶兒 1」），点进详情却列出 200+ 条，
  且内容是《Running Man》《WWE Raw》等完全无关条目（实测线上 sitemap 共 220 条，即几乎全库）。
- **根因：同一个人名存在两套互相独立的数据源，口径必然对不上**
  - `hotPeople()`（列表数字）实时遍历 `media_items` + `personNames()` 解析 → 显示 1；
  - `itemsByPerson()`（详情列表）查 `person_index` 索引表 → 另一套结果。
  - 叠加一：v4.5.8 建的 `person_index` **没有唯一约束**，重复 `(name,kind,item_id)` 行会把 COUNT 放大；
  - 叠加二：`ensurePersonIndex()` 原逻辑「表非空就 return」，索引部分缺失后**永远不会再回填**；
  - 叠加三：v4.6.0 的兜底回退走 `whereTag()`，其第三个条件是**任意子串**匹配，会把大量无关条目卷进来。
- **修复**：
  1. **口径统一到 `person_index`**：`hotPeople()`、`allPersonTags()` 均改读索引表，与 `itemsByPerson()` 同源；
     列表显示的部数与详情列出的条数从此天然一致（`hotPeople` 顺带省掉一次全表扫 + 逐行 `json_decode`）。
  2. **加唯一约束** `UNIQUE KEY uq_person (name, kind, item_id)`（`CacheStore` / `Installer` / `install.sql` 四处 DDL 同步）；
     插入改用 `INSERT IGNORE` 幂等。
  3. **索引自愈**：`ensurePersonIndex()` 不再「非空就不管」，先 `upgradePersonIndexSchema()` 给老库去重并补唯一键，
     再由 `personIndexIsStale()` 做**重复行检测 + 40 条小样本覆盖率检测**，判定过期即自动全量重建。
  4. **兜底回退收紧**：新增 `listItemsExactGenre()` / `countItemsExactGenre()`，用
     `JSON_CONTAINS(genres, JSON_QUOTE(?))` 只认**精确 JSON 元素**，不再用宽松子串。
- 验证：新增 `.workbuddy/verify_person_sync_461.mjs` **30/30**；四套既有断言全回归（36/30/20/27）；
  php-parser **72/72**；admin 内联 JS **3/3**；版本号合规自检通过。
- ⚠️ **本版含表结构变化**：`person_index` 新增唯一键，老库首次访问人物页/标签页时自动去重 + 补键 + 必要时重建索引
  （数据量大时可能有一次稍慢的重建，属正常）。

# v4.6.0（2026-10-03）后台「标签项目」点进去 0 条修复

- **现象**：后台 → 标签项目 → 点标签/热门导演/演员，标题正常但列表空（截图：标签「雨真相」共 0 条）。
- **根因（两处）**：
  1. `/api/v1/admin/items` **完全忽略 `tag` 入参** —— 前台按标签能查到、后台查不到，后台只按 `q` 搜标题；
  2. `openTagDetail(tag, kind)` 的 `kind` **只被用来改标题，取数时压根没用**，一律按 `tag=` 查。
     而 `CacheStore::peopleTags()` 写进 `genres` 时**只取前 3 导演 + 前 6 演员**，
     排在后面的演员/导演（如「雨真相」）`genres` 里根本没有 → 按标签查**必然 0 条**。
- **修复**：
  - `api/unified.php`：`admin/items` 新增 `tag` / `person` + `kind`(director|actor) / `per` 入参；
    人物走 `itemsByPerson()`（精确查 v4.5.8 建的 `person_index`，不受 `peopleTags` 截断影响），
    并对 `person` 做长度与 UTF-8 合法性校验；`per` 默认 20、上限 100（原先硬编码 20，详情一次看不全）。
    双保险：`person_index` 未覆盖到该人时（少数脚本直接改 `extra` 不走索引维护）自动回退按 `genres` 子串再查一次。
  - `admin/index.php`：`openTagDetail` 按 `kind` 分流 —— 人物走 `person=`+`kind=`，常规标签走 `tag=`；
    删除把标题无条件覆盖回「标签…」的冗余赋值（导演/演员标题此前白写了）；加载前显示「加载中…」，失败在提示行显示而非只弹 alert。
- 验证：新增 `.workbuddy/verify_admin_tag_4510.mjs` **29/29**；php-parser 语法 **72/72**；admin 内联 JS **3/3**。
- 纯取数口径修复，不改表结构、不改数据。

# v4.5.9（2026-10-03）首页标签云「点了没反应」修复（纯前端布局）

- **现象**：点首页标签云里的标签进人物页，页面像"没反应"（实测后端 HTTP 200 / 0.3s / HTML 完整，标签链接本身也正常跳转，故障在前端布局层）。
- **根因**：标签云是 JS 绝对定位（`.tc-t{position:absolute;transform:translate3d()}`）放在 `.banner{overflow:hidden}` 里，而容器高度原先只按 `place(tags,false)`（**不打乱**那次装箱）计算；实际生效的 `base` 却是 `shuffle()` 打乱后重新装箱的，行分布可能更高 → 超出部分被裁掉，**标签看得见却点不到**。叠加 `drift()` 每 2.8 秒只钳制下界、**不钳上界**，标签还会持续向下漂出可视区，漂得越久点不中的越多。
- **修复**（`core/Views.php` 标签云 JS）：
  - `rebuild()` 改为遍历 arrange / scatter 两组坐标，用 `offsetHeight` 取**真实最大底边**并留 24px 余量来定容器高度；容器显式 `overflow:visible`，不再裁剪。
  - `drift()` 增加 x / y **上界钳制**（按 `stage.clientWidth / clientHeight` 与标签自身尺寸计算），标签漂不出可视区。
  - 新增**降级兜底**：1.2s 后自检，若排版未生效（`minHeight` 未写成功）则把全部标签退化为 `position:static` + 清除 transform 的普通可点链接，杜绝"全部堆在左上角互相压住、点不到"。
- 点击链路本身未改（原先就正确：点标签直接放行跳转，只有点空白处才切换排列/漂浮），本版补了回归断言防止改坏。
- 验证：新增 `.workbuddy/verify_tagcloud_459.mjs` **20/20**；php-parser 语法 **72/72**。
- 纯前端布局修复，不改表结构、不改数据、不影响后端接口与静态详情页。

# v4.5.8（2026-10-03）点标签进人物页「卡住」修复 + person_index 索引表

- **修 `?sort=` 脏值**：人物页 `/person/` 与标签页 `/tag/` 原先直接透传 `$_GET['sort']`，非法值（实测出现过 `?sort=2020` —— 标签云里的人名标签被当参数带进来）会被原样渲染进 4 个排序 Tab 的链接，表现为「点标签进去地址栏挂着怪参数、页面像卡住」。现两处都补白名单（`api_calls`/`clicks`/`updated_at`/`rating`），非法值回落 `clicks`，与首页 `home.php` 口径统一。
- **人物页性能根治（真正的卡顿来源）**：原 `CacheStore::itemsByPerson` 对 `media_items.extra` 做 `LIKE '%名字%'` —— `extra` 是**无索引的 TEXT**，等于每次打开人物页都**全表扫描** + 把每行 `extra` 拉回 PHP 逐条 `json_decode`，条目一多必然卡死。
  新增 **`person_index(name, kind, item_id)`** 索引表（`name+kind` 联合索引），人物页改走索引精确匹配 + SQL 层分页排序，`extra LIKE` 全表扫彻底移除。
- **索引自动维护 / 自愈**：`put()`（采集入库）、`insertManual()`（手动与本地导入）、`updateById()`（后台改 extra）、`Sync` 富化写回，四处写入点均同步索引；`deleteById()` 同步清理。老站点升级后首次打开人物页自动建表并全量回填（`ensurePersonIndex`），无需手工执行 SQL；另提供 `rebuildPersonIndex()` 供 cron / 后台手动重建。
- 建表已同步进 `core/Installer.php` 与 `sql/install.sql`（新装站点直接具备）。
- 排序稳定性不降反升：人物页排序由 SQL `ORDER BY ... , m.id DESC` 保证全序唯一，`updated_at` 用 `IFNULL` 兜住 NULL。
- 验证：php-parser 语法 72/72；新增 `.workbuddy/verify_person_458.mjs` 索引与白名单断言 **36/36**；`verify_sort_457.mjs` 排序断言 **27/27**。
- ⚠️ **本版含表结构变化（新增 person_index）**，升级包为全量覆盖；覆盖后建议访问一次任意人物页触发索引回填（旧数据），或后台手动重建。

# v4.5.7（2026-10-03）首页 / 人物页四个排序 Tab 判断修正

- 修复排序「看起来没反应、顺序错乱」：`listBySort` 原先裸写 `ORDER BY $order DESC`，四个问题叠加 —— ①`updated_at` 未出现在 SELECT 中，`LIMIT/OFFSET` 分页无行序保证；②`rating` / `updated_at` 允许 `NULL`，MySQL 裸排序让 NULL 恒沉底（前台显示 `0.0`，夹在中间就像没排序）；③无次级排序键，同分条目由 InnoDB 任意决定先后（表现为「热度」下 15 → 2 → 3 → 1 → 12 这种非降序）；④全序不唯一，翻页会重复 / 漏条目。
- 现按档位补齐：评分 `IFNULL(rating,0) → api_calls → year → id`；最新 `IFNULL(updated_at) → id`；热度 `api_calls → clicks → id`；点击 `clicks → api_calls → id`，四档均以 `id DESC` 兜底。**无评分条目按 0 参与排序**（不再一律沉底），「最新」口径仍为 `updated_at`（保持不变，仅修稳定性）。
- 同步修正同源问题：`CacheStore::listItems`（`/api/v1` 列表接口）补 `updated_at` 与次级键；`CacheStore::itemsByPerson`（人物页 usort）NULL 显式归 0、`updated_at` 改用 `strtotime` 时间戳比较（原先掉进 `strcmp` 字符串比较）、补 `id` 兜底。
- 纯排序口径修正，不改表结构、不改数据、不改页面结构；覆盖升级即可，无需清缓存 / 重新生成静态页（静态详情页 `/uisc/{id}.html` 不受影响）。

# v4.5.6（2026-10-02）全量死代码清理（行为等价）

- AST 全量扫描 72 个 PHP 文件、确认零调用后，删除 30 个死方法 + 6 个死函数，涵盖 `Views::navHtml`/`gameBlock`、`CacheStore::allIds`/`countItemsWithLinks`/`demoteToPending`、`DB::ensureColumn`/`ensureIndex`、`User::deleteRequests`/`markAdmin`/`sessionPath`/`sessionUsable`、`RateLimit::isExceeded`/`setEnabled`/`remaining`、`Headers::staticFile`、`Http::lastMeta`、`Mail::whyNotReady`、`PanQr::supports`、`PanSou::labelOf`、`Settings::has`、`StaticGen::lastStats`、安装器 `cli_q`/`cli_rand_token`、自检 `ml_bool` 等。
- 整文件删除 `core/Search.php`（MySQL 全文搜索类，`byKeyword`/`suggest`/`hotTags`/`hasFulltextIndex` 全库零调用），同步移除 `index.php` 的 `require` 与 `core/Guard.php` 允许列表条目。
- 精简适配器契约：`Adapter` 接口及 `Tmdb`/`HongguoDuanju` 实现的 `getSource()`（`Sync` 仅 `new $cls()`，从不调用）；另移除 `core/Version.php` 的 `ml_version_parts()`（`function_exists` 包裹、零调用）。
- 纯删除、无任何调用点，线上行为完全等价；覆盖升级即可，无需清缓存 / 重新生成静态页。

# v4.5.4（2026-10-02）图片代理再改名 mimg.php（随机名避开安全规则）

- pic.php 线上仍 404（与 img.php 同症状，疑似安全规则名单较广），主端点改为不易被名单命中的 mimg.php；pic.php / img.php 均保留为兼容壳，三个地址同一份逻辑，谁没被拦谁生效。
- 页面全部生成 /mimg.php?p=… 新地址；覆盖升级后需在后台重新「生成静态页」。

# v4.5.3（2026-10-02）演员头像补齐增加百度图片搜索兜底

- 百科没有词条的冷门短剧演员（如宋芮依、刘洪皓），自动用百度图片搜索按姓名取第一张竖版图，下载校验后落盘（依赖 image.baidu.com acjson 接口，需带 image.baidu.com Referer）。
- 补图顺序变为：本地缓存 → 已解析远程缓存 → 百度百科词条头图 → 百度图片搜索 → TMDB 人物搜索 → 7 天失败标记（标记文件名由 .fail 改为 .nof，升级后此前补图失败的演员会立即用新链路重试一次）。

# v4.5.2（2026-10-02）头像补齐扩展到 TMDB 外国演员 + 胶囊排布收紧

- 百科没有词条的外国演员，自动改用 TMDB 人物搜索（用已配置的 TMDB Key）补头像：命中即返回 TMDB 头像地址（走 pic.php 代理），并写 .url 缓存避免重复请求。补图顺序：本地缓存 → 已解析远程缓存 → 百度百科词条头图 → TMDB 人物搜索 → 7 天失败标记。
- 演员胶囊内边距与间距收紧（16→14px / 8→6px），每行可多排一名，减少行尾留白。


# v4.5.1（2026-10-02）演职员头像百度百科补齐

- 短剧等来源拿不到演职员头像时，详情页渲染自动按姓名查百度百科词条头图，服务器下载校验后落盘 `uisc-assets/people/{md5}.jpg`，一次补齐永久使用。
- 失败（无词条/网络失败）写 7 天重试标记，页面照常显示占位卡；覆盖 `extra.cast` 无 profile 与 `extra.actors` 字符串两条渲染路径。
- 注意：补图发生在「生成静态页」时，缺失较多时首次构建变慢，补齐后恢复。

# v4.5.0（2026-10-02）图片代理改名 pic.php + 直连回退

- 线上 `img.php` 被主机安全规则按文件名拦截（文件在磁盘但访问 404），图片代理整体改名 **pic.php**，全部页面生成新地址；`img.php` 保留为兼容壳转发旧地址。
- 海报、列表封面、演职员头像增加 onerror 一次性直连回退：代理失败时访客网络能直连 image.tmdb.org 仍可出图。
- 整包 68 文件。

# v4.4.9（2026-10-02）信息块 3 列网格

- 「XX信息」块由均分改为 3 列网格：**导演占 1/3、演员占 2/3**，其余字段各占 1/3；单字段块占满整行；窄屏塌成单列。

# v4.4.8（2026-10-02）前台水印整体下线

- 移除全部页面的水印样式与铺点脚本（切换页面卡顿排查结论），顺带恢复文字选中与右键菜单；`watermark()` 保留返回空串，`watermark.enable` 设置失效。

# v4.4.7（2026-10-02）详情页正文 1.5 倍 + 简介铺满

- 详情页正文文字放大 1.5 倍（简介 21px、信息块标签 18px/值 21.5px、人名胶囊 19px、下载按钮与提示同步）；手机端略低一档。
- 简介文字去掉 900px 宽度上限，铺满海报右侧可用宽度。

# v4.4.6（2026-10-02）短剧「时长」下线

- 短剧信息块不再显示时长；后台表单不再提供；红果采集不再写入。电影时长保留。

# v4.4.5（2026-10-02）导演行常驻

- 影视五分类（电影/电视剧/短剧/动漫/综艺）详情页信息块「导演」行必须存在：没抓到显示「暂无」，行不再消失；书籍等无此概念的分类不受影响。

# v4.4.4（2026-10-02）后台「清除缓存」按钮

- 后台右上角新增一键清除全部缓存：CacheStore 文件缓存（标签/人物/接口级）+ 多级缓存（文件/Redis/Memcached/APCu）；只删缓存文件，不碰 config.php 与 install.lock。

# v4.4.3（2026-10-02）详情页头部过滤人名

- 导演/演员名自 v3.6.0 起并入 genres 列，详情页头部标签栏此前会重复展示人名；现按全局人名清单 + 本条目演职员双层过滤，人名只在下方演职人员/信息块出现。

# v4.4.2（2026-10-02）标签项目页统计与点击行为修正

- 统计行改为全量计数「共 N 项（标签 X · 导演 Y · 演员 Z）」，与页面卡片总数一致。
- 点导演/演员改为与标签一致的就地展开作品列表（「导演/演员「xx」的作品」），不再直接跳条目管理。
- 说明：进入某标签时隐藏导演/演员区块是站长要求的行为，保持不变。

# v4.4.1（2026-10-02）标签项目页调整（中间版）

- 曾误移除热门导演/演员区块，v4.4.2 恢复；该区块保留是站长要求。

# v4.4.0（2026-10-02）头部标签指标右对齐 + 版本规则恢复

- 详情页头部类型标签与评分/年份/热度/点击整体贴右对齐。
- 版本号恢复「末段超 9 进位」规则（此前 4.3.10~4.3.15 违规连号，本版自 4.3.15 进位）。

# v4.3.15（2026-10-02）详情页头部重排 + 空参数加固

- 头部重排：标题 + 类型标签 + 指标同一横排，简介文字移入头部区域（不再单独成卡）。
- Site::tidy 每处 preg_replace 主体显式 (string) 强转，消除「Passing null」Deprecated（此前生产日志 Deprecated 风暴同类残留）。

# v4.3.14（2026-10-02）季数/集数全分类下线

- 所有分类不再采集、不再展示「季数/集数」（含 TMDB number_of_seasons / number_of_episodes）；后台表单同步移除；历史值不再渲染。

# v4.3.13（2026-10-02）标签详情只看该标签

- 后台标签项目页点进某标签后，隐藏常规标签组与热门导演/演员区块，只显示该标签条目；返回时恢复。

# v4.3.12（2026-10-02）条目匹配页清理重复按钮

- 下线条目匹配工具栏的「去同步采集」跨页跳转按钮与重复的「+ 新增项目」入口（功能只留在所属标签页）。

# v4.3.11（2026-10-02）跳转扫码下线 + 网盘自动转存引擎

- 「跳转/扫码」首页拦截整体下线（后台设置标签改名「前台显示」）。
- 新增网盘自动转存：core/PanTransfer.php（夸克/UC 同族 API：转存→建分享→改写为站长链接）、cron/pan_transfer.php 计划任务、采集后 5 分钟自动派生、后台端点与条目管理「转存网盘链接」按钮、pan_transfers 记录表去重。
- /person/ 与 /tag/ 路由对非法 UTF-8 参数直接 404（防探测垃圾刷日志）。

# v4.3.10（2026-10-02）后台按钮清理

- 条目管理工具栏「同步热门」按钮下线（采集功能保留在「同步采集」标签页）。

# v4.3.9（2026-10-02）短剧「出品方/地区」下线

- 短剧扩展字段删「出品方」「地区」；红果采集不再写出品公司名；历史值不再渲染。

# v4.3.8（2026-10-02）重打包

- 同日中间重打包版本（内容同 4.3.7 基线）。

# v4.3.7（2026-10-02）详情页改版起点

- 详情页信息块与头部布局重构起点；发布流水线切换到 Node 打包器。

---

<!-- 更早的历史版本（v3.x ~ v4.3.6）见归档记录 -->
