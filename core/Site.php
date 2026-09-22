<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Config.php';

/**
 * 站点层：基础设置 / SEO / 跳转与扫码 / 图片上传
 * ==================================================================
 * 数据来源：app_settings（后台「站点设置」保存） > config/config.php 的 site|seo|redirect 段
 * 全部走 Config::sub()，所以「后台留空 = 用配置文件 / 内置默认」的语义天然成立。
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / fn() / 返回类型 / 标量类型提示 等 7.0+ 语法）
 */
class Site
{
    /* ================================================================ */
    /* 基础设置                                                          */
    /* ================================================================ */

    /** 站点信息（后台「基础设置」页的 9 项） */
    public static function info()
    {
        return array(
            'name'         => (string) Config::sub('site', 'name', '媒资资料库'),
            'name_hide'    => self::flag(Config::sub('site', 'name_hide', 0)),
            'slogan'       => (string) Config::sub('site', 'slogan', ''),
            'logo'         => (string) Config::sub('site', 'logo', ''),
            'favicon'      => (string) Config::sub('site', 'favicon', ''),
            'url'          => rtrim((string) Config::sub('site', 'url', ''), '/'),
            'footer_intro' => (string) Config::sub('site', 'footer_intro', ''),
            'declare'      => (string) Config::sub('site', 'declare', ''),
            'copyright'    => (string) Config::sub('site', 'copyright', ''),
        );
    }

    /** '1' / 'on' / 'true' / 1 都当开启 */
    public static function flag($v)
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));
        return in_array($s, array('1', 'on', 'true', 'yes', 'y'), true);
    }

    /** 站点头像/图标地址：支持填绝对网址，也支持上传后的相对路径 */
    public static function asset($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $path) || strpos($path, 'data:') === 0 || $path[0] === '/') {
            return $path;
        }
        // 上传返回的是「uisc-assets/xxx.jpg」这种相对路径，拼一次即可
        if (strpos($path, 'uisc-assets/') === 0) {
            return '/' . $path;
        }
        return '/uisc-assets/' . ltrim($path, '/');
    }

    /** 站点根地址（用于 canonical / sitemap / robots） */
    public static function baseUrl()
    {
        $u = rtrim((string) Config::sub('site', 'url', ''), '/');
        if ($u !== '') {
            return $u;
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        if ($host === '') {
            return '';
        }
        return ($https ? 'https://' : 'http://') . $host;
    }

    /* ================================================================ */
    /* SEO                                                               */
    /* ================================================================ */

    /**
     * 生成某个页面的 SEO 三件套。
     * $ctx: home | list | item
     * $data: 上下文数据（title/type_label/year/region/type/sort/page/id/overview…）
     * 返回 array(title, keywords, description, canonical)
     */
    public static function seo($ctx, array $data = array())
    {
        $info = self::info();
        $site = $info['name'];
        $home = (string) Config::sub('seo', 'title', '');
        if ($home === '') {
            $home = $site . ($info['slogan'] !== '' ? ' - ' . $info['slogan'] : '');
        }
        $kwBase = (string) Config::sub('seo', 'keywords', '');
        $descBase = (string) Config::sub('seo', 'description', '');
        $base = self::baseUrl();

        if ($ctx === 'item') {
            $title  = isset($data['title']) ? trim((string) $data['title']) : '';
            $type   = isset($data['type_label']) ? trim((string) $data['type_label']) : '';
            $year   = isset($data['year']) ? trim((string) $data['year']) : '';
            $region = isset($data['region']) ? trim((string) $data['region']) : '';
            $pat = (string) Config::sub('seo', 'pattern_item', '{title}({year}) - {type} - {site}');
            $t = self::fill($pat, array(
                '{title}' => $title, '{year}' => $year, '{type}' => $type,
                '{site}' => $site, '{region}' => $region,
            ));

            $kwArr = array();
            foreach (array($title, $type, $year . '年', $region) as $x) {
                if ($x !== '') {
                    $kwArr[] = $x;
                }
            }
            if ($kwBase !== '') {
                foreach (preg_split('/[,，、\s]+/u', $kwBase) as $x) {
                    $x = trim($x);
                    if ($x !== '') {
                        $kwArr[] = $x;
                    }
                }
            }

            $len = (int) Config::sub('seo', 'item_desc_len', 120);
            $desc = '';
            if ($len > 0 && isset($data['overview'])) {
                $desc = self::clip((string) $data['overview'], $len);
            }
            if ($desc === '') {
                $desc = $descBase;
            }
            if ($desc === '') {
                $desc = self::clip($title . '：' . $type . ($year !== '' ? '，' . $year . '年' : '') . '。', 120);
            }

            $id = isset($data['id']) ? (int) $data['id'] : 0;
            return array(
                'title'       => self::tidy($t),
                'keywords'    => implode(',', array_slice(self::unique($kwArr), 0, 20)),
                'description' => $desc,
                'canonical'   => ($base !== '' && $id > 0) ? ($base . '/uisc/' . $id . '.html') : '',
            );
        }

        if ($ctx === 'list') {
            $type  = isset($data['type_label']) ? trim((string) $data['type_label']) : '';
            $sort  = isset($data['sort_label']) ? trim((string) $data['sort_label']) : '';
            $page  = isset($data['page']) ? (int) $data['page'] : 1;
            $pat = (string) Config::sub('seo', 'pattern_type', '{type}{sort} - 第{page}页 - {site}');
            $t = self::fill($pat, array(
                '{type}' => $type, '{sort}' => $sort !== '' ? ' · ' . $sort : '',
                '{page}' => (string) max(1, $page), '{site}' => $site,
            ));

            $kwArr = array();
            foreach (array($type, $sort) as $x) {
                if ($x !== '') {
                    $kwArr[] = $x;
                }
            }
            if ($kwBase !== '') {
                foreach (preg_split('/[,，、\s]+/u', $kwBase) as $x) {
                    $x = trim($x);
                    if ($x !== '') {
                        $kwArr[] = $x;
                    }
                }
            }
            $desc = $descBase;
            if ($desc === '') {
                $desc = self::clip(($type !== '' ? $type : '全部内容') . '：' . $info['slogan'], 150);
            }
            return array(
                'title'       => self::tidy($t),
                'keywords'    => implode(',', array_slice(self::unique($kwArr), 0, 20)),
                'description' => $desc,
                'canonical'   => $base,
            );
        }

        // home
        return array(
            'title'       => $home,
            'keywords'    => $kwBase,
            'description' => $descBase !== '' ? $descBase : $info['slogan'],
            'canonical'   => $base !== '' ? ($base . '/') : '',
        );
    }

    /** 把 seo() 的产物拼成可插入 <head> 的 HTML 片段 */
    public static function headMeta($ctx, array $data = array())
    {
        $s = self::seo($ctx, $data);
        $h = '<title>' . self::h($s['title']) . '</title>' . "\n";
        if ($s['keywords'] !== '') {
            $h .= '<meta name="keywords" content="' . self::h($s['keywords']) . '">' . "\n";
        }
        if ($s['description'] !== '') {
            $h .= '<meta name="description" content="' . self::h($s['description']) . '">' . "\n";
        }
        $info = self::info();
        if ($s['canonical'] !== '') {
            $h .= '<link rel="canonical" href="' . self::h($s['canonical']) . '">' . "\n";
        }
        if ($info['favicon'] !== '') {
            $h .= '<link rel="icon" href="' . self::h(self::asset($info['favicon'])) . '">' . "\n";
        }
        $h .= self::openGraph($ctx, $s, $info, $data);
        $code = (string) Config::sub('seo', 'stats_code', '');
        if (trim($code) !== '') {
            $h .= "<!-- 统计代码（后台「SEO 设置」填写） -->\n" . $code . "\n";
        }
        return $h;
    }

    /** 社交分享卡片（微信/QQ 分享时显示站点 logo 与描述） */
    private static function openGraph($ctx, array $s, array $info, array $data)
    {
        $h = '<meta property="og:type" content="' . ($ctx === 'item' ? 'video.other' : 'website') . '">' . "\n";
        $h .= '<meta property="og:site_name" content="' . self::h($info['name']) . '">' . "\n";
        $h .= '<meta property="og:title" content="' . self::h($s['title']) . '">' . "\n";
        if ($s['description'] !== '') {
            $h .= '<meta property="og:description" content="' . self::h($s['description']) . '">' . "\n";
        }
        if ($s['canonical'] !== '') {
            $h .= '<meta property="og:url" content="' . self::h($s['canonical']) . '">' . "\n";
        }
        $img = '';
        if ($ctx === 'item' && !empty($data['poster'])) {
            $img = (string) $data['poster'];
        } elseif ($info['logo'] !== '') {
            $img = self::asset($info['logo']);
        }
        if ($img !== '') {
            $h .= '<meta property="og:image" content="' . self::h($img) . '">' . "\n";
        }
        return $h;
    }

    /** robots.txt 内容（后台可覆盖） */
    public static function robotsTxt()
    {
        $custom = trim((string) Config::sub('seo', 'robots', ''));
        if ($custom !== '') {
            return $custom . "\n";
        }
        $base = self::baseUrl();
        $out  = "User-agent: *\n";
        $out .= "Allow: /\n";
        $out .= "Disallow: /admin\n";
        $out .= "Disallow: /api/\n";
        $out .= "Disallow: /3/\n";
        $out .= "Disallow: /track\n";
        $out .= "Disallow: /check\n";
        if (self::flag(Config::sub('seo', 'sitemap', 1)) && $base !== '') {
            $out .= "\nSitemap: " . $base . "/sitemap.xml\n";
        }
        return $out;
    }

    /** sitemap.xml（按已入库条目动态生成，只取 id 避免依赖具体字段） */
    public static function sitemapXml()
    {
        $base = self::baseUrl();
        $limit = (int) Config::sub('seo', 'sitemap_size', 5000);
        if ($limit < 1) {
            $limit = 5000;
        }
        $ids = array();
        try {
            $st = DB::pdo()->query('SELECT `id` FROM `media_items` ORDER BY `id` DESC LIMIT ' . $limit);
            $ids = $st->fetchAll(\PDO::FETCH_COLUMN, 0);
        } catch (\Exception $e) {
            $ids = array();
        }
        $today = date('Y-m-d');
        $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if ($base !== '') {
            $x .= "  <url><loc>" . self::h($base . '/') . "</loc><lastmod>" . $today . "</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>\n";
        }
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $x .= "  <url><loc>" . self::h($base . '/uisc/' . $id . '.html') . "</loc><lastmod>" . $today . "</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
        }
        $x .= '</urlset>' . "\n";
        return $x;
    }

    /* ================================================================ */
    /* 跳转 / 扫码                                                       */
    /* ================================================================ */

    /** 当前配置的拦截规则；返回 null 表示不拦截 */
    public static function redirectRule()
    {
        $mode   = trim((string) Config::sub('redirect', 'mode', 'jump_scan'));
        $target = trim((string) Config::sub('redirect', 'target', ''));
        $qr     = trim((string) Config::sub('redirect', 'qr', ''));
        $tip    = trim((string) Config::sub('redirect', 'qr_tip', ''));
        if ($tip === '') {
            $tip = '请用手机扫码访问本站';
        }
        if (!in_array($mode, array('jump_scan', 'jump_only', 'scan_only'), true)) {
            return null;
        }
        if ($target === '' && $qr === '') {
            return null;                     // 两样都没配 = 不拦
        }
        if ($target === '' && $mode !== 'scan_only') {
            $mode = 'scan_only';             // 没填跳转地址，自动降级为仅扫码
        }
        if ($qr === '' && $mode === 'scan_only') {
            return null;                     // 要扫码却没传二维码 = 不拦
        }
        return array(
            'mode'   => $mode,
            'target' => $target,
            'qr'     => $qr !== '' ? self::asset($qr) : '',
            'tip'    => $tip,
            'mobile' => self::flag(Config::sub('redirect', 'mobile', 0)),
        );
    }

    /** 简单判定是否 PC 访客（拿不到 UA 时按 PC 处理，宁可少拦不可错拦） */
    public static function isPc()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string) $_SERVER['HTTP_USER_AGENT']) : '';
        if ($ua === '') {
            return true;
        }
        $mobile = array('mobile', 'android', 'iphone', 'ipod', 'windows phone',
                        'micromessenger', 'blackberry', 'opera mini', 'iemobile');
        foreach ($mobile as $m) {
            if (strpos($ua, $m) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * 拦截页 HTML。返回 null 表示「本次访问不用拦」。
     * ★ 只拦首页入口：内页是已生成的静态文件，拦住反而伤收录。
     */
    public static function interceptHtml()
    {
        $rule = self::redirectRule();
        if ($rule === null) {
            return null;
        }
        if (!$rule['mobile'] && !self::isPc()) {
            return null;
        }
        $info = self::info();
        $name = self::h($info['name']);
        $tip  = self::h($rule['tip']);
        $qr   = self::h($rule['qr']);
        $tgt  = self::h($rule['target']);
        $showQr   = ($rule['mode'] === 'scan_only' || $rule['mode'] === 'jump_scan');
        $showJump = ($rule['mode'] === 'jump_only' || $rule['mode'] === 'jump_scan');

        $h  = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">' . "\n";
        $h .= '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
        $h .= '<meta name="robots" content="noindex,nofollow">' . "\n";
        $h .= '<title>' . $tip . ' · ' . $name . '</title>' . "\n";
        if ($showJump && $tgt !== '') {
            $h .= '<meta http-equiv="refresh" content="' . ($showQr ? '3' : '1') . ';url=' . $tgt . '">' . "\n";
        }
        $h .= '<style>'
            . '*{box-sizing:border-box;margin:0;padding:0}'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;'
            . 'background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}'
            . '.card{background:#1e293b;border:1px solid #334155;border-radius:18px;padding:36px 30px;max-width:420px;width:100%;text-align:center}'
            . '.qr{width:220px;height:220px;object-fit:contain;border-radius:12px;background:#fff;padding:8px;margin:0 auto 18px;display:block}'
            . '.ph{width:220px;height:220px;border:1px dashed #475569;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;color:#94a3b8;font-size:13px}'
            . 'h1{font-size:17px;font-weight:600;margin-bottom:10px}'
            . 'p{color:#94a3b8;font-size:13px;line-height:1.8}'
            . 'a{display:inline-block;margin-top:18px;color:#38bdf8;font-size:13px;text-decoration:none}'
            . '</style></head><body><div class="card">';
        if ($showQr) {
            if ($qr !== '') {
                $h .= '<img class="qr" src="' . $qr . '" alt="扫码访问">';
            } else {
                $h .= '<div class="ph">二维码未配置</div>';
            }
        }
        $h .= '<h1>' . $tip . '</h1>';
        if ($showJump && $tgt !== '') {
            $h .= '<p>没反应？<a href="' . $tgt . '">点这里直接访问</a></p>';
        }
        $h .= '</div></body></html>';
        return $h;
    }

    /* ================================================================ */
    /* 图片上传                                                          */
    /* ================================================================ */

    /** 上传目录（站点根 /uisc-assets，与静态内页 /uisc/ 分开，避免被重建清掉） */
    public static function uploadDir()
    {
        return __DIR__ . '/../uisc-assets';
    }

    /**
     * 保存后台传来的图片。$file 为 $_FILES 里的单个元素。
     * 安全要点：不看用户文件名、只认图片真实类型、随机改名、限制体积。
     */
    public static function upload($file, $kind = 'site')
    {
        if (!is_array($file) || !isset($file['tmp_name']) || !isset($file['error'])) {
            throw new \Exception('没有收到上传文件');
        }
        if ((int) $file['error'] !== 0) {
            $msg = '上传失败（错误码 ' . (int) $file['error'] . '）';
            if ((int) $file['error'] === 1 || (int) $file['error'] === 2) {
                $msg = '图片太大了，请压到 2MB 以内';
            }
            throw new \Exception($msg);
        }
        $tmp  = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new \Exception('上传文件校验未通过');
        }
        $size = @filesize($tmp);
        if ($size === false || $size <= 0) {
            throw new \Exception('上传文件为空');
        }
        if ($size > 2 * 1024 * 1024) {
            throw new \Exception('图片太大了，请压到 2MB 以内');
        }

        $ext = self::detectImage($tmp);
        if ($ext === '') {
            throw new \Exception('这不是有效的图片（只支持 jpg / png / gif / webp / bmp / ico）');
        }

        $kind = preg_replace('/[^a-z0-9_-]/i', '', (string) $kind);
        if ($kind === '') {
            $kind = 'site';
        }
        $dir = self::uploadDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \Exception('无法创建 uploads 目录，请检查站点目录权限');
        }
        self::writeGuards($dir);

        $name = $kind . '-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $dest = rtrim($dir, '/\\') . '/' . $name;
        if (!@move_uploaded_file($tmp, $dest)) {
            throw new \Exception('写入失败，请检查 uisc-assets 目录是否可写');
        }
        @chmod($dest, 0644);

        return array(
            'path' => 'uisc-assets/' . $name,
            'url'  => self::baseUrl() . '/uisc-assets/' . $name,
            'size' => (int) $size,
        );
    }

    /** 用真实文件内容判定图片类型，返回扩展名；不是图片返回 '' */
    private static function detectImage($tmp)
    {
        $info = @getimagesize($tmp);
        if (is_array($info) && isset($info['mime'])) {
            $map = array(
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/bmp'  => 'bmp',
                'image/x-ms-bmp' => 'bmp',
                'image/vnd.microsoft.icon' => 'ico',
                'image/x-icon' => 'ico',
                'image/ico' => 'ico',
            );
            if (isset($map[$info['mime']])) {
                return $map[$info['mime']];
            }
        }
        // PHP 5.6 的 getimagesize 可能不认 ico，这里补一个文件头判定
        $fh = @fopen($tmp, 'rb');
        if ($fh) {
            $head = fread($fh, 8);
            fclose($fh);
            if (strlen($head) >= 4 && substr($head, 0, 4) === "\x00\x00\x01\x00") {
                return 'ico';
            }
        }
        return '';
    }

    /** 上传目录里放两个保险：拒访 index.php + 禁止执行脚本的 .htaccess */
    private static function writeGuards($dir)
    {
        $idx = rtrim($dir, '/\\') . '/index.php';
        if (!file_exists($idx)) {
            @file_put_contents($idx, "<?php\nhttp_response_code(404);\nexit('Not Found');\n");
        }
        $ht = rtrim($dir, '/\\') . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "php_flag engine off\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n  Require all denied\n</FilesMatch>\n");
        }
    }

    /* ================================================================ */
    /* 小工具                                                            */
    /* ================================================================ */

    public static function h($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** HTML 转纯文本（页脚的多行文本用） */
    public static function text($s)
    {
        return nl2br(self::h((string) $s));
    }

    /** 模板占位符填充 */
    private static function fill($tpl, array $map)
    {
        $keys = array();
        $vals = array();
        foreach ($map as $k => $v) {
            $keys[] = $k;
            $vals[] = (string) $v;
        }
        return str_replace($keys, $vals, (string) $tpl);
    }

    /** 清理填充后残留的空括号 / 多余分隔符 */
    private static function tidy($s)
    {
        $s = str_replace(array('()', '（）', '[]', '【】', '{}'), '', (string) $s);
        $s = preg_replace('/\s*[-–—]\s*[-–—]\s*/u', ' - ', $s);
        $s = preg_replace('/\s*·\s*·\s*/u', ' · ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\s*[-–—·]\s*$/u', '', $s);
        $s = preg_replace('/^\s*[-–—·]\s*/u', '', $s);
        $s = trim($s);
        return $s === '' ? '' : $s;
    }

    /** 按「字」截断（中英文混排都按字符算，避免截出半个字） */
    public static function clip($s, $len)
    {
        $s = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $s)));
        if ($s === '') {
            return '';
        }
        $len = (int) $len;
        if ($len < 1) {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s, 'UTF-8') <= $len) {
                return $s;
            }
            return mb_substr($s, 0, $len, 'UTF-8') . '…';
        }
        if (strlen($s) <= $len * 3) {
            return $s;
        }
        return substr($s, 0, $len) . '…';
    }

    /** 去重（保持顺序） */
    private static function unique(array $arr)
    {
        $out = array();
        foreach ($arr as $x) {
            $x = trim((string) $x);
            if ($x !== '' && !in_array($x, $out, true)) {
                $out[] = $x;
            }
        }
        return $out;
    }
}
