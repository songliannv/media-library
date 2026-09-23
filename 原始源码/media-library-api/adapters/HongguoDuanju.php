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
 * 对接 orz.icicic.icu 代理 API，只取元数据（标题 / 封面 / 简介 / 类型 / 集数 / 播放量），
 * 不涉及任何播放或下载地址解析。
 *
 * API 端点（?act=…）：
 *   recommend 推荐 / rank 排行 / new 上新 / search 搜索 / detail 详情
 * 上游返回包裹格式：{"code":200,"msg":"…","count":N,"data":[…]}
 *
 * 频率限制：每 10 秒一次请求，避免被上游封禁。
 *
 * 兼容 PHP 5.6 ~ 8.2：不用 ??（空合并）/ 带可见性的类常量 / 标量类型提示。
 */
class HongguoDuanju implements Adapter
{
    private $cfg;
    private $lastRequestTime = 0;

    /** 最小请求间隔（秒）—— 不加 public/private，兼容 PHP 5.6 */
    const MIN_INTERVAL = 10;
    const DEFAULT_BASE = 'https://orz.icicic.icu/api/api.php';

    public function __construct()
    {
        $this->cfg = Config::get('hongguoduanju', []);
    }

    public function getSource()
    {
        return 'hongguoduanju';
    }

    /** 上游接口基址（config.php / 后台设置里可改） */
    private function base()
    {
        $b = (isset($this->cfg['base']) && is_string($this->cfg['base'])) ? trim($this->cfg['base']) : '';
        return $b !== '' ? $b : self::DEFAULT_BASE;
    }

    /** 构建 API URL */
    public function url(array $params = [])
    {
        return $this->base() . '?' . http_build_query($params);
    }

    /** 限流：确保两次请求间隔至少 MIN_INTERVAL 秒 */
    private function throttle()
    {
        $now = time();
        $elapsed = $now - $this->lastRequestTime;
        if ($this->lastRequestTime > 0 && $elapsed < self::MIN_INTERVAL) {
            sleep(self::MIN_INTERVAL - $elapsed);
        }
        $this->lastRequestTime = time();
    }

    /**
     * 通用请求并解开上游包裹，返回「条目数组」。
     * ★ v1.9.4 fix：原实现直接 return $resp['data']，而 $resp['data'] 是
     *   整个 {code,msg,count,data} 对象 —— 遍历它会把 code/msg/count 也当成条目，
     *   归一化出一堆 book_id 为空的垃圾数据。这里必须再取一层 ['data']。
     */
    private function request(array $params)
    {
        $this->throttle();
        $url = $this->url($params);
        $resp = Http::get($url);

        if (!isset($resp['code']) || (int) $resp['code'] !== 200) {
            return [];
        }
        $j = (isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : [];

        if (isset($j['data'])) {
            if (isset($j['code']) && (int) $j['code'] >= 400) {
                return [];
            }
            return is_array($j['data']) ? $j['data'] : [];
        }
        // 少数接口直接返回数组
        return $j;
    }

    /** 搜索短剧 */
    public function search($query, $page = 1, $type = '')
    {
        $data = $this->request([
            'act'     => 'search',
            'keyword' => (string) $query,
            'limit'   => 20,
        ]);
        $items = $this->normalizeBatch($data);
        return ['total' => count($items), 'items' => $items];
    }

    /** 推荐短剧（最热口径） */
    public function recommend($limit = 20)
    {
        $data = $this->request([
            'act'   => 'recommend',
            'limit' => max(1, min(100, (int) $limit)),
        ]);
        return $this->normalizeBatch($data);
    }

    /** 排行短剧（人气口径） */
    public function rank($limit = 20)
    {
        $data = $this->request([
            'act'   => 'rank',
            'limit' => max(1, min(100, (int) $limit)),
        ]);
        return $this->normalizeBatch($data);
    }

    /** 最新短剧 */
    public function latest($limit = 20)
    {
        $data = $this->request([
            'act'   => 'new',
            'limit' => max(1, min(100, (int) $limit)),
        ]);
        return $this->normalizeBatch($data);
    }

    /** 详情（兼容上游返回 list 或 dict 两种形态） */
    public function detail($type, $id)
    {
        $data = $this->request([
            'act'     => 'detail',
            'book_id' => (string) $id,
        ]);
        if (!is_array($data) || !$data) {
            return null;
        }
        $row = isset($data[0]) ? $data[0] : $data;   // list → 第一项；dict → 自身
        if (!is_array($row) || !isset($row['book_id'])) {
            return null;
        }
        return $this->normalize($row, 'short');
    }

    /** 取热门（便捷入口，供 API 的 trending 汇总调用） */
    public function trending($window = 'week', $limit = 20, $order = 'both')
    {
        if ($order === 'hot') {
            return $this->recommend($limit);
        }
        if ($order === 'popular') {
            return $this->rank($limit);
        }
        $out  = $this->recommend($limit);
        $seen = [];
        foreach ($out as $it) {
            $seen[(string) $it['source_id']] = 1;
        }
        foreach ($this->rank($limit) as $it) {
            if (!isset($seen[(string) $it['source_id']])) {
                $out[] = $it;
            }
        }
        return $out;
    }

    /**
     * 拉取「一个具体端点」并归一化 —— 由 Core\Sync::endpointsFor() 生成清单后逐个调用。
     * ★ v1.9.4：Adapter 接口要求实现本方法，原实现缺失 —— 只要加载本文件就会
     *   Fatal error（抽象方法未实现），且 try/catch 抓不住。
     *
     * @param array $ep ['path'=>'?act=recommend','label'=>'…','kind'=>'hot|popular|new','page'=>1]
     */
    public function trendingFetch(array $ep, $limit = 20, $order = 'both')
    {
        $path = isset($ep['path']) ? (string) $ep['path'] : '?act=recommend';
        $qs   = ltrim($path, '?');
        $q    = [];
        if ($qs !== '') {
            parse_str($qs, $q);
        }
        if (!isset($q['act']) || $q['act'] === '') {
            $q['act'] = 'recommend';
        }
        // 只保留上游认识的参数，避免把 limit/page 之外的东西透传过去
        $params = ['act' => (string) $q['act'], 'limit' => max(1, min(100, (int) $limit))];
        if (isset($q['keyword']) && $q['keyword'] !== '') {
            $params['keyword'] = (string) $q['keyword'];
        }
        if (isset($q['type']) && $q['type'] !== '') {
            $params['type'] = (string) $q['type'];
        }
        $data = $this->request($params);
        return $this->normalizeBatch($data);
    }

    /** 批量归一化 */
    private function normalizeBatch(array $data)
    {
        $out = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['book_id'])) {
                continue;                                  // 跳过非条目元素
            }
            $out[] = $this->normalize($item, 'short');
        }
        return $out;
    }

    /**
     * 归一化数据
     * ★ v1.9.4：第二个参数是为了与 Adapter 接口签名一致（PHP 8 下参数不匹配会 Fatal error）
     */
    public function normalize(array $raw, $type = 'short')
    {
        // 解析类型字段，格式如 "剧情?3?" 或 "家庭,剧情?6?"
        $typeStr = isset($raw['type']) ? (string) $raw['type'] : '';
        $genres  = [];
        if ($typeStr !== '') {
            foreach (explode(',', $typeStr) as $part) {
                $clean = preg_replace('/\?\d+\?$/', '', trim($part));
                if ($clean !== '' && $clean !== null) {
                    $genres[] = $clean;
                }
            }
        }

        $episodeCnt = isset($raw['episode_cnt']) ? (int) $raw['episode_cnt'] : 0;
        $playCnt    = isset($raw['play_cnt']) ? (int) $raw['play_cnt'] : 0;

        // 封面：上游可能给相对路径（//xxx 或 /xxx），补成 https
        $cover = isset($raw['cover']) ? (string) $raw['cover'] : '';
        if ($cover !== '' && strpos($cover, 'http') !== 0) {
            $cover = (strpos($cover, '//') === 0) ? ('https:' . $cover) : ('https://' . ltrim($cover, '/'));
        }

        return [
            'source'     => 'hongguoduanju',
            'source_id'  => (string) (isset($raw['book_id']) ? $raw['book_id'] : ''),
            'type'       => 'short',
            'title'      => isset($raw['title']) ? (string) $raw['title'] : '',
            'original_title' => '',
            'year'       => null,
            'rating'     => null,
            'vote_count' => $playCnt,
            'genres'     => $genres,
            'poster'     => $cover,
            'backdrop'   => '',
            'overview'   => isset($raw['intro']) ? (string) $raw['intro'] : '',
            'extra'      => [
                'episodes'       => $episodeCnt,
                'author'         => isset($raw['author']) ? (string) $raw['author'] : '',
                'duration'       => isset($raw['duration']) ? (string) $raw['duration'] : '',
                'publish_time'   => isset($raw['publish_time']) ? (string) $raw['publish_time'] : '',
                'record_number'  => isset($raw['record_number']) ? (string) $raw['record_number'] : '',
                'sub_title_list' => isset($raw['sub_title_list']) ? (string) $raw['sub_title_list'] : '',
            ],
        ];
    }

    /** 本适配器可用的端点清单（供后台展示 / 自检页用） */
    public function endpointList()
    {
        return [
            ['path' => '?act=recommend', 'label' => '推荐短剧',   'kind' => 'hot',     'page' => 1],
            ['path' => '?act=rank',      'label' => '排行短剧',   'kind' => 'popular', 'page' => 1],
            ['path' => '?act=new',       'label' => '最新短剧',   'kind' => 'new',     'page' => 1],
            ['path' => '?act=search',    'label' => '搜索短剧',   'kind' => 'search',  'page' => 1],
        ];
    }

    /**
     * 连通性自检（后台「一键测试全部数据源」调用）
     * 这里不回传异常，改成结构化失败信息 —— 否则后台只能看到一个 500。
     */
    public function testConnection()
    {
        try {
            $data = $this->recommend(5);
            if (count($data) === 0) {
                return ['ok' => false, 'label' => '无数据', 'detail' => '接口连通但没取到条目，可能上游改版或限流'];
            }
            return ['ok' => true, 'label' => '正常', 'detail' => '已获取 ' . count($data) . ' 条推荐数据'];
        } catch (\Exception $e) {
            return ['ok' => false, 'label' => '异常', 'detail' => $e->getMessage()];
        }
    }
}
