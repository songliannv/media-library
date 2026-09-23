<?php
namespace Adapters;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/../core/Guard.php';
ml_guard_shield(__FILE__);

use Core\Config;
use Core\Http;

/**
 * 红果短剧适配器（v2）
 * 对接 orz.icicic.icu 代理 API，提供短剧元数据
 * 
 * API 端点：
 *   - search: 搜索短剧
 *   - recommend: 推荐
 *   - rank: 排行
 *   - new: 上新
 *   - detail: 详情
 *   
 * 频率限制：每10秒一次请求防止被封
 */
class HongguoDuanju implements Adapter
{
    private $cfg;
    private $lastRequestTime = 0;
    private const MIN_INTERVAL = 10; // 最小请求间隔（秒）

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
    public function url(array $params = [])
    {
        $base = $this->cfg['base'] ?? 'https://orz.icicic.icu/api/api.php';
        return $base . '?' . http_build_query($params);
    }

    /**
     * 限流：确保两次请求间隔至少 MIN_INTERVAL 秒
     */
    private function throttle()
    {
        $now = time();
        $elapsed = $now - $this->lastRequestTime;
        if ($elapsed < self::MIN_INTERVAL) {
            sleep(self::MIN_INTERVAL - $elapsed);
        }
        $this->lastRequestTime = time();
    }

    /**
     * 通用 API 请求
     */
    private function request(array $params)
    {
        $this->throttle();
        $url = $this->url($params);
        $resp = Http::get($url);
        
        // 解析响应
        if (isset($resp['code']) && $resp['code'] == 200) {
            return $resp['data'] ?? [];
        }
        return [];
    }

    /**
     * 搜索短剧
     */
    public function search($query, $page = 1, $type = '')
    {
        $data = $this->request([
            'act' => 'search',
            'keyword' => $query,
            'limit' => 20
        ]);
        
        $items = $this->normalizeBatch($data);
        return ['total' => count($items), 'items' => $items];
    }

    /**
     * 获取推荐短剧
     */
    public function recommend($limit = 20)
    {
        $data = $this->request([
            'act' => 'recommend',
            'limit' => $limit
        ]);
        return $this->normalizeBatch($data);
    }

    /**
     * 获取排行短剧
     */
    public function rank($limit = 20)
    {
        $data = $this->request([
            'act' => 'rank',
            'limit' => $limit
        ]);
        return $this->normalizeBatch($data);
    }

    /**
     * 获取最新短剧
     */
    public function latest($limit = 20)
    {
        $data = $this->request([
            'act' => 'new',
            'limit' => $limit
        ]);
        return $this->normalizeBatch($data);
    }

    /**
     * 获取详情
     */
    public function detail($type, $id)
    {
        $data = $this->request([
            'act' => 'detail',
            'book_id' => $id
        ]);
        
        if (is_array($data) && !empty($data)) {
            return $this->normalize($data[0] ?? []);
        }
        return null;
    }

    /**
     * 获取热门短剧（兼容旧接口）
     */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        return $this->rank($limit);
    }

    /**
     * 批量归一化
     */
    private function normalizeBatch(array $data)
    {
        $out = [];
        foreach ($data as $item) {
            $out[] = $this->normalize($item);
        }
        return $out;
    }

    /**
     * 归一化数据
     */
    public function normalize(array $raw)
    {
        // 解析类型字段，格式如 "剧情?3?" 或 "家庭,剧情?6?"
        $typeStr = $raw['type'] ?? '';
        $genres = [];
        if (!empty($typeStr)) {
            // 提取类型部分（逗号分隔）
            $parts = explode(',', $typeStr);
            foreach ($parts as $part) {
                // 移除可能的数字后缀 ?N?
                $clean = preg_replace('/\?\d+\?$/', '', trim($part));
                if (!empty($clean)) {
                    $genres[] = $clean;
                }
            }
        }

        // 解析集数
        $episodeCnt = (int) ($raw['episode_cnt'] ?? 0);
        
        // 解析播放量
        $playCnt = (int) ($raw['play_cnt'] ?? 0);
        
        // 处理封面图
        $cover = $raw['cover'] ?? '';
        if (!empty($cover) && strpos($cover, 'http') !== 0) {
            $cover = 'https:' . $cover;
        }

        return [
            'source' => 'hongguoduanju',
            'source_id' => (string) ($raw['book_id'] ?? ''),
            'type' => 'short',
            'title' => $raw['title'] ?? '',
            'author' => $raw['author'] ?? '',
            'year' => null,
            'rating' => null,
            'vote_count' => $playCnt,
            'genres' => $genres,
            'poster' => $cover,
            'backdrop' => '',
            'overview' => $raw['intro'] ?? '',
            'extra' => [
                'episodes' => $episodeCnt,
                'duration' => $raw['duration'] ?? '',
                'publish_time' => $raw['publish_time'] ?? '',
                'record_number' => $raw['record_number'] ?? '',
                'sub_title_list' => $raw['sub_title_list'] ?? '',
            ]
        ];
    }

    /**
     * 获取接口清单
     */
    public function endpointList()
    {
        return [
            ['path' => '?act=recommend', 'label' => '推荐短剧', 'kind' => 'hot', 'page' => 1],
            ['path' => '?act=rank', 'label' => '排行短剧', 'kind' => 'popular', 'page' => 1],
            ['path' => '?act=new', 'label' => '最新短剧', 'kind' => 'new', 'page' => 1],
            ['path' => '?act=search', 'label' => '搜索短剧', 'kind' => 'search', 'page' => 1],
        ];
    }

    /**
     * 测试连接
     */
    public function testConnection()
    {
        try {
            $data = $this->recommend(5);
            return [
                'ok' => true,
                'label' => '正常',
                'detail' => '已获取 ' . count($data) . ' 条推荐数据'
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'label' => '异常',
                'detail' => $e->getMessage()
            ];
        }
    }
}
