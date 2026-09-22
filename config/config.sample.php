<?php
/**
 * 媒资资料库 API - 配置【示例文件】
 * ==================================================================
 * ★ 程序实际读取的是同目录下的 config.php，不是本文件。
 *
 * 三种安装方式（任选其一）：
 *   1) 推荐：浏览器打开 https://你的域名/install.php
 *            按向导填数据库信息，一键建表并生成 config/config.php
 *            （装完自动写入 config/install.lock，该页面从此对所有人返回 404）；
 *   2) 终端：在宝塔「终端」里进入站点目录，执行
 *            php tools/install-cli.php
 *            按提示输入数据库信息，效果与网页安装完全一致；
 *   3) 手动：把本文件复制/改名为 config.php，填上下面的数据库信息与密钥，
 *            再导入 sql/install.sql 建表。
 *
 * 关于网页安装器（v1.6.3 起）：
 *   install.php 是**带自锁**的 —— 只要站点已安装（存在 config/install.lock，
 *   或本文件的副本 config.php 里 db 段已填真值），它对所有人返回 404，
 *   与文件不存在完全一样，没人能借此重装站点或读到服务器信息；
 *   只有在"尚未安装"时才显示安装向导，而那正是最需要它的时候。
 *   不需要它可直接删掉站点根的 install.php，安装改用
 *   php tools/install-cli.php（仅命令行可执行，公网 404）。
 *   其余安装 / 初始化类路径（/setup、/upgrade、/update）仍然一律 404。
 *
 * 为什么发布包里只有示例、没有 config.php：
 *   避免升级覆盖源码时把你的数据库口令、API key 冲掉。
 *   所以升级时也请**保留站点上的 config/config.php 不动**。
 *
 * 兼容 PHP 5.6 ~ 8.2。所有密钥都只留在服务端，对外只暴露本服务地址。
 *
 * 说明：下面的「TMDB Key / RAWG Key / 启用数据源 / 后台令牌」以及网盘类型，
 *       装好后都可在【后台 → 系统设置】里直接增删改（存在数据库 app_settings 表，
 *       优先级高于本文件；某项留空即回退读本文件的值）。所以本文件通常只需要填对 db。
 */

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);
return [
    // 数据库（宝塔新建的 MySQL 库）
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'media_library',
        'user'     => 'media_lib',
        'pass'     => 'CHANGE_ME',      // ← 改成宝塔上该库用户的真实密码
        'charset'  => 'utf8mb4',
    ],

    // TMDB（影视/人物） https://www.themoviedb.org/settings/api 申请
    'tmdb' => [
        'api_key'   => 'YOUR_TMDB_API_KEY',
        'base'      => 'https://api.themoviedb.org/3',
        'image_base'=> 'https://image.tmdb.org/t/p/',
        'lang'      => 'zh-CN',
    ],

    // RAWG（游戏） https://rawg.io/apidocs 申请
    'rawg' => [
        'api_key'   => 'YOUR_RAWG_API_KEY',
        'base'      => 'https://api.rawg.io/api',
    ],

    // Bangumi（动漫） https://bangumi.github.io/api/
    'bangumi' => [
        'base'      => 'https://api.bgm.tv',
    ],

    // 启用的数据源适配器（新增源只需在此加一项并在 adapters/ 加对应类）
    'adapters' => ['tmdb', 'rawg', 'bangumi'],

    // 网盘类型（后台「下载链接」下拉用）**不建议写在这里**：
    //   优先级为  后台「系统设置」  >  config/pan_types.php  >  内置默认。
    //   想加网盘请用后台「系统设置」，或改 config/pan_types.php（升级包覆盖它也不会影响密钥）。

    // 采集（同步）策略 —— 0 也可作为合法值，故每项都请写全。
    //   ★ 更推荐在【后台 → 同步采集】里改：即时生效、优先级高于本文件、升级覆盖也不会丢。
    //   每一项的含义在后台那一页都有中文说明，这里只列文件兜底值。
    'sync' => [
        'limit'           => 20,     // 每个源、每次最多入库多少条（TMDB 单页 20 / RAWG 单页 40）
        'max_pages'       => 1,      // 每个源最多翻几页（翻页越多越费上游配额）
        'max_requests'    => 8,      // 单次采集的上游请求总数硬闸（防止跑太久被网关掐断）
        'window'          => 'week', // 热门榜时间窗：day（今日）| week（本周）
        'order'           => 'both', // 取值口径：hot（最热）| popular（最多人看）| both
        'min_votes'       => 0,      // 低于该投票数的条目不入库（0 = 不限，即"最多人看"的门槛）
        'dedupe'          => 'skip', // 已采集过的条目：skip（跳过，推荐）| update（刷新数据）
        'skip_same_title' => 1,      // 同名同年（跨数据源）是否也跳过：1 是 / 0 否
        'interval'        => 55,     // 两次自动同步的最小间隔（分钟），0 = 不限
        'build'           => 1,      // 同步后是否为新增条目生成静态页：1 是 / 0 否
    ],

    // 缓存策略
    'cache' => [
        'ttl_days'   => 7,    // 详情缓存有效期（天），到期前代理直接返回本地
        'stale_days' => 30,   // 超过该天数由 sync_refresh 重新拉取
    ],

    // TMDB 兼容代理是否强制校验 key（默认 false：ZBLOK 把 base URL 改成这里即可，零改动）
    'proxy' => [
        'require_key' => false,
        'proxy_key'   => '',
    ],

    // 后台管理 token（请求头 X-Admin-Token 或 ?token=）
    'admin' => [
        'token' => 'CHANGE_ADMIN_TOKEN',   // ← 改成你自己的随机字符串
    ],

    // ==================================================================
    // 站点设置（v1.6.0 起）—— 下面 6 段
    // ------------------------------------------------------------------
    // ★ 强烈建议在【后台 → 站点设置】里改：即时生效、优先级高于本文件、
    //   整包覆盖升级也不会丢（值存在数据库 app_settings 表）。
    //   这里只是「文件兜底值」。
    // ★ 留空 = 用内置默认。注意 0 是合法值（例如 name_hide = 0 表示「显示名称」），
    //   所以不要用「注释掉整行」来取消设置，直接留空字符串即可。
    // ==================================================================

    // 基础设置：站点名 / LOGO / 页脚
    'site' => [
        'name'         => '媒资资料库',
        'name_hide'    => 0,        // 1 = 顶栏只显示 LOGO，不显示文字站名
        'slogan'       => '',       // 顶栏副标题 / 首页 banner 主标题
        'logo'         => '',       // 图片网址；也可在后台直接上传（存到 /uisc-assets/）
        'favicon'      => '',       // 浏览器标签页小图标（.ico / .png）
        'url'          => '',       // 例：https://cs.zyfx.wang（结尾不要带 /），用于 canonical / sitemap / robots
        'footer_intro' => '',       // 页脚第一段（支持换行）
        'declare'      => '',       // 页脚声明行
        'copyright'    => '',       // 页脚版权（支持换行，可写备案号）
    ],

    // SEO：首页三件套 + 标题模板 + 统计代码 + robots / sitemap
    'seo' => [
        'title'         => '',      // 首页 <title>；留空 = 站点名 - 宣传语
        'keywords'      => '',      // 逗号分隔
        'description'   => '',      // 建议 80~160 字
        'stats_code'    => '',      // 第三方统计整段代码，原样插到每个页面 </head> 前
        'pattern_type'  => '{type} - 第{page}页 - {site}',      // 列表页 / 分类页标题模板
        'pattern_item'  => '{title}({year}) - {type} - {site}', // 内页标题模板
        'item_desc_len' => 0,       // 内页 description 取简介前多少字；0 = 直接用上面的 description
        'robots'        => '',      // 访问 /robots.txt 时输出；留空用默认规则
        'sitemap'       => 1,       // 是否生成 /sitemap.xml：1 是 / 0 否
        'sitemap_size'  => 5000,    // sitemap 最多收录条数
    ],

    // 跳转与扫码 + 前台模板（PC 访客的访问方式）
    //   ★ target 与 qr 都为空时**不会拦截任何访客**，等于关闭。
    'redirect' => [
        'mode'         => 'jump_scan', // jump_scan 跳转+扫码 | jump_only 仅跳转 | scan_only 仅扫码
        'target'       => '',          // 跳转目标地址（你的新域名 / 推广页）
        'mobile'       => 0,           // 1 = 手机端也按上面的方式处理；0 = 手机直接进站（推荐）
        'qr'           => '',          // 群 / 公众号二维码图片（可后台直接上传）
        'qr_tip'       => '',          // 二维码下方提示语；留空用默认
        'tpl'          => 'classic',   // 前台模板：classic 经典卡片墙 | simple 简约紧凑
        'simple_mode'  => 'auto',      // 前台明暗：auto 跟随系统 | light 亮色 | dark 暗色
        'nav_show'     => 'home,latest', // 顶栏入口，可选 home,all,top,game,latest,request,contact（逗号分隔）
        'nav_external' => '',          // 自定义外链，一行一个「链接文字|网址」
        'rank_style'   => 'grid',      // 榜单样式：grid 有图 | list 无图（纯文字，加载更快）
        'latest'       => 1,           // 首页是否显示「最新更新」区块：1 开 / 0 关
    ],

    // 网盘账号（后台「站点设置 → 网盘链接」用）
    //   ★ 只做保存与展示，程序不会主动登录你的网盘账号。
    'pan' => [
        // 要管理哪几种网盘（决定后台显示哪些页签）；留空 = 全部 6 种
        'group' => 'quark,aliyun,baidu,uc,xunlei,guangya',
        // 每种网盘三件套，按需填写（键名 = 网盘代号 + _cookie / _dir / _tmp）：
        // 'quark_cookie'  => '',   'quark_dir'   => '/资源/电影',  'quark_tmp'   => '/资源/临时',
        // 'aliyun_cookie' => '',   'aliyun_dir'  => '',             'aliyun_tmp'  => '',
        // 其余同理：baidu / uc / xunlei / guangya
    ],

    // 接口配置：PanSou 网盘搜索（采集后自动补网盘下载地址）
    //   PanSou 是开源的网盘资源聚合搜索服务，默认监听 8888 端口。
    'pansou' => [
        'enable' => 0,                        // 1 = 启用；不启用则「采集后自动补地址」不工作
        'mode'   => 'local',                  // local 与站点同机 | remote 远程部署
        'url'    => 'http://127.0.0.1:8888',  // 只填服务根地址，不要带 /api/search
        'user'   => '',                       // 服务端开了鉴权才需要
        'pass'   => '',
        'line'   => 'PanSou 主线路',           // 只是后台显示的备注名
        'types'  => '',                       // 只保留哪些网盘（逗号分隔）；留空 = 全部类型
        'auto'   => 1,                        // 采集后自动补下载地址：1 开 / 0 关
        'policy' => 'exact',                   // exact 仅高度匹配才自动写 | write 搜到就写 | pending 只进待确认
        'match'  => 80,                        // 标题匹配最低相似度（%）
        'max'    => 5,                         // 每条资源最多写入几个网盘链接
        'delay'  => 1000,                      // 两次搜索的最小间隔（毫秒），防被限流
        'ttl'    => 720,                       // 搜索结果缓存时长（分钟）；0 = 不缓存
    ],

    // 资源请求（注册 + 求片区，v1.6.2 起）
    //   ★ 同样建议在【后台 → 站点设置 → 资源请求】里改，这里只是文件兜底值。
    //   ★ 用到的两张表（app_users / app_requests）由程序首次使用时**自动创建**，
    //     覆盖升级不用手工导 SQL。
    'req' => [
        'open'       => 1,            // 是否开放注册：1 开 / 0 关（关闭后只有老账号能登录）
        'logon'      => 1,            // 提交资源请求是否要求登录：1 必须登录 / 0 游客也能提交
        'audit'      => 0,            // 注册是否需要管理员审核：1 需要 / 0 直接可用
        'verify'     => 1,            // 注册是否用算术验证码：1 开 / 0 关（不依赖 GD 图形库）
        'guest_view' => 1,            // 未登录访客能否浏览请求列表：1 能 / 0 仅登录可见
        'public'     => 1,            // 是否展示「大家都在找」列表：1 显示 / 0 不显示
        'contact'    => 1,            // 是否收集联系方式：1 收集 / 0 不收集（表单更短）
        'nav'        => 1,            // 顶部导航是否显示入口：1 显示 / 0 隐藏
        'daily'      => 5,            // 每人每天最多提交几条；0 = 不限制
        'mintitle'   => 2,            // 资源名称最少字数（挡住「1」「求」这类无效提交）
        'page_title' => '资源请求',    // 页面名称，导航入口与页面大标题都用它
        'page_intro' => '',           // 页面说明；留空用内置文案
        'notice'     => '',           // 提交框上方的公告条；留空不显示
    ],
];
