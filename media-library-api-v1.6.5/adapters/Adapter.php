<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 数据源适配器接口
 * 新增一个来源（如豆瓣/Steam）只需实现本接口并在 config 的 adapters 中登记。
 *
 * 归一化结构（统一字段，供统一 API / 后台使用）：
 *  [
 *    'source'        => 'tmdb',
 *    'source_id'     => '550',
 *    'type'          => 'movie' | 'tv' | 'person' | 'game' | 'anime',
 *    'title'         => '标题',
 *    'original_title'=> '原标题',
 *    'year'          => 2009,
 *    'rating'        => 8.5,
 *    'vote_count'    => 12345,
 *    'genres'        => ['动作','科幻'],
 *    'poster'        => 'https://.../poster.jpg',   // 完整 URL
 *    'backdrop'      => 'https://.../backdrop.jpg', // 完整 URL
 *    'overview'      => '简介',
 *    'extra'         => [...],                       // 源特有字段
 *  ]
 */
interface Adapter
{
    public function getSource();
    public function search($query,$page,$type = '');
    public function detail($type,$id);
    /**
     * 取热门（便捷入口）。
     * @param string $window 'day' | 'week'
     * @param int    $limit  最多返回多少条（各源官方单页上限见 Core\Sync::profiles()）
     * @param string $order  'hot'（最热）| 'popular'（最多人看）| 'both'
     */
    public function trending($window = 'week', $limit = 20, $order = 'both');
    /**
     * 拉取「一个具体接口」并归一化 —— 由 Core\Sync::endpointsFor() 生成接口清单后调用。
     * @param array $ep ['path'=>'/trending/all/week?page=1','label'=>'…','kind'=>'hot|popular|discover','page'=>1]
     * @return array 归一化条目数组（结构同 normalize() 的返回）
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both');
    public function normalize(array $raw,$type);
}
