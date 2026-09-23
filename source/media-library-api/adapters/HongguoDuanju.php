<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Http;

/**
 * 红果短剧适配器
 * 专门用于国产短剧数据抓取
 */
class HongguoDuanju implements Adapter
{
    private $cfg;

    public function __construct()
    {
        $this->cfg = Config::get('hongguoduanju', []);
    }

    public function getSource()
    {
        return 'hongguoduanju';
    }

    /**
     * 构建 API URL
     */
    public function url($path, array $q = [])
    {
        $base = $this->cfg['base'] ?? 'https://www.hongguoduanju.com';
        return $base . $path . (count($q) ? '?' . http_build_query($q) : '');
    }

    /**
     * 搜索短剧
     */
    public function search($query, $page, $type = '')
    {
        $raw = $this->searchRaw($query, $page);
        $out = [];
        foreach ((isset($raw['list']) ? $raw['list'] : []) as $r) {
            $out[] = $this->normalize($r, 'short');
        }
        return ['total' => (int) ($raw['total'] ?? count($out)), 'items' => $out];
    }

    /**
     * 搜索原始响应
     */
    public function searchRaw($query, $page)
    {
        // 红果短剧搜索接口
        $url = $this->url('/api/search', [
            'keyword' => $query,
            'page' => $page,
            'page_size' => 20
        ]);
        return Http::get($url)['data'] ?? ['list' => [], 'total' => 0];
    }

    /**
     * 获取详情
     */
    public function detail($type, $id)
    {
        $raw = $this->fetchRaw('short', $id);
        if (empty($raw['id'])) return null;
        return $this->normalize($raw, 'short');
    }

    /**
     * 获取详情原始响应
     */
    public function fetchRaw($type, $id)
    {
        $url = $this->url('/api/detail', ['id' => $id]);
        return Http::get($url)['data'] ?? [];
    }

    /**
     * 获取热门短剧
     */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        $raw = $this->trendingRaw($window);
        $out = [];
        foreach ((isset($raw['list']) ? $raw['list'] : []) as $r) {
            $out[] = $this->normalize($r, 'short');
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * 获取热门原始响应
     */
    public function trendingRaw($window)
    {
        $url = $this->url('/api/trending', ['window' => $window, 'limit' => 50]);
        return Http::get($url)['data'] ?? ['list' => []];
    }

    /**
     * 拉取具体接口
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both')
    {
        $path = $ep['path'] ?? '/api/trending';
        $q = $ep['params'] ?? [];
        $url = $this->url($path, $q);
        $raw = Http::get($url)['data'] ?? ['list' => []];
        $out = [];
        foreach ((isset($raw['list']) ? $raw['list'] : []) as $r) {
            $out[] = $this->normalize($r, 'short');
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * 归一化数据
     */
    public function normalize(array $raw, $type)
    {
        $poster = $this->extractImage($raw, ['cover', 'poster', 'image', 'thumb']);
        $backdrop = $this->extractImage($raw, ['backdrop', 'banner', 'background']);
        
        return [
            'source' => 'hongguoduanju',
            'source_id' => (string) ($raw['id'] ?? ''),
            'type' => 'short',
            'title' => $raw['title'] ?? $raw['name'] ?? '',
            'original_title' => $raw['original_name'] ?? '',
            'year' => isset($raw['year']) ? (int) $raw['year'] : null,
            'rating' => isset($raw['rating']) ? (float) $raw['rating'] : null,
            'vote_count' => isset($raw['play_count']) ? (int) $raw['play_count'] : null,
            'genres' => isset($raw['tags']) ? (array) $raw['tags'] : [],
            'poster' => $poster,
            'backdrop' => $backdrop,
            'overview' => $raw['description'] ?? $raw['intro'] ?? '',
            'extra' => [
                'episodes' => $raw['episodes'] ?? 0,
                'actor' => $raw['actor'] ?? '',
                'director' => $raw['director'] ?? '',
                'status' => $raw['status'] ?? 'ongoing',
            ]
        ];
    }

    /**
     * 从原始数据中提取图片
     */
    private function extractImage(array $raw, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($raw[$key]) && !empty($raw[$key])) {
                $img = $raw[$key];
                // 如果是数组，取第一个
                if (is_array($img) && isset($img[0])) {
                    $img = $img[0];
                }
                // 确保是完整 URL
                if (strpos($img, 'http') !== 0) {
                    $img = $this->cfg['image_base'] ?? '' . $img;
                }
                return $img;
            }
        }
        return '';
    }

    /**
     * 获取接口清单
     */
    public function endpointList()
    {
        return [
            ['path' => '/api/trending', 'label' => '热门短剧', 'kind' => 'hot', 'page' => 1],
            ['path' => '/api/new', 'label' => '最新短剧', 'kind' => 'new', 'page' => 1],
            ['path' => '/api/hot', 'label' => '热播短剧', 'kind' => 'popular', 'page' => 1],
        ];
    }
}
