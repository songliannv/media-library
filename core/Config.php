<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 配置加载器
 * ==================================================================
 * 配置有两个来源，优先级从高到低：
 *   1) 数据库 app_settings 表（后台「系统设置」页保存的）  ← Core\Settings
 *   2) config/config.php（安装向导生成的文件，兜底）
 *
 * 也就是说：后台改过的项生效，没改过的项仍然读 config.php。
 * 这样即使是只读的配置文件、或整包覆盖升级，也不会把设置冲掉。
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / fn() / 返回类型 等 7.0+ 语法）
 */
class Config
{
    /** 合并后的配置（后台覆盖已生效），业务代码全部读它 */
    private static $cfg  = null;

    /** config/config.php 的原始内容（未叠加后台覆盖），后台用来显示「文件里的值」 */
    private static $base = null;

    public static function load()
    {
        if (self::$cfg !== null) {
            return self::$cfg;
        }
        $file = __DIR__ . '/../config/config.php';
        if (!file_exists($file)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'config.php missing, run: php tools/install-cli.php'));
            exit;
        }
        $cfg = require $file;
        if (!is_array($cfg)) {
            $cfg = array();
        }
        // 先落基础配置：万一下面读数据库时又间接调用 load()（DB 需要 db 配置），
        // 能立刻拿到数组返回，不会无限递归。
        self::$base = $cfg;
        self::$cfg  = $cfg;

        self::applyOverlay();
        return self::$cfg;
    }

    /**
     * 把后台保存的设置叠加到配置上。
     * 空值不覆盖（后台留空 = 沿用 config.php）。
     * 全程容错：数据库不可用/未建表时静默跳过，绝不影响前台。
     */
    private static function applyOverlay()
    {
        $file = __DIR__ . '/Settings.php';
        if (!file_exists($file)) {
            return;
        }
        require_once $file;
        if (!class_exists('\\Core\\Settings')) {
            return;
        }
        $s = \Core\Settings::all();
        if (!$s) {
            return;
        }

        if (!empty($s['admin_token'])) {
            self::$cfg['admin']['token'] = $s['admin_token'];
        }
        if (!empty($s['tmdb_api_key'])) {
            self::$cfg['tmdb']['api_key'] = $s['tmdb_api_key'];
        }
        if (!empty($s['rawg_api_key'])) {
            self::$cfg['rawg']['api_key'] = $s['rawg_api_key'];
        }
        if (!empty($s['adapters'])) {
            $list = \Core\Settings::strToList($s['adapters']);
            if ($list) {
                self::$cfg['adapters'] = $list;
            }
        }
        if (!empty($s['pan_types'])) {
            $list = \Core\Settings::strToList($s['pan_types']);
            if ($list) {
                self::$cfg['pan_types'] = $list;
            }
        }

        /* 采集（同步）参数：后台「同步采集」页保存的值叠加到 $cfg['sync']。
           注意这里**不能用 empty()** 判断 —— sync_build=0、sync_interval=0 都是合法值。 */
        $syncMap = \Core\Settings::syncMap();
        $hasSync = false;
        foreach ($syncMap as $k => $child) {
            if (!array_key_exists($k, $s) || $s[$k] === '') {
                continue;
            }
            if (!$hasSync) {
                if (!isset(self::$cfg['sync']) || !is_array(self::$cfg['sync'])) {
                    self::$cfg['sync'] = array();
                }
                $hasSync = true;
            }
            self::$cfg['sync'][$child] = $s[$k];   // 仍是字符串，Core\Sync 会规范化
        }

        /* 站点设置 5 组（基础设置 / SEO / 跳转扫码 / 网盘账号 / 接口配置）：
           键与子键的对应关系集中在 Core\Settings::groupMaps()，这里只做通用叠加。
           同样「空值 = 不覆盖」，但 0 是合法值 —— 故用 array_key_exists + === '' 判断，
           绝不能用 empty()，否则 pan_group 之类的「全部不勾」会被当成「没设置」。 */
        $groupMaps = \Core\Settings::groupMaps();
        foreach ($groupMaps as $section => $map) {
            $hit = false;
            foreach ($map as $k => $child) {
                if (!array_key_exists($k, $s) || $s[$k] === '') {
                    continue;
                }
                if (!$hit) {
                    if (!isset(self::$cfg[$section]) || !is_array(self::$cfg[$section])) {
                        self::$cfg[$section] = array();
                    }
                    $hit = true;
                }
                self::$cfg[$section][$child] = $s[$k];
            }
        }
    }

    /** 取合并后的配置段 */
    public static function get($key, $default = null)
    {
        $c = self::load();
        return (isset($c[$key]) ? $c[$key] : $default);
    }

    /**
     * 取二级配置，如 sub('cache','ttl_days',7)、sub('admin','token','')
     */
    public static function sub($key, $child, $default = null)
    {
        $c = self::load();
        if (isset($c[$key]) && is_array($c[$key]) && isset($c[$key][$child])) {
            return $c[$key][$child];
        }
        return $default;
    }

    /** 取「配置文件里的原始值」（不含后台覆盖），后台设置页用来做对比/占位提示 */
    public static function base($key, $default = null)
    {
        self::load();
        return (isset(self::$base[$key]) ? self::$base[$key] : $default);
    }

    /** 取「配置文件里的原始二级值」 */
    public static function baseSub($key, $child, $default = null)
    {
        self::load();
        if (isset(self::$base[$key]) && is_array(self::$base[$key]) && isset(self::$base[$key][$child])) {
            return self::$base[$key][$child];
        }
        return $default;
    }

    /**
     * 网盘类型列表（后台「下载链接」下拉用）
     * 优先级：后台「系统设置」保存的 pan_types  >  config/pan_types.php  >  内置默认
     */
    public static function panTypes()
    {
        $c = self::load();
        if (isset($c['pan_types']) && is_array($c['pan_types']) && $c['pan_types']) {
            return array_values($c['pan_types']);
        }

        $file = __DIR__ . '/../config/pan_types.php';
        if (file_exists($file)) {
            $list = require $file;
            if (is_array($list) && $list) {
                $out = array();
                foreach ($list as $x) {
                    if (is_string($x) && $x !== '') {
                        $out[] = $x;
                    }
                }
                if ($out) {
                    return $out;
                }
            }
        }
        return array('夸克', '迅雷', '光鸭', '百度', 'UC'); // 兜底
    }
}
