<?php
namespace Api;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Json;
use Core\CacheStore;
use Core\Http;
use Adapters\Tmdb;

/**
 * TMDB 兼容代理
 * 路径保持 TMDB v3 形态，ZBLOK 只需把 TMDB base URL 改成你的站点（如 https://api.zyfx.wang）
 * 即可无缝使用，主题零改动。返回结构与 TMDB 官方完全一致（直接透传上游 JSON）。
 */
class TmdbProxy
{
    public static function handle($path)
    {
        $p    = trim($path, '/');      // 3/movie/550
        $parts = explode('/', $p);
        array_shift($parts);           // 去掉 '3'
        $seg  = (isset($parts[0]) ? $parts[0] : '');
        $tmdb = new Tmdb();
        $ttl  = (int) Config::sub('cache', 'ttl_days', 7);

        // /3/movie|tv|person/{id}
        if (in_array($seg, ['movie', 'tv', 'person'], true)) {
            $id = (isset($parts[1]) ? $parts[1] : '');
            if (!is_numeric($id)) { Json::error(400, 'invalid id'); }
            CacheStore::bumpApiCall('tmdb', $seg, $id);
            $cached = CacheStore::getRaw('tmdb', $seg, $id);
            if ($cached !== null && CacheStore::isFresh('tmdb', $seg, $id)) {
                Json::send($cached);
            }
            $raw = $tmdb->fetchRaw($seg, $id);
            if (empty($raw['id'])) { Json::error(404, 'not found'); }
            CacheStore::put('tmdb', $seg, $id, $tmdb->normalize($raw, $seg), $raw, $ttl);
            Json::send($raw);
        }

        // /3/search/{movie|tv|person|multi}
        if ($seg === 'search') {
            $type  = (isset($parts[1]) ? $parts[1] : 'multi');
            $q     = self::query();
            $query = (isset($q['query']) ? $q['query'] : '');
            $page  = (int) ((isset($q['page']) ? $q['page'] : 1));
            if ($query === '') { Json::error(400, 'query required'); }
            if (!in_array($type, ['movie', 'tv', 'person', 'multi'], true)) { Json::error(400, 'bad search type'); }
            $raw = $tmdb->searchRaw($type, $query, $page);
            foreach ((isset($raw['results']) ? $raw['results'] : []) as $r) {
                $rt = (isset($r['media_type']) ? $r['media_type'] : $type);
                if (in_array($rt, ['movie', 'tv', 'person'], true)) {
                    CacheStore::put('tmdb', $rt, (string) $r['id'], $tmdb->normalize($r, $rt), $r, $ttl);
                }
            }
            Json::send($raw);
        }

        // /3/trending/{all|movie|tv}/{day|week}
        if ($seg === 'trending') {
            $mt  = (isset($parts[1]) ? $parts[1] : 'all');
            $win = (isset($parts[2]) ? $parts[2] : 'week');
            Json::send($tmdb->trendingRaw($mt, $win));
        }

        // /3/genre/{movie|tv}/list
        if ($seg === 'genre') {
            $which = (isset($parts[1]) ? $parts[1] : 'movie');
            Json::send(Http::get($tmdb->url("/genre/{$which}/list"))['data']);
        }

        // /3/configuration
        if ($seg === 'configuration') {
            Json::send(Http::get($tmdb->url('/configuration'))['data']);
        }

        Json::error(404, 'endpoint not found');
    }

    private static function query()
    {
        $m = $_SERVER['REQUEST_METHOD'];
        if ($m === 'GET') {
            $qs = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
            parse_str(($qs === null ? '' : $qs), $out);
            return $out;
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
}
