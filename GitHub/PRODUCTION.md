# 万能资料库 · 生产环境部署指南

> **版本**: v4.5.1  
> **适用**: 2C2G 服务器 / 生产环境  
> **更新时间**: 2026-10-02  
> **基线要求**: PHP 8.2+ / MySQL 8.0+ 或 MariaDB 10.4+ / Nginx 1.20+
>
> **数据库选型**: 2C2G 小内存机器**推荐 MariaDB 10.4**（更省内存、更稳定）；MySQL 8.0 / 8.4 性能上限更高但内存占用更重，建议大规格机器再考虑。

---

## v4.5.x 升级要点（2026-10-02）

- **必须重新生成静态页**：详情页模板与图片地址已改（/pic.php），覆盖升级后在后台点「生成静态页」。
- **网盘自动转存**：后台「站点设置 → 添加网盘」配置夸克/UC Cookie（可扫码登录）；建议宝塔计划任务加：php 站点目录/cron/pan_transfer.php --limit=20（每 10 分钟）。
- **图片代理**：主端点为 /pic.php（原 img.php 被部分主机安全规则按文件名拦截）；如装 WAF 请放行 pic.php；img.php 为兼容壳。
- **演职员头像**：生成静态页时自动按姓名到百度百科补图，首次较慢，之后走本地缓存。
- **清除缓存**：后台右上角一键清空全部缓存。

## 目录

1. [服务器配置推荐](#1-服务器配置推荐)
2. [系统环境安装](#2-系统环境安装)
3. [PHP 8.2 配置](#3-php-82-配置)
4. [数据库配置（MySQL 8.0/8.4 与 MariaDB 10.4）](#4-数据库配置mysql-80--84-与-mariadb-104)
5. [Nginx 配置](#5-nginx-配置)
6. [Redis 配置](#6-redis-配置)
7. [PanSou 接口配置](#7-pansou-接口配置)
8. [性能调优](#8-性能调优)
9. [安全加固](#9-安全加固)
10. [监控与日志](#10-监控与日志)
11. [常见问题排查](#11-常见问题排查)
12. [完整部署脚本](#12-完整部署脚本)

---

## 1. 服务器配置推荐

### 1.1 配置分级

| 级别 | CPU | 内存 | 带宽 | 适用场景 | 并发用户 | 日PV |
|---|---|---|---|---|---|---|
| **入门** | 2核 | 2GB | 100M | 个人站/小站 | 50-100 | 10-50万 |
| **标准** | 4核 | 8GB | 200M | 中型站 | 200-500 | 50-200万 |
| **进阶** | 8核 | 16GB | 500M | 大型站 | 500-1000 | 200-500万 |
| **旗舰** | 16核+ | 32GB+ | 1G+ | 超大型站 | 1000+ | 500万+ |

### 1.2 你的配置（2C2G200M）

```
✅ 2核 CPU     → PHP-FPM 12 workers（保守值，按 40MB/进程估）
✅ 2GB RAM     → 数据库 buffer_pool=256M（MariaDB 10.4 或 MySQL 8.x）, PHP memory_limit=256M
✅ 200M 带宽   → 支持 50 并发，单文件上传限制 100M
✅ 数据库选型   → 2C2G 首选 MariaDB 10.4；MySQL 8.0/8.4 亦可但需压内存参数
```

**内存分配参考：**
```
数据库 Buffer Pool:   256MB (13%)     （MySQL / MariaDB 通用）
PHP-FPM (12 workers): 480MB (24%)
Redis:                128MB (6%)
OS + 系统:            500MB (25%)
其他缓冲:             636MB (32%)
─────────────────────────────
总计:                 2000MB (100%)
```

### 1.3 带宽评估

| 场景 | 带宽占用 | 状态 |
|---|---|---|
| 单用户浏览 | 0.5-2Mbps | ✅ 充裕 |
| 10 并发用户 | 5-10Mbps | ✅ 充裕 |
| 50 并发用户 | 25-50Mbps | ✅ 充裕 |
| 大文件上传 | 200Mbps（满载） | ⚠️ 单用户占满 |
| **建议** | **限制并发上传** | **Nginx 限制 5 个并发上传** |

---

## 2. 系统环境安装

### 2.1 操作系统

**推荐**: CentOS 7.9 / Ubuntu 22.04 / Debian 12

```bash
# 检查系统版本
cat /etc/os-release
uname -r
```

### 2.2 宝塔面板安装

```bash
# CentOS
yum install -y wget && wget -O install.sh http://download.bt.cn/install/install_6.0.sh && sh install.sh

# Ubuntu/Debian
wget -O install.sh http://download.bt.cn/install/install-ubuntu_6.0.sh && bash install.sh
```

### 2.3 安装组件（宝塔 → 软件商店）

| 组件 | 版本 | 说明 |
|---|---|---|
| Nginx | 1.24+ | Web 服务器 |
| PHP | 8.2.28+ | 脚本语言 |
| MySQL / MariaDB | 8.0 / 8.4 或 MariaDB 10.4 | 数据库 |
| Redis | 7.x | 缓存 |
| Fail2Ban | 最新 | 防暴力破解 |

---

## 3. PHP 8.2 配置

### 3.1 扩展安装

```bash
# 必装扩展
pecl install redis
pecl install apcu
echo "extension=redis" >> /www/server/php/8.2/etc/php.ini
echo "extension=apcu" >> /www/server/php/8.2/etc/php.ini

# 验证
php -m | grep -E "curl|redis|apcu|imagick|pdo_mysql|opcache"
```

**扩展清单：**

| 扩展 | 状态 | 用途 |
|---|---|---|
| `pdo_mysql` | ✅ 内置 | 数据库连接 |
| `curl` | ✅ 内置 | 外部 API 调用 |
| `mbstring` | ✅ 内置 | 多字节字符串 |
| `openssl` | ✅ 内置 | HTTPS 加密 |
| `json` | ✅ 内置 | JSON 处理 |
| `fileinfo` | ✅ 内置 | 文件类型检测 |
| `tokenizer` | ✅ 内置 | 代码解析 |
| `redis` | ⚠️ 需安装 | Redis 缓存 |
| `apcu` | ⚠️ 需安装 | APCu 用户级缓存 |
| `imagick` | ⚠️ 可选 | 图片处理 |
| `xdebug` | ❌ 禁用 | 调试用，生产必须禁用 |

### 3.2 PHP.ini 配置

```ini
; /www/server/php/8.2/etc/php.ini

; ===== 性能 =====
opcache.enable = 1
opcache.memory_consumption = 128        ; 2GB 内存给 128MB
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0         ; 生产环境关闭文件变更检测
opcache.revalidate_freq = 0

; ===== 内存 =====
memory_limit = 256M
max_execution_time = 30
max_input_time = 60

; ===== 安全 =====
display_errors = Off
log_errors = On
error_log = /www/wwwroot/zyfx.wang/log/php_error.log
expose_php = Off
max_execution_time = 30

; ===== 上传 =====
upload_max_filesize = 100M
post_max_size = 120M
max_file_uploads = 20

; ===== 时区 =====
date.timezone = Asia/Shanghai

; ===== 扩展 =====
extension=pdo_mysql
extension=curl
extension=redis
extension=apcu
; extension=xdebug    ; 禁用！
```

### 3.3 PHP-FPM 配置（2C2G 关键）

```ini
; /www/server/php/8.2/etc/php-fpm.d/www.conf

pm = dynamic
pm.max_children = 12          ; 2GB 内存，按 ~40MB/worker 估算，留余量取 12
pm.start_servers = 4
pm.min_spare_servers = 3
pm.max_spare_servers = 6
pm.max_requests = 500         ; 防内存泄漏，定期回收进程
pm.status_path = /status      ; FPM 状态监控
pm.ping.path = /ping          ; 健康检查
```

### 3.4 应用配置（config/config.php）

```php
<?php
return [
    // 数据库
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'zyfx_wang',
        'pass'    => '你的数据库密码',
        'dbname'  => 'zyfx_wang',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // PDO 连接池（PHP-FPM 场景）
    'pdo' => [
        'persistent' => true,       // 启用持久连接（免 TCP 握手）
        'statement_cache' => 128,   // 语句缓存上限
    ],

    // Redis 缓存
    'cache' => [
        'driver' => 'redis',        // 或 'memcached'、'apcu'、'file'
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 2,
            'prefix' => 'ml_',
        ],
        'apcu' => true,             // 开启 APCu 作为备选缓存
        'file_ttl' => 3600,        // 文件缓存 TTL（秒）
    ],

    // PanSou 网盘搜索
    'pansou' => [
        'url'  => 'http://127.0.0.1:8888',
        'types' => ['115', '123', '百度', '阿里', '夸克', 'UC'],
        'auto_enrich' => 1,        // 采集后自动补地址
        'cache_ttl' => 720,        // 缓存 12 小时
    ],

    // API 配置
    'api' => [
        'timeout' => 30,           // 上游请求超时（秒）
        'retry' => 3,              // 重试次数
    ],
];
```

---

## 4. 数据库配置（MySQL 8.0 / 8.4 与 MariaDB 10.4）

> 本项目使用 PDO + utf8mb4，MySQL 8.0 / 8.4 与 MariaDB 10.4 三者均可直接运行，无需改任何代码。
> **2C2G 推荐 MariaDB 10.4**；大规格机器可选 MySQL 8.0 / 8.4。

### 4.1 数据库选型建议

| 数据库 | 2C2G 适配 | 内存压力 | 说明 |
|---|---|---|---|
| **MariaDB 10.4** | ✅ 首选 | 低 | 最省内存、最稳，小机器首推 |
| MariaDB 10.5+ | ⚠️ 可用 | 中 | 功能更新但稳定性略逊 10.4 |
| MySQL 8.0 | ⚠️ 谨慎 | 高 | 性能强但内存占用重，需压内存参数 |
| MySQL 8.4 | ❌ 不建议 | 高 | 偏新，小机器压测风险高 |
| MySQL 5.5 / 5.6 / 5.7 | ❌ 不推荐 | — | 已超出本项目基线 |

**选择结论**：2核2G / 200M 带宽服务器，直接装 **MariaDB 10.4** 最省心；只有当你已确定用 MySQL 8.x 生态或机器规格 ≥ 4C8G 时，再选 MySQL 8.0 / 8.4。

### 4.2 MySQL 8.0 / 8.4 的 my.cnf

> 8.0 与 8.4 共用同一份配置；8.4 默认 `caching_sha2_password` 认证，PDO 原生支持，无需额外处理。

```ini
; /www/server/mysql/etc/my.cnf

[mysqld]
# 字符集
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
init-connect='SET NAMES utf8mb4'

# InnoDB 缓冲池（2GB 内存给 256MB）
innodb_buffer_pool_size = 256M
innodb_buffer_pool_instances = 1
innodb_log_file_size = 64M
innodb_log_buffer_size = 16M

# 性能优化
innodb_flush_log_at_trx_commit = 2     # 性能优先（允许丢失 1 秒事务）
innodb_flush_neighbors = 0             # SSD 优化
innodb_io_capacity = 200               # SSD IOPS
innodb_read_io_capacity = 200

# 连接数
max_connections = 200
thread_cache_size = 16
table_open_cache = 1024

# 慢查询日志
slow_query_log = 1
slow_query_log_file = /www/server/mysql/log/slow-query.log
long_query_time = 2

# 日志
log_error = /www/server/mysql/log/error.log
log-bin = /www/server/mysql/log/mysql-bin
binlog_expire_logs_seconds = 604800    # 7 天

# MySQL 8.x 内存节省（小机器建议关闭，省 50~100MB）
performance_schema = OFF

[mysql]
default-character-set = utf8mb4

[client]
default-character-set = utf8mb4
```

### 4.3 MariaDB 10.4 的 my.cnf

> 与 MySQL 8.x 的差异：MariaDB 10.4 **保留 query cache**（小站读多可开）；`performance_schema` 默认即关闭更省内存；其余 InnoDB 参数一致。

```ini
; /etc/my.cnf  (MariaDB 10.4)

[mysqld]
# 字符集
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
init-connect='SET NAMES utf8mb4'

# InnoDB 缓冲池（2GB 内存给 256MB）
innodb_buffer_pool_size = 256M
innodb_buffer_pool_instances = 1
innodb_log_file_size = 32M
innodb_log_buffer_size = 16M

# 性能优化
innodb_flush_log_at_trx_commit = 2
innodb_flush_neighbors = 0
innodb_io_capacity = 200
innodb_read_io_capacity = 200

# 连接数（小机器压到 100 足够）
max_connections = 100
thread_cache_size = 16
table_open_cache = 400

# 查询缓存（MariaDB 10.4 仍支持，小站读多可开）
query_cache_type = 1
query_cache_size = 16M
query_cache_limit = 2M

# 慢查询日志
slow_query_log = 1
slow_query_log_file = /www/server/mysql/log/slow-query.log
long_query_time = 2

# 日志
log_error = /www/server/mysql/log/error.log

[mysql]
default-character-set = utf8mb4

[client]
default-character-set = utf8mb4
```

### 4.4 数据库初始化（两者通用）

```sql
-- 创建数据库和用户
CREATE DATABASE zyfx_wang 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'zyfx_wang'@'127.0.0.1' IDENTIFIED BY '你的数据库密码';
GRANT ALL PRIVILEGES ON zyfx_wang.* TO 'zyfx_wang'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 4.5 验证配置

```bash
# 检查数据库版本
mysql -V
# MySQL 显示 8.0.x / 8.4.x；MariaDB 显示 10.4.x

# 检查字符集
mysql -e "SHOW VARIABLES LIKE 'character_set_server';"
# 应该显示 utf8mb4

# 检查 InnoDB 缓冲池
mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
# 应该显示 268435456 (256MB)

# 检查连接数上限
mysql -e "SHOW VARIABLES LIKE 'max_connections';"
```

---

## 5. Nginx 配置

### 5.1 网站配置

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name zyfx.wang;
    
    # 强制 HTTPS
    if ($scheme != "https") {
        return 301 https://$host$request_uri;
    }

    # SSL 证书
    ssl_certificate     /www/server/panel/vhost/cert/zyfx.wang/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/zyfx.wang/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # 站点根目录
    root /www/wwwroot/zyfx.wang;
    index index.php index.html;

    # PHP 处理
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 上传限制（2C2G 限制并发）
    limit_conn conn 5;
    limit_conn_zone $binary_remote_addr zone=conn:10m;
    client_max_body_size 100M;
    client_body_timeout 120s;

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # 日志
    access_log /www/wwwroot/zyfx.wang/log/access.log;
    error_log /www/wwwroot/zyfx.wang/log/error.log;
}
```

### 5.2 限流配置

```nginx
# /www/server/nginx/conf/nginx.conf

http {
    # 限流：每 IP 每秒 10 个请求
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    
    # 连接限制：每 IP 最多 50 个连接
    limit_conn_zone $binary_remote_addr zone=conn:10m;
}

# 在 server 块中应用
location /api/ {
    limit_req zone=api burst=20 nodelay;
    limit_conn conn 50;
}
```

### 5.3 验证配置

```bash
# 检查 Nginx 配置
nginx -t

# 重载配置
nginx -s reload

# 检查状态
systemctl status nginx
```

---

## 6. Redis 配置

### 6.1 redis.conf

```conf
# /www/server/panel/install/redis/redis.conf

bind 127.0.0.1
protected-mode yes
port 6379
daemonize yes
timeout 0
tcp-keepalive 300

# 内存限制（2GB 内存给 128MB）
maxmemory 128mb
maxmemory-policy allkeys-lru

# 持久化
save 900 1
save 300 10
save 60 10000

# 日志
logfile /www/server/redis/redis.log
loglevel notice
```

### 6.2 验证配置

```bash
# 检查 Redis 状态
redis-cli ping
# 应该返回 PONG

# 检查内存
redis-cli info memory
# 查看 used_memory 和 maxmemory

# 检查连接数
redis-cli info clients
```

---

## 7. PanSou 接口配置

### 7.1 PanSou 服务部署

```bash
# 安装 PanSou（假设已在另一台服务器或同机部署）
# 默认监听 8888 端口

# 测试连通性
curl http://127.0.0.1:8888/api/health
```

### 7.2 后台配置

进入后台 → **接口配置** → **接口配置** Tab：

| 配置项 | 推荐值 | 说明 |
|---|---|---|
| 启用 PanSou 接口 | 启用 | 开启网盘搜索 |
| 接口地址 | `http://127.0.0.1:8888` | 同服务器 |
| 搜索超时 | 8 秒 | 网络慢可调到 15 |
| 结果缓存时长 | 720 分钟（12 小时） | 减少重复请求 |
| 每资源最多链路 | 5 条 | 避免冗余 |
| 补地址间隔 | 1000 秒 | 防止请求过快 |
| 启用网盘类型 | 115/123/百度/阿里/夸克/UC | 按需勾选 |

### 7.3 测试 PanSou

**方法 1：后台测试**

```
接口配置 → 测试连接
```

期望结果：
```
✅ 连接成功 | HTTP 200 | 12ms | 6 个频道 | 8 个插件 | v3.x.x
```

**方法 2：API 测试**

```bash
# 测试接口连通性
curl -X POST http://127.0.0.1/api/unified.php?action=pansou_test \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <你的token>"

# 测试资源搜索
curl -X POST http://127.0.0.1/api/unified.php?action=pansou_search \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <你的token>" \
  -d '{"keyword": "流浪地球", "refresh": true}'
```

**期望返回：**
```json
{
  "ok": true,
  "data": {
    "keyword": "流浪地球",
    "results": [
      {
        "title": "流浪地球(2019).mkv",
        "type": "百度",
        "url": "https://pan.baidu.com/s/xxx"
      }
    ]
  }
}
```

### 7.4 PanSou 配置问题排查

| 问题 | 原因 | 解决 |
|---|---|---|
| 测试连接失败 | PanSou 服务未启动 | `systemctl status pansou` |
| HTTP 连接拒绝 | 端口 8888 未开放 | `firewall-cmd --add-port=8888/tcp` |
| 搜索结果空 | PanSou 插件未配置 | 检查 PanSou 后台插件配置 |
| 补地址失败 | 网盘类型不匹配 | 检查 `pansou.types` 配置 |
| 速度慢 | 搜索缓存未启用 | 设置 `cache_ttl=720` |

---

## 8. 性能调优

### 8.1 OPcache 配置

```ini
; /www/server/php/8.2/etc/php.ini

opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 0
opcache.interned_strings_buffer = 16
opcache.fast_shutdown = 1
```

**验证 OPcache：**
```bash
php -r "echo function_exists('opcache_get_status') ? 'OPcache: ON' : 'OPcache: OFF';"
# 应该输出：OPcache: ON

# 检查 OPcache 状态
php -r "print_r(opcache_get_status());"
```

### 8.2 缓存策略

```php
// config/config.php
'cache' => [
    'driver' => 'redis',        // 主缓存：Redis
    'apcu' => true,             // 备选缓存：APCu（Redis 挂了不宕机）
    'file_ttl' => 3600,        // 文件缓存 TTL（秒）
],
```

**缓存层级：**
```
Redis (热数据) → APCu (备选) → File (冷数据)
     ↓              ↓              ↓
  内存高速        进程内共享     磁盘持久化
  1-5ms          0.1-0.5ms    5-20ms
```

### 8.3 数据库优化

```sql
-- 检查慢查询
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- 查看慢查询日志
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- 分析查询计划
EXPLAIN SELECT * FROM media_items WHERE title LIKE '%关键词%';

-- 检查索引使用情况
SHOW INDEX FROM media_items;
```

### 8.4 压力测试

```bash
# 使用 wrk 进行压力测试
wrk -t4 -c100 -d30s http://127.0.0.1/

# 或使用 hey
hey -z 30s -c 100 http://127.0.0.1/
```

**期望性能（2C2G）：**
- QPS: 500-1000
- P99 延迟: < 200ms
- 错误率: < 0.1%

---

## 9. 安全加固

### 9.1 系统安全

```bash
# 修改 SSH 端口
vim /etc/ssh/sshd_config
# Port 2222

# 禁用 root 登录
PermitRootLogin no

# 只允许密钥登录
PasswordAuthentication no
PubkeyAuthentication yes

# 重启 SSH
systemctl restart sshd
```

### 9.2 防火墙配置

```bash
# 开放必要端口
firewall-cmd --permanent --add-port=80/tcp
firewall-cmd --permanent --add-port=443/tcp
firewall-cmd --permanent --add-port=2222/tcp
firewall-cmd --reload

# 检查规则
firewall-cmd --list-all
```

### 9.3 Fail2Ban 配置

```ini
# /etc/fail2ban/jail.local

[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 2222
filter = sshd
logpath = /var/log/secure

[nginx-http-auth]
enabled = true
port = http,https
filter = nginx-http-auth
logpath = /www/wwwlogs/error.log
```

### 9.4 应用安全

```php
// config/config.php
'security' => [
    'csrf_enabled' => true,      // 启用 CSRF 防护
    'cors_enabled' => true,      // 启用 CORS
    'rate_limit' => 100,         // 每 IP 每分钟 100 请求
    'trusted_hosts' => [],      // 可信主机白名单
],
```

---

## 10. 监控与日志

### 10.1 日志配置

```nginx
# Nginx 日志
access_log /www/wwwroot/zyfx.wang/log/access.log;
error_log /www/wwwroot/zyfx.wang/log/error.log;
```

```ini
; PHP 日志
log_errors = On
error_log = /www/wwwroot/zyfx.wang/log/php_error.log
```

### 10.2 日志轮转

```bash
# /etc/logrotate.d/zyfx-wang

/www/wwwroot/zyfx.wang/log/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 www www
    sharedscripts
    postrotate
        [ -f /www/wwwroot/zyfx.wang/log/nginx.pid ] && kill -USR1 $(cat /www/wwwroot/zyfx.wang/log/nginx.pid)
    endscript
}
```

### 10.3 监控脚本

```bash
#!/bin/bash
# /root/monitor.sh

SITE="http://127.0.0.1"
ALERT_EMAIL="admin@example.com"

# 检查站点状态
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $SITE)
if [ "$STATUS" != "200" ]; then
    echo "站点异常: HTTP $STATUS" | mail -s "告警" $ALERT_EMAIL
fi

# 检查 MySQL
MYSQL_STATUS=$(mysqladmin ping 2>/dev/null)
if [ "$MYSQL_STATUS" != "mysqld is alive" ]; then
    echo "MySQL 异常: $MYSQL_STATUS" | mail -s "告警" $ALERT_EMAIL
fi

# 检查 Redis
REDIS_STATUS=$(redis-cli ping 2>/dev/null)
if [ "$REDIS_STATUS" != "PONG" ]; then
    echo "Redis 异常: $REDIS_STATUS" | mail -s "告警" $ALERT_EMAIL
fi

# 检查磁盘
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 85 ]; then
    echo "磁盘告警: ${DISK_USAGE}%" | mail -s "告警" $ALERT_EMAIL
fi

# 检查内存
MEM_USAGE=$(free -m | awk 'NR==2 {printf "%.0f", $3/$2*100}')
if [ "$MEM_USAGE" -gt 85 ]; then
    echo "内存告警: ${MEM_USAGE}%" | mail -s "告警" $ALERT_EMAIL
fi
```

**添加到 crontab：**
```bash
# 每 5 分钟检查一次
*/5 * * * * /root/monitor.sh
```

---

## 11. 常见问题排查

### 11.1 Fatal Error 排查

| 错误 | 原因 | 解决 |
|---|---|---|
| `time_zone='00:00'` | MySQL 5.7 不识别 | 升级到 MySQL 8.0+ 或 MariaDB 10.4，或重启 PHP-FPM |
| `class not found` | 扩展未安装 | 安装缺失扩展 |
| `permission denied` | 文件权限问题 | `chmod 755 /www/wwwroot/zyfx.wang` |
| `connection refused` | 服务未启动 | 检查 MySQL/Redis 状态 |

### 11.2 性能问题排查

```bash
# 检查 PHP-FPM 状态
curl http://127.0.0.1/status

# 检查 MySQL 慢查询
mysql -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';"

# 检查 Redis 内存
redis-cli info memory

# 检查系统负载
top -bn1 | head -10
vmstat 1 5
```

### 11.3 备份与恢复

```bash
# 每日备份脚本
#!/bin/bash
# /root/backup.sh

BACKUP_DIR="/www/backup"
DATE=$(date +%Y%m%d)

# 备份数据库
mysqldump -u zyfx_wang -p'密码' zyfx_wang | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# 备份代码
tar -czf $BACKUP_DIR/site_$DATE.tar.gz /www/wwwroot/zyfx.wang

# 保留最近 7 天
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

# 添加到 crontab
# 0 2 * * * /root/backup.sh
```

---

## 12. 完整部署脚本

### 12.1 一键部署脚本

```bash
#!/bin/bash
# deploy.sh - 生产环境一键部署脚本

set -e

echo "=== 开始部署 ==="

# 1. 安装系统依赖
echo "安装系统依赖..."
yum install -y epel-release
yum install -y wget curl gcc make php php-devel

# 2. 安装 PHP 扩展
echo "安装 PHP 扩展..."
pecl install redis
pecl install apcu
echo "extension=redis" >> /www/server/php/8.2/etc/php.ini
echo "extension=apcu" >> /www/server/php/8.2/etc/php.ini

# 3. 配置 PHP
echo "配置 PHP..."
cat > /www/server/php/8.2/etc/php-fpm.d/www.conf << 'EOF'
pm = dynamic
pm.max_children = 12
pm.start_servers = 4
pm.min_spare_servers = 3
pm.max_spare_servers = 6
pm.max_requests = 500
pm.status_path = /status
EOF

# 4. 配置 MySQL
echo "配置 MySQL..."
cat > /www/server/mysql/etc/my.cnf.d/performance.cnf << 'EOF'
[mysqld]
innodb_buffer_pool_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 200
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
EOF

# 5. 配置 Nginx
echo "配置 Nginx..."
cat > /www/server/nginx/conf/nginx.conf.d/limit.conf << 'EOF'
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_conn_zone $binary_remote_addr zone=conn:10m;
EOF

# 6. 创建目录
echo "创建目录..."
mkdir -p /www/wwwroot/zyfx.wang/log
mkdir -p /www/backup

# 7. 设置权限
echo "设置权限..."
chown -R www:www /www/wwwroot/zyfx.wang
chmod -R 755 /www/wwwroot/zyfx.wang

# 8. 重启服务
echo "重启服务..."
systemctl restart nginx
systemctl restart php-fpm-82
systemctl restart mysqld
systemctl restart redis

echo "=== 部署完成 ==="
echo "访问: http://你的域名"
echo "后台: http://你的域名/admin"
```

### 12.2 部署验证清单

```bash
# 1. PHP 版本
php -v

# 2. PHP 扩展
php -m | grep -E "curl|redis|apcu|pdo_mysql|opcache"

# 3. MySQL 版本
mysql -V

# 4. MySQL 字符集
mysql -e "SHOW VARIABLES LIKE 'character_set_server';"

# 5. Redis 状态
redis-cli ping

# 6. Nginx 配置
nginx -t

# 7. OPcache
php -r "echo function_exists('opcache_get_status') ? 'ON' : 'OFF';"

# 8. 站点访问
curl -I http://127.0.0.1

# 9. 后台访问
curl -I http://127.0.0.1/admin

# 10. PanSou 测试
curl -X POST http://127.0.0.1/api/unified.php?action=pansou_test \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>"
```

---

## 附录

### A. 常用命令速查

```bash
# 服务管理
systemctl status nginx php-fpm-82 mysqld redis
systemctl restart nginx php-fpm-82 mysqld redis
systemctl reload nginx

# PHP
php -v
php -m
php -r "phpinfo();"

# MySQL
mysql -u root -p
mysql -e "SHOW DATABASES;"
mysql -e "SHOW TABLES FROM zyfx_wang;"

# Redis
redis-cli
redis-cli ping
redis-cli info

# 日志
tail -f /www/wwwroot/zyfx.wang/log/access.log
tail -f /www/wwwroot/zyfx.wang/log/error.log
```

### B. 配置文件路径

| 组件 | 配置文件 |
|---|---|
| PHP | `/www/server/php/8.2/etc/php.ini` |
| PHP-FPM | `/www/server/php/8.2/etc/php-fpm.d/www.conf` |
| MySQL | `/www/server/mysql/etc/my.cnf` |
| Nginx | `/www/server/nginx/conf/nginx.conf` |
| Redis | `/www/server/panel/install/redis/redis.conf` |
| 应用 | `/www/wwwroot/zyfx.wang/config/config.php` |

### C. 版本更新日志

| 版本 | 日期 | 主要变更 |
|---|---|---|
| v4.0.6 | 2026-09-29 | 生产文档双基线：MariaDB 10.4 + MySQL 8.0/8.4，2C2G 推荐 MariaDB 10.4 |
| v3.8.0 | 2026-09-28 | 修复 max_execution_time MySQL 5.7 兼容 |
| v3.7.9 | 2026-09-27 | MySQL 扩展常量 defined() 保护 |
| v3.7.8 | 2026-09-27 | 千万级加固 + 现代化美化 |

---

**文档版本**: v1.1  
**最后更新**: 2026-09-29  
**作者**: 万能资料库开发团队
