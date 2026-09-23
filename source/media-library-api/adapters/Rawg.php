<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Http;

/**
 * RAWG 适配器（游戏） https://api.rawg.io/api
 * 免费 key 申请：https://rawg.io/apidocs
 */
class Rawg implements Adapter
{
    private $cfg;

    public function __construct()
    {
        $this->cfg = Config::get('rawg');
    }

    public function getSource()
    {
        return 'rawg';
    }

    private function url($path, array $q = [])
    {
        $q['key'] = $this->cfg['api_key'];
        return $this->cfg['base'] . $path . '?' . http_build_query($q);
    }

    public function normalize(array $raw,$type)
    {
        $genres = [];
        foreach ((isset($raw['genres']) ? $raw['genres'] : []) as $g) { $genres[] = (isset($g['name']) ? $g['name'] : ''); }
        $year = !empty($raw['released']) ? (int) substr($raw['released'], 0, 4) : null;
        return [
            'source'         => 'rawg',
            'source_id'      => (string) ((isset($raw['id']) ? $raw['id'] : '')),
            'type'           => 'game',
            'title'          => (isset($raw['name']) ? $raw['name'] : ''),
            'original_title' => (isset($raw['name']) ? $raw['name'] : ''),
            'year'           => $year,
            'rating'         => (isset($raw['rating']) ? $raw['rating'] : null),
            'vote_count'     => (isset($raw['ratings_count']) ? $raw['ratings_count'] : ((isset($raw['reviews_count']) ? $raw['reviews_count'] : null))),
            'genres'         => array_filter($genres),
            'poster'         => (isset($raw['background_image']) ? $raw['background_image'] : ''),
            'backdrop'       => (isset($raw['background_image']) ? $raw['background_image'] : ''),
            'overview'       => (isset($raw['description_raw']) ? $raw['description_raw'] : ((isset($raw['description']) ? $raw['description'] : ''))),
            'extra'          => ['metacritic' => (isset($raw['metacritic']) ? $raw['metacritic'] : null), 'playtime' => (isset($raw['playtime']) ? $raw['playtime'] : null)],
        ];
    }

    public function search($query,$page,$type = '')
    {
        $raw = Http::get($this->url('/games', ['search' => $query, 'page' => $page, 'page_size' => 20]))['data'];
        $out = [];
        foreach ((isset($raw['results']) ? $raw['results'] : []) as $r) { $out[] = $this->normalize($r, 'game'); }
        return ['total' => (isset($raw['count']) ? $raw['count'] : count($out)), 'items' => $out];
    }

    public function detail($type,$id)
    {
        $raw = Http::get($this->url("/games/{$id}"))['data'];
        if (empty($raw['id'])) return null;
        return $this->normalize($raw, 'game');
    }

    /** 取热门：hot=最多人添加 / popular=评分人气最高 */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        $out  = [];
        $seen = [];
        $eps  = [];
        if ($order === 'hot' || $order === 'both')     { $eps[] = ['path' => '/games?ordering=-added',  'label' => '最多人添加', 'kind' => 'hot', 'page' => 1]; }
        if ($order === 'popular' || $order === 'both') { $eps[] = ['path' => '/games?ordering=-rating', 'label' => '评分人气最高', 'kind' => 'popular', 'page' => 1]; }
        foreach ($eps as $ep) {
            try { $items = $this->trendingFetch($ep, $limit, $order); }
            catch (\Exception $ex) { continue; }
            foreach ($items as $it) {
                $k = $it['source_id'];
                if (isset($seen[$k])) { continue; }
                $seen[$k] = true;
                $out[] = $it;
            }
            if (count($out) >= $limit) { break; }
        }
        return array_slice($out, 0, $limit);
    }

    /**
     * 拉一个具体接口。RAWG 官方 page_size 上限 40，这里强制不超过 40。
     * @param array $ep ['path'=>'/games?ordering=-added&page=1', ...]
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both')
    {
        $path  = isset($ep['path']) ? (string) $ep['path'] : '/games?ordering=-added';
        $parts = explode('?', $path, 2);
        $q     = [];
        if (isset($parts[1])) { parse_str($parts[1], $q); }
        $q['page']      = isset($q['page']) ? (int) $q['page'] : 1;
        $q['page_size'] = min(40, max(1, (int) $limit));      // 官方硬限制：≤40

        $raw = Http::get($this->url($parts[0], $q))['data'];
        if (!is_array($raw)) { return []; }
        $out = [];
        foreach ((isset($raw['results']) ? $raw['results'] : []) as $r) {
            $out[] = $this->normalize($r, 'game');
            if (count($out) >= (int) $limit) { break; }
        }
        return $out;
    }
}
