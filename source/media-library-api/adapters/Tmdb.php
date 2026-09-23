<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Http;

/**
 * TMDB 适配器（影视 / 人物）
 * 影视类详情会带上 credits/images/videos，缓存进 media_items.payload，代理原样回吐。
 */
class Tmdb implements Adapter
{
    private $cfg;

    public function __construct()
    {
        $this->cfg = Config::get('tmdb');
    }

    public function getSource()
    {
        return 'tmdb';
    }

    public function url($path, array $q = [])
    {
        $q['api_key'] = $this->cfg['api_key'];
        if (!isset($q['language'])) {
            $q['language'] = (isset($this->cfg['lang']) ? $this->cfg['lang'] : 'zh-CN');
        }
        return $this->cfg['base'] . $path . '?' . http_build_query($q);
    }

    /** 取上游原始响应（代理用，保持 TMDB 原生结构） */
    public function fetchRaw($type,$id)
    {
        $q = [];
        if ($type !== 'person') {
            $q['append_to_response'] = 'credits,images,videos';
        }
        return Http::get($this->url("/{$type}/{$id}", $q))['data'];
    }

    public function searchRaw($type,$query,$page)
    {
        return Http::get($this->url("/search/{$type}", ['query' => $query, 'page' => $page]))['data'];
    }

    public function trendingRaw($mediaType,$window)
    {
        return Http::get($this->url("/trending/{$mediaType}/{$window}"))['data'];
    }

    public function normalize(array $raw,$type)
    {
        $img     = $this->cfg['image_base'];
        $poster  = !empty($raw['poster_path']) ? $img . 'w500' . $raw['poster_path'] : '';
        $backdrop= !empty($raw['backdrop_path']) ? $img . 'original' . $raw['backdrop_path'] : '';
        $title   = (isset($raw['title']) ? $raw['title'] : (isset($raw['name']) ? $raw['name'] : ''));
        $orig    = (isset($raw['original_title']) ? $raw['original_title'] : (isset($raw['original_name']) ? $raw['original_name'] : ''));
        $date    = (isset($raw['release_date']) ? $raw['release_date'] : (isset($raw['first_air_date']) ? $raw['first_air_date'] : ((isset($raw['birthday']) ? $raw['birthday'] : null))));
        $year    = $date ? (int) substr($date, 0, 4) : null;
        $genres  = [];
        foreach ((isset($raw['genres']) ? $raw['genres'] : []) as $g) { $genres[] = (isset($g['name']) ? $g['name'] : ''); }
        return [
            'source'         => 'tmdb',
            'source_id'      => (string) ((isset($raw['id']) ? $raw['id'] : '')),
            'type'           => $type,
            'title'          => $title,
            'original_title' => $orig,
            'year'           => $year,
            'rating'         => (isset($raw['vote_average']) ? $raw['vote_average'] : null),
            'vote_count'     => (isset($raw['vote_count']) ? $raw['vote_count'] : null),
            'genres'         => array_filter($genres),
            'poster'         => $poster,
            'backdrop'       => $backdrop,
            'overview'       => (isset($raw['overview']) ? $raw['overview'] : ''),
            'extra'          => ['popularity' => (isset($raw['popularity']) ? $raw['popularity'] : null)],
        ];
    }

    public function search($query,$page,$type = '')
    {
        $t   = $type ?: 'multi';
        $raw = $this->searchRaw($t, $query, $page);
        $out = [];
        foreach ((isset($raw['results']) ? $raw['results'] : []) as $r) {
            $rt = (isset($r['media_type']) ? $r['media_type'] : $type);
            if (in_array($rt, ['movie', 'tv', 'person'], true)) {
                $base = $this->normalize($r, $rt);
                // ★ 二次获取详情，补充完整信息
                $detail = $this->detail($rt, $r['id']);
                if ($detail !== null) {
                    foreach (['title','original_title','year','rating','vote_count','genres','poster','backdrop','overview','extra'] as $k) {
                        if (!empty($detail[$k])) {
                            $base[$k] = $detail[$k];
                        }
                    }
                }
                $out[] = $base;
            }
        }
        return ['total' => (isset($raw['total_results']) ? $raw['total_results'] : count($out)), 'items' => $out];
    }

    public function detail($type,$id)
    {
        $raw = $this->fetchRaw($type, $id);
        if (empty($raw['id'])) return null;
        return $this->normalize($raw, $type);
    }

    /** 取热门：hot=当下最热（trending 榜） / popular=最多人看（popular 榜） */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        $out  = [];
        $seen = [];
        foreach ($this->endpointList($window, $order) as $ep) {
            try {
                $items = $this->trendingFetch($ep, $limit, $order);
            } catch (\Exception $ex) {
                continue;   // 单个接口失败不影响其它口径
            }
            foreach ($items as $it) {
                $k = (isset($it['type']) ? $it['type'] : '') . '|' . $it['source_id'];
                if (isset($seen[$k])) { continue; }
                $seen[$k] = true;
                $out[] = $it;
            }
            if (count($out) >= $limit) { break; }
        }
        return array_slice($out, 0, $limit);
    }

    /** 本源的接口清单（与 Core\Sync::endpointsFor 的 tmdb 分支保持一致） */
    private function endpointList($window, $order)
    {
        $window = ($window === 'day') ? 'day' : 'week';
        $hot    = ($order === 'hot' || $order === 'both');
        $pop    = ($order === 'popular' || $order === 'both');
        $eps    = [];
        if ($hot)  { $eps[] = ['path' => '/trending/all/' . $window, 'label' => '热门趋势', 'kind' => 'hot', 'page' => 1]; }
        if ($pop) {
            $eps[] = ['path' => '/movie/popular', 'label' => '电影 · 最多人看', 'kind' => 'popular', 'page' => 1];
            $eps[] = ['path' => '/tv/popular',    'label' => '剧集 · 最多人看', 'kind' => 'popular', 'page' => 1];
            $eps[] = ['path' => '/discover/tv?with_genres=10764&sort_by=popularity.desc', 'label' => '综艺 · 最多人看', 'kind' => 'discover', 'page' => 1];
        }
        return $eps;
    }

    /**
     * 拉一个具体接口（TMDB 官方单页上限 20 条，故 limit 会被服务端分页截断）。
     * @param array $ep ['path'=>'/trending/all/week?page=1', ...]
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both')
    {
        $path  = isset($ep['path']) ? (string) $ep['path'] : '/trending/all/week';
        $parts = explode('?', $path, 2);
        $route = $parts[0];
        $q     = [];
        if (isset($parts[1])) { parse_str($parts[1], $q); }
        $q['page'] = isset($q['page']) ? (int) $q['page'] : 1;

        $raw = Http::get($this->url($route, $q))['data'];
        if (!is_array($raw)) { return []; }

        $results = (isset($raw['results']) && is_array($raw['results'])) ? $raw['results'] : [];
        $isTv    = (strpos($route, '/tv') === 0) || (strpos($route, 'discover/tv') !== false);
        $isVar   = (strpos($route, '10764') !== false);           // 真人秀 = 综艺
        $out     = [];
        foreach ($results as $r) {
            $rt = (isset($r['media_type']) ? $r['media_type'] : '');
            if ($rt === '') { $rt = $isTv ? 'tv' : 'movie'; }
            if ($rt === 'person') { continue; }                   // 人物不入库（站点无人物栏目）
            $type  = $isVar ? 'variety' : $rt;
            $base  = $this->normalize($r, $type);
            // ★ 二次获取详情，补充完整信息
            $detail = $this->detail($type, $r['id']);
            if ($detail !== null) {
                foreach (['title','original_title','year','rating','vote_count','genres','poster','backdrop','overview','extra'] as $k) {
                    if (!empty($detail[$k])) {
                        $base[$k] = $detail[$k];
                    }
                }
            }
            $out[] = $base;
            if (count($out) >= (int) $limit) { break; }
        }
        return $out;
    }
}
