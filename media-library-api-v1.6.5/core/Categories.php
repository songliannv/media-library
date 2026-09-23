<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 内容分类单一来源（short / movie / tv / anime / variety / game）。
 * 后台「按分类建栏目 + 新增」、主页类型筛选、内页扩展字段渲染 都从这里取，
 * 新增分类只需改本文件，无需动路由与前台。
 */
class Categories
{
    /** 站点使用的 6 大分类（顺序即导航顺序） */
    const TYPES = ['short', 'movie', 'tv', 'anime', 'variety', 'game'];

    /** 类型中文标签 */
    const LABELS = [
        'short'   => '短剧',
        'movie'   => '电影',
        'tv'      => '电视剧',
        'anime'   => '动漫',
        'variety' => '综艺',
        'game'    => '游戏',
        'person'  => '人物',   // TMDB 人物，库中存在但不单独建栏目
        'manual'  => '自定义',
    ];

    /**
     * 每个分类「新增/编辑」时除公共字段外的扩展字段。
     * 公共字段（所有分类通用）：标题/原标题/年份/评分/类型标签/海报/背景图/简介/下载地址
     * 这里只列「分类特有的」字段，存进 media_items.extra(JSON)。
     */
    const EXTRA_FIELDS = [
        'short'   => [
            ['k' => 'episodes', 'label' => '集数',   'type' => 'text',  'ph' => '如 80'],
            ['k' => 'director', 'label' => '导演',   'type' => 'text',  'ph' => '导演名'],
            ['k' => 'region',   'label' => '地区',   'type' => 'text',  'ph' => '如 中国大陆'],
        ],
        'movie'   => [
            ['k' => 'runtime',  'label' => '时长(分钟)', 'type' => 'text', 'ph' => '如 128'],
            ['k' => 'director', 'label' => '导演',       'type' => 'text', 'ph' => '导演名'],
        ],
        'tv'      => [
            ['k' => 'seasons',  'label' => '季数', 'type' => 'text', 'ph' => '如 3'],
            ['k' => 'episodes', 'label' => '集数', 'type' => 'text', 'ph' => '如 24'],
        ],
        'anime'   => [
            ['k' => 'episodes', 'label' => '话数',     'type' => 'text', 'ph' => '如 12'],
            ['k' => 'studio',   'label' => '制作公司', 'type' => 'text', 'ph' => '如 MAPPA'],
        ],
        'variety' => [
            ['k' => 'host',     'label' => '主持人', 'type' => 'text', 'ph' => '如 何炅'],
            ['k' => 'episodes', 'label' => '期数',   'type' => 'text', 'ph' => '如 12'],
        ],
        'game'    => [
            ['k' => 'platforms', 'label' => '平台',   'type' => 'text', 'ph' => '如 PC/PS5/Switch'],
            ['k' => 'developer', 'label' => '开发商', 'type' => 'text', 'ph' => '如 CD Projekt'],
        ],
    ];

    /** 扩展字段 key → 中文展示名（内页渲染用） */
    const EXTRA_LABELS = [
        'episodes' => '集数',
        'director' => '导演',
        'region'   => '地区',
        'runtime'  => '时长',
        'seasons'  => '季数',
        'studio'   => '制作公司',
        'host'     => '主持人',
        'platforms'=> '平台',
        'developer' => '开发商',
    ];

    /** 对外公开的分类 schema（后台前端拉取后动态生成表单） */
    public static function schema()
    {
        return [
            'types'        => self::TYPES,
            'labels'       => self::LABELS,
            'extra_fields' => self::EXTRA_FIELDS,
            'extra_labels' => self::EXTRA_LABELS,
        ];
    }

    public static function label($t)
    {
        return (isset(self::LABELS[$t]) ? self::LABELS[$t] : $t);
    }
}
