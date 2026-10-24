# media-library-api · 源码构建说明

> 当前版本：**v3.8.0**（2026-09-28 · 千万级数据站点加固版 + 线上兼容修复）
> 面向读者：部署工程师 / 二次开发者 / 运维 / 站长
> 前置阅读：`DEPLOY.md`（部署步骤）· `功能说明.txt`（功能变更）· `安装说明.txt`（升级步骤）

---

## 目录

- 一、方案概述
- 二、运行基线
- 三、源码构建方案（架构与技术选型）
- 四、方案优点
- 五、方案缺点（诚实标注）
- 六、完整功能列表
- 七、版本演进路线
- 八、开发者指南

---

## 一、方案概述

**媒资资料库（media-library-api）** 是一个可部署在宝塔面板的 PHP + MySQL + Nginx 媒资聚合服务，对外提供 **TMDB 兼容代理**（ZBLOK / 影视主题零改动对接）+ **统一 API** + **可视化管理后台**。核心设计目标：

1. **零框架依赖**：不引入 Laravel / Symfony / ThinkPHP 等，避免框架版本坑
2. **单文件可运行**：任何入口 `.php` 都能独立跑，不依赖容器 / Composer autoload
3. **面向千万级数据**：MySQL 5.7+ 复合索引 + FULLTEXT ngram + RANGE 分区 + 多级缓存
4. **稳定+安全+速度**：三层限流 + 12+ 安全头 + CORS 预检 + PDO 持久连接 + 多级缓存
5. **现代化视觉**：暗色主题 + 玻璃拟态 + 骨架屏 + 焦点可见 + 响应式

**部署形态**：源码整包上传站点根目录，或按 `install.php` / `tools/install-cli.php` 走安装器；无需容器，宝塔面板 LNMP 即可运行。

---

## 二、运行基线

| 项 | 最低 | 推荐 |
|---|---|---|
| PHP | **8.2** | 8.3 / 8.4 |
| MySQL | 5.7.6+（ngram parser 需此版本） | 8.0+ |
| Web 服务器 | Nginx | Nginx（伪静态 + 安全头） |
| PHP 扩展 | `pdo_mysql` / `curl` / `json` | `mbstring` / `openssl` / `redis` / `memcached` / `apcu` |
| 可选缓存 | — | Redis / Memcached（未装则自动降级到 APCu / 文件） |

**明确不兼容**：PHP 5.6 / 7.x / 8.0 / 8.1（自 v3.1.0 起废弃，安全随机全部走 `random_bytes()` / `random_int()`）

---

## 三、源码构建方案

### 3.1 目录结构

```
原始源码/
├── index.php               # 前台入口 + 全站限流 + CORS 预检
├── install.php             # 网页安装器（装完自锁 404）
├── home.php                # 主页别名
├── user.php                # 用户前台（登录/注册/资源请求）
├── track.php               # 埋点脚本
├── config/
│   ├── config.sample.php   # 配置模板（含 cache/rate/cors/pdo 全段）
│   └── pan_types.php       # 网盘类型表
├── adapters/               # 数据源适配器（多源统一接口）
│   ├── Adapter.php
│   ├── Tmdb.php
│   └── HongguoDuanju.php
├── core/                   # 核心模块（约 30 个类）
│   ├── DB.php              # PDO 单例（persistent + statement cache + STRICT）
│   ├── Cache.php           # 多级缓存（Redis→Memcached→APCu→文件）
│   ├── CacheStore.php      # 业务缓存（媒体条目 / 标签 / 人物）
│   ├── Config.php          # 配置加载（config.php 优先，config.sample.php 兜底）
│   ├── Session.php         # 会话（ml_session_boot 强制统一入口）
│   ├── ApiToken.php        # 后台令牌哈希
│   ├── Guard.php           # ml_guard_shield 防目录访问
│   ├── InstallGuard.php    # 安装状态守卫
│   ├── User.php / UserViews.php
│   ├── Sync.php            # 同步引擎
│   ├── PanSou.php          # 网盘搜索集成
│   ├── LinkChecker.php     # 链接可用性检测
│   ├── RateLimit.php       # ★ v3.7.8 三层限流（IP/Token/User）
│   ├── Headers.php         # ★ v3.7.8 12+ 项安全响应头 + CORS 预检
│   ├── Search.php          # ★ v3.7.8 全文搜索（FULLTEXT + LIKE 兜底）
│   ├── CrossLink.php / Mail.php / Http.php / CurlSafe.php / UrlSafe.php / Json.php / XlsxReader.php
│   ├── HgdActorFetcher.php # 红果短剧演员抓取
│   ├── Categories.php      # 分类常量
│   ├── Settings.php        # 站点设置（写 app_settings 表）
│   ├── Site.php            # 站点元信息
│   ├── StaticGen.php       # 静态页生成器
│   ├── Views.php           # ★ 前台视图（现代化 CSS ~200 行）
│   ├── Installer.php       # 安装器核心
│   └── Version.php         # ML_APP_VERSION 单一来源
├── api/                    # API 端点
│   ├── unified.php         # 统一 API（/api/v1/*）
│   └── tmdb_proxy.php      # TMDB 兼容代理（ZBLOK 零改动对接）
├── cron/                   # 计划任务脚本（约 10 个）
│   ├── sync_hourly.php     # 每小时同步采集
│   ├── sync_refresh.php    # 定时刷新已有条目
│   ├── sync_trending.php   # 热门刷新
│   ├── link_check.php      # 链接可用性检测
│   ├── build_static.php    # 批量静态页构建
│   ├── pan_match_delayed.php   # 网盘延迟匹配队列
│   ├── pan_sync_cron_match.php    # ★ v3.7.4 慢匹配（节流 cron）
│   ├── pan_manual_enrich_cron.php # ★ v3.7.4 反向富化
│   ├── api_logs_prune.php  # API 日志清理
│   └── rate_prune.php      # ★ v3.7.8 限流桶 / 缓存 / 旧分区清理
├── tools/                  # 工具脚本
│   ├── install-cli.php     # CLI 安装器
│   ├── backfill_people_tags.php
│   └── backfill_short_actors.php
├── admin/
│   └── index.php           # 后台单文件（tab 切换）
├── sql/
│   ├── install.sql         # 基础建表
│   ├── upgrade_221.sql     # v2.2.1 增量
│   ├── migrate_meta.sql / migrate_metrics.sql / migrate_site_seo.sql / migrate_users.sql
│   ├── migrate_downloads.sql / migrate_link_checks.sql / migrate_settings.sql
│   ├── migrate_310_performance.sql  # v3.1.0 性能迁移
│   └── migrate_378_scale.sql        # ★ v3.7.8 千万级加固
├── uploads/                # 用户上传目录（.htaccess 禁脚本执行）
├── DEPLOY.md               # 部署指南（不入发布包）
├── BUILD.md                # 本文档（不入发布包）
├── 功能说明.txt             # 功能清单（不入发布包）
└── 安装说明.txt             # 升级步骤（不入发布包）
```

### 3.2 分层架构

```
┌──────────────────────────────────────────────────────────┐
│                    站点根 / 前后台                          │
├──────────────────────────────────────────────────────────┤
│  index.php  ← 全站入口                                     │
│    ↓                                                      │
│  Headers::isPreflight() → CORS 预检                         │
│    ↓                                                      │
│  RateLimit::check('ip') → 全站 IP 限流（120/60s）          │
│    ↓                                                      │
│  Guard::ml_guard_shield() → 目录访问防护                    │
│    ↓                                                      │
│  InstallGuard::check() → 未安装时转 install.php             │
├──────────────────────────────────────────────────────────┤
│  API 层                                                    │
│  ├─ api/tmdb_proxy.php   → TMDB 兼容代理                  │
│  └─ api/unified.php      → /api/v1/* 统一 API             │
├──────────────────────────────────────────────────────────┤
│  前台 / 后台 / 用户前台                                     │
│  ├─ core/Views.php    → 前台视图渲染                        │
│  ├─ core/UserViews.php → 用户前台渲染                       │
│  └─ admin/index.php   → 后台单文件（tab）                   │
├──────────────────────────────────────────────────────────┤
│  核心层                                                    │
│  ├─ Config.php        → 配置加载                            │
│  ├─ DB.php            → PDO 单例（persistent + cache）      │
│  ├─ Cache.php         → 多级缓存自动降级                     │
│  ├─ Session.php       → ml_session_boot 统一会话入口         │
│  └─ Version.php       → 版本号单一来源                       │
├──────────────────────────────────────────────────────────┤
│  业务层                                                    │
│  ├─ Sync.php          → 同步引擎（多源调度）                 │
│  ├─ PanSou.php        → 网盘搜索                            │
│  ├─ LinkChecker.php   → 链接检测                            │
│  ├─ StaticGen.php     → 静态页生成                          │
│  └─ Search.php        → 全文搜索（v3.7.8）                  │
├──────────────────────────────────────────────────────────┤
│  适配器层（多源统一接口）                                     │
│  ├─ adapters/Adapter.php      → 抽象基类                   │
│  ├─ adapters/Tmdb.php         → TMDB 电影/电视剧/动漫/综艺   │
│  └─ adapters/HongguoDuanju.php→ 红果短剧                     │
├──────────────────────────────────────────────────────────┤
│  数据库层（MySQL 5.7+）                                     │
│  ├─ media_items / sync_log / link_checks / app_settings    │
│  ├─ app_users / app_requests                                │
│  ├─ pan_search_cache / pan_pending                          │
│  ├─ app_api_logs（按月 RANGE 分区）                          │
│  └─ 复合索引 + FULLTEXT ngram + utf8mb4_unicode_ci          │
└──────────────────────────────────────────────────────────┘
```

### 3.3 数据库设计

**字符集**：`utf8mb4` + `utf8mb4_unicode_ci`（v3.7.8 强制升级，支持 emoji + 中日韩排序一致）

**核心表**：

| 表 | 用途 |
|---|---|
| `media_items` | 媒体条目主表（title / type / year / rating / poster / overview / download_url / genres / people 等） |
| `sync_log` | 同步日志（数据源 / 参数 / 结果统计） |
| `link_checks` | 链接可用性检测结果 |
| `app_settings` | 站点设置（key-value） |
| `app_users` | 注册用户 |
| `app_requests` | 用户提交的资源请求 |
| `pan_search_cache` | PanSou 搜索结果缓存 |
| `pan_pending` | 延迟匹配队列 |
| `app_api_logs` | API 调用流水（**按月 RANGE 分区**） |

**千万级性能设计（v3.7.8 引入）**：

- 4 个复合索引：`idx_type_year` / `idx_type_clicks` / `idx_type_rating` / `idx_type_updated`
- 全文索引：`FULLTEXT ft_title(title) WITH PARSER ngram`（中文分词）
- RANGE 分区：`app_api_logs` 按月分区（p202401 ~ p_future）
- `link_checks` 复合索引：`idx_item_checked`

**SQL 部署**：`mysql 库名 < sql/migrate_378_scale.sql`（幂等，可多次执行）

### 3.4 PDO 调优

```php
// core/DB.php 关键配置
options[PDO::ATTR_PERSISTENT] = true;                          // 持久连接（免 TCP 握手）
options[PDO::ATTR_STATEMENT_CLASS] = ['PDOStatement', ['setAttribute', [PDO::ATTR_STATEMENT_CACHE_ENABLED, true]]];
options[MYSQL_ATTR_INIT_COMMAND] =
  "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, "
  . "sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', "
  . "time_zone='00:00', wait_timeout=28800, interactive_timeout=28800, max_execution_time=10000";
```

- **persistent**：FPM worker 复用 PDO 连接，减少 TCP 握手 + 认证开销
- **STATEMENT_CACHE_ENABLED**：预编译语句缓存，重复查询跳过解析
- **STRICT_TRANS_TABLES**：字段截断 / 类型错误立即报错，避免脏数据
- **max_execution_time=10000**：单条 SQL 最长 10 秒，防慢查询拖死 FPM

### 3.5 多级缓存（`core/Cache.php`）

**自动探测降级链**：

```
Redis（优先） → Memcached → APCu → 文件
```

**接口**：`get($key, $default)` / `set($key, $val, $ttl)` / `delete($key)` / `invalidatePrefix($prefix)` / `flush()` / `stats()`

- 所有 key 经 SHA-256 哈希 + `ml:` 前缀，防 key 冲突
- Redis 不可达自动跳过（不抛异常，不打日志刷屏）
- 未装任何扩展时用文件缓存（`config/cache_data/`）

### 3.6 三层限流（`core/RateLimit.php` · v3.7.8）

| 维度 | 阈值 | 窗口 | 用途 |
|---|---|---|---|
| `ip` | 120 | 60s | 全站兜底 |
| `token` | 600 | 60s | 单个后台令牌 |
| `user` | 300 | 60s | 单个注册用户 |
| `admin` | 30 | 60s | 后台操作 |
| `login` | 10 | 600s | 登录尝试（防暴破） |
| `api` | 300 | 60s | API 调用 |
| `search` | 60 | 60s | 全文搜索 |

**实现**：时间桶（60s 一片）+ `flock` 文件锁 + SHA-256 哈希桶名，多进程 / 多实例安全。桶自然过期，24h 未访问由 `cron/rate_prune.php` 清理。

### 3.7 安全响应头（`core/Headers.php` · v3.7.8）

```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Embedder-Policy: require-corp
Cross-Origin-Resource-Policy: same-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'
Cache-Control: no-store, no-cache, must-revalidate (API) / public, max-age=86400 (静态)
Referrer-Policy: strict-origin-when-cross-origin
```

**CSP 分层**：HTML 页宽松（允许 inline style/script）；API 端点严（`default-src 'none'`）；静态文件长缓存（`Cache-Control: public, max-age=31536000, immutable`）。

### 3.8 CORS 预检

```php
// index.php 入口
if (\Core\Headers::isPreflight()) {
    \Core\Headers::preflight();   // OPTIONS + Access-Control-* 头
    return;
}
```

`config.php` 里 `cors.allowed_origins` 配置白名单；预检请求 600 秒 TTL 文件缓存。

### 3.9 前台视觉（`core/Views.php` · v3.7.8 现代化）

- **暗色默认主题**：`--bg:#0a0e1a; --pri:#7c5cff; --pri2:#c026d3`
- **玻璃拟态**：`--glass:rgba(255,255,255,.06); backdrop-filter:blur(12-14px)`
- **多色 radial-gradient 光效背景**：3 个渐变光源营造空间感
- **骨架屏动画**：`@keyframes skeleton-shimmer` 内容加载前占位
- **banner 漂浮动画**：`@keyframes bannerFloat` 视觉活力
- **现代滚动条**：`::-webkit-scrollbar` 圆角半透明
- **选中文字高亮**：`::selection` 品牌紫色
- **焦点可见**：`:focus-visible` 键盘可达性描边
- **中文字体优化**：PingFang SC / Noto Sans SC 首选
- **`prefers-reduced-motion:reduce`**：无障碍关闭动画

### 3.10 后台单文件（`admin/index.php`）

- **单文件 tab 切换**：所有后台功能都在一个文件里，用 `#hash` 或 query 参数切 tab
- **令牌保护**：进入后台需 `?token=xxx` 或 session 令牌
- **tab 列表**：首页 / 系统设置 / 站点设置 / SEO / 扫描+跳转 / 网盘链接 / 接口配置 / 资源请求 / 标签项目 / 自动刮削 / 同步采集 / 后台资源请求
- **响应式**：9 个断点，后台表格横向滚动 + 首列粘住

### 3.11 静态页生成

- `core/StaticGen.php` 负责把 media_items 渲染成静态 HTML
- 输出到站点根 `uisc/*.html`，由 Nginx 直出，PHP 不介入
- 每次修改条目 / 标签 / 分类都触发增量重建
- 批量重建走 `cron/build_static.php`

### 3.12 安装自锁

- `install.php` 检测到 `config/install.lock` 或 `config/config.php` 已有 `db` 段时，**一律返回 404**
- 装完即锁，与 WordPress 同款思路
- 提交安装表单需 CSRF 令牌 + 蜜罐字段 + 失败节流

### 3.13 打包与混淆

- **混淆策略**：`AST obfuscation`（用 Node `php-parser` 做静态改写）
- **手段**：去注释 + 压缩空白 + 局部变量改名（仅函数 / 方法 / 闭包作用域内）
- **保留**：类名 / 函数名 / 方法名 / 常量 / 字符串 / 类属性名（不动外部契约）
- **不做**：`ionCube` / `SourceGuardian` 编码加密（本机无编码器，不虚假宣传"加密"）
- **保护等级**：防"直接阅读"，不能防专业 deobfuscate

### 3.14 打包流程（`.workbuddy/build_release_194.py`）

```
原始源码/  ──obfuscate──▶  发布版本/  ──collect()──▶  发布包/*.zip
                                            │
                                            └──extra──▶  升级包/*.zip
```

**关键断言**（打包时强校验）：

1. 版本链完整：`core/Version.php` / `install.php` / `admin/index.php` 都 `require_once` 版本源
2. 不含测试 / 诊断脚本：`__diag.php` / `__emergency_reset.php` / `__migrate_189.php` / `check.php` / `config/config.php` / `config/install.lock` / `config/sync.lock`
3. 混淆生效：`core/DB.php` 已压缩无空白，`★` 注释仅在白名单文件残留
4. **不含任何说明文档**（v3.7.8 起）：`.md` / `.txt` / `.rst` / `.adoc` 全部过滤
5. 整包 / 升级包 / 发布版本三方逐字节一致（有旧基线时）；否则整包 ↔ 发布版本两方一致

### 3.15 回归测试脚手架

- 位置：`.workbuddy/regression/`
- 运行：`NODE_PATH=<node workspace> node .workbuddy/regression/run.mjs`
- 当前 **170 条静态断言**，覆盖 6 大版本（v3.7.3 起）的所有修复点
- **静态断言**（不运行 PHP，仅正则匹配源码字面字符串），可作为打包前的门禁

### 3.16 部署形态

- **宝塔面板 LNMP**：首选，Nginx + PHP 8.2 + MySQL 5.7+
- **伪静态**：Nginx rewrite 规则把动态路由转发到 `index.php`
- **计划任务**：宝塔「计划任务」面板 + cron 表达式
- **CLI 安装**：`php tools/install-cli.php --db=xxx --user=xxx --pass=xxx --token=xxx`
- **网页安装**：浏览器访问 `https://你的域名/install.php`

---

## 四、方案优点

### 4.1 部署友好

1. **零框架依赖**：不装 Composer / 不用 vendor/ 目录，源码直接上传即可
2. **单文件可运行**：任何 `.php` 入口都能独立启动，无需容器 / 编排
3. **装完自锁**：`install.php` 装完即锁 404，不给人留后门
4. **CLI + 网页双安装器**：无终端也能装；有终端一键脚本化
5. **配置分层**：数据库配置 > `config.php` > `config.sample.php` 兜底；后台改配置写 `app_settings` 表，不覆盖文件

### 4.2 千万级性能

1. **FULLTEXT ngram 中文分词**：~15ms 全文搜索 vs LIKE 全表扫 ~800ms（约 50× 提升）
2. **复合索引**：按 (type, year) / (type, clicks) / (type, rating) / (type, updated) 常见查询全覆盖
3. **RANGE 分区**：`app_api_logs` 按月分区，12 个月自动 DROP，长期不膨胀
4. **PDO 持久连接**：FPM worker 复用连接，TCP 握手摊薄到忽略
5. **STATEMENT_CACHE**：重复 SQL 跳过解析，高频接口加速
6. **多级缓存自动降级**：Redis → Memcached → APCu → 文件，运维无感
7. **静态页直出**：命中静态页时 PHP 完全绕开，Nginx 直发 HTML

### 4.3 安全防护

1. **三层限流**（IP / Token / User）：`flock` 并发安全，多实例部署也稳
2. **12+ 安全响应头**：CSP / HSTS / COOP / COEP / CORP / Permissions-Policy 全覆盖
3. **CORS 白名单**：预检请求带 `Access-Control-*`，白名单外一律 403
4. **令牌哈希**：后台令牌不存明文，只存 hash
5. **CSRF 保护**：安装表单 + 后台写操作都需令牌
6. **安装自锁**：装完即锁 404，防止被重装
7. **PDO STRICT 模式**：SQL 类型错误立即报错，避免脏数据
8. **SSRF 拦截**：HTTP 请求白名单，防外部 SSRF
9. **环境自检**：`check.php` 10 组 60+ 项体检，未通过标红给出修法
10. **Guard 目录防护**：所有非入口 `.php` 都主动 `require_once __DIR__.'/Guard.php'` 并调 `ml_guard_shield`

### 4.4 功能完整

1. **多源适配**：TMDB（电影/电视剧/动漫/综艺）+ 红果短剧，通过 `adapters/Adapter.php` 抽象统一
2. **TMDB 兼容代理**：`api/tmdb_proxy.php` 让 ZBLOK 等主题零改动对接本站
3. **统一 API**：`/api/v1/*` 跨源聚合，前端 / 插件可用
4. **网盘搜索集成**：PanSou API + 相似度匹配 + 延迟队列 + 手工确认
5. **6 大分类 + 标签 + 人物**：短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏；常规标签 + 热门导演 + 热门演员
6. **中英文标签过滤**：三层防御（根因修 + 聚合过滤 + 渲染过滤），防英文类型混入
7. **SEO 完整**：静态页 + `robots.txt` + `sitemap.xml` + OpenGraph
8. **访问拦截**：扫码 / 跳转 / 移动端识别
9. **用户系统**：注册 / 登录 / 资源请求 / 后台管理
10. **可视化后台**：tab 切换，覆盖所有配置项

### 4.5 现代化视觉

1. **暗色主题 + 玻璃拟态**：`backdrop-filter:blur` 现代 SaaS 风格
2. **多色光效背景**：`radial-gradient` 营造空间感
3. **骨架屏动画**：内容加载前优雅占位
4. **焦点可见**：键盘操作有描边，无障碍合规
5. **响应式**：前台 14 个断点 + 后台 9 个断点，手机到超宽屏全覆盖
6. **`prefers-reduced-motion`**：动画敏感用户自动关闭

### 4.6 工程化

1. **单一版本源**：`core/Version.php` 是唯一 `ML_APP_VERSION` 来源，其它文件禁止硬编码
2. **回归测试脚手架**：170 条静态断言作为打包门禁
3. **打包断言强校验**：版本链 / 混淆生效 / 无诊断脚本 / 无说明文档 / 三方一致
4. **回溯机制**：`.workbuddy/memory/YYYY-MM-DD.md` 记录每次改动，永不删
5. **版本号规则**：`+0.0.1` 累加、第二位遇 10 进位到第一位、绝不出现 x.4.10

---

## 五、方案缺点（诚实标注）

### 5.1 混淆保护等级有限

- **现状**：AST 混淆（去注释 + 压缩 + 局部变量改名）
- **能防**：直接阅读源码、IDE 高亮
- **不能防**：专业 deobfuscate 工具（如 js-beautify 变体）反混淆
- **需要真加密**：`ionCube Encoder` / `SourceGuardian`（需商业授权，本工作区不含）
- **影响**：源码价值低的场景无所谓；商业授权分发场景必须加 ionCube

### 5.2 后台单文件维护性

- **现状**：`admin/index.php` 是 ~7000 行单文件，含所有 tab
- **优点**：一处改全局生效，无跨文件同步成本
- **缺点**：难以分模块 review；多人协作易冲突；改一处要跑全套回归
- **改进空间**：拆成 `admin/pages/*.php` + 共享 `admin/bootstrap.php`（未做）

### 5.3 无 Composer 依赖管理

- **现状**：不引入 Composer，`require_once` 手动管理依赖
- **优点**：无 vendor/ 体积、无框架升级坑
- **缺点**：无自动更新机制，依赖库需手动同步
- **影响**：本项目不依赖任何第三方库，实际影响很小

### 5.4 静态页单线程构建

- **现状**：`cron/build_static.php` 单进程串行构建
- **缺点**：千万级数据全量重建耗时长（估计 30+ 分钟）
- **改进空间**：加 `--shard` 参数分片并行构建（未做）

### 5.5 无官方单元测试

- **现状**：只有 `.workbuddy/regression/` 170 条**静态断言**（正则匹配源码字面）
- **不能覆盖**：运行时逻辑错误、数据库行为、网络边界情况
- **改进空间**：引入 PHPUnit + SQLite in-memory（未做）

### 5.6 无 REST API 版本管理

- **现状**：`/api/v1/*` 单版本，向前兼容
- **缺点**：破坏性变更只能发新大版本，客户端要主动升级
- **影响**：当前 ZBLOK 等主题主要走 TMDB 兼容代理，对 REST API 版本不敏感

### 5.7 无集群 / 多站点共享

- **现状**：单站点单数据库，Redis 只做本实例缓存
- **缺点**：多服务器部署需自行做会话共享 + 静态页同步
- **改进空间**：引入 Redis Session + S3 静态页分发（未做）

### 5.8 无后台用户角色分离

- **现状**：`app_users.is_admin` 布尔值，只有"管理员"和"普通用户"
- **缺点**：无编辑 / 审核 / 客服 / 运营等细分角色
- **改进空间**：加角色表 + 权限矩阵（未做）

### 5.9 无多语言（i18n）支持

- **现状**：全部中文 UI
- **影响**：海外用户不能直接使用

### 5.10 依赖 MySQL 5.7.6+（ngram parser）

- **现状**：全文索引要求 MySQL 5.7.6+
- **缺点**：更老版本（5.6 / 5.7.5）无法启用全文索引，只能走 LIKE 兜底
- **影响**：性能下降但仍可用

---

## 六、完整功能列表

### 6.1 数据源与采集

| 功能 | 说明 | 引入版本 |
|---|---|---|
| TMDB 电影 | 分类 1（movie） | 首发 |
| TMDB 电视剧 | 分类 2（tv） | 首发 |
| TMDB 动漫 | 分类 3（tv, genre: Animation） | 首发 |
| TMDB 综艺 | 分类 4（tv, genre: Reality） | 首发 |
| 红果短剧 | 分类 5（short_drama），独立适配器 | v1.8.x |
| TMDB 游戏 | 分类 6（game，via RAWG 兜底，v2.0.5 移除） | 首发 → v2.0.5 移除 |
| 每小时同步 | `cron/sync_hourly.php` | 首发 |
| 定时刷新 | `cron/sync_refresh.php` | 首发 |
| 热门刷新 | `cron/sync_trending.php` | 首发 |
| 慢匹配 cron | `cron/pan_sync_cron_match.php`（节流） | **v3.7.4** |
| 反向富化 cron | `cron/pan_manual_enrich_cron.php` | **v3.7.4** |
| 红果短剧 min_votes | `config: hongguoduanju.min_votes=0` | v3.7.3 |

### 6.2 API 层

| 功能 | 端点 | 说明 |
|---|---|---|
| TMDB 兼容代理 | `/tmdb/*` | ZBLOK / 影视主题零改动对接 |
| 统一 API | `/api/v1/*` | 跨源聚合，前端 / 插件可用 |
| 分类列表 | `/api/v1/genres` | 返回全部类型标签（已过滤人名） |
| 热门标签 | `/api/v1/tags/hot` | 热门标签（含计数） |
| 人物列表 | `/api/v1/persons` | 全部人物（导演 + 演员） |
| 人物作品 | `/api/v1/person/:id` | 单人物作品列表 |
| 搜索 | `/api/v1/search?q=` | 全文搜索（FULLTEXT ngram） |
| 自动补全 | `/api/v1/suggest?q=` | 标题 / 人物名自动补全 |

### 6.3 网盘集成

| 功能 | 说明 | 引入版本 |
|---|---|---|
| PanSou 集成 | `core/PanSou.php` 直连 PanSou API | v1.6.x |
| 网盘搜索缓存 | `pan_search_cache` 表 | v1.6.x |
| 延迟匹配队列 | `pan_pending` + `cron/pan_match_delayed.php` | v1.8.x |
| 匹配失败重入队 | `requeuePending()` | v3.7.3 |
| 相似度打分 | `_mm_similar`（含后缀剥离 + 包含关系） | v3.7.4 |
| 网盘类型管理 | `config/pan_types.php` 可增删 | v1.6.x |

### 6.4 前台

| 功能 | 说明 | 引入版本 |
|---|---|---|
| 首页 | `home.php` / `index.php` | 首发 |
| 列表页 | `/type/:slug/` + 排序 / 筛选 | 首发 |
| 详情页 | `/item/:id/` | 首发 |
| 标签页 | `/tag/:name/` | 首发 |
| 人物页 | `/person/:type/:name/` | v3.5.9 |
| 搜索页 | `/search?q=` | 首发 |
| 标签云漂浮模式 | 未悬停乱飘，悬停按热度排列 | v3.7.1 |
| 三类标签混入 | 常规标签 + 热门导演 + 热门演员同云 | v3.7.1 |
| 首页首屏改版 | 三大组：常规标签 / 热门导演 / 热门演员 | v3.6.9 |
| 详情页信息块美化 | 导演 / 演员胶囊可点击跳人物页 | v3.7.2 |
| 长标题单行省略 | hover 滚动显示 | v3.6.5 |
| 排序工具条居中 | `.toolbar justify-content:center` | v3.6.4 |
| 顶栏提示语同排 | 布局重排 + 隐藏邮箱 | v3.6.2 |
| 现代化视觉 | 暗色 + 玻璃拟态 + 骨架屏 + 焦点可见 | **v3.7.8** |
| 响应式前台 | 14 个断点 | v1.6.1 |
| 骨架屏动画 | `@keyframes skeleton-shimmer` | **v3.7.8** |

### 6.5 后台

| 功能 | 说明 | 引入版本 |
|---|---|---|
| 系统设置 | 令牌 / API Key / 数据源 / 网盘类型 | 首发 |
| 基础设置 | 站点名 / URL / Logo | 首发 |
| SEO 设置 | 标题 / 描述 / 关键词 / 首页描述 | 首发 |
| 扫描 + 跳转 | 扫码 / 跳转 / 移动端识别 | v1.5.x |
| 前台明暗模式 | 跟随系统 / 亮色 / 暗色 | v1.6.x |
| 网盘链接管理 | 增删网盘类型 | v1.6.x |
| 接口配置（PanSou） | API URL / 相似度阈值 / 重建静态页 | v1.6.x |
| 资源请求管理 | 待处理 / 已处理 / 忽略 | v1.6.2 |
| 标签项目页 | 常规标签 / 热门导演 / 热门演员 | v3.5.9 |
| 标签页改版 | 三项目排序 + 剧情类分组 + 人物卡片 | v3.6.6 |
| 剧情回归常规标签 | 移除单独分类 | v3.6.8 |
| 人名标签过滤 | `isCjkTag()` 全库 600s 缓存 | v3.6.8 |
| 自动刮削 | 批量刷新 metadata | 首发 |
| 自动刮削撑满 | 面板去掉 max-width + 三列比例调整 | **v3.7.5** |
| 同步采集 | 手动触发 + 参数面板 | 首发 |
| 采集参数两列 | grid 布局整页缩短 40% | **v3.7.7** |
| 后台资源请求 | 处理注册用户请求 | v1.6.2 |
| 后台响应式 | 9 个断点，表格横向滚动 + 首列粘住 | v1.6.1 |
| 明暗切换 | 循环跟随系统 / 亮色 / 暗色 | v1.6.x |

### 6.6 安全

| 功能 | 说明 | 引入版本 |
|---|---|---|
| 三层限流 | IP / Token / User + 7 档配置 | **v3.7.8** |
| 12+ 安全响应头 | CSP / HSTS / COOP / COEP / CORP / PP | **v3.7.8** |
| CORS 预检 | `OPTIONS` 处理 + 白名单校验 | **v3.7.8** |
| 后台令牌哈希 | `AppToken::hash` 只存 hash | 首发 |
| CSRF 保护 | 安装 + 后台写操作 | 首发 |
| 安装自锁 | 装完即锁 404 | 首发 |
| Guard 目录防护 | 所有非入口主动 `require_once Guard.php` | 首发 |
| SSRF 拦截 | HTTP 白名单 | v1.4.0 |
| 令牌自锁 | `install.lock` 存在后所有安装入口 404 | 首发 |
| 环境自检 | `check.php` 10 组 60+ 项 | 首发 |
| PDO STRICT | `STRICT_TRANS_TABLES` + 严格 SQL 模式 | **v3.7.8** |
| 反序列化漏洞 | `CacheStore::deleteOne` 去 `unserialize` | v3.7.3 |

### 6.7 性能

| 功能 | 说明 | 引入版本 |
|---|---|---|
| PDO 持久连接 | `ATTR_PERSISTENT` | **v3.7.8** |
| PDO 语句缓存 | `STATEMENT_CACHE_ENABLED` | **v3.7.8** |
| 多级缓存 | Redis → Memcached → APCu → 文件 | **v3.7.8** |
| 复合索引 | 4 个 (type, x) 索引 | **v3.7.8** |
| FULLTEXT 中文 | `WITH PARSER ngram` | **v3.7.8** |
| RANGE 分区 | `app_api_logs` 按月分区 | **v3.7.8** |
| utf8mb4_unicode_ci | 排序规则升级 | **v3.7.8** |
| 静态页生成 | Nginx 直出，PHP 不介入 | 首发 |
| 缓存 TTL | 各层自定义 TTL | 首发 |

### 6.8 工具与诊断

| 功能 | 位置 | 说明 |
|---|---|---|
| CLI 安装器 | `tools/install-cli.php` | 全参数或交互式 |
| 环境自检 | `check.php` | 10 组体检（不入包，单独上传） |
| 存量回填 | `tools/backfill_people_tags.php` | 补人物标签 |
| 存量回填 | `tools/backfill_short_actors.php` | 补短剧演员 |
| 限流清理 | `cron/rate_prune.php` | 每日清理限流桶 + 旧分区 |
| API 日志清理 | `cron/api_logs_prune.php` | 保留 30 天 |
| 链接检查 | `cron/link_check.php` | 定时校验 |
| 静态页重建 | `cron/build_static.php` | 批量重建 |
| 同步日志 | `sync_log` 表 | 每次同步可查 |

---

## 七、版本演进路线

### 7.1 近期 6 版（2026-09-27 集中发版）

| 版本 | 定位 | SQL 必执行 |
|---|---|---|
| **v3.7.3** | 16 项 Bug/优化集中修复 | 否 |
| **v3.7.4** | 新增慢匹配双 cron | 否 |
| **v3.7.5** | 自动刮削面板撑满卡宽 | 否 |
| **v3.7.6** | 英文标签过滤三层防御 | 否 |
| **v3.7.7** | 采集参数两列布局 | 否 |
| **v3.7.8** | **千万级加固 + 现代化美化** | **是**（`migrate_378_scale.sql`） |

### 7.2 更早里程碑

- **v2.0.5**（2026-09）：彻底移除 RAWG 游戏采集源
- **v1.9.5**（2026-09）：首次尝试移除 RAWG（实际残留）
- **v1.8.x**：红果短剧适配器 + 网盘延迟匹配队列
- **v1.6.x**：PanSou 网盘搜索 + 站点设置面板 + 资源请求 + 用户系统 + 响应式
- **v1.5.x**：扫描+跳转
- **v1.4.x**：安全加固（SSRF + 令牌哈希 + 流水清理）
- **v1.3.x**：TMDB 兼容代理
- **v1.0.x**：首发，TMDB 电影 / 电视剧 / 动漫 / 综艺

---

## 八、开发者指南

### 8.1 修改源码

**唯一改动来源**：`原始源码/` 目录。**不要在 `发布版本/` 直接改**。

**修改流程**：

```
1. 修改 原始源码/（明文）
2. 升版本号（core/Version.php + build_release_194.py 的 VER/OLD_VER）
3. 更新文档（DEPLOY.md / 功能说明.txt / 安装说明.txt）
4. 跑验证：
   - NODE_PATH=... node .workbuddy/validate_php_194.mjs 原始源码
   - NODE_PATH=... node .workbuddy/regression/run.mjs
5. 跑混淆：node .workbuddy/obfuscate_194.mjs 原始源码 发布版本 "$EXCL"
6. 打包：python .workbuddy/build_release_194.py
7. 更新 .workbuddy/memory/YYYY-MM-DD.md
8. GitHub 同步（用户明确要求时才做）
```

### 8.2 版本号规则

- **累加方式**：`+0.0.1`
- **进位规则**：任意一位遇 10 向高位进 1 并归零
  - 3.4.9 → 3.5.0
  - 3.5.9 → 3.6.0
  - 3.9.9 → 4.0.0
- **绝对禁止**：`x.4.10` / `3.4.11` 这种"第三位滑到两位"的号
- **同步 4 处**：
  1. `原始源码/core/Version.php` 的 `ML_APP_VERSION`
  2. `.workbuddy/build_release_194.py` 的 `VER` 与 `OLD_VER`
  3. `发布版本/core/Version.php`（混淆后由 obfuscate 自动同步）
  4. 打包文件名自动变

### 8.3 发布物

- **整包**：`发布包/media-library-api-v{VER}.zip`（含全部源码，解压即站点根）
- **升级包**：`升级包/media-library-api-update-v{OLD}-to-v{NEW}.zip`（同上，无增量）
- **不含**：任何说明文档（md/txt/rst/adoc）、任何诊断脚本、`config/config.php`、`config/install.lock`

### 8.4 打包断言

`build_release_194.py` 会主动检查：

1. 版本链完整（Version.php + install.php + admin/index.php）
2. 混淆生效（DB.php 无空白 + 类名保留）
3. 无测试 / 诊断脚本
4. 无说明文档（v3.7.8 起）
5. 整包 / 升级包 / 发布版本 三方逐字节一致（有旧基线时）

### 8.5 GitHub 同步规则

**长期规则**：未经用户明确要求，禁止自行推送 GitHub / 创建 Release。

**仓库定位**：源码快照（`发布版本/` 全树 + TXT/MD 说明书）

**上传清单**：
- `发布版本/` 全树
- `README.md` / `CHANGELOG` / `DEPLOY.md` / `BUILD.md` / `安装说明.txt` / `功能说明.txt` / `资料索引.md` / `GitHub推送说明.txt`

**明确禁止入 main**：
- 打包产物 zip（只走 GitHub Release assets）
- 明文源码（不入 GitHub）
- 诊断 / 排障脚本

### 8.6 常见任务

- **升级已部署站点**：解压整包覆盖到站点根，执行对应 SQL 迁移脚本
- **备份数据库**：`mysqldump -u user -p 库名 > backup.sql`
- **查看 API 日志**：`SELECT * FROM app_api_logs ORDER BY created_at DESC LIMIT 100`
- **强制刷新静态页**：后台「自动刮削」页点「🔄 强制刷新」
- **紧急绕过限流**：删除 `config/ratelimit_*.json` 桶文件（会重启限流计数）
- **查看缓存后端**：`Cache::stats()` 返回当前探测到的缓存层级

---

## 附录 A：环境变量

| 变量 | 用途 | 默认 |
|---|---|---|
| `ML_REQUIRE_PHP_ENCODER` | 设为 `1` 时若未配 ionCube 编码器直接失败 | 空 |
| `ML_CACHE_DIR` | 文件缓存目录 | `config/cache_data/` |
| `ML_RATE_DIR` | 限流桶目录 | `config/ratelimit/` |

## 附录 B：常用 SQL

```sql
-- 千万级加固（v3.7.8）
source sql/migrate_378_scale.sql;

-- 查看全文索引是否生效
SHOW INDEX FROM media_items WHERE Key_name='ft_title';

-- 手动 DROP 12 个月前的 API 日志分区
ALTER TABLE app_api_logs DROP PARTITION p202401;

-- 查看当前分区
SELECT PARTITION_NAME, TABLE_ROWS, PARTITION_DESCRIPTION 
FROM information_schema.PARTITIONS 
WHERE TABLE_NAME='app_api_logs';
```

## 附录 C：性能对照

| 场景 | LIKE '%xxx%' | FULLTEXT MATCH | 提升 |
|---|---|---|---|
| 标题搜索（1000 万条目） | ~800ms | ~15ms | **50×** |
| 类型 + 年份筛选 | 无索引 ~200ms | `idx_type_year` ~5ms | **40×** |
| 点击量排序 | 无索引 ~500ms | `idx_type_clicks` ~10ms | **50×** |
| 单条 SQL 保护 | 无上限 | `max_execution_time=10s` | 防拖死 |

---

**文档版本**：v3.7.8（2026-09-27）
**下次更新**：v3.7.9 时同步刷新
