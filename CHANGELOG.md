# 版本记录 · media-library-api

## v2.1.3（2026-09-24）

- **新增：TMDB ⇋ 短剧聚合 跨源联动**（`core/CrossLink.php`）
  - 短剧侧按标题三级匹配（exact / start / contain）实时查 TMDB，用 TMDB 的
    rating / vote_count / genres / overview / poster 填空，`extra.tmdb_links` 记关联
  - TMDB 侧在本地搜短剧条目，把短剧的 play_count / episodes / duration /
    record_number 挂到 `extra`，**不改 TMDB 主字段**（保持 TMDB 权威数据）
  - 匹配门槛：`min_rating=6.0`、`MIN_LEN=4`（避免"妈妈"过度匹配）、TMDB 侧
    0.3 秒节流防限流；标题归一化包含繁→简映射
  - 触发：同步后自动跑 or `POST /api/v1/admin/crosslink/run`（支持 ids/limit/force）；
    查询：`GET /api/v1/admin/crosslink/cfg`（含最近 30 条联动明细）
  - 配置：`crosslink.enable` / `auto_after_sync` / `batch_size` /
    `min_rating` / `match_level` / `dry_run`

## v2.1.2（2026-09-24）

- **短剧聚合适配器重写**（`adapters/HongguoDuanju.php`）
  - 老代码用 `?act=recommend/search/new` 参数，上游 orz.icicic.icu 根本不认，
    每个请求都超时——同步跑 0 条是真实的
  - 全部换成官方参数：`type=days_7_to_date`（3 个 action）/
    `type=rank`（3 个 selected_items）/ `type=search&name=X` / `type=bookid`
  - rank 返回 `{cell_data:[{video_data:[…]}]}` 嵌套结构，新增 `flattenCells()` 抽平
  - duration 兼容双形态：`"1小时28分钟"` 与 `8285` 秒数
  - 分类白名单 60+ 主流分类 + `^[\x{4e00}-\x{9fa5}]{2,4}$` 中文兜底过滤上游乱码
  - source_id 三级兜底：`book_id → vid → series_id`
  - `detail()` 语义改正：上游 `type=bookid` 返回的是分集列表（不是书籍元数据），
    detail 只填 `extra.episodes`，不覆盖其它字段
- `core/Sync.php` 的 `endpointsFor('hongguoduanju')` 从 2 端点扩到 5 端点

## v2.1.1（2026-09-24）

- **TMDB 二次判定增强**（`adapters/Tmdb.php`）
  - `append_to_response` 扩到 `credits,images,videos,release_dates,recommendations`
  - 中文 overview 为空时自动降级 `language=en-US` 拉一次兜底
  - `normalize` 用 `primary_release_year` / `first_air_date_year` 优先，
    中英文分类映射（Action→动作、Drama→剧情…），从 credits/videos/images
    提取演员表 / 预告片 / 剧照写入 `extra.cast/trailer/videos/stills`
  - `detail` 权威合并策略：字段无条件覆盖 base（不再用 `!empty` 保留 0 值），
    poster 保底用 base，genres 合并去重
  - `search` 新增第 4 参数 `$year`（可选，向后兼容），支持
    `primary_release_year` / `first_air_date_year` 过滤

## v2.1.0（2026-09-24）

- **修复：同步面板卡在"正在加载..."**（`admin/index.php`）
  - `loadSyncPlan()` 失败时把错误同步塞进全部 4 个盒子（syncParams / syncPlan /
    cronBox / syncRecent），不再只塞 syncParams 就 return
  - 成功路径末尾补调 `loadSyncRecent()`
  - `loadSyncRecent()` 在 `!SYNC_PLAN` 时明确输出友好提示，不再静默卡死

## v2.0.9（2026-09-24）

- **修复：采集失败 —— PHP Warning $a1 Undefined Variable**
  - `core/Views.php` 的 `renderItemPage()` 引用了 `$__showAdminLink`，
    该变量只在 `homeHtml()` 里定义过一次；PHP 函数作用域内不共享，
    混淆后变量名被重命名为 `$a1` 之类的短名，触发警告文本漏到静态页
  - 补上定义：`$__showAdminLink = (bool) Config::sub('admin', 'show_link', 1);`

## v2.0.8（2026-09-24）

- 前台顶部导航栏搜索框改为**绝对定位真居中**
  - 从 flex + `margin:0 auto` 改为 `position:absolute;left:50%;top:50%;
    transform:translate(-50%,-50%)`，居中在整个视口而不是两侧项目之间
  - `.brand` 和 `.admin` 加 `flex-shrink:0`，`.search` 加 `max-width:38%`

## v2.0.7（2026-09-24）

- **修复：后台采集同步页 tab-logs 结构 bug**
  - 第 783 行 `<div ` 标签残缺（无属性、无收尾 `>`）吞掉了后面所有 tab
  - 第 815 行 `tab-users` 缺 `<div ` 前缀，把 id/class 当字面文本渲染
  - 修复后 div 标签 124/124 平衡
- 顶栏 sub 单行显示 + 字号 13.5px（避免换行）

## v2.0.6 ~ v2.0.0（2026-09-24）

- 详见 `CHANGELOG.md` 归档版本

---

以下为更早期归档版本（v1.8.x / v1.9.x）：

## v1.8.9（2026-09-23）

- 新增书籍/音乐类型（book/music），扩展分类系统
- 后台新增「用户中心」tab，支持用户管理（列表/启用禁用/编辑/删除）
- 新增图片本地下载功能：新增条目时可勾选自动下载海报/背景图到服务器
- 创建 uploads/ 目录及 .htaccess 安全配置
- 提供 __migrate_189.php 数据库迁移脚本

## v1.8.8（2026-09-23）

- **新增：首页首屏「热门标签云」（替换原 banner）**
  - **数据**：聚合 `media_items.genres`，按「该标签下所有条目的 `clicks` 之和」降序，取前 **50** 个；
    新增 `Core\CacheStore::hotTags($limit)` 与 `cmpHotTag()` 比较器（PHP 5.6 兼容，不用飞船运算符）。
  - **交互**：默认每个标签在自己的位置附近缓慢漂浮（每 2.8s 重新抖动一次坐标 + 2.8s 过渡 = 持续游动）；
    **鼠标移入整块区域 → 停止漂浮、按热度（DOM 顺序）排成整齐标签墙**，移出恢复漂浮；
    触屏设备没有 hover，改为点空白处切换（点标签本身仍然跳转）。
  - **点击标签 → `/?tag=<标签>`**：`listBySort()` / `countItems()` 新增 `$tag` 参数，
    用 `genres LIKE '"标签"'`（带引号）精确匹配 JSON 数组元素，避免子串误命中；
    新增 `likeEsc()` 转义 LIKE 通配符。列表页顶部显示「当前标签 + 清除标签」，
    排序 / 分类 / 翻页链接都会带上 `&tag=`，切换筛选不会丢标签条件。
  - **视觉**：字号按热度分三档（前 8 名 17.5px / 9~22 名 15px / 其余 13.5px）；
    布局用「行装箱 + 随机抖动」（间距 12px、抖动 ±6/±5px），保证任意两个标签不重叠、不越出容器；
    舞台高度由 JS 实测行数后写死，手机端只显示前 **24** 个（50 个会把首屏占满）。
  - **保留**：原 `h1` 以 `.sr-only` 形式保留（不破坏 SEO 与页面语义）；
    列表页 / 搜索页 / 标签页 / 翻页**仍显示原来的标题 + 统计徽章**，只有真首页换成标签云。
  - 系统「减少动态效果」（`prefers-reduced-motion`）下不漂浮，直接显示排列好的静态标签墙。
- **修复（顺带）：「最新更新」列表里混进了一段 CSS 源码**
  - `homeHtml()` 拼接 `$latestHtml` 时误把 `.adm-btn` 的 4 行 CSS 写进了字符串里，
    开启「最新更新」后会把这段 CSS 当纯文本显示在页面上；现已移回 `css()`。
- **影响面**：前端视图 + 查询参数，**无数据库结构变更、无路由变更、无需改 Nginx**；
  条目数据、已保存的设置、外部 API 令牌调用全部不受影响（`/api/v1/*` 未改动）。

---

## v1.8.7（2026-09-23）

- **修复：后台勾选「启用哪些数据源」保存后，仍提示「请至少启用一个数据源（TMDB / RAWG / Bangumi）」**
  - **根因**：前端把勾选结果作为 JSON **数组**提交（`adapters: ['tmdb','rawg','bangumi']`），
    而 `Core\Settings::strToList()` 只做了一次 `(string) $s` 强转 —— PHP 把数组转成字面量
    `"Array"`，于是白名单过滤后为空，校验必然失败。**与账号 / 密码 / 数据源本身都无关。**
  - 修法：`strToList()` 增加数组分支（先经 `listToStr()` 归一化），并保持对逗号字符串的兼容。
- **修复（同类）：站点设置里的多选字段保存后被静默清空**
  - `nav_show`（顶部导航显示）、`pan_group`（要管理哪些网盘）、`pansou_types`（启用哪些网盘类型）
    三个多选字段，保存时落到通用分支的 `is_array() ? '' : …` 判断上 → 被当成空字符串，
    而 `Settings::set()` 里 `''` 表示「清除覆盖」→ 勾选被丢弃、静默退回 `config.php` 默认值。
  - 修法：保存时按 `schema()` 的 `type === 'list'` 命中列表型字段，改走 `listToStr()` 落库。
- **修复（连带）：站点设置各页不回显当前值**
  - `settingsView()` 此前只构建了 API Key / 数据源 / 网盘类型 / 采集 / 邮件这几组的 items，
    `site` / `seo` / `redirect` / `pan` / `pansou` / `req` 各组从未构建 → 前端拿不到「当前值」，
    表现为输入框打开即空白、勾选框永远不勾；保存时还会把没动过的字段一起写成空值（= 清掉覆盖）。
  - 修法：按 `Core\Settings::groupMaps()` 统一补齐每组 items（列表型给出数组形态的 value），
    `sync` / `mail` 两组保留原有带 `default` 兜底的实现，不覆盖。
- **影响面**：纯后端逻辑与视图数据，**无数据库结构变更、无路由变更、无需改 Nginx**；
  已保存过的 `app_settings` 行不受影响（本次只是让它们能被正确读出与写回）。

---

## v1.8.6（2026-09-23）

- **修复：后台登录「输入账号密码没反应 / 提交后又跳回登录页」**
  - `admin/index.php` 本文件就是后台登录页（合法入口），此前若 Nginx 把 `/admin/` 直接落到该文件，
    会被文件内的 `ml_guard_shield(__FILE__)` 误判为「内部文件」而返回 404；现已在本文件顶部补
    `ML_APP` / `ML_ROOT` 定义，两条访问路径（经根 index.php 分发、直接定位到本文件）都能正常打开；
  - 新增 `core/Session.php` 统一会话引导，`admin/index.php` 与 `User::boot()` 共用：
    · `session.save_path` 目录不存在 / 不可写（宝塔上该目录常属 root，php-fpm 属 www）时，
      自动改用系统临时目录下的 `ml_sessions`，失败还会再换一次；
    · 反代（宝塔 / CDN）下 `$_SERVER['HTTPS']` 为空时，按 `X-Forwarded-Proto` 判定真实协议，
      避免 cookie 被标成 `Secure` 后浏览器直接丢弃 → 每次请求都是新会话；
    · 会话真的不可用时，登录页会明确提示原因，不再静默失败；
  - 登录蜜罐字段由 `website` 改为 `ml_hp`：`website` 是浏览器 / 密码管理器的高频自动填充字段，
    被填上就会命中「检测到异常提交，已忽略」，表现出来正是「点了登录没反应」；
  - 登录提交增加容错：即使 `ad_do` 等隐藏字段被代理 / 扩展吃掉，只要带了账号密码仍按登录处理；
  - `check.php` 的「会话（登录状态）」一栏同步显示是否已自动改用可写目录。
- **新增：随包自救 / 排障工具（排障用完请删）**
  - `__diag.php`：一键体检 —— 会话是否存得住（含往返测试）、入口分发、数据库账号（状态 / 锁定 /
    散列长度）、给定账号密码的校验结果与管理员判定，直接给出结论与对应处理；
  - `__emergency_reset.php`：由「只改密码」升级为「**改密码 + 解除锁定 + 账号状态复位**」，
    支持用**账号或邮箱**匹配，并且**成功后自动删除自身**，避免留下后门；
  - `core/Guard.php` 守卫清单与 `check.php` 必查文件加入 `core/Session.php`。
- **升级提示**：本版无数据库结构变更；覆盖升级后请 `Ctrl+F5` 强刷后台登录页。

---

## v1.8.5（2026-09-23）

- **紧急修复：管理员密码重置工具确认随包发出**
  - v1.8.4 整包已含 `__emergency_reset.php`，本次重新打包确认无误；
  - 修复 build 脚本漏发问题，把诊断/维护类 PHP 加进 must 集合。

- **修复：版本号从 v1.8.4 升到 v1.8.5**

---

## v1.8.4（2026-09-23）

- **紧急修复：管理员密码重置脚本 + 诊断工具随包发出**
  - 用户在 v1.8.3 全新安装后「无法登录」后台；
  - 新增 `__emergency_reset.php`（一次性密码重置脚本）：用邮箱+新密码直接改 app_users.pass_hash，跑完删掉；
  - 把这类工具加进 build 脚本的 must/expect 集合，防止下次漏发。

---

## v1.8.3（2026-09-23）

- **修复：MySQL 5.6 全新安装建表报 1089**
- **调整：注册账号一律用邮箱**

---

## v1.8.2（2026-09-23）

- **邮件服务（SMTP）+ 密码找回 / 修改（全新功能）**

---

## v1.8.1（2026-09-23）

- **后台数据源测试修复**

---

## v1.8.0（2026-09-23）

- 后台登录改为账号 + 密码
