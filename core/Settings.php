<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/* Settings 可能被 Config 在很早的阶段懒加载（此时 index.php 还没 require DB.php），
   这里自己兜住依赖，避免 PHP 5.6 下 "Class 'Core\DB' not found" 的致命错误。 */
require_once __DIR__ . '/DB.php';

/**
 * 运行时可改设置（后台「系统设置」页写入数据库 app_settings 表）
 * ==================================================================
 * 设计目标：让「后台管理令牌 / TMDB Key / RAWG Key / 启用数据源 / 网盘类型」
 * 这几项不必再改 config/config.php —— 装完后在后台点点就能增删改。
 *
 * 优先级：app_settings（后台保存）  >  config/config.php（文件兜底）
 * 空值语义：某一项为空字符串 = **不覆盖**，自动回退到 config.php 的值。
 *           所以后台「清空某项再保存」等价于「恢复成配置文件里的值」。
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / 返回类型 / 标量类型提示 等 7.0+ 语法）
 */
class Settings
{
    /** 设置表名（不带库前缀，本项目的表都是裸名） */
    const TABLE = 'app_settings';

    /** 允许后台写入的键白名单（防止任意键写入） */
    public static function keys()
    {
        $base = array(
            'admin_token', 'tmdb_api_key', 'rawg_api_key', 'adapters', 'pan_types',
            // —— 采集（同步）参数：后台「同步采集」页可改，作用见 Core\Sync ——
            'sync_limit', 'sync_max_pages', 'sync_max_requests', 'sync_window', 'sync_order',
            'sync_min_votes', 'sync_dedupe', 'sync_skip_same_title', 'sync_interval', 'sync_build',
        );
        // —— 站点设置：后台「站点设置」页的 5 个子页，键由各自 map() 统一产出 ——
        $groupMaps = self::groupMaps();
        foreach ($groupMaps as $map) {
            foreach ($map as $k => $child) {
                $base[] = $k;
            }
        }
        return $base;
    }

    /**
     * 5 组站点设置的「设置键 → 配置段子键」映射（keys / Config 叠加 / 后台渲染共用一份）
     * 这样新增一个设置项只需要改这里 + schema()，不会出现三处不同步。
     */
    public static function groupMaps()
    {
        return array(
            'site'     => self::siteMap(),
            'seo'      => self::seoMap(),
            'redirect' => self::redirectMap(),
            'pan'      => self::panMap(),
            'pansou'   => self::pansouMap(),
            'req'      => self::reqMap(),
        );
    }

    /** 基础设置（网站名称 / LOGO / 页脚 …） */
    public static function siteMap()
    {
        return array(
            'site_name'         => 'name',
            'site_name_hide'    => 'name_hide',
            'site_slogan'       => 'slogan',
            'site_logo'         => 'logo',
            'site_favicon'      => 'favicon',
            'site_url'          => 'url',
            'site_footer_intro' => 'footer_intro',
            'site_declare'      => 'declare',
            'site_copyright'    => 'copyright',
        );
    }

    /** SEO（标题 / 关键词 / 描述 / 统计代码 / 内页模板 / robots / sitemap） */
    public static function seoMap()
    {
        return array(
            'seo_title'        => 'title',
            'seo_keywords'     => 'keywords',
            'seo_description'  => 'description',
            'seo_stats_code'   => 'stats_code',
            'seo_pattern_type' => 'pattern_type',
            'seo_pattern_item' => 'pattern_item',
            'seo_item_desc_len' => 'item_desc_len',
            'seo_robots'       => 'robots',
            'seo_sitemap'      => 'sitemap',
            'seo_sitemap_size' => 'sitemap_size',
        );
    }

    /** 跳转与扫码 + 前端模板（PC 端访问方式 / 群二维码 / 导航 / 模板） */
    public static function redirectMap()
    {
        return array(
            'redirect_mode'    => 'mode',
            'redirect_target'  => 'target',
            'redirect_mobile'  => 'mobile',
            'redirect_qr'      => 'qr',
            'redirect_qr_tip'  => 'qr_tip',
            'tpl_switch'       => 'tpl',
            'nav_show'         => 'nav_show',
            'nav_external'     => 'nav_external',
            'tpl_simple_mode'  => 'simple_mode',
            'list_rank_style'  => 'rank_style',
            'list_latest'      => 'latest',
        );
    }

    /** 网盘账号（每种网盘：Cookie / 默认转存目录 / 临时资源目录） */
    public static function panAccounts()
    {
        return array(
            'quark'   => '夸克网盘',
            'aliyun'  => '阿里云盘',
            'baidu'   => '百度网盘',
            'uc'      => 'UC网盘',
            'xunlei'  => '迅雷云盘',
            'guangya' => '光鸭网盘',
        );
    }

    public static function panMap()
    {
        $out = array('pan_group' => 'group');
        foreach (self::panAccounts() as $code => $name) {
            $out['pan_' . $code . '_cookie'] = $code . '_cookie';
            $out['pan_' . $code . '_dir']    = $code . '_dir';
            $out['pan_' . $code . '_tmp']    = $code . '_tmp';
        }
        return $out;
    }

    /** 接口配置（PanSou 网盘搜索）+ 采集后自动补下载地址 */
    public static function pansouMap()
    {
        return array(
            'pansou_enable' => 'enable',
            'pansou_mode'   => 'mode',
            'pansou_url'    => 'url',
            'pansou_user'   => 'user',
            'pansou_pass'   => 'pass',
            'pansou_line'   => 'line',
            'pansou_types'  => 'types',
            'pansou_auto'   => 'auto',
            'pansou_policy' => 'policy',
            'pansou_match'  => 'match',
            'pansou_max'    => 'max',
            'pansou_delay'  => 'delay',
            'pansou_ttl'    => 'ttl',
        );
    }

    /**
     * 资源请求 / 注册（后台「站点设置 → 资源请求」6 个二级页之一）
     * 落到 $cfg['req'] 段，消费方是 core/User.php 与 core/UserViews.php。
     */
    public static function reqMap()
    {
        return array(
            'req_open'       => 'open',        // 是否开放注册
            'req_logon'      => 'logon',       // 提交是否要求登录
            'req_audit'      => 'audit',       // 注册是否需要审核
            'req_verify'     => 'verify',      // 注册是否用算术验证码
            'req_guest_view' => 'guest_view',  // 游客可否浏览请求列表
            'req_public'     => 'public',      // 是否展示"大家都在找"
            'req_contact'    => 'contact',     // 是否收集联系方式
            'req_nav'        => 'nav',         // 顶部导航是否显示入口
            'req_daily'      => 'daily',       // 每人每天条数上限
            'req_mintitle'   => 'mintitle',    // 资源名称最少字数
            'req_page_title' => 'page_title',  // 页面名称
            'req_page_intro' => 'page_intro',  // 页面说明
            'req_notice'     => 'notice',      // 页面公告
        );
    }

    /** 采集参数键 → sync 段子键（Config 叠加与后台渲染共用一份映射） */
    public static function syncMap()
    {
        return array(
            'sync_limit'           => 'limit',
            'sync_max_pages'       => 'max_pages',
            'sync_max_requests'    => 'max_requests',
            'sync_window'          => 'window',
            'sync_order'           => 'order',
            'sync_min_votes'       => 'min_votes',
            'sync_dedupe'          => 'dedupe',
            'sync_skip_same_title' => 'skip_same_title',
            'sync_interval'        => 'interval',
            'sync_build'           => 'build',
        );
    }

    /** 每项的元信息：类型 + 中文标题 + 说明（后台渲染与文档共用一份定义） */
    public static function schema()
    {
        $out = array(
            'admin_token' => array(
                'type'  => 'text',
                'label' => '后台管理令牌（admin.token）',
                'tip'   => '登录后台用。留空则沿用 config.php 里的值；改完保存后本页会用新令牌。',
            ),
            'tmdb_api_key' => array(
                'type'  => 'text',
                'label' => 'TMDB API Key（影视 / 短剧 / 电视剧，可留空）',
                'tip'   => 'https://www.themoviedb.org/settings/api 申请，免费。留空则沿用 config.php。',
            ),
            'rawg_api_key' => array(
                'type'  => 'text',
                'label' => 'RAWG API Key（游戏，可留空）',
                'tip'   => 'https://rawg.io/apidocs 免费申请。留空则沿用 config.php。',
            ),
            'adapters' => array(
                'type'  => 'list',
                'label' => '启用哪些数据源',
                'tip'   => '决定「同步热门 / 采集」会跑哪些源；至少启用一个。',
                'options' => array(
                    'tmdb'    => 'TMDB（影视 / 短剧 / 电视剧）',
                    'rawg'    => 'RAWG（游戏，需 key）',
                    'bangumi' => 'Bangumi（动漫，免 key）',
                ),
            ),
            'pan_types' => array(
                'type'  => 'list',
                'label' => '网盘类型（后台「下载链接」下拉用）',
                'tip'   => '可自由增删；无需改文件、也无需升级包。留空则沿用 config/pan_types.php。',
            ),

            /* —— 采集（同步）参数：后台「同步采集」页渲染为表单 —— */
            'sync_limit' => array(
                'type' => 'int', 'group' => 'sync', 'min' => 1, 'max' => 200, 'unit' => '条',
                'label' => '每个源、每次最多入库多少条',
                'tip'   => '把「一次同步」限制在可控范围内：既遵守各源配额，也避免站点被灌满。TMDB 单次上限 20 条/页，RAWG 单页上限 40，所以填 20~40 最合适。',
            ),
            'sync_max_pages' => array(
                'type' => 'int', 'group' => 'sync', 'min' => 1, 'max' => 5, 'unit' => '页',
                'label' => '每个源最多翻几页',
                'tip'   => '翻页越多越费上游配额（TMDB/RAWG 都按请求计费）。建议 1；想多收内容就调「每源条数」。',
            ),
            'sync_max_requests' => array(
                'type' => 'int', 'group' => 'sync', 'min' => 1, 'max' => 60, 'unit' => '次',
                'label' => '单次同步的上游请求总数硬闸',
                'tip'   => '超过这个数量的接口调用会被直接放弃，防止计划任务跑太久被网关掐断。RAWG 免费额度 20,000 次/月（约 27 次/小时），请留有余量。',
            ),
            'sync_window' => array(
                'type' => 'select', 'group' => 'sync',
                'options' => array('day' => '今日（更新快，跟热榜更紧）', 'week' => '本周（稳，噪音少）'),
                'label' => 'TMDB 热门榜的时间窗口',
                'tip'   => '对应官方 /trending/all/{day|week}。每小时跑一次建议用「今日」。',
            ),
            'sync_order' => array(
                'type' => 'select', 'group' => 'sync',
                'options' => array('hot' => '只要「最热门」', 'popular' => '只要「最多人看」', 'both' => '两者都取（推荐，自动去重）'),
                'label' => '按什么口径取内容',
                'tip'   => '最热 = TMDB trending / RAWG -added / Bangumi 当日在播；最多人看 = TMDB popular 榜 / RAWG -rating / Bangumi 排行榜。',
            ),
            'sync_min_votes' => array(
                'type' => 'int', 'group' => 'sync', 'min' => 0, 'max' => 1000000, 'unit' => '票',
                'label' => '投票数低于此值的条目不要',
                'tip'   => '这是「最多人看」的量化门槛：TMDB 用 vote_count、Bangumi 用评分人数。0 = 不限。填 100 可过滤掉冷门条目。',
            ),
            'sync_dedupe' => array(
                'type' => 'select', 'group' => 'sync',
                'options' => array('skip' => '已存在就跳过（推荐）', 'update' => '已存在则刷新数据'),
                'label' => '遇到已采集过的条目怎么办',
                'tip'   => '「跳过」最省上游配额，也符合「重复就跳过」；「刷新」会在跳过入库的同时更新评分/热度等字段。',
            ),
            'sync_skip_same_title' => array(
                'type' => 'select', 'group' => 'sync',
                'options' => array('1' => '跳过（推荐，避免站内重复内容）', '0' => '不跳过（允许不同源的同名作品各存一条）'),
                'label' => '同名同年（跨数据源）是否也跳过',
                'tip'   => '例如同一部片在 TMDB 与 Bangumi 都有：开启后只保留先采集到的那条，站内不会出现两遍。年份允许差 1 年。',
            ),
            'sync_interval' => array(
                'type' => 'int', 'group' => 'sync', 'min' => 0, 'max' => 1440, 'unit' => '分钟',
                'label' => '两次自动同步的最小间隔',
                'tip'   => '防止手动连点或计划任务撞车导致频繁请求上游。计划任务是每小时跑一次，这里填 55 即可（0 = 不限制）。',
            ),
            'sync_build' => array(
                'type' => 'select', 'group' => 'sync',
                'options' => array('1' => '是（推荐，只生成新增的那几条）', '0' => '否，稍后统一生成'),
                'label' => '同步后顺手生成新增条目的静态页',
                'tip'   => '只生成本次新入库的 /uisc/{id}.html，不会全量重建（全量重建请用后台「生成静态页」按钮）。',
            ),

            /* ================= 站点设置 · 基础设置 ================= */
            'site_name' => array(
                'type' => 'text', 'group' => 'site',
                'label' => '网站名称',
                'tip'   => '显示在浏览器标题、页面顶栏与页脚。留空则沿用 config.php 的 site.name。',
            ),
            'site_name_hide' => array(
                'type' => 'select', 'group' => 'site',
                'options' => array('0' => '显示', '1' => '隐藏'),
                'label' => '隐藏网站名称',
                'tip'   => '当 LOGO 图片里已经包含文字时，把顶栏的文字名称隐藏掉，只留图形。',
            ),
            'site_slogan' => array(
                'type' => 'text', 'group' => 'site',
                'label' => '网站宣传语',
                'tip'   => '显示在顶栏站点名下方 / 首页副标题。',
            ),
            'site_logo' => array(
                'type' => 'image', 'group' => 'site',
                'label' => '网站 LOGO',
                'tip'   => '方形 LOGO，建议 80×80 像素。可直接上传，也可填图片网址。',
            ),
            'site_favicon' => array(
                'type' => 'image', 'group' => 'site',
                'label' => '网站 icon（favicon）',
                'tip'   => '浏览器标签页小图标，建议 32×32 的 .ico 或 .png。',
            ),
            'site_url' => array(
                'type' => 'text', 'group' => 'site',
                'label' => '网站网址',
                'tip'   => '用于生成 canonical、robots.txt 与 sitemap.xml 里的绝对地址。例：https://cs.zyfx.wang（结尾不要带 /）。',
            ),
            'site_footer_intro' => array(
                'type' => 'textarea', 'group' => 'site',
                'label' => '底部介绍',
                'tip'   => '页脚第一段文字。支持换行。',
            ),
            'site_declare' => array(
                'type' => 'text', 'group' => 'site',
                'label' => '底部声明',
                'tip'   => '页脚第二行（通常写「本站仅提供索引，不存储任何资源」之类的免责声明）。',
            ),
            'site_copyright' => array(
                'type' => 'textarea', 'group' => 'site',
                'label' => '底部版权',
                'tip'   => '页脚最下面一段。支持换行，可写备案号、维权邮箱等。',
            ),

            /* ================= 站点设置 · SEO ================= */
            'seo_title' => array(
                'type' => 'text', 'group' => 'seo',
                'label' => 'SEO 标题（首页）',
                'tip'   => '首页 <title>。留空则用「网站名称 - 网站宣传语」。',
            ),
            'seo_keywords' => array(
                'type' => 'textarea', 'group' => 'seo',
                'label' => 'SEO 关键词',
                'tip'   => '逗号分隔。首页 <meta name="keywords">，同时作为分类页/内页关键词的拼接补充。',
            ),
            'seo_description' => array(
                'type' => 'textarea', 'group' => 'seo',
                'label' => 'SEO 描述',
                'tip'   => '首页 <meta name="description">，建议 80~160 字。',
            ),
            'seo_stats_code' => array(
                'type' => 'textarea', 'group' => 'seo',
                'label' => '统计代码',
                'tip'   => '直接粘贴第三方统计的整段代码（如 51.la / 百度统计），会原样插入每个页面 </head> 前。',
            ),
            'seo_pattern_type' => array(
                'type' => 'text', 'group' => 'seo',
                'label' => '分类 / 列表页标题模板',
                'tip'   => '可用占位符：{site} 网站名、{type} 分类名、{sort} 排序名、{page} 页码。例：{type} - 第{page}页 - {site}',
            ),
            'seo_pattern_item' => array(
                'type' => 'text', 'group' => 'seo',
                'label' => '内页标题模板',
                'tip'   => '可用占位符：{title} 条目标题、{year} 年份、{type} 类型、{site} 网站名、{region} 地区。例：{title}({year}) - {type} - {site}',
            ),
            'seo_item_desc_len' => array(
                'type' => 'int', 'group' => 'seo', 'min' => 0, 'max' => 500, 'unit' => '字',
                'label' => '内页描述取简介前多少字',
                'tip'   => '自动把条目简介截断作为 description。0 = 不截取，直接用 SEO 描述。建议 120。',
            ),
            'seo_robots' => array(
                'type' => 'textarea', 'group' => 'seo',
                'label' => 'robots.txt 内容',
                'tip'   => '访问 /robots.txt 时输出。留空则用默认内容（允许收录首页与内页，屏蔽后台与接口）。',
            ),
            'seo_sitemap' => array(
                'type' => 'select', 'group' => 'seo',
                'options' => array('1' => '生成', '0' => '不生成'),
                'label' => '生成 sitemap.xml',
                'tip'   => '访问 /sitemap.xml 时按已入库条目动态生成，便于搜索引擎抓取。',
            ),
            'seo_sitemap_size' => array(
                'type' => 'int', 'group' => 'seo', 'min' => 100, 'max' => 50000, 'unit' => '条',
                'label' => 'sitemap 最多收录条数',
                'tip'   => '单文件 sitemap 建议不超过 5 万条。数据量小时可填大一些。',
            ),

            /* ================= 站点设置 · 跳转与扫码 ================= */
            'redirect_mode' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array(
                    'jump_scan' => '跳转 + 扫码',
                    'jump_only' => '仅跳转',
                    'scan_only' => '仅扫码',
                ),
                'label' => 'PC 端访问方式',
                'tip'   => '跳转：访客打开网站直接跳到你设定的地址；扫码：先显示二维码，提示用手机扫码访问。',
            ),
            'redirect_target' => array(
                'type' => 'text', 'group' => 'redirect',
                'label' => '跳转目标地址',
                'tip'   => '例如你的新域名或推广页。留空则不跳转（等于「仅扫码」）。',
            ),
            'redirect_mobile' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array('0' => '否，手机直接进站', '1' => '是，手机也按上面的方式处理'),
                'label' => '手机端是否也应用此规则',
                'tip'   => '一般选「否」——只拦 PC 访客，手机用户直接看站，体验更好。',
            ),
            'redirect_qr' => array(
                'type' => 'image', 'group' => 'redirect',
                'label' => '群二维码 / 公众号二维码',
                'tip'   => '扫码模式下展示的图片，建议正方形、清晰可扫。',
            ),
            'redirect_qr_tip' => array(
                'type' => 'text', 'group' => 'redirect',
                'label' => '扫码提示文案',
                'tip'   => '二维码下方的提示，例：请用手机扫码访问本站，或扫码加入交流群。',
            ),

            /* ================= 站点设置 · 前端模板 ================= */
            'tpl_switch' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array('classic' => '经典模板', 'simple' => '简约模板'),
                'label' => '前台模板',
                'tip'   => '经典 = 卡片墙；简约 = 更紧凑的列表版式。切换后前台立即生效。',
            ),
            'nav_show' => array(
                'type' => 'list', 'group' => 'redirect',
                'label' => '顶部导航显示',
                'tip'   => '勾选要在顶栏出现的入口。',
                'options' => array(
                    'home'    => '首页',
                    'all'     => '全网榜单',
                    'top'     => 'Top250',
                    'game'    => '游戏排行',
                    'latest'  => '最新更新',
                    'contact' => '联系我们',
                ),
            ),
            'nav_external' => array(
                'type' => 'textarea', 'group' => 'redirect',
                'label' => '顶部其他外链',
                'tip'   => '一行一个，格式：链接文字|网址。例：更多资源|https://example.com',
            ),
            'tpl_simple_mode' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array('auto' => '跟随系统', 'light' => '亮色', 'dark' => '暗色'),
                'label' => '前台明暗模式',
                'tip'   => '跟随系统 = 按访客设备的深浅色偏好自动切换，经典与简约模板都支持；亮色 / 暗色为固定显示。',
            ),
            'list_rank_style' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array('grid' => '有图模式', 'list' => '无图模式'),
                'label' => '全网榜单样式',
                'tip'   => '有图 = 卡片网格；无图 = 纯文字列表，加载更快。',
            ),
            'list_latest' => array(
                'type' => 'select', 'group' => 'redirect',
                'options' => array('1' => '开启', '0' => '关闭'),
                'label' => '最新列表',
                'tip'   => '是否在首页显示「最新更新」区块。',
            ),

            /* ================= 站点设置 · 接口配置（PanSou） ================= */
            'pansou_enable' => array(
                'type' => 'select', 'group' => 'pansou',
                'options' => array('1' => '启用', '0' => '停用'),
                'label' => '启用 PanSou 接口',
                'tip'   => 'PanSou 是开源的网盘资源搜索服务（聚合 TG 频道与网盘插件）。启用后，采集到资源可自动去搜网盘链接并写入下载地址。',
            ),
            'pansou_mode' => array(
                'type' => 'select', 'group' => 'pansou',
                'options' => array('local' => '机部署（与站点同一台服务器）', 'remote' => '远程部署（另填公网地址）'),
                'label' => '部署方式',
                'tip'   => '机部署一般填 http://localhost:8888 或 http://127.0.0.1:8888，不必走公网。',
            ),
            'pansou_url' => array(
                'type' => 'text', 'group' => 'pansou',
                'label' => '接口地址',
                'tip'   => 'PanSou 服务根地址，不要带 /api/search。例：http://localhost:8888',
            ),
            'pansou_user' => array(
                'type' => 'text', 'group' => 'pansou',
                'label' => '账号（仅服务端开启鉴权时才需要）',
                'tip'   => '默认部署无需填写。',
            ),
            'pansou_pass' => array(
                'type' => 'text', 'group' => 'pansou',
                'label' => '密码 / 令牌（仅服务端开启鉴权时才需要）',
                'tip'   => '默认部署无需填写。',
            ),
            'pansou_line' => array(
                'type' => 'text', 'group' => 'pansou',
                'label' => '线路展示名',
                'tip'   => '只是给你自己在后台看的备注名，例：PanSou 主线路。',
            ),
            'pansou_types' => array(
                'type' => 'list', 'group' => 'pansou',
                'label' => '启用哪些网盘类型',
                'tip'   => '决定搜索结果里保留哪些网盘。只勾你站点真正会展示的几种，能显著减少无用的补地址请求。',
                'options' => array(
                    'baidu'   => '百度',
                    'aliyun'  => '阿里',
                    'quark'   => '夸克',
                    'guangya' => '光鸭',
                    'tianyi'  => '天翼',
                    'uc'      => 'UC',
                    'mobile'  => '移动',
                    '115'     => '115',
                    'pikpak'  => 'PikPak',
                    'xunlei'  => '迅雷',
                    '123'     => '123',
                    'magnet'  => '磁力',
                    'ed2k'    => '电驴',
                    'other'   => '其他',
                ),
            ),
            'pansou_auto' => array(
                'type' => 'select', 'group' => 'pansou',
                'options' => array('1' => '开启（推荐）', '0' => '关闭'),
                'label' => '采集后自动补网盘下载地址',
                'tip'   => '同步/采集入库新条目后，自动用标题去 PanSou 搜索，把命中的网盘链接写进该条目的下载地址。',
            ),
            'pansou_policy' => array(
                'type' => 'select', 'group' => 'pansou',
                'options' => array(
                    'exact'   => '仅标题高度匹配才自动写入（推荐）',
                    'write'   => '只要搜到就自动写入',
                    'pending' => '一律只进「待确认」队列，人工过一遍',
                ),
                'label' => '自动补地址的写入策略',
                'tip'   => '搜到的结果未必就是同一部作品（同名不同版本很常见）。默认「仅高度匹配才自动写入」，其余进待确认队列，避免写入错误链接。',
            ),
            'pansou_match' => array(
                'type' => 'int', 'group' => 'pansou', 'min' => 0, 'max' => 100, 'unit' => '%',
                'label' => '标题匹配最低相似度',
                'tip'   => '越高越严格。建议 75~85：既不会漏掉「片名+年份」这类正常差异，又能挡掉同名不同版本。',
            ),
            'pansou_max' => array(
                'type' => 'int', 'group' => 'pansou', 'min' => 1, 'max' => 10, 'unit' => '个',
                'label' => '每条资源最多写入几个网盘链接',
                'tip'   => '每种网盘取最优的一条。建议 3~5，页面更清爽。',
            ),
            'pansou_delay' => array(
                'type' => 'int', 'group' => 'pansou', 'min' => 0, 'max' => 10000, 'unit' => '毫秒',
                'label' => '补地址时两次搜索的最小间隔',
                'tip'   => 'PanSou 会去搜 TG 频道和第三方插件，连续请求容易被限流。建议 800~1500。',
            ),
            'pansou_ttl' => array(
                'type' => 'int', 'group' => 'pansou', 'min' => 0, 'max' => 43200, 'unit' => '分钟',
                'label' => '搜索结果缓存时长',
                'tip'   => '同一个关键词在此时长内不重复请求。0 = 不缓存。建议 720（12 小时）。',
            ),
        );

        /* ---------------- 资源请求（注册 + 求片区） ---------------- */
        $out['req_open'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '开放注册（推荐）', '0' => '关闭注册'),
            'label' => '是否开放注册',
            'tip'   => '关闭后只有已有账号能登录，前台注册页显示「暂未开放注册」。',
        );
        $out['req_logon'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '必须先登录（推荐）', '0' => '游客也能提交'),
            'label' => '提交资源请求是否要求登录',
            'tip'   => '默认要求先注册登录，这样用户能在页面里随时看到处理进度。',
        );
        $out['req_audit'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('0' => '注册后直接可用（推荐）', '1' => '注册后需管理员审核'),
            'label' => '注册是否需要审核',
            'tip'   => '开启后新账号为「待审核」，在「资源请求 → 注册用户」里点「通过」即可放开。',
        );
        $out['req_verify'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '开启（推荐）', '0' => '关闭'),
            'label' => '注册是否使用算术验证码',
            'tip'   => '一道加减法，不依赖图形库（没装 GD 也能跑）。「同 IP 一小时最多注册 3 个」的限流始终生效。',
        );
        $out['req_guest_view'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '允许（推荐）', '0' => '仅登录可见'),
            'label' => '未登录访客能否浏览请求列表',
            'tip'   => '开放浏览能带来自然搜索流量；联系方式与站长回复始终只有提交者本人可见。',
        );
        $out['req_public'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '显示（推荐）', '0' => '不显示'),
            'label' => '请求页是否展示「大家都在找」列表',
            'tip'   => '关掉之后只剩提交表单与自己的请求。',
        );
        $out['req_contact'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '收集（推荐）', '0' => '不收集'),
            'label' => '是否收集联系方式',
            'tip'   => '默认用注册邮箱预填。不需要就关掉，表单更短、转化更高。',
        );
        $out['req_nav'] = array(
            'type' => 'select', 'group' => 'req',
            'options' => array('1' => '显示（推荐）', '0' => '隐藏'),
            'label' => '顶部导航显示「资源请求」入口',
            'tip'   => '就算「顶部导航显示」里也勾了资源请求，也不会重复出现。',
        );
        $out['req_daily'] = array(
            'type' => 'int', 'group' => 'req', 'min' => 0, 'max' => 999, 'unit' => '条',
            'label' => '每人每天最多提交几条',
            'tip'   => '0 = 不限制。建议 3 ~ 10：既能挡脚本刷，也不会挡住正常用户。',
        );
        $out['req_mintitle'] = array(
            'type' => 'int', 'group' => 'req', 'min' => 1, 'max' => 60, 'unit' => '字',
            'label' => '资源名称最少字数',
            'tip'   => '挡住「1」「求」这类无效提交，建议 2。',
        );
        $out['req_page_title'] = array(
            'type' => 'text', 'group' => 'req',
            'label' => '页面名称',
            'tip'   => '导航入口与页面大标题都用它，默认「资源请求」。改成「求片区」也行。',
        );
        $out['req_page_intro'] = array(
            'type' => 'textarea', 'group' => 'req',
            'label' => '页面说明',
            'tip'   => '显示在标题下面，说说怎么提交、多久处理一次。留空用内置文案。',
        );
        $out['req_notice'] = array(
            'type' => 'textarea', 'group' => 'req',
            'label' => '页面公告',
            'tip'   => '显示在提交框上方的公告条，适合写「本周优先处理电影类请求」。留空不显示。',
        );

        /* 网盘账号：按「网盘列表」循环生成三件套（Cookie / 默认转存目录 / 临时资源目录），
           新增一种网盘只需改 self::panAccounts()，这里不用动。 */
        foreach (self::panAccounts() as $code => $name) {
            $out['pan_' . $code . '_cookie'] = array(
                'type' => 'textarea', 'group' => 'pan',
                'label' => $name . ' · Cookie',
                'tip'   => '从浏览器登录该网盘后复制整串 Cookie。本页只负责保存与检测，不会主动登录你的账号。',
            );
            $out['pan_' . $code . '_dir'] = array(
                'type' => 'text', 'group' => 'pan',
                'label' => $name . ' · 默认转存目录',
                'tip'   => '转存后的存放目录，例：/资源/电影。留空表示网盘根目录。',
            );
            $out['pan_' . $code . '_tmp'] = array(
                'type' => 'text', 'group' => 'pan',
                'label' => $name . ' · 临时资源目录',
                'tip'   => '转存过程中的中转目录，处理完可清理。留空表示与默认转存目录相同。',
            );
        }

        /* 要管理哪些网盘：决定「网盘链接」页显示哪几个页签 */
        $panOpt = array();
        foreach (self::panAccounts() as $code => $name) {
            $panOpt[$code] = $name;
        }
        $out['pan_group'] = array(
            'type' => 'list', 'group' => 'pan',
            'options' => $panOpt,
            'label' => '要管理哪些网盘',
            'tip'   => '勾选的网盘才会在「网盘链接」页出现页签。不用的就别勾，界面更清爽。',
        );

        return $out;
    }

    private static $cache    = null;   // array|null，已读出的覆盖值
    private static $ensured  = false;  // 是否已尝试建表
    private static $writable = false;  // 建表/写表是否可用

    /* ------------------------------------------------------------------ */
    /* 读                                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * 读全部覆盖值（只读，不建表）。
     * 表不存在 / 数据库不可用 → 返回空数组（等于「没有后台覆盖」）。
     */
    public static function all()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = array();
        if (!class_exists('PDO')) {
            return self::$cache;   // 没装 PDO 的环境不该在这里抛致命错误
        }
        try {
            $pdo  = DB::pdo();
            $rows = $pdo->query('SELECT `k`,`v` FROM `' . self::TABLE . '`')->fetchAll();
            $keys = self::keys();
            foreach ($rows as $r) {
                $k = isset($r['k']) ? (string) $r['k'] : '';
                if (in_array($k, $keys, true)) {
                    self::$cache[$k] = isset($r['v']) ? (string) $r['v'] : '';
                }
            }
        } catch (\Exception $e) {
            // 未建表 / 连不上库 / 无权限，一律视作「无覆盖」，不影响前台
            self::$cache = array();
        }
        return self::$cache;
    }

    /** 取单个覆盖值（空字符串代表「没有覆盖」） */
    public static function get($key, $default = '')
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : $default;
    }

    /** 该键是否被后台覆盖过（且非空） */
    public static function has($key)
    {
        $all = self::all();
        return isset($all[$key]) && $all[$key] !== '';
    }

    /* ------------------------------------------------------------------ */
    /* 写                                                                 */
    /* ------------------------------------------------------------------ */

    /** 确保设置表存在（后台保存前 / check.php 里调用），返回是否可写 */
    public static function ensureTable()
    {
        if (self::$ensured) {
            return self::$writable;
        }
        self::$ensured = true;
        try {
            DB::pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `' . self::TABLE . "` (
  `k`          VARCHAR(64) NOT NULL,
  `v`          TEXT,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$writable = true;
        } catch (\Exception $e) {
            self::$writable = false;
        }
        return self::$writable;
    }

    /**
     * 保存设置。值为空字符串 → 删除该键（回退到 config.php）。
     * 只认白名单里的键，其余忽略。
     */
    public static function set(array $kv)
    {
        if (!self::ensureTable()) {
            throw new \Exception('设置表不可写：数据库连不上，或该数据库用户没有建表权限');
        }
        $pdo  = DB::pdo();
        $keys = self::keys();
        $del  = $pdo->prepare('DELETE FROM `' . self::TABLE . '` WHERE `k` = ?');
        $ins  = $pdo->prepare('INSERT INTO `' . self::TABLE . '` (`k`,`v`,`updated_at`) VALUES (?,?,NOW())');
        $saved = 0;
        foreach ($kv as $k => $v) {
            if (!in_array($k, $keys, true)) {
                continue;
            }
            $v = is_array($v) ? self::listToStr($v) : trim((string) $v);
            $del->execute(array($k));                       // 先删后插，兼容所有 MySQL 版本
            if ($v === '') {
                continue;                                   // 留空 = 清除覆盖
            }
            $ins->execute(array($k, $v));
            $saved++;
        }
        self::$cache = null;                                // 失效缓存，让本次请求后续读到新值
        return $saved;
    }

    /** 清除指定键的覆盖（不传则清空全部），恢复为 config.php 的值 */
    public static function clear($key = '')
    {
        $keys = $key === '' ? self::keys() : array($key);
        $kv   = array();
        foreach ($keys as $k) {
            $kv[$k] = '';
        }
        return self::set($kv);
    }

    /* ------------------------------------------------------------------ */
    /* 列表值转换（逗号分隔字符串 <-> 数组）                              */
    /* ------------------------------------------------------------------ */

    /** 数组 -> 存储字符串 */
    public static function listToStr($arr)
    {
        if (is_string($arr)) {
            $arr = self::strToList($arr);
        }
        if (!is_array($arr)) {
            return '';
        }
        $out = array();
        foreach ($arr as $x) {
            $x = trim((string) $x);
            if ($x !== '' && !in_array($x, $out, true)) {
                $out[] = $x;
            }
        }
        return implode(',', $out);
    }

    /** 存储字符串 -> 数组（兼容中英文逗号 / 换行 / 顿号） */
    public static function strToList($s)
    {
        $s = str_replace(array('，', '、', '；', ';', "\r", "\n"), array(',', ',', ',', ',', ',', ','), (string) $s);
        $out = array();
        foreach (explode(',', $s) as $x) {
            $x = trim($x);
            if ($x !== '' && !in_array($x, $out, true)) {
                $out[] = $x;
            }
        }
        return $out;
    }
}
