# 版本记录 · media-library-api

> 每个版本对应 `../发布包/media-library-api/` 下的一个 zip 包（**整包**）。发布包解压后内容直接放入宝塔站点根即可运行。
> 本目录（`发布版本/`）另放一份**不打包的最新源码**，打开即见、可直接部署。
> **增量升级包**（只含变化文件，给在跑的站点覆盖升级）另存于 `../升级包/<项目>/`。
> 源码对应位置：`../../原始源码/media-library-api/`

## 版本一览

| 版本 | 日期 | 发布包 | 主要内容 |
|---|---|---|---|
| v1.6.4 | 2026-09-23 | `media-library-api-v1.6.4.zip` | **紧急修复：首页 Fatal error `Class 'Config' not found`** —— `home.php` 是全局命名空间文件，却用了短类名 `Config::sub(...)` 而没有 `use Core\Config;`，站点一装好、一访问首页就 500（v1.6.0 引入，因当时站点从未装成所以一直没暴露）；本次补上 `use Core\Config;` 与 `core/Site.php` 的显式 require（**零行为变化**）；同时新增发布前必跑项 **`phpclassscan.py` 类名解析扫描**（`phpcheck`/`php56scan` 查不出这类运行时命名空间问题）；**数据库无变更** |
| v1.6.3 | 2026-09-22 | `media-library-api-v1.6.3.zip` | **恢复网页版安装器（带自锁）**：新增 **`install.php` 网页安装向导**（环境自检 → 填表 → 一键安装，深色适配 + 手机端单列）—— **已安装即 404**（存在 `config/install.lock` 或 config.php 的 db 已填真值就对所有人返回 404，与文件不存在无异，没人能借此重装站点），只在"尚未安装"时可用；新增 **`core/Installer.php` 安装内核**，`install.php` 与 `tools/install-cli.php` 共用同一份建表 / 生成配置逻辑；守卫与推荐的 Nginx 规则**精确放行 `/install.php`**（故意不写 install，否则未装就永远装不了），其余 setup/upgrade/update/install.php.bak 仍一律 404；首页引导页新增「🚀 开始网页安装」主按钮；自检与后台「安全防护」安装器判据改为**三态**（未上传 / 已自锁 / 尚未安装因而可用）；`sql/install.sql` 补齐 `app_users` / `app_requests`；安装备忘移到 `config/install-info.txt`（不再裸放公网）；FAQ 新增"网页安装器安全吗"与"https 502 / http 正常"两条；`preview/` 新增 **`install.html` 安装向导预览** 与 **`notinstalled.html` 未安装引导页预览**；**`安装说明.txt` / `功能说明.txt` 两份说明书首次随整包发布** |
| v1.6.2 | 2026-09-22 | `media-library-api-v1.6.2.zip` | **注册 + 资源请求**：前台新增**注册 / 登录 / 退出**与独立页 **`/request`「资源请求」**（注册用户可提交想找的资源：片名 / 类型 / 年份 / 备注 / 联系方式，并能查看进度、撤回未处理请求）；后台新增一级页签 **「资源请求」**（请求列表 + 注册用户，可改状态 / 回复 / 删除 / 重置密码 / 禁用账号）；「站点设置」新增 **资源请求** 二级页（13 项开关：开放注册 / 必须登录才能提交 / 注册审核 / 图形验证 / 游客可看 / 公开列表 / 显示联系方式 / 导航入口 / 每日上限 / 最少标题字数 / 页面标题 / 页面简介 / 站长公告）；两张表 **自动创建 + 缺列自愈**；自带风控（IP 注册限频 / 登录锁定 / 每日提交上限 / 同名去重 / 蜜罐 / 算术验证码）；密码 `password_hash` 加盐；自检新增 **第 11 组「注册与资源请求」**；`preview/` 新增注册页 / 资源请求页 / 后台资源请求三个预览页 |
| v1.6.1 | 2026-09-22 | `media-library-api-v1.6.1.zip` | **全站响应式自适应**：主页与后台同时适配手机 / 平板 / 大屏（380 / 560 / 768 / 1024 / 1400 / 1800px 六档断点 + 手机横屏）；前台明暗模式新增「**跟随系统**」并设为默认（经典 / 简约模板通用）；前台顶栏折行、导航与工具栏横滑、卡片 320px 屏仍两列、内页下载地址竖排；后台新增**明暗切换**、手机端**整屏弹层**、**表格横滑且首列粘住**；刘海安全区 / 触屏去 hover 粘滞 / 减少动画 / 高对比度 / 打印排版 / iOS 输入框防缩放；自检新增 **10.6b「响应式自适应」**；`preview/` 新增多设备预览台 |
| v1.6.0 | 2026-09-22 | `media-library-api-v1.6.0.zip` | **站点设置大版本**：后台新增「站点设置」页（基础设置 / SEO / 扫描+跳转 / 网盘链接 / 接口配置 5 个二级页）；前台接入 LOGO / 站点名 / 宣传语 / 页脚三段 / 顶部导航 / 经典·简约双模板 / 榜单有图无图 / 首页「最新更新」区块；新增动态 **`/robots.txt`** 与 **`/sitemap.xml`**；新增 PC 端**跳转+扫码**拦截（三模式 + 手机端策略 + 降级规则）；新增 **PanSou 网盘搜索对接**与「**采集后自动补网盘下载地址**」（相似度打分 + 按网盘去重 + 待确认队列）；自检新增**第 10 组**「站点设置 / SEO / 网盘接口」 |
| v1.5.0 | 2026-09-22 | `media-library-api-v1.5.0.zip` | **采集重构**：修掉「点同步没反应」；新增限流 + 去重 + 只取最热/最多人看；新增后台「同步采集」页（参数可改、各源官方限制对照、计划任务一键复制）；新增每小时计划任务 `cron/sync_hourly.php`；自检新增第 9 组「采集与计划任务」 |
| v1.4.1 | 2026-09-22 | `media-library-api-v1.4.1.zip` | **自检页修复**：去掉 PHP 版本误判 FAIL、修掉 display_errors 自我误报；低优先级项目降级为「信息」；**新增对安装器 / 内部文件 / 定时脚本的公网暴露面实测**；伪静态目录保护改 `^~` 前缀 |
| v1.4.0 | 2026-09-22 | `media-library-api-v1.4.0.zip` | **安全加固**：网页版安装器 `install.php` **彻底移除**（改命令行 `tools/install-cli.php`，公网 404）；新增 `core/Guard.php`（安装路径黑洞 + 敏感路径拦截 + 内部文件反直访 + 防护状态）；`check.php` 未授权一律 404；后台新增**「安全防护」页** |
| v1.3.0 | 2026-09-22 | `media-library-api-v1.3.0.zip` | 后台新增**「系统设置」页**：管理员令牌 / TMDB Key / RAWG Key / 启用数据源 / 网盘类型全部可在后台增删改（存 `app_settings` 表，优先级高于 `config.php`，升级覆盖不丢）；Key 支持一键「测试连通」；修复后台接口地址在根目录部署时拼成 `//api/...` 的 BUG；`check.php` 校正 PHP 版本要求并显示后台覆盖项 |
| v1.2.1 | 2026-09-22 | `media-library-api-v1.2.1.zip` | **修复「还没填数据库就提示已安装完成」**：安装判定改为看 `config.php` 的 db 是否填了真值（占位值视为未安装）；发布包**不再自带 `config/config.php`**（改带示例 `config/config.sample.php`），升级覆盖不再重置数据库配置；未安装时首页给友好引导页；`check.php` 新增「安装状态」项 |
| v1.2.0 | 2026-09-22 | `media-library-api-v1.2.0.zip` | **全量兼容 PHP 5.6 ~ 8.2**（修掉 `??`/`fn()`/返回类型等 7.0+ 语法导致的 Parse error）；新增**网址全新安装向导** `install.php`；补充 Nginx/Apache 伪静态规则；修复 `Tmdb::url()` 私有方法被外部调用、cron 缺 `StaticGen` 引用等致命 BUG |
| v1.1.1 | 2026-09-22 | `media-library-api-v1.1.1.zip` | 新增**一键环境自检** `check.php`（环境/权限/配置/数据库/表结构/路由伪静态/安全暴露/上游连通/php -l 语法，7 组共 60+ 项，支持 JSON 与写库测试） |
| v1.1.0 | 2026-09-22 | `media-library-api-v1.1.0.zip` | 全站美化；前台下线 API 字样（仅后台可见）；下载区仅在有链接时显示 + 卡片「可下载」角标；后台新增**链接失效检查**（单条/批量 + 状态标） |
| v1.0.0 | 2026-09-22 | `media-library-api-v1.0.0.zip` | 首个完整版：TMDB 兼容代理 + 三源适配器 + 6 大分类 + 主页/静态内页 + 后台管理 + 网盘下载 |

**增量升级包**（只含变化文件，用于已在跑的站点覆盖升级，比传整包快）：

| 升级包 | 从 → 到 | 内容 |
|---|---|---|
| `media-library-api-update-v1.6.3-to-v1.6.4.zip` | v1.6.3 → v1.6.4 | **2 个变化文件（新增 0 + 修改 2）**：`home.php`（P0 修复）、`index.php`（版本号）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |
| `media-library-api-update-v1.6.2-to-v1.6.3.zip` | v1.6.2 → v1.6.3 | 13 个变化文件（新增 4 + 修改 9）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |
| `media-library-api-update-v1.6.1-to-v1.6.2.zip` | v1.6.1 → v1.6.2 | 14 个变化文件（新增 4 + 修改 10）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |
| `media-library-api-update-v1.6.0-to-v1.6.1.zip` | v1.6.0 → v1.6.1 | 9 个变化文件（新增 0 + 修改 9）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |
| `media-library-api-update-v1.5.0-to-v1.6.0.zip` | v1.5.0 → v1.6.0 | 16 个变化文件（新增 3 + 修改 13）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |
| `media-library-api-update-v1.4.1-to-v1.5.0.zip` | v1.4.1 → v1.5.0 | 17 个变化文件（新增 2 + 修改 15）+ `UPGRADE.txt` / `UPGRADE.md` 升级说明（存放：`升级包/media-library-api/`） |

---

## v1.6.4（2026-09-23）

**紧急修复：一装好就访问首页即 500 —— `Fatal error: Class 'Config' not found`**

### 1) 症状

站点安装完成后打开首页（`http://你的域名/`）直接白屏报错：

```
Fatal error: Uncaught Error: Class 'Config' not found in /www/wwwroot/xxx/home.php:33
Stack trace: #0 /www/wwwroot/xxx/index.php(172): require() #1 {main} thrown in .../home.php on line 33
```

### 2) 根因

`home.php` 是**全局命名空间**文件（没有 `namespace` 声明），其中「最新更新」区块
（v1.6.0 引入）写的是**短类名**：

```php
&& \Core\Site::flag(Config::sub('redirect', 'latest', 1))) {
```

- `\Core\Site` 带了完整命名空间 → 正常；
- `Config::` 既没有前导 `\`，文件里也没有 `use Core\Config;`
  → PHP 在**全局命名空间**里找 `Config` 类 → 找不到 → 致命错误。

同一段代码在 `core/Views.php` 里是正确的，因为那个文件有 `namespace Core;`，
`Config::` 会自动解析成 `Core\Config`。**从 Views.php 抄到 home.php 时漏掉了命名空间差异。**

> 为什么 v1.6.0 ~ v1.6.3 一直没暴露：这段时间站点始终处于「未安装」状态，
> 首页走的是「站点尚未安装」引导页，`home.php` 从未被执行过。站点一装好，进门就炸。

### 3) 修复

`home.php` 头部补齐依赖（两行改动，零行为变化）：

```php
require_once __DIR__ . '/core/Site.php';   // ★ 下面用到 \Core\Site::flag()
...
use Core\Config;                          // ★ 短类名 Config:: 必须显式 use
use Core\Views;
use Core\CacheStore;
```

### 4) 顺手加固：新增「类名解析扫描」

`phpcheck`（括号结构）和 `php56scan`（PHP 7.0+ 语法）**都查不出这类"运行时才炸"的命名空间问题**。
本次新增发布前必跑项 **`phpclassscan.py`**：剥离注释与字符串后，按 PHP 的命名空间解析规则
逐个核对每个 `Foo::` / `new Foo` / 类型提示，凡解析不到的类名一律报出并附上下文。
全项目 44 个 PHP 文件已扫过，本类问题清零（仅余 2 处内嵌 JS 的 `new FormData` / `new Uint8Array` 误报，已人工确认）。

### 5) 影响面

| 项 | 说明 |
|---|---|
| 受影响版本 | **v1.6.0 ~ v1.6.3**（只要站点已安装，访问首页即 500） |
| 受影响文件 | **仅 `home.php`** |
| 最快修复 | 用本版 `home.php` 覆盖线上同名文件即可（`index.php` 只改了版本号，可选覆盖） |
| 数据库 | **无任何变更**，不用导 SQL |

---

## v1.6.3（2026-09-22）

**恢复网页版安装器 `install.php`，改为「带自锁」—— 未安装时可以网页安装，装完自动 404**

起因：站点在**尚未安装**时访问网址即 404，而 v1.4.0 起网页安装器已被彻底移除，
导致"拿不到终端就装不了站"，源码等于白给。本次把网页安装器**恢复并加锁**，
同时把两个安装入口统一到同一套内核。（顺带实测确认：`http://cs.zyfx.wang/` 返回
"站点尚未安装"引导页 200 —— 伪静态与守卫本身是好的；`https://` 全站 502 属服务器
443 段 PHP 转发问题，已写进 FAQ。）

### 1) 新增 `core/Installer.php` —— 安装内核（网页与命令行共用）

- 一份实现、两处使用：`install.php`（网页）与 `tools/install-cli.php`（命令行）。
- 提供：环境自检（PHP 版本 / `pdo_mysql` / `curl` / `json` / `mbstring` / `openssl` / 目录可写性）、
  建表 SQL（**6 张表**，含 v1.6.2 的 `app_users` / `app_requests`）、`config/config.php` 生成、
  `config/pan_types.php` 生成、写 `config/install.lock`、数据库连接失败的四条常见原因、恒时比较等。
- `tools/install-cli.php` 里原先**内嵌的建表 SQL 与配置生成函数全部删掉改为委托**
  （文件从 20.8 KB 降到 12.2 KB），从此不会出现"两份实现不一致"。
- 安装信息备忘从站点根 `install-info.txt` 移到 **`config/install-info.txt`**
  —— `config/` 公网访问 404，避免后台令牌被裸放在公网可读的位置。

### 2) 新增 `install.php` —— 网页安装向导（带自锁）

- **已安装即 404**：只要 `config/install.lock` 存在，或 `config/config.php` 的 `db` 段已填真值
  （即 `ml_install_state()` 判定为已安装），本页对所有人返回 404，**与文件不存在完全一样**；
  只在"尚未安装"时才显示向导 —— 那正是最需要它的时候。与 WordPress「装完即锁」同一思路。
- 向导三步：**环境自检**（9 项，不通过项标红并直接给修法）→ **填表**
  （数据库地址/端口/库名/用户/密码 · 站点名与网址 · 后台令牌（可一键生成）· TMDB/RAWG Key · 数据源勾选）
  → **一键安装**（成功页给出后台令牌、后台地址、自检地址，并列出接下来该做的 5 件事）。
- 安全：只接受 **POST**；会话 **CSRF 恒时比较**；**蜜罐字段**；同一会话失败 20 次后需等 10 分钟；
  安装前**二次确认**仍未安装（防并发 / 防绕过）；`config.php` 写盘后立刻回读抽检内容。
- 界面：渐变头部 + 步骤编号卡片 + 状态圆点，**深色模式自动适配**，手机端单列、按钮满宽。

### 3) 守卫与路由放行（`core/Guard.php` / `.htaccess` / DEPLOY.md）

- `ml_guard_boot()` 的安装路径黑洞**精确放行 `/install.php`**（交其自身判定）；
  其余 `setup` / `upgrade` / `update` / `install.php.bak` 仍然一律 404。
- `ml_guard_status()` 新增 **`install_present` / `install_locked` / `install_active`**：
  真正要盯的风险项是 **`install_active`**（文件存在 **且** 尚未安装）；
  `install_removed` 保留兼容语义，`install_ghosts` 不再把 `install.php` 当残留文件。
- 推荐的 Nginx 规则第 4 条与 `.htaccess` 同步去掉 `install`
  —— **故意不写 install**：写死了未安装时就永远装不了；已在 DEPLOY.md 与安装说明里注明原因。

### 4) 首页引导页 · 自检 · 后台口径同步

- 首页「站点尚未安装」引导页新增 **「🚀 开始网页安装」** 主按钮（渐变卡片 + 悬停位移 + 手机端满宽），
  终端与手动方式保留为备选；文案由"已移除网页版安装器"改为"安装器带自锁"。
  页面还给出**排查提示**：若点按钮仍是 404，说明伪静态里还留着
  `location ~* ^/(install|setup|upgrade|update)`，把 `install` 去掉即可。
  该页带 `noindex,nofollow`，并跟随系统深色模式。
- `check.php`：第 3 组安装状态提示改为"两种装法"；第 8 组安装器判据改为**三态**；
  `/install.php` 的公网可达性探测**在未安装时跳过**（此时返回 200 属正常），
  已安装仍是 200 才判 fail。
- 后台「安全防护」页同步为三态显示（未上传 / 已自锁 / 尚未安装因而可用）。

### 5) 文档与预览

- `DEPLOY.md`：第二节重写为 **「方式一：网页安装（推荐，不用终端）」/「方式二：命令行安装」**；
  安全加固清单、Apache 片段、后台说明同步；FAQ 新增
  **「网页安装器安全吗？会不会被人重装我的站点？」** 与
  **「`https://` 打开是 502 Bad Gateway，但 `http://` 正常？」**（443 段 PHP 转发排查四步）。
- `安装说明.txt` / `功能说明.txt`：安装章节重写为两种方式，FAQ 与安全提醒同步。
- `sql/install.sql` 补齐 `app_users` / `app_requests`（手动安装也是 **6 张表**）。
- `preview/` 新增 **`install.html`（安装向导预览）** 与 **`notinstalled.html`（未安装引导页预览）**，
  并加入多设备预览台的页面切换（现 **10 个页面**）；`install.html` 的"关于安全"措辞改为"带自锁"；
  `security.html` 安装器口径更新。

### 校验

- `phpcheck` **44 个 PHP，0 问题**；`php56scan --all` **0 命中**；`node --check` 内联 JS **12/12 OK**。

---

## v1.6.2（2026-09-22）

**新增注册功能与「资源请求」页：注册用户可在独立页面填写想找的资源，站长在后台逐条处理**

起因：希望「添加一个注册功能，注册的可以在主页一个单独名为 资源请求 页面填入需要寻找的内容」。

### 1) 前台 · 注册 / 登录 / 退出（`core/User.php` + `core/UserViews.php` + `user.php`）

- 新增三个页面：**`/user/register` 注册**、**`/user/login` 登录**、**`/user/logout` 退出**（退出后可回任何页）。
- 注册字段：用户名（2 ~ 20 位，支持中文）/ 邮箱（选填）/ 密码（6 ~ 64 位，不能与用户名相同）/ 确认密码 / **算术验证码**。
- **密码安全**：使用 `password_hash()`（`PASSWORD_DEFAULT`）加盐存储；登录校验走 `password_verify()`。
  **账号不存在时也照跑一次假散列校验**，避免通过响应时间判断账号是否存在。
- **风控（内置，后台可调）**：
  | 项 | 默认值 |
  |---|---|
  | 同一 IP 每小时最多注册 | 3 个 |
  | 登录失败锁定 | 满 5 次锁 15 分钟 |
  | 每人每天最多提交请求 | 5 条 |
  | 同一 IP 十分钟最多提交 | 15 条 |
  | 同名请求 | 去重，不允许重复提交 |
  | 蜜罐字段 | 隐藏 `website` 输入框，被填写即拒绝 |
  | 算术验证码 | 会话存题面与答案，**一次有效**，30 分钟过期 |
- **CSRF**：所有写操作都带令牌，比较用**恒时算法**（PHP 5.6 无 `hash_equals`，自实现逐字节异或累加）。
- **会话**：`session_set_cookie_params` + `@ini_set` 兼容写法（`cookie_samesite` 只在 PHP 7.3+ 生效，低版本静默忽略）；
  **延迟建会话** —— 只有浏览器已带本会话 cookie 或发生写操作时才 `session_start()`，普通访客浏览首页不会凭空生成会话文件。
- **POST → 重定向 → GET**：注册 / 登录 / 提交请求全部不依赖 JS，避免「点了没反应」类静默失败；提示走一次性 flash。

### 2) 前台 · 「资源请求」页（`/request`）

- **独立页面**，标题、简介、公告都可在后台改（默认为「资源请求」）。
- 页面结构：**状态统计条**（总数 + 待处理 / 已找到 / 暂未找到 / 已回复 / 已关闭）→ **公告** → **提交区** → **我的请求** → **大家都在找** → **分页**。
- **提交区**：资源名称（必填，字数下限可在后台设）/ 类型（6 大分类下拉）/ 年份（1900 ~ 2100）/ 补充说明（≤500 字）/ 联系方式（默认带上账号邮箱，可在后台关掉）。
  输入资源名时会**异步查站内是否已收录**并给出同名片提示。
- **必须登录才能提交**（默认开启，可在后台关）：未登录时显示引导卡，给出「注册新账号 / 已有账号，去登录」两个入口。
- **我的请求**：只看自己的，未处理的可以**自己撤回**。
- **大家都在找**：公开列表（可在后台关掉），带分页；已处理的会显示状态徽标，找到的会给出**站内条目链接**。
- 页面默认 `noindex,follow`（功能页不进收录，但爬虫可继续跟随链接）。

### 3) 前台 · 顶栏与导航入口（`core/Views.php`）

- 顶栏右上角新增**用户区**：未登录显示「登录 / 注册」；已登录显示「我的请求 / 用户名 / 退出」。手机端自动隐藏用户名。
- 顶部导航新增「**资源请求**」入口（可在后台关掉）；站长自定义外链不受影响。

### 4) 后台 · 「资源请求」一级页签（`admin/index.php`）

- 新增一级页签 **「资源请求」**，内含两个二级页：
  - **请求列表**：状态统计条 + 关键字搜索 + 状态筛选 + 分页；每条可**标记已找到 / 回复 / 关闭 / 重开 / 删除**，「已找到」可直接关联站内条目。
  - **注册用户**：列表显示用户名 / 邮箱 / 状态 / 提交数；可**通过审核 / 禁用 / 解禁 / 重置密码 / 删除**。
- **删除用户只停用账号并保留其历史请求**（请求的 `user_id` 置 0），不会连带删掉别人的求助记录。
- 全部走后台既有的 `api()` + `busy()` 封装（7 个新端点：`admin/req_stats` / `req_list` / `req_save` / `req_del` / `user_list` / `user_save` / `user_del`），无自定义请求逻辑。
- 状态与密码在 `user_save` 里**各自独立处理**，只改状态不会顺手清空密码。

### 5) 后台 · 「站点设置 → 资源请求」（13 项开关）

| 分组 | 设置项 |
|---|---|
| 开关 | 开放注册 · 必须登录才能提交 · 注册后需审核 · 图形（算术）验证码 · 允许游客查看列表 · 公开「大家都在找」 · 显示联系方式字段 · 顶部导航显示入口 |
| 限制 | 每人每天最多提交条数（0 = 不限） · 资源名称最少字数 |
| 文案 | 页面标题 · 页面简介 · 站长公告 |

设置写入 `app_settings` 表（优先级高于 `config.php`，**升级覆盖不丢**）；空值不覆盖，**`0` 是合法值**。

### 6) 数据层（`core/User.php`）

- 两张新表：**`app_users`**（账号 / 邮箱 / `password_hash` / 状态 / 失败次数 / 锁定时间 / 注册 IP 与时间）与
  **`app_requests`**（用户 ID / 标题 / 类型 / 年份 / 备注 / 联系方式 / 状态 / 站长回复 / 关联条目 / IP / 时间）。
- **自动建表 + 缺列自愈**：启动时 `SHOW COLUMNS` 与期望列比对，缺哪列补哪列（`ALTER TABLE ... ADD COLUMN`），
  **覆盖升级不用手工执行 SQL**；无建表权限时可用兜底 `sql/migrate_users.sql` 手工导入。

### 7) 自检（`check.php`）

- 新增 **第 11 组「注册与资源请求」**（全站现为 **11 组**）：注册开关 / 提交登录要求 / 防风控摘要 / 可见性与文案 /
  两张表与缺列情况 / 用户与请求数量 / `password_hash` 可用性 / **会话目录可写性** / 代码就位与 `index.php` 路由；
  并会**真实探测** `/user/register` 与 `/request` 的 HTTP 状态。

### 8) 预览页（`preview/`）

- 新增 `register.html`（注册页）、`request.html`（资源请求页）、`user-admin.html`（后台资源请求）；
- 预览台 `responsive.html` 加入这三个页面，可直接对比手机 / 平板 / 桌面的表现。

---

## v1.6.1（2026-09-22）

**全站响应式自适应：主页与后台同一套代码适配手机 / 平板 / 大屏，并补齐多端细节**

起因：希望「对主页和后台进行自适应设计，适配能找到的所有要求」。

### 1) 前台主页（`core/Views.php`）

- **容器宽度变量化**：新增 `--maxw`（1180px，1400px 断点升到 1320，1800px 升到 1480）与 `--pad`（左右内边距），改一处全站生效。
- **六档断点**：`<=380` 小屏手机 / `<=560` 手机 / `<=768` 平板竖屏 / `<=1024` 平板横屏 / `>=1400` 大屏 / `>=1800` 超宽屏，另含 **手机横屏**（`max-height:520px` + `landscape`）。
- **顶栏**：手机端搜索框**折到第二行铺满**，LOGO 与「管理后台」按钮缩小，不再横向溢出。
- **顶部导航**：横向滑动 + 触摸目标加高（≥38px）+ 隐藏滚动条。
- **工具栏**（排序 / 分类筛选）：手机端**整条横滑**，不再折成两行把首屏顶下去。
- **卡片网格**：164 → 152 / 140 / 126px 自适应，**320px 屏仍能并排两张卡**。
- **内页**：hero 海报 184 → 124 → 104px，标题与标签分级缩放；「下载地址」手机端改**网盘标签与地址竖排**（长网址不再被挤碎）；分页、演职员横滑、KV 键值表单列化。
- **最新更新**：手机端标题独占一行（两行截断），分类与年份折到下一行。页脚在手机端居中。

### 2) 多端细节（前台）

| 场景 | 处理 |
|---|---|
| **刘海屏 / 底部横条** | `viewport-fit=cover` + `env(safe-area-inset-*)`（`@supports` 兜底），左右留白与页脚底部自动避让 |
| **触屏** | `@media (hover:none)` 取消卡片位移与链接/按钮的 hover 粘滞高亮 |
| **跟随系统深色** | 新增「前台明暗模式 = 跟随系统」（`body.mode-auto` + `prefers-color-scheme:dark`），**经典与简约模板都支持**；设置项 `simple_mode` 增加 `auto` 选项并设为默认 |
| **减少动画** | `prefers-reduced-motion:reduce` 关闭过渡与位移 |
| **高对比度** | `prefers-contrast:more` 加深文字与边框 |
| **打印** | `@media print` 隐藏顶栏 / 导航 / 首屏 / 工具栏 / 分页 / 页脚，hero 改黑字白底，卡片防跨页断裂 |
| **iOS 输入框** | 搜索框在小屏用 16px 字号，**阻止聚焦时整页被放大** |
| **键盘可达性** | `:focus-visible` 统一描边（顶栏 / 导航用白色描边） |

### 3) 后台管理（`admin/index.php`）

- **五档断点**：`<=380` / `<=560` / `<=768` / `<=1024` / `>=1400`，另含手机横屏与打印。
- **顶栏**：小屏收紧标题并自动隐藏副标题；**新增右上角明暗切换按钮**（循环 跟随系统 → 亮色 → 暗色）。
- **导航**：一级 Tab、站点设置的 5 个二级 Tab、条目的分类胶囊 —— 窄屏一律**横向滑动**。
- **表格**：包入 `.tablewrap` 横向滚动容器（`table{min-width:680px}`），窄屏滑动查看时**首列（封面）粘住**，不失去参照。
- **表单**：`.toolbar` 手机端改两列网格（搜索框独占一行）、`.token-bar` 输入框自适应、`.site-2col` 单列、下载链接行折行、`.grid2` 竖排。
- **弹层**：手机端**整屏显示**（去圆角、占满高度、底部避让安全区），软键盘不会把表单顶出屏幕；**手机横屏**时回到居中卡片。
- **统计卡**：小屏固定 2 列；日志与 SEO 预览框长行可换行、可横向滚动。
- **明暗主题**：`data-theme` + `localStorage`（键 `ml_admin_theme`）；进页面**先定主题避免亮色闪烁**；处于「跟随系统」时随系统偏好变化自动跟随；暗色共约 38 条规则（提示条 / 状态标 / 徽章 / 输入框 / 弹层 / 表格全覆盖）。
- **提示条重构**：原先 9 处内联色（`style="background:#ecfdf5…"`）统一改为类名 `.note-ok / .note-warn / .note-err`，暗色下才能正常适配。

### 4) 自检与文档

- `check.php` 新增 **10.6b「响应式自适应」**：统计前后台断点数量，检测刘海安全区 / 底部避让 / 跟随系统深色 / 减少动画 / 打印；断点为 0 时提示用升级包覆盖。10.6 项同步显示「明暗：跟随系统 / 亮色 / 暗色」。
- `DEPLOY.md` 新增「**十六、响应式与多端适配**」章节 + 6 条 FAQ。
- `preview/` 新增 **`responsive.html` 多设备自适应预览台**（并排 375 / 768 / 1280 三档 + 单档放大 + 5 个页面切换）；其余 11 个预览页统一注入 v1.6.1 正式 CSS；后台预览页支持明暗切换。
  > 预览页属于源码目录里的开发辅助文件，**不随发布包发布**。

### 5) 升级与兼容

- **改动文件（9 个，全部为修改）**：`index.php`（版本号 1.6.1）、`core/Views.php`、`core/Settings.php`、`admin/index.php`、`api/unified.php`、`check.php`、`config/config.sample.php`、`tools/install-cli.php`、`DEPLOY.md`。
- **要执行 SQL 吗？** **不需要**。仅 `app_settings` 里 `tpl_simple_mode` 的**默认值语义**变为 `auto`（取值仍是字符串，**无字段变更**）；已保存过该项的站点保持原值不变。
- **升级后外观会变吗？** 桌面端**不会**（断点集中在 1400px 以上与 1024px 以下）；手机 / 平板明显变好。想固定亮色：后台「站点设置 → 扫描+跳转 → 前台明暗模式」选「亮色」。

> **验证**：`phpcheck` 39 个 PHP 文件 **0 问题**；`php56scan` PHP 7.0+ 语法 **0 命中**；后台内嵌 JS 抽出后 `node --check` **rc=0**；整包与源码**逐字节一致**；增量包每个文件与整包**逐字节一致**。

---

## v1.6.0（2026-09-22）

**站点设置大版本：后台 5 个新设置页 + 前台完整接入 + 采集后自动补网盘下载地址**

起因：希望后台有「基础设置 / SEO / 扫描+跳转 / 网盘链接 / 接口配置」五个页面，前台按这些设置完整生效（LOGO、站名、宣传语、SEO、页脚、导航、模板），并且**采集到资源后自动扫描并写入网盘下载地址**。

### 1) 后台新增「站点设置」页（左侧一级入口，下含 5 个二级页）

| 二级页 | 设置项 |
|---|---|
| **基础设置** | 网站名称 / 隐藏名称 / 宣传语 / LOGO / icon / 网站网址 / 底部介绍 / 底部声明 / 底部版权 |
| **SEO** | SEO 标题 / 关键词 / 描述 / 统计代码 / 列表页标题模板 / 内页标题模板 / 内页描述截取字数 / robots.txt / 生成 sitemap / sitemap 收录上限 |
| **扫描 + 跳转** | PC 端访问方式（跳转+扫码 / 仅跳转 / 仅扫码）/ 跳转目标地址 / 手机端是否同样处理 / 群二维码 / 扫码提示语；**前端模板**（经典 · 简约 + 简约明暗）/ **顶部导航显示** / 顶部其他外链 / 全网榜单样式（有图·无图）/ 最新列表开关 |
| **网盘链接** | 按网盘分组（夸克 / 阿里 / 百度 / UC / 迅雷 / 光鸭，可勾选管理范围）× 三件套（Cookie / 默认转存目录 / 临时资源目录） |
| **接口配置** | PanSou 启用 / 部署方式 / 接口地址 / 账号密码 / 线路名 / 启用网盘类型 / 采集后自动补地址 / 写入策略 / 相似度阈值 / 每条最多链接数 / 请求间隔 / 缓存时长；含**测试连接**、**试搜**、**立即补地址**、**清空缓存**、**待确认队列** |

- **图片直接上传**：`POST /api/v1/admin/site_upload` —— 不看用户文件名、用 `getimagesize` 判真实类型（PHP 5.6 下 ico 用文件头兜底）、**随机改名**、单张 2MB 上限，存到站点根 `/uisc-assets/`（自动写入「拒访 index.php」+「禁执行 .htaccess」）。
- **SEO 效果预览**：`GET /api/v1/admin/seo_preview` —— 取站内**最新一条真实数据**套用模板，直接看到 `<title>` / description / canonical / robots 的实际输出。
- **设置层零改动扩展**：新增 `Core\Settings::groupMaps()`，把「设置键 → 配置段子键」的映射收敛成**一份**（`keys()` / `Config::applyOverlay()` / 后台渲染 / 自检共用），避免四处不同步。`Config::applyOverlay()` 改成按 group 通用叠加，新增设置组只需在 `schema()` 里加一项。

> **「0 是合法值」铁律**：叠加判断一律 `array_key_exists($k,$s) && $s[$k] !== ''`，**绝不能用 `empty()`** —— 否则 `site_name_hide=0`（显示名称）、`pansou_enable=0`、`list_latest=0` 这类「关闭」会被误判成「没设置」。

### 2) 新增 `core/Site.php`（约 780 行）—— 站点信息 / SEO / 跳转扫码 / 上传

- `info()`（9 项站点信息）、`flag()`、`asset()`（绝对网址 / `data:` / 以 `/` 开头直接返回，`uisc-assets/` 前缀只补一次，避免 `uisc-assets/uisc-assets/xx.png`）、`baseUrl()`（未填「网站网址」时按请求 Host 推断）。
- `seo($ctx,$data)` / `headMeta()`：**home / list / item 三种上下文**，输出 title + keywords + description + canonical + favicon + OpenGraph + 内联统计代码。
- `robotsTxt()` / `sitemapXml()`：`/robots.txt` 与 `/sitemap.xml` 由 `index.php` **路由动态输出**（留空走默认规则；sitemap 按已入库条目生成，可设收录上限）。
- `redirectRule()` / `isPc()` / `interceptHtml()`：**PC 端三模式**拦截页（自包含静态 HTML + `noindex,nofollow`），含**降级规则**（没填跳转地址 → 自动降级「仅扫码」；要扫码却没二维码 → 不拦截）；`redirect_mobile=0` 时只拦 PC、手机直接进站；**只拦首页入口**，内页静态文件不拦（不影响收录）。
- `upload()` / `writeGuards()`：见上。

### 3) 前台完整接入（`core/Views.php`）

- **顶栏** `topbarHtml()`：LOGO + 站点名（可隐藏，但**只有存在 LOGO 时才真隐藏**，否则顶栏会空白）+ 宣传语。
- **导航** `navHtml()`：入口来自后台勾选（首页 / 全网榜单 / Top250 / 游戏排行 / 最新更新 / 联系我们），支持「顶部其他外链」一行一个 `文字|网址`；**一个都没勾 = 顶栏不显示导航**。
- **模板** `bodyClass()`：`tpl-classic` / `tpl-simple`（+ `mode-light` / `mode-dark`）；简约模板更紧凑、去装饰，暗黑模式是一整套 CSS 变量覆写。
- **页脚** `footerHtml()`：底部介绍 / 声明 / 版权三段（`id="contact"` 供「联系我们」导航定位）。
- **最新更新区块**：仅首页（无筛选、无搜索、第一页）且后台开启时取 8 条，避免每次翻页都多跑查询。
- **榜单无图模式**：`.grid-plain` 把卡片改成横向纯文字行（`c-thumb` 隐藏），加载更快。

### 4) 新增 `core/PanSou.php`（约 31 KB）—— 网盘搜索对接 + 自动补地址

- **对接**：`GET /api/health`（频道 / 插件 / 版本）与 `GET /api/search?kw=…&res=merge&cloud_types=…`；`curl` 优先、`file_get_contents` 兜底，支持 Basic Auth；**兼容旧 fork 的 `keyword` 参数名**；兼容返回体的 `merged_by_type` 与 `results` **两种结构**。
- **打分**：`similar_text` 相似度 + **包含关系加分**（资源标题常带「1080P / 全集 / 更新至xx」）+ **年份命中加分**；每种网盘取**最高分一条**，按相似度排序后截断到「每条最多几个链接」。
- **写入**：`attach()` 按**网盘标签去重** —— 某网盘已有链接就**保留站长手工填的那条**，绝不被覆盖；带提取码的链接自动拼 `?pwd=xxxx`；下载地址统一存 JSON，同时兼容旧版「一行一个地址」的纯文本。
- **三种写入策略**：`exact`（仅高度匹配才自动写，默认）/ `write`（搜到就写）/ `pending`（一律进待确认）。
- **待确认队列**：`pan_pending` 表，后台可逐条**采纳**（写入并出队）或**丢弃**；同一条目只保留最新一次搜索结果；顶栏显示待确认条数。
- **缓存**：`pan_search_cache` 表 + TTL（默认 720 分钟）；`mb_substr` 有 `function_exists` 兜底。
- **自动建表**：`ensureTables()` 首次使用时 `CREATE TABLE IF NOT EXISTS`（两张表），无需手工导 SQL。

### 5) 采集后自动补下载地址（`core/Sync.php`）

- 静态页生成后新增一个阶段：拿**本次新入库**的条目去 PanSou 搜一遍，按策略写入或进队列；**补到地址的条目会再生成一次静态页**（否则前台看不到下载按钮）。
- 中文标题搜不到高分时，自动用 `original_title`（原始外文标题）再搜一次。
- 报告新增 `pan` 字段：`checked / written / queued / nomatch / links / items / errors / ms`（后台「最近采集记录」可见）。
- 全程 `catch (\Exception) + catch (\Error)` 双兜底 —— 补地址失败**绝不影响采集本身**。

### 6) 统一 API 新增端点（`api/unified.php`）

`admin/site_upload`、`admin/seo_preview`、`admin/pansou_state`、`admin/pansou_test`、`admin/pansou_search`、`admin/pansou_cache_clear`、`admin/enrich_run`（`set_time_limit(300)`）、`admin/pending_list`、`admin/pending_apply`、`admin/pending_drop`。
`saveSettings()` 增加新键的范围与枚举校验（含 `pansou_url` 必须 `^https?://`）—— 前后端双重校验（沿用 v1.5.0「永不返回非 JSON」的约定）。

### 7) 自检新增第 10 组「站点设置 / SEO / 网盘接口」（`check.php`）

站点名与网址是否填写 · LOGO/icon 状态 · 页脚三段 · SEO 三件套是否齐全 · 标题模板与占位符说明 · 统计代码 · robots/sitemap 配置 · **新功能代码是否就位**（`core/Site.php`、`core/PanSou.php`、三条路由字符串）· **真实 HTTP 探测 `/robots.txt` 与 `/sitemap.xml`** · **`/uisc-assets` 可写性**（不存在时自动回退检查站点根）· 跳转扫码配置是否**自相矛盾** · 前台模板与导航 · 网盘账号填写情况 · **PanSou 连通性实测**（`/api/health`，回报频道数 / 插件数 / 版本 / 耗时）· 自动补地址参数 · **待确认队列条数**。

### 8) 配置与文档

- `config/config.sample.php` 与 CLI 安装器生成的 `config/config.php` 都补上 `site` / `seo` / `redirect` / `pan` / `pansou` 五段（每段带中文注释，明确「建议在后台改」）。
- 新增 `sql/migrate_site_seo.sql`：显式对齐 `app_settings` + PanSou 两张表（全是 `CREATE TABLE IF NOT EXISTS`，**不改动既有表**）。
- `DEPLOY.md` 新增第十五章「站点设置」（5 个页面的完整说明 + PanSou 部署四步 + 写入规则 + 表格）与 12 条新 FAQ；「常见问题」顺延为第十六章。
- `preview/` 新增 5 个后台页面预览 + 1 个前台效果预览（`front-site.html`，可实时切换经典/简约、白天/暗黑、有图/无图、最新列表开关）。
- `core/Guard.php` 的守卫清单加入 `core/Site.php`、`core/PanSou.php`。
- 版本号 `ML_APP_VERSION` → **1.6.0**。

### 9) 兼容性

- 仍然 **PHP 5.6 ~ 8.2 全兼容**：新代码无 `??` / `fn()` / 返回类型 / 标量类型提示 / `\Throwable` / 数组解构 / `?->` / `match`。
- **升级不需要执行 SQL**：站点设置只往已有的 `app_settings` 写 key-value，**无新增或改动字段**；PanSou 两张表自动创建。
- **已有站点升级不会变样**：所有新设置项默认空 → 前台走内置默认（无导航也照常显示主页；跳转扫码未配置 = 不拦截）。

---

## v1.5.0（2026-09-22）

**采集（同步）重构：修掉「点了没反应」，并加上限流 / 去重 / 最热口径 / 每小时计划任务**

起因：后台点「同步热门」**毫无反应**（连报错框都不弹）；同时希望「每次同步加限制、对比源站要求、只取最热门和最多人看的、重复就跳过」，并在计划任务里每小时采集一次。

### 1) 「点了没反应」的真因与修法

| 位置 | 之前 | 现在 |
|---|---|---|
| 后台 `api()` | `fetch(...).then(r=>r.json())` —— 服务端一旦返回 **HTML**（PHP 致命错误页 / 502 / 504 网关超时），`json()` 解析失败直接中断，页面"毫无反应"，只在浏览器控制台留红字 | **永不抛异常**：任何异常/非 JSON 都转成 `{success:false, error:"…"}`；提示里带 HTTP 状态码 + 原因（`504`=超时被网关掐断、`500`=PHP 致命错误、`404`=伪静态没配）+ 响应前 180 字 |
| 同步按钮 | 点完就干等，无任何反馈 | 按钮进入忙碌态并显示"正在采集（按各源官方要求限流，可能要十几秒）…"，结束后在下方**用表格列出每个源、每个接口**抓到多少条 / 新增多少 / 跳过多少 |
| 后端 `POST /api/v1/admin/sync` | 裸调，任何异常都会变成 HTML 致命错误页 | 整体 `try/catch`（含 `\Error`）+ `set_time_limit(180)`，**必定返回 JSON**；单条写入失败也不影响整批 |

### 2) 新增 `core/Sync.php` —— 采集引擎（网页按钮与计划任务共用）

- **只取「最热」与「最多人看」**：
  - TMDB：`/trending/all/{day|week}`（最热）+ `/movie/popular`、`/tv/popular`、综艺 `discover/tv?with_genres=10764`（最多人看）
  - RAWG：`/games?ordering=-added`（最多人添加）+ `ordering=-rating`（评分人气最高）
  - Bangumi：`/calendar`（当日在播）+ `/v0/subjects?type=2&sort=rank`（排行榜，按评分人数排序）
- **按各源官方要求限流**（`profiles()` 里一句话写清官方配额，后台「同步采集」页原样展示成对照表）：
  - TMDB：官方约 50 请求/秒、单页 20 条 → 自设 **300 ms/请求**、每源 ≤ 20 条
  - RAWG：免费 20,000 次/月（≈27/小时）、单页 ≤ 40 条 → 自设 **1500 ms/请求**
  - Bangumi：免 Key、建议 ≥1 秒/请求 → 自设 **1200 ms/请求**，并自动带 UA
  - 另有两条硬闸：**单次上游请求总数上限**（默认 8）、**每源每次入库条数上限**（默认 20）
- **重复就跳过**：`CacheStore::exists()`（同源同 ID）+ `CacheStore::existsSameTitle()`（跨源同名同年，年份容差 ±1）。
- **只给新增条目生成静态页**（`StaticGen::buildOne`），不做全量重建 —— 所以每小时跑也轻。
- **三重防重复触发**：最小间隔（默认 55 分钟，未到期直接跳过且**不写日志**，避免自己刷新计时）、`config/sync.lock` 进程锁（10 分钟僵尸锁自动失效）、请求总数硬闸。
- 每次结果写 `sync_log`（`task=sync:manual` / `sync:hourly` / `sync:trending`），含每源明细。

### 3) 后台新增「同步采集」页

- **采集参数可改**（存 `app_settings`，键名 `sync_*`）：每源条数 / 翻页数 / 请求上限 / 时间窗 / 口径 / 最低票数 / 重复处理 / 同名是否跳过 / 最小间隔 / 是否生成静态页。前后端都有范围校验（如「每源条数」1~200）。
- **各源官方要求 vs 本次限制对照表**：每个源的接口文档链接、官方配额、单页上限、本次要打的接口清单、请求间隔。
- **每小时计划任务**：直接给出**可一键复制的命令**（自动按当前 PHP 版本取 `/www/server/php/xx/bin/php`，不用手拼路径）+ 宝塔配置四步。
- **最近采集记录**：时间 / 触发方式（后台手动 / 计划任务）/ 新增条数 / 成败 / 各源明细。
- 按钮：`立即采集`、`强制采集（忽略最小间隔）`、`预演（只探测不入库）`。
- 新接口 `GET /api/v1/admin/sync_plan`。

### 4) 新增每小时计划任务 `cron/sync_hourly.php`

```bash
php /www/wwwroot/你的站点/cron/sync_hourly.php        # 宝塔：Shell 脚本 / 每 1 小时
```
支持 `--limit= --window= --order= --sources= --max-requests= --interval= --force --dry --no-build --quiet --help`；退出码 `0/1` 便于在面板日志里看成败；**非 CLI 访问返回 404**。
`cron/sync_trending.php` 改为复用同一引擎（默认本周窗口 + 每源 40 条 + 末尾全量重建静态页）。

### 5) 新增数据源画像与适配器能力

- `Adapter` 接口新增 `trendingFetch(array $ep, $limit, $order)`；三个适配器全部实现，并强制遵守单页上限（RAWG ≤40）。
- 同步时**跳过 person（人物）**（站点无人物栏目），TMDB 真人秀 `10764` 映射为「综艺」。

### 6) 自检 `check.php` 新增第 9 组「采集与计划任务」

启用的数据源、**已启用但缺 Key**（这是采集 0 条的头号原因，直接判 fail）、采集参数摘要、`cron/` 脚本是否就位、最近一次采集（时间/新增/成败/备注）、同步锁残留、可复制的计划任务命令（路径按宝塔实际 PHP 版本探测，不再误用 FPM 的 `php-fpm` 路径）。

### 7) 其他

- `config/config.sample.php` 增加 `sync` 段（文件兜底值，注释说明可全在后台改）。
- `core/Guard.php` 的守卫清单加入 `core/Sync.php`；`DEPLOY.md` 第九节整节重写为「采集（同步）与定时计划任务」，FAQ 新增 6 条（含"点了没反应""新增 0 条""计划任务有没有在跑"）。

**新增/改动文件**

| 文件 | 说明 |
|---|---|
| `core/Sync.php` | **新增**：采集引擎（限流 / 去重 / 最热口径 / 进程锁 / 写日志） |
| `cron/sync_hourly.php` | **新增**：每小时采集脚本（CLI 专用） |
| `adapters/Adapter.php`、`Tmdb.php`、`Rawg.php`、`Bangumi.php` | 新增 `trendingFetch()`，支持条数上限与多口径端点 |
| `core/CacheStore.php` | 新增 `exists()` / `existsSameTitle()`；`put()` 返回本地 id |
| `core/Settings.php`、`core/Config.php` | 新增 10 个 `sync_*` 设置键 + schema + 叠加逻辑（0 也是合法值） |
| `api/unified.php` | `admin/sync` 走 Sync 引擎并整体兜底；新增 `admin/sync_plan`；设置校验扩展 |
| `admin/index.php` | `api()` 永不抛错 + 忙碌态；新增「同步采集」Tab；同步结果改为表格展示 |
| `check.php` | 新增第 9 组「采集与计划任务」 |
| `index.php` | `ML_APP_VERSION` → `1.5.0` |
| `config/config.sample.php`、`core/Guard.php`、`DEPLOY.md`、`CHANGELOG.md`、`资料索引.md` | 同步 |

> 升级方式：**整包覆盖**（保留站点上的 `config/config.php`、`config/install.lock`、`uisc/`）。**数据库无变更**（不新增表、不新增字段；采集参数存在既有的 `app_settings` 表里）。
> 覆盖后到 **后台 →「同步采集」** 点一次「立即采集」，再按页面提示把每小时计划任务加上即可。

### 增量升级包（v1.4.1 → v1.5.0）

已在跑的站点不必传整包，用增量包覆盖即可：

- 存放：`升级包/media-library-api/`（增量升级包统一放此目录，按项目分子目录）
- 包名：`media-library-api-update-v1.4.1-to-v1.5.0.zip`（**19 条目 / 108.7 KB**）
- 内容：**17 个变化文件**（新增 2 + 修改 15，逐字节等同整包内同名文件）+ `UPGRADE.txt`（GBK，记事本友好）/ `UPGRADE.md`
- 差异来源：v1.4.1 与 v1.5.0 两个发布包逐文件比对（MD5），**删除 0 个 / 未变 28 个**，故未变文件不进包
- 不含 `config/config.php`、`config/install.lock`、`config/pan_types.php`、`uisc/`、任何 `*.bak-*`
- **不碰数据库**：无表结构变更，无需执行任何 `sql/migrate_*.sql`

| 类别 | 文件 |
|---|---|
| 新增（2） | `core/Sync.php`、`cron/sync_hourly.php` |
| 修改（15） | `index.php`、`admin/index.php`、`api/unified.php`、`core/Settings.php`、`core/Config.php`、`core/CacheStore.php`、`core/Guard.php`、`adapters/Adapter.php`、`adapters/Tmdb.php`、`adapters/Rawg.php`、`adapters/Bangumi.php`、`cron/sync_trending.php`、`config/config.sample.php`、`check.php`、`DEPLOY.md` |

---

## v1.4.1（2026-09-22）

**线上自检报告修复 + 暴露面实测**

起因：`check.php` 报告 `score=64`、`Fail 1 / Warn 10`，其中多条是**自检页自己的判断问题**，同时真实暴露面没被覆盖。

**1) 修掉误报（这些不是服务器的问题）**

| 项 | 之前 | 现在 |
|---|---|---|
| PHP 版本 | PHP 7.2.33 直接 `fail`，提示"需要 PHP 7.4+" | 判定下限正确回到 **5.6**；5.6/7.x/8.x 一律 `ok`。低于 7.4 时只给一条"可升可不升"的建议 |
| display_errors | 报 `On（建议关闭）` —— 其实是**自检页自己** `@ini_set('display_errors','1')` 后再去读，永远为 On | 先记录**服务器原始值**再临时打开；报告里显示的是原值，并在说明里注明"本页临时打开只为排错" |
| exec 被禁用 | `warn` | 降为 `info`：禁用是更安全且正常的配置，只影响本页的 `php -l` 自检，不影响站点 |
| 静态页已生成 0 个 / 资料总条数 0 / 分类无数据 / 无下载链接 / 无检测记录 / 无采集记录 | 一律 `warn`，把"站点刚装好还没内容"当成部署缺陷，拉低评分 | 降为 `info`（内容为空不等于部署有问题）；**有数据但静态页没生成**仍会 `warn`（由「静态页覆盖率」负责） |

**2) 新增暴露面实测（这次真正抓到线上问题）**

第 8 组新增 "8b / 8c" 两段：

- **8b · 定时脚本防公网触发**：扫描 `cron/*.php` 是否都带 `ml_guard_shield()`。不带守卫的脚本只要能从网址打开，任何人都可以反复远程触发采集 / 重建静态页（白烧服务器和上游配额）——直接 `fail`。
- **8c · 公网可达性实测**：只探测**只读**路径（避免自检本身误触发采集），逐条给出 `ok / warn / fail`：
  - `/install.php`（网页安装器，应已删除）
  - `/config/config.php`（数据库配置）
  - `/core/Config.php`、`/home.php`（内部文件；若回显 Fatal error 会连带泄露服务器绝对路径）
  - `/sql/install.sql`（内容含 `CREATE TABLE` 即判定最严重泄露）
  - `/config/config.sample.php`、`/DEPLOY.md`
  - 末尾追加「内部文件暴露面汇总」，一眼看清有几个高危地址仍可访问。
- `404 / 403 / 30x` 都判为安全；只有真能拿到 200 才报警。

**3) 修正伪静态写法（这是线上 `/config/config.php` 还能执行的真因）**

`DEPLOY.md` 第四节的目录保护从正则 `location ~* /(config|sql|...)/` 改为 **`location ^~ /config/ { return 404; }`** 逐目录写法。

原因：Nginx 匹配优先级为「`=` > `^~` 前缀 > 正则 > 普通前缀」，而宝塔给站点预置的 PHP 处理段是正则 `location ~ [^/]\.php(/|$)`。用普通前缀或后置的正则，`/config/config.php` 都会被那条 `.php` 正则抢先命中并交给 PHP 执行（实测返回 200 空body）；只有 `^~` 能压过正则。

**4) 补上一个漏掉的守卫：安装器生成的配置文件**

`config/config.php` 与 `config/pan_types.php` 是**安装器生成**的（发布包不带，避免冲掉数据库口令），
所以它们没有跟着 v1.4.0 的改动加上"反直接访问"守卫 —— 实测 `/config/config.php` 被直接请求时
**返回 200（PHP 执行了它）**。已在 `tools/install-cli.php` 的生成模板里补上守卫（带 `is_file()` 兜底，
`core/Guard.php` 万一缺失也不会连带把站点搞崩）；对**已存在**的站点文件不会被覆盖，走 Nginx `^~ /config/` 规则兜底，
自检页的提示也会按 `config/` 与非 `config/` 给出不同修法。

**新增/改动文件**

| 文件 | 说明 |
|---|---|
| `check.php` | 修 4 处误报；第 8 组新增 8b/8c；8c 提示按 `config/` 定制；变量与检测项说明同步 |
| `tools/install-cli.php` | 生成的 `config.php` / `pan_types.php` 现在自带"反直接访问"守卫 |
| `index.php` | `ML_APP_VERSION` → `1.4.1` |
| `DEPLOY.md` | 伪静态目录保护改 `^~`（含原因说明）；加固清单新增「定时脚本」一行 |
| `CHANGELOG.md` / `资料索引.md` | 同步 |

> 升级方式：**只上传 `check.php`（+ 若还没升过 v1.4.0，请整包覆盖）**，无需动数据库。
> 建议用 **不带 `nohttp=1`** 的地址再跑一次，才能看到"暴露面实测"结果。

---

## v1.4.0（2026-09-22）

**安全加固：网页版安装器下线 + 三层纵深防护**

起因：`install.php` 挂在公网上，任何人访问到它就能**重装站点**，或从它的环境自检里**读到服务器信息**。本次把安装器从站点根移除，并给整套系统补上防护。

**1) 删除 `install.php`，改为命令行安装器**

| 变化 | 说明 |
|---|---|
| 删除 | 站点根的 `install.php` 已从源码与发布包中**移除** |
| 新增 | `tools/install-cli.php` —— 仅命令行可执行（公网访问返回 404）；支持交互式或 `--db= --user= --pass= --token=` 参数式安装：测库 → 自动建表 → 生成 `config.php` / `pan_types.php` → 写 `install.lock`，写前自动备份 |
| 重装 / 迁移 | `php tools/install-cli.php --force` |
| 首页引导页 | 未安装时改为提示「在终端运行 `php tools/install-cli.php`」 |

**2) 新增 `core/Guard.php`（三层纵深防护）**

| 能力 | 作用 |
|---|---|
| `ml_guard_boot()` | 入口守卫：`/install` `/setup` `/upgrade` 等**安装类路径黑洞**；`/config/` `/sql/` `/core/` `/adapters/` `/cron/` `/tools/` 等敏感目录与 `.sql/.md/.lock/.bak` 等后缀一律 404；点文件一律 404 |
| `ml_guard_shield()` | 内部文件守卫：**每个内部 PHP 文件顶部**调用，当该文件本身被直接用网址请求时返回 404（被入口 `require` 时无影响，CLI 运行不受影响） |
| `ml_guard_status()` | 汇总防护状态，供后台「安全防护」页与 `check.php` 展示 |
| `ml_guard_deny()` | 统一 404 出口：伪装成普通 404 页（JSON 请求返回 JSON），**不提示"被保护 / 需要令牌"**，避免被扫描器确认 |

配套：`config/ sql/ core/ adapters/ cron/ tools/` 各放入一个「拒访 `index.php`」（目录级访问一律 404）；`cron/*.php` 也加了守卫（防止被网址触发采集/重建）。

**3) `check.php` 未授权一律 404**

- 以前：令牌非默认值时显示"需要鉴权"输入框 —— 等于告诉扫描器"这里有自检页"，还可能被爆破。
- 现在：**未通过令牌校验的请求直接 404**（不出现任何"需要令牌"字样）；令牌来自后台「系统设置」或 `config.php`（两者都认）。
- 站点尚未设置任何令牌时（刚装完），只放行 `127.0.0.1` / 内网地址。
- 新增第 8 组检查「安全防护」：安装器是否已移除、目录拒访文件、内部文件守卫覆盖率，并对 `/install.php`、`/config/config.sample.php`、`/sql/install.sql`、`/DEPLOY.md` 做**公网可达性实测**。

**4) 后台新增「安全防护」页**

- 逐项状态（安装器 / CLI 安装器 / 安装锁 / 目录拒访 / 内部守卫 / `.htaccess`）。
- 一键「打开环境自检报告」：自动携带当前后台令牌，不用手输。
- 新增接口 `GET /api/v1/admin/security`。

**5) 其他**

- `/track.php` 埋点改为 `/track?id=N`（统一走入口路由）；`track.php` 保留以兼容旧静态页，因它是公开埋点端点故不加守卫。
- `.htaccess` 重写：新增安装器路径黑洞、`tools/` 与更多后缀的拒绝规则。
- `DEPLOY.md`：第二/三节改为「命令行安装 / 手动安装」，第四/五节改为「Nginx 伪静态 + 安全加固」并附**安全加固清单表**，第十三节更新自检页说明，常见问题新增 6 条。

**新增/改动文件**

| 文件 | 说明 |
|---|---|
| `install.php` | **删除** |
| `tools/install-cli.php` | **新增**：命令行安装器（仅 CLI 可执行） |
| `core/Guard.php` | **新增**：入口守卫 + 内部文件守卫 + 防护状态 |
| `index.php` | 加载守卫、移除 `/install` 路由、改写未安装引导页、定义 `ML_APP_VERSION` |
| `check.php` | 未授权返回 404；新增「8 · 安全防护」分组；核心文件清单加 `core/Guard.php` |
| `admin/index.php` | 新增「安全防护」Tab + `loadSecurity()` / `openSelfCheck()` |
| `api/unified.php` | 新增 `admin/security` 端点 |
| `core/*`、`adapters/*`、`api/*`、`home.php`、`config/config.sample.php` | 顶部加入 `ml_guard_shield()` 守卫 |
| `config/ sql/ core/ adapters/ cron/ tools/ index.php` | **新增**：目录拒访页 |
| `.htaccess`、`DEPLOY.md`、`CHANGELOG.md` | 同步 |

> 升级方式：**整包覆盖**（保留站点上的 `config/config.php`、`config/install.lock`、`uisc/`），然后**删掉站点根的 `install.php`**，再上传 `tools/` 目录即可。**数据库无任何变更。**

---

## v1.3.0（2026-09-22）

**新增：后台「系统设置」——这几项不用再改 `config.php` 了**

原先安装向导里填的这些项，装完就只能去服务器改 `config/config.php`。现在后台多了一个「系统设置」页，全部可视化增删改：

| 设置项 | 后台能力 |
|---|---|
| 后台管理令牌 `admin.token` | 直接改 + 「随机生成」；保存后本页自动切换到新令牌 |
| TMDB API Key | 直接改 + 「测试连通」（区分 Key 无效 / 网络不通） |
| RAWG API Key | 同上 |
| 启用哪些数据源 | TMDB / RAWG / Bangumi 勾选，至少一个 |
| 网盘类型 | 点「添加」逐个加（如 123网盘）、点 `×` 删除、「恢复默认」一键还原 |

**存储与优先级**

- 新增 `core/Settings.php` + 数据库表 **`app_settings`**（k/v）；首次打开设置页若表不存在会**自动建表**，无需手工导 SQL。
- `core/Config.php` 改为叠加式加载：**后台保存的值 > `config/config.php`**；某项留空即**回退**读配置文件，所以「清空并保存」= 恢复默认。
- 因此**整包覆盖升级再也不会冲掉这些设置**（它们在数据库里，不在文件里），`config.php` 也可以设为只读。

**顺带修的 BUG**

- 后台接口基址在**域名根目录**部署时被拼成 `//api/v1/...`（协议相对 URL，会请求到 `api` 这个主机），导致后台所有查询/保存失效；现按子目录/根目录正确拼接。
- `check.php`：PHP 版本要求仍写着「7.4+」（v1.2.0 已降级到 5.6 兼容），已更正为 5.6+；并新增显示「后台设置覆盖了哪几项」、表结构检查加入 `app_settings`。
- 修复 `check.php` 自身新增代码里的一处括号不匹配（结构检查发现）。

**新增/改动文件**

| 文件 | 说明 |
|---|---|
| `core/Settings.php` | **新增**：设置读写 + 白名单 + 列表值转换 + 自动建表 |
| `core/Config.php` | 重写：支持后台覆盖叠加、新增 `base()/baseSub()` 读取文件原值 |
| `api/unified.php` | 新增 `admin/settings`、`settings_reset`、`settings_test` 端点 |
| `admin/index.php` | 新增「系统设置」Tab（动态按后端 schema 渲染）；修复 API 基址拼接 |
| `install.php` | 建表加入 `app_settings`；表单提示改为「装完可在后台改」 |
| `sql/install.sql`、`sql/migrate_settings.sql` | 新增表 / 迁移脚本（迁移可不手工执行） |
| `check.php`、`DEPLOY.md`、`config/config.sample.php` | 同步 |
| `index.php` | 预加载 `core/Settings.php` |

> 升级方式：直接覆盖源码即可；`app_settings` 表会在首次打开设置页时自动创建（也可先导入 `sql/migrate_settings.sql`）。**数据库无破坏性变更。**

---

## v1.2.1（2026-09-22）

**修复：上传后打开 `/install.php` 直接显示「该站点已完成安装」，但我根本没填过数据库**

- 根因：发布包自带了 `config/config.php`（模板，`db.pass` 是占位值 `CHANGE_ME`），而安装向导只判断「文件是否存在」：
  ```php
  $installed = file_exists($LOCK_FILE) || file_exists($CFG_FILE);   // ← 旧逻辑
  ```
  于是全新站点一上传就被判定成"已安装"。
- 修复：新增 `core/InstallGuard.php`，判定改为**看 db 段是否填了真值**——

  | 状态 | 判定 |
  |---|---|
  | 有 `config/install.lock` | 已安装 |
  | `config.php` 的 db 已填真值 | 已安装 |
  | `config.php` 存在但口令仍是 `CHANGE_ME` 之类的占位值 | **未安装**（放行向导） |
  | 没有 `config.php` | 未安装 |

  直接读文件文本而不 `include`，配置文件有语法错误也不会把向导带崩；空口令**不**算占位（避免误判真实站点）。

**变更：发布包不再自带 `config/config.php`**

- 改为提供示例 `config/config.sample.php`；正式配置由安装向导生成，或手动复制改名。
- 好处：**升级覆盖源码再也不会把数据库口令 / API Key 重置**（此前整包覆盖会用占位模板盖掉真实配置）。
- 向导覆盖已有 `config.php` / `pan_types.php` 前，会先备份为 `*.bak-日期时间`。

**新增：未安装时的友好引导页**

- `index.php` 增加未安装守卫：当 db 未填（或没有 config.php）时，访问首页 / 后台 / API 会显示引导页并给出「前往安装向导」按钮，而不是直接抛数据库连接错误。`/install*`、`/check*` 不受影响。

**新增：`check.php` 的「安装状态」检查项**

- 配置分组里新增一行，用同一套判定显示「已完成安装 / 尚未安装」及原因，修复引导一步到位。

**升级方式（从 v1.2.0 或更早）**

1. 覆盖上传全部文件到站点根（**保留** `config/config.php`、`config/install.lock`、`uisc/`）。
2. 无 SQL 变更，不需要导库。
3. 若站点上仍是旧包的占位 `config.php`（没真正装过），上传后直接访问 `/install.php` 就能正常进向导了。
4. 访问 `/check.php` 确认「安装状态」为已完成。

---

## v1.2.0（2026-09-22）

**兼容性（重点）**
- **修复 `Parse error: syntax error, unexpected '=>' ... in core/Config.php on line 43`**：该行 `array_filter($list, fn($x) => ...)` 用了 PHP 7.4 的箭头函数，在 PHP 5.6 下直接语法错误。
- 全量降级为 **PHP 5.6 ~ 8.2 通用语法**，涉及 26 个文件：

  | 7.0+ 语法 | 现写法 |
  |---|---|
  | `$a['k'] ?? $d`（7.0） | `isset($a['k']) ? $a['k'] : $d`（约 180 处） |
  | `fn($x) => ...`（7.4） | `function ($x) { return ...; }` |
  | `function f(): array`（7.0） | 去掉返回类型 |
  | `function f(string $s)`（7.0） | 去掉标量类型提示（保留 `array` 提示） |
  | `public const X`（7.1） | `const X` |
  | `foreach ($a as [$x,$y])` / `[$x,$y] = f()`（7.1） | 索引取值 / `list()` |
  | `catch (\Throwable $e)`（7.0） | `catch (\Exception $e)` |
  | `random_bytes()`（7.0） | `substr(md5(uniqid('', true)), 0, 6)` |
  | `new $map[$e]()`（易踩坑） | `$cls = $map[$e]; new $cls();` |

- 新增 `Config::sub('cache','ttl_days',7)` 辅助方法，替代 `Config::get('cache')['ttl_days'] ?? 7` 这类写法。

**新增**
- **网址全新安装向导 `install.php`**（入口 `/install.php`、`/install`，已在 `index.php` 注册路由）：环境自检 → 填数据库信息 → **自动建库/建表** → 自动生成 `config/config.php` 与 `config/pan_types.php` → 建 `uisc/` → 写 `config/install.lock` 锁定安装。自包含建表 SQL，不依赖 `sql/` 目录；已安装后再访问会提示并指引重装（`?force=1`）。
- `.htaccess`：Apache 环境的重写 + 目录保护规则（Nginx 会忽略）。

**修复**
- `Adapters\Tmdb::url()` 原为 `private`，却被 `Api\TmdbProxy` 调用 → 改为 `public`（原会导致 `/3/genre/*`、`/3/configuration` 直接致命错误）。
- `cron/sync_trending.php`、`cron/sync_refresh.php` 调用了 `StaticGen::buildAll()` 却未 `require`/`use` → 补齐引用，并给重建加 try/catch，避免采集成功但脚本崩在最后一步。
- `sync_refresh` 把 `['refreshed'=>N]` 混进错误日志数组导致日志结构混乱 → 已分离。
- 后台 JS 里的 `??`（`it.rating??''`）改为显式判空，兼容老浏览器。

**文档**
- `DEPLOY.md` **重写**：PHP 版本支持对照表、向导安装/手动安装双路径、**第四节专讲 Nginx 伪静态**（含目录保护规则）、Apache `.htaccess` 方案、PHP 兼容性对照表、常见问题（含 Parse error / 404 / MySQL8 认证）。

**数据库**
- 无需改动，无新增 SQL；直接覆盖文件即可升级（`install.php` 在已有库上重复执行也安全，全部 `CREATE TABLE IF NOT EXISTS`）。

---

## v1.1.1（2026-09-22）

**新增**
- **一键环境自检 `check.php`**（入口 `/check.php`、`/check`、`/health`，已在 `index.php` 注册路由）。7 组检查、60+ 检测项，带健康度评分与逐项修复建议：
  1. 运行环境：PHP 版本、`pdo_mysql`/`curl`/`json`/`mbstring`/`openssl`、`display_errors`、时区、内存/超时、`exec` 可用性、磁盘空间
  2. 文件与目录权限：`uisc/`、`config/`、站点根真实写入探针 + 22 个核心文件完整性
  3. 配置项：`config.php` 载入、数据库口令/`admin.token` 是否仍为默认占位、TMDB/RAWG key、网盘类型列表
  4. 数据库与数据：连接、版本/字符集、三张表与关键字段齐全度（缺列直接给出 `ALTER` 补救 SQL）、分类分布、已填下载链接数、静态页覆盖率、链接检测与采集记录
  5. 路由 / 伪静态 / 安全：实时请求 `/api/v1/categories`、`/3/configuration`、`/`；探测伪静态是否生效；检测 `config/config.php` 与 `sql/install.sql` 是否可被公网直接下载（高危）
  6. 上游连通性：TMDB / RAWG / Bangumi / 图片 CDN 从服务器侧的可达性
  7. 代码语法：服务器允许 `exec` 时用 `php -l` 逐个校验 PHP 文件
- **JSON 模式** `?format=json`（含 `success` / `verdict` / `score` / `sections`），便于脚本与监控调用。
- **可选写库测试** `?write=1`：插入一条临时数据后立即删除，验证数据库写入权限，不留残留。

**安全**
- 报告内数据库口令 / API key / 后台令牌全部自动打码（仅首尾字符）。
- 当 `admin.token` 非默认值时，自检页强制鉴权（`?token=` 或 `X-Admin-Token`）。
- 文档明确提示：自检通过后删除或重命名 `check.php`。

**文档**
- `DEPLOY.md`：部署步骤新增「一键自检」，并新增第九节「环境自检（check.php）」完整说明。

**兼容**
- 无需改动数据库，无新增 SQL；直接覆盖文件即可升级。

---

## v1.1.0（2026-09-22）

**新增**
- **后台链接失效检查**：`core/LinkChecker.php`（HEAD，失败回退 GET，带超时）+ `link_checks` 表；后台列表新增「下载链接」状态列（✓ 有效 / ✗ 失效 / 未检测）、每行「查」按钮、工具栏「检查全部链接」批量检测；编辑弹窗每条链接旁显示状态标。

**优化 / 美化**
- 全站视觉升级：渐变顶栏、首页 banner（收录数/分类/排序）、卡片悬浮与海报缩放、圆角阴影、分类胶囊按钮、内页 hero 与下载标签重做；后台同步美化（卡片、统计块、状态标、按钮）。
- **前台下线 API 字样**：主页卡片与内页不再展示「API 调用次数」，排序 tab「API 调用」改为「热度」；后台仍保留该指标。
- **下载区仅在有链接时显示**：内页下载块、卡片「可下载」角标均基于是否填写有效链接判断；空的链接行自动跳过。

**修复**
- 下载链接解析统一（JSON 数组 / 旧纯文本多行），过滤空链接，避免出现空行占位。
- 内页计数脚本改为仅在存在点击元素时执行，去掉多余元素引用。

**数据库**
- 新库：导入 `sql/install.sql`（已含 `link_checks` 表）。
- 老库升级：额外导入 `sql/migrate_link_checks.sql`（数据不丢）。

---

## v1.0.0（2026-09-22）

**接口**
- TMDB v3 兼容代理：`/3/movie`、`/3/tv`、`/3/person`、`/3/search`、`/3/trending`、`/3/genre`、`/3/configuration`
- 统一 API：`/api/v1/search`、`items`、`item`、`trending`、`genres`、`categories`、`metric/{id}`
- 后台管理 API：`/api/v1/admin/*`（token 鉴权）

**功能**
- 6 大分类：短剧 / 电影 / 电视剧 / 动漫 / 综艺 / 游戏（单一来源 `core/Categories.php`）
- 主页：卡片墙 + 排序（API 调用 / 点击 / 最新 / 评分）+ 分类筛选 + 分页
- 静态内页：`/uisc/{id}.html` 按类型差异化渲染（影视→演职人员、游戏→平台/厂商、动漫→话数/制作、手动项→分类扩展字段）
- 双计数：**API 调用次数**（消费端请求详情自增）、**点击次数**（内页埋点自增）
- 多条网盘下载：夸克 / 迅雷 / 光鸭 / 百度 / UC（`config/pan_types.php` 可升级包扩展）
- 可视化管理后台：6 类栏目 + 新增 / 编辑 / 删除 + 单条或全量重建静态页

**数据源适配器**
- TMDB（影视）、RAWG（游戏）、Bangumi（动漫）；新增来源只需加一个类 + 配置登记

**部署**
- 环境：宝塔面板 PHP 8.x + MySQL + Nginx
- 步骤见发布包内 `DEPLOY.md`

**数据库**
- 新库：导入 `sql/install.sql`
- 老库升级：依次导入 `sql/migrate_metrics.sql` → `migrate_meta.sql` → `migrate_downloads.sql`（数据不丢）
