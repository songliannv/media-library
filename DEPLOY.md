# 媒资资料库 API · 宝塔部署指南

> 当前版本：**v4.5.1** · PHP 8.2+ · MariaDB 10.4+ / MySQL 5.7+ · 发布包为 AST 混淆源码，不是编码加密。
> 版本号规则：末段 ≤9 递增，超过 9 进位到中段（如 4.4.9 → 4.5.0）。

本项目是 PHP + MySQL/MariaDB + Nginx 的媒资资料库，当前有效数据源为 **TMDB** 与 **红果短剧（HongguoDuanju）**。后台使用管理员账号 + 密码登录；API 调用令牌仅用于外部接口调用，两者不混用。

## v4.5.x 能力速览（相对 v4.0.6 的主要变化）

- **图片代理改名 `pic.php`**（v4.3.7 引入，v4.5.0 前为 `img.php`）：TMDB 海报 / 背景图 / 演职员头像统一由服务器中转并落盘缓存。部分主机安全软件按文件名拦截 `img.php`，改名后绕开；旧地址由同名兼容壳转发。图片加载失败时前台自动回退直连 TMDB。
- **网盘自动转存**（v4.3.11）：后台「站点设置 → 添加网盘」给夸克 / UC 扫码登录 Cookie 后，条目里「别人的」分享会自动转存到你的网盘并改写为你的分享链接（详见第七节）。
- **演职员头像百度百科补齐**（v4.5.1）：短剧等来源拿不到头像时，按姓名查百科词条头图，服务器下载落盘，一次补齐永久使用。
- **后台右上角「清除缓存」**：一键清空标签 / 人物 / 接口级文件缓存与多级缓存。
- **已下线**：首页跳转 / 扫码拦截、前台水印；短剧 / 电视剧的季数、集数、出品方、地区、短剧时长等字段不再采集与展示。

## 一、前置条件

| 项目 | 要求 |
|---|---|
| PHP | 8.2 或更高版本（安装器硬校验） |
| 数据库 | MariaDB 10.4+ 或 MySQL 5.7+，需 `utf8mb4`、InnoDB |
| 数据库权限 | 账号需 `CREATE`、`ALTER` 权限 |
| PHP 扩展 | `pdo_mysql`、`curl`、`json`（必装）；建议 `mbstring`、`openssl` |
| Nginx | 1.20 或更高版本 |
| 目录权限 | `config/` 可写；站点根可写时可自动创建 `uisc/` |
| 数据源 | TMDB 需要 API Key；红果短剧免 Key |

发布包解压后直接是站点根目录，不需要再套一层项目目录。

## 二、全新机器：宝塔准备

### 1. 安装面板

非 root 账号（如阿里云 ECS 默认 `admin`）必须加 `sudo`：

```bash
curl -sSO https://download.bt.cn/install/installStable_12.sh;sudo bash installStable_12.sh
```

结束后会打印外网面板地址、`username`、`password`，**立刻保存**。

收尾卡住或忘记登录信息时，另开一个 SSH 窗口重新打印：

```bash
sudo /etc/init.d/bt default
```

### 2. 端口放行（两层都要）

- 阿里云控制台 → 安全组 → 入方向：`22`、`80`、`443`、面板端口
- 宝塔面板 → 安全 → 防火墙：`80`、`443`、面板端口

浏览器打不开面板，绝大多数情况是安全组没有放行面板端口。

### 3. 小内存机器先加交换分区

2GB 及以下的机器，先加 swap 再装数据库，否则数据库**编译**安装会被系统 OOM 杀掉：

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 4. 一键装 LNMP

软件商店 → LNMP → 一键安装：

| 组件 | 版本 |
|---|---|
| Nginx | 最新稳定版 |
| 数据库 | MariaDB 10.4.x，**务必选「极速安装」** |
| PHP | 8.2 |

### 5. 域名解析

域名 A 记录必须指向当前这台机器。换机器后要改解析并等待 DNS 生效。

## 三、全新安装

### 方式一：网页安装

1. 宝塔新建数据库，记录库名、用户名、密码。
2. 上传发布包并解压到站点根目录。
3. **修正文件属主**：

   ```bash
   sudo chown -R www:www /www/wwwroot/你的站点目录
   ```

   宝塔上传/解压出的文件属主是 `root`，PHP 进程（`www`）写不了 `config/`，安装会卡在「config/ 目录不可写」。
4. PHP 版本选 8.2+，启用 `pdo_mysql`、`curl`、`json`，建议启用 `mbstring`、`openssl`。
5. 配置 Nginx 伪静态（见第四节）。
6. 打开安装页，**先用 http**（SSL 未部署时 https 会直接连接失败）：

   ```
   http://你的域名/install.php
   ```

7. 按向导填写数据库信息、管理员账号、管理员密码、站点名称和网址。数据源默认 `tmdb`、`hongguoduanju`。
8. 点击开始安装。

安装器直接创建 8 张核心表：

- `media_items`
- `sync_log`
- `link_checks`
- `app_settings`
- `app_users`
- `app_requests`
- `app_api_tokens`
- `app_api_logs`

安装器同时生成：

- `config/config.php` — 数据库连接与运行配置
- `config/pan_types.php` — 网盘类型（可自行增删）
- `config/install.lock` — 安装锁
- `config/install-info.txt` — 安装备忘（含管理员账号）

写入安装锁后，`install.php` 会自动返回 404。**安装完成后删除 `config/install-info.txt`**。

### 方式二：命令行安装

在站点根目录执行（建议用宝塔的 PHP CLI）：

```bash
sudo php /www/wwwroot/你的站点/tools/install-cli.php
```

网页安装器和命令行安装器共同调用 `core/Installer.php`，建表结构和配置生成逻辑完全一致。全新安装不依赖历史升级包、历史迁移 SQL 或已删除脚本。

### 手动 SQL 安装

只有在安装器不可用或数据库账号没有建表权限时，才使用 `sql/install.sql`。该文件应与安装器和运行时自愈结构保持一致。手动安装后，将 `config/config.sample.php` 复制为 `config/config.php`，填写 `db` 段，再创建管理员账号。

## 四、Nginx 伪静态与目录保护

必须配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /config/   { return 404; }
location ^~ /sql/      { return 404; }
location ^~ /core/     { return 404; }
location ^~ /cron/     { return 404; }
location ^~ /adapters/ { return 404; }
location ^~ /tools/    { return 404; }

location ~* \.(sql|md|lock|bak|ini|log|env|sh|yml|yaml|conf|sample)$ {
    return 404;
}

location ~* ^/(setup|upgrade|update) {
    return 404;
}
```

不要在最后一条规则中拦截 `install.php`。未安装时必须允许 `/install.php` 显示安装向导；安装完成后由安装锁和程序守卫返回 404。

## 五、升级已有站点

v4.5.x 的升级包采用全量覆盖方式。上传并覆盖时保留：

- `config/config.php`
- `config/install.lock`
- `config/pan_types.php`（如果自行添加过网盘类型）
- `uisc/`（已经生成的静态页）

覆盖后同样执行 `sudo chown -R www:www /www/wwwroot/你的站点目录`。

发布包和升级包均不包含运行时配置、安装锁、`preview/`、历史迁移 SQL、诊断脚本和说明文档。升级不需要手工执行历史迁移 SQL；运行时的 `ensureTables()` / `syncColumns()` 会为旧表补齐必要列。

## 六、后台与 API

后台地址：`/admin`。登录方式是安装时创建的管理员账号 + 密码。不要使用旧版后台令牌登录，也不要把 API Token 当作后台密码。

API 调用令牌在后台「API 调用」页面创建，外部调用时通过 `X-Api-Token` 请求头或 `token` 参数传入。API Token 只负责外部调用限额、IP 白名单和调用日志。

## 七、同步、静态页与网盘自动转存

当前同步源：

- TMDB：电影、电视剧、综艺等影视数据。
- 红果短剧：国产短剧数据。

只有状态为 `active` 的正式条目进入前台和静态页；本地导入或搜索得到但尚未刮削成功的条目保持 `pending`，进入条目暂存。

### 网盘自动转存（夸克 / UC）

1. 后台「站点设置 → 添加网盘」：对夸克（或 UC）点「📱 扫码登录」回填 Cookie；可选填「默认转存目录」（如 `/资源/电影`，目录不存在会自动逐级创建）。
2. 之后三条通道自动干活：采集入库后约 5 分钟自动转存新条目；条目管理工具栏「转存网盘链接」按钮手动小批量处理；大批量挂宝塔计划任务：

   ```bash
   php /www/wwwroot/你的站点/cron/pan_transfer.php --limit=20
   ```

3. 转存 = 把别人的分享存进你的网盘 → 创建你自己的永久分享 → 条目下载地址改写为你的链接；已转存过的链接记录在 `pan_transfers` 表，不会重复处理。百度 / 迅雷 / 光鸭等未开放接口的网盘保持原链接直跳。

### 图片代理与演职员头像

- TMDB 图片统一走 `/pic.php?p=…`（服务器中转 + 30 天落盘缓存）。**如站点装有安全软件（WAF），请把 `pic.php` 加入放行名单**；旧地址 `/img.php` 由同名兼容壳转发。
- 演职员头像缺失时，生成静态页会自动按姓名到百度百科补图并落盘到 `uisc-assets/people/`。**缺失较多时首次生成静态页会变慢**（每人最多数秒），补齐一轮后恢复正常速度。

可选计划任务：

```bash
php /www/wwwroot/你的站点/cron/sync_hourly.php --quiet
php /www/wwwroot/你的站点/cron/sync_refresh.php --quiet
php /www/wwwroot/你的站点/cron/build_static.php --quiet
php /www/wwwroot/你的站点/cron/link_check.php --quiet
php /www/wwwroot/你的站点/cron/api_logs_prune.php --days=90
php /www/wwwroot/你的站点/cron/rate_prune.php
php /www/wwwroot/你的站点/cron/pan_transfer.php --limit=20   # 网盘自动转存（配了夸克/UC Cookie 后建议每 10 分钟）
```

这些脚本只能通过 CLI 执行，浏览器访问会被拒绝。已删除的慢匹配旧计划任务不应重新添加。改了数据但页面数字不对时，后台右上角「清除缓存」一键重建。

## 八、安装后验收

1. 首页 `http://你的域名/` 正常显示，不再是 `config.php missing` 的 JSON 报错。
2. 访问 `install.php` 返回 404（安装锁生效）。
3. 登录 `/admin`，管理员账号可进入后台。
4. 后台「系统自检」页各项通过。
5. `config/install-info.txt` 已删除。

离线自检页 `check.php` **不在发布包内**。需要时单独上传到站点根目录，查完立即删除，不要长期留在正式站点上。

## 九、常见问题

### 浏览器报「网络连接异常 / 网站失去响应」，但 http 地址能打开

站点还没部署 SSL，或者 `443` 没有放行。先用 http 完成安装，装完再用宝塔部署 SSL 证书并在安全组、面板防火墙两处放行 `443`。

### 安装卡在「config/ 目录不可写」

```bash
sudo chown -R www:www /www/wwwroot/你的站点目录
```

### 宝塔安装数据库失败，提示编译进程被杀掉

内存不足。先加 2GB 交换分区（见第二节第 3 步），数据库改选「极速安装」，版本用 MariaDB 10.4.x。

### 浏览器打不开宝塔面板

安全组放行面板端口，并在面板「安全 → 防火墙」里再放行一次。

### 首页正常但 `/admin` 或 API 404

通常是 Nginx 缺少 `try_files`，或伪静态规则未保存。先确认动态请求是否转到 `index.php`。

### 未安装访问 `/install.php` 仍是 404

检查 Nginx 是否仍有 `^/(install|setup|upgrade|update)` 规则。应只拦 `setup|upgrade|update`，不能在 Nginx 层拦安装器。

### 后台登录后反复回到登录页

检查 PHP-FPM 的会话目录权限、反向代理 HTTPS 识别和 Cookie；项目统一使用 `core/Session.php` 的 `ml_session_boot()`。

### 未安装时访问 `/` 返回 500 和 `{"error":"config.php missing"}`

属正常现象，未安装时动态入口没有配置可读。走完安装即消失。

### 升级是否需要导入旧 SQL

不需要。v4.5.x 的全新安装由 `core/Installer.php` 一次创建完整结构；旧站点由运行时自愈补齐列。只有数据库账号没有建表或 ALTER 权限时，才需由管理员根据当前 `sql/install.sql` 手工处理。
