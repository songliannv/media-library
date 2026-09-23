<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Http;

/**
 * Bangumi 适配器（动漫） https://api.bgm.tv
 * 基础接口免 key，但建议自带 UA 以免被限流。
 */
class Bangumi implements Adapter
{
    private $cfg;

    public function __construct()
    {
        $this->cfg = Config::get('bangumi');
    }

    public function getSource()
    {
        return 'bangumi';
    }

    private function url($path, array $q = [])
    {
        return $this->cfg['base'] . $path . (empty($q) ? '' : '?' . http_build_query($q));
    }

    public function normalize(array $raw,$type)
    {
        $images = (isset($raw['images']) ? $raw['images'] : []);
        $poster = (isset($images['large']) ? $images['large'] : ((isset($images['common']) ? $images['common'] : ((isset($images['medium']) ? $images['medium'] : ((isset($images['small']) ? $images['small'] : '')))))));
        $genres = [];
        foreach ((isset($raw['tags']) ? $raw['tags'] : []) as $t) {
            if (is_array($t)) { $genres[] = (isset($t['name']) ? $t['name'] : ''); } else { $genres[] = $t; }
        }
        $genres = array_slice(array_filter($genres), 0, 6);
        $year   = !empty($raw['date']) ? (int) substr($raw['date'], 0, 4) : null;
        $rating = isset($raw['rating']['score']) ? $raw['rating']['score'] : null;
        return [
            'source'         => 'bangumi',
            'source_id'      => (string) ((isset($raw['id']) ? $raw['id'] : '')),
            'type'           => 'anime',
            'title'          => (isset($raw['name']) ? $raw['name'] : ''),
            'original_title' => (isset($raw['name_cn']) ? $raw['name_cn'] : ((isset($raw['name']) ? $raw['name'] : ''))),
            'year'           => $year,
            'rating'         => $rating,
            'vote_count'     => (isset($raw['rating']['total']) ? $raw['rating']['total'] : null),
            'genres'         => $genres,
            'poster'         => $poster,
            'backdrop'       => $poster,
            'overview'       => (isset($raw['summary']) ? $raw['summary'] : ''),
            'extra'          => ['tags' => (isset($raw['tags']) ? $raw['tags'] : [])],
        ];
    }

    public function search($query,$page,$type = '')
    {
        $raw  = Http::get($this->url('/search/subjects', ['type' => 2, 'keyword' => $query, 'limit' => 20, 'offset' => ($page - 1) * 20]))['data'];
        $list = (isset($raw['data']) ? $raw['data'] : ((isset($raw['list']) ? $raw['list'] : ((isset($raw['results']) ? $raw['results'] : [])))));
        $out  = [];
        foreach ($list as $r) { $out[] = $this->normalize($r, 'anime'); }
        return ['total' => (isset($raw['total']) ? $raw['total'] : count($out)), 'items' => $out];
    }

    public function detail($type,$id)
    {
        $raw = Http::get($this->url("/subjects/{$id}"))['data'];
        if (empty($raw['id'])) return null;
        return $this->normalize($raw, 'anime');
    }

    /** 取热门：hot=当日在播（当季最热） / popular=排行榜（人气最高） */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        $out  = [];
        $seen = [];
        $eps  = [['path' => '/calendar', 'label' => '当日在播', 'kind' => 'hot', 'page' => 1]];
        if ($order === 'popular' || $order === 'both') {
            $eps[] = ['path' => '/v0/subjects?type=2&sort=rank', 'label' => '动画排行榜', 'kind' => 'popular', 'page' => 1];
        }
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
     * 拉一个具体接口（/calendar 当日在播，或 /v0/subjects 排行榜）。
     * 官方要求自带 User-Agent，且建议请求间隔 ≥1 秒（间隔由 Core\Sync 控制）。
     * @param array $ep ['path'=>'/calendar', ...]
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both')
    {
        $path  = isset($ep['path']) ? (string) $ep['path'] : '/calendar';
        $parts = explode('?', $path, 2);
        $q     = [];
        if (isset($parts[1])) { parse_str($parts[1], $q); }
        $q['limit'] = min(40, max(1, (int) $limit));

        $raw = Http::get($this->url($parts[0], $q), ['User-Agent: MediaLibraryAPI/1.0 (media library collector)'])['data'];
        if (!is_array($raw)) { return []; }

        // 三种可能的返回结构都兼容：v0 的 data / 旧的 list|results / calendar 的按星期分组
        $list = [];
        if (isset($raw['data']) && is_array($raw['data'])) {
            $list = $raw['data'];
        } elseif (isset($raw['results']) && is_array($raw['results'])) {
            $list = $raw['results'];
        } elseif (isset($raw['list']) && is_array($raw['list'])) {
            $list = $raw['list'];
        } elseif (isset($raw[0]['items'])) {
            foreach ($raw as $day) {
                foreach (((isset($day['items']) ? $day['items'] : [])) as $r) { $list[] = $r; }
            }
        }

        $out = [];
        foreach ($list as $r) {
            if (!is_array($r) || empty($r['id'])) { continue; }
            $out[] = $this->normalize($r, 'anime');
        }

        // 「最多人看」：按评分人数（rating.total）从多到少排，再截断
        if ($order === 'popular' || $order === 'both') {
            usort($out, function ($a, $b) {
                $va = (int) (isset($a['vote_count']) ? $a['vote_count'] : 0);
                $vb = (int) (isset($b['vote_count']) ? $b['vote_count'] : 0);
                if ($va === $vb) { return 0; }
                return ($va > $vb) ? -1 : 1;
            });
        }
        return array_slice($out, 0, (int) $limit);
    }
}
