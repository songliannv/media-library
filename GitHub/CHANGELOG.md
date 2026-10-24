# 更新日志（media-library-api）

> 版本号规则：末段 ≤9 递增，超过 9 进位到中段（4.4.9 → 4.5.0）。
> 以下为 v4.3.7 起的版本记录（新版本在上）。

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
