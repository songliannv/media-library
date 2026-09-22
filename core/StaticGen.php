<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 静态页生成器：把 media_items 每条渲染成 /uisc/{id}.html（自包含静态页，供博客/SEO 直接使用）。
 * Nginx 对 /uisc/*.html 直接走静态文件，无需进 PHP。
 */
class StaticGen
{
    public static function outDir()
    {
        return __DIR__ . '/../uisc';
    }

    /** 全量生成，返回生成条数 */
    public static function buildAll()
    {
        $dir = self::outDir();
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }
        $ids = CacheStore::allIds();
        $n = 0;
        foreach ($ids as $id) {
            $row = CacheStore::getRowById((int) $id);
            if (!$row) { continue; }
            file_put_contents($dir . '/' . (int) $id . '.html', Views::renderItemPage($row));
            $n++;
        }
        return $n;
    }

    /** 生成单条（同步/编辑后即时产出） */
    public static function buildOne($id)
    {
        $dir = self::outDir();
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }
        $row = CacheStore::getRowById($id);
        if (!$row) { return false; }
        file_put_contents($dir . '/' . $id . '.html', Views::renderItemPage($row));
        return true;
    }
}
