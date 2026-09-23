# 媒资资料库 media-library-api · 增量升级包

> **v1.6.2 → v1.6.3** · 2026-09-22 · 增量包（只含变化文件，覆盖上传即可，无需重装）

## 本次变化（共 13 个文件：修改 9，新增 4，删除 0）

| 文件 | 变化 | MD5 |
|---|---|---|
| `.htaccess` | 修改 | `eebb3063c115f3832ad674b924f3e096` |
| `DEPLOY.md` | 修改 | `354d2de8cc895849466cf00a2674b665` |
| `admin/index.php` | 修改 | `a4b211086ad90537470cf993319f1eb5` |
| `check.php` | 修改 | `cb00c302a9d4273c2112ac5ff91a568c` |
| `config/config.sample.php` | 修改 | `2a1c9fccd4b5722a1d8b4650df3ca44c` |
| `core/Guard.php` | 修改 | `b7916ccfb7466530dd6837d2dca53d4d` |
| `index.php` | 修改 | `8ac2bbea1f696a992928e876bcb2d71f` |
| `sql/install.sql` | 修改 | `b5e0a2bb8416168d49869eafb8b99af3` |
| `tools/install-cli.php` | 修改 | `91aed662c4b7205da68530037c05fbf0` |
| `core/Installer.php` | 新增 | `2f5a237958ed18c7d06059aa6012edfb` |
| `install.php` | 新增 | `3c711bf003c365cd79a1b03aa47cee49` |
| `功能说明.txt` | 新增 | `ea3181793170ae6cedb5d67d9635d301` |
| `安装说明.txt` | 新增 | `2e8fb86ac4db07d81b767e3b4f79c563` |

## 升级步骤

1. **先备份**：把站点目录整体留一份（至少备份 `config/config.php` 与数据库）。
2. 把本包内文件按相同路径**覆盖上传**到站点根目录，保持目录结构不变。
3. 清理 opcache 或重启一次 PHP（宝塔 → 软件商店 → PHP → 重启）。
4. 前台与后台都强刷一次（`Ctrl+F5`，手机端清一下缓存）。
5. 打开 `check.php`，看 **第 8 组「安全防护」** 里的「网页安装器 install.php」应为
   **已防护**（存在 + 已写入 install.lock，访问返回 404）；再直接访问 `/install.php`
   应返回 **404**（这是本次升级后的正确行为）。

## 不需要做的事

- ❌ **不需要手工执行任何 SQL**。本次 `sql/install.sql` 只是补齐 `app_users` / `app_requests`
  两张表的建表语句（供「手动安装」路径使用）；已在跑的站点这两张表由程序自动创建，无表结构变更。
- ❌ 不需要重新安装，也不需要动 `config/config.php`。
- ❌ 不会影响已保存的后台设置（设置存在数据库 `app_settings` 表，不在文件里）。
- ⚠️ **建议顺手改一处伪静态**（不影响已装好的站点，但建议改）：
  把 `location ~* ^/(install|setup|upgrade|update) { return 404; }`
  改成 `location ~* ^/(setup|upgrade|update) { return 404; }`。
  原因：**故意去掉 install** —— 网页安装器 `install.php` 由它自身判定（已安装即 404），
  若在 Nginx 层把它拦死，将来重装或迁移时安装向导就永远打不开了。

## 本次改了什么

**新增 · 网页安装器（带自锁）**
- 新增 `install.php`：浏览器打开即安装向导 —— **环境自检 → 填数据库/站点信息 → 一键安装**，
  自动建表、生成 `config/config.php`、写 `config/install.lock`，成功页给出后台令牌 / 后台地址 / 自检地址。
  **仅在"尚未安装"时可用；一旦已安装（install.lock 存在，或 config.php 的 db 已填真值），
  它对所有人返回 404，与文件不存在完全一样**，没人能借此重装站点。
  安全措施：只接受 POST + 会话 CSRF（恒时比较）+ 蜜罐字段 + 失败节流（20 次后等 10 分钟）+ 安装前二次确认。
- 新增 `core/Installer.php`：安装内核，`install.php` 与 `tools/install-cli.php` **共用同一份**
  环境自检 / 建表 SQL / 配置生成 / 写锁逻辑，从此不会出现两份实现不一致。

**修改 · 守卫与路由**
- `core/Guard.php`：安装路径黑洞**精确放行 `/install.php`**（交其自身判定），
  其余 `setup` / `upgrade` / `update` / `install.php.bak` 仍一律 404；
  防护状态新增 `install_present` / `install_locked` / `install_active` 三个字段。
- `.htaccess`：Apache 环境同样放行 `install.php`，其余安装类路径照旧 404。
- `index.php`：首页「站点尚未安装」引导页新增 **「🚀 开始网页安装」** 主按钮，
  文案由"安装器已移除"改为"安装器带自锁"。

**修改 · 自检与后台**
- `check.php`：安装状态提示改为"两种装法"；第 8 组安装器判据改为**三态**
  （未上传 / 已自锁 / 尚未安装因而可用）；`/install.php` 可达性探测在未安装时自动跳过。
- `admin/index.php`：「安全防护」页同步为三态显示。

**修改 · 命令行安装器**
- `tools/install-cli.php`：原先内嵌的建表 SQL 与配置生成函数**全部删掉改为委托**给 `core/Installer.php`
  （文件从 20.8 KB 降到 12.2 KB）；安装备忘从站点根 `install-info.txt` 移到
  **`config/install-info.txt`**（该目录公网 404，避免令牌裸放公网）。

**修改 · 文档**
- `DEPLOY.md`：第二节重写为「方式一：网页安装 / 方式二：命令行安装」；
  FAQ 新增"网页安装器安全吗？会不会被人重装我的站点？"与
  "`https://` 打开是 502 Bad Gateway，但 `http://` 正常？"（443 段 PHP 转发排查四步）。
- 新增随包发布的两份说明书：**`安装说明.txt`**（一步步装站）与 **`功能说明.txt`**（功能清单）。

## 回滚方法

把备份的对应文件覆盖回来即可。
回滚后即使站点根还留着 `install.php`，旧版 `core/Guard.php` 会把 `/install.php` 一律 404，
**不会产生安全问题**。若想更彻底，直接删掉站点根的 `install.php`。

## 小提示

- 升级后访问首页（`/`）会多出一个「🚀 开始网页安装」按钮（**只在未安装时显示**）；
  点它若仍是 404，就是伪静态里那条 `^/(install|...)` 还没去掉 `install`。
- 还没安装的站点现在可以：浏览器打开 `https://你的域名/install.php`，两分钟装好。
- 装好后访问 `/install.php` 返回 404 是**正常现象**，说明自锁生效。
- 不打算留安装器：删掉站点根的 `install.php` 即可，功能不受任何影响。
- 想看长什么样：源码里的 `preview/install.html`（安装向导）与
  `preview/notinstalled.html`（未安装时的首页引导页），都是纯静态预览。
