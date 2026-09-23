<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 本地缓存/索引层：media_items 既存原始上游响应(payload) 又存可检索的归一化字段。
 * 代理走 payload（保持 TMDB 原生结构），统一 API/后台走归一化字段。
 */
class CacheStore
{
    /** LIKE 通配符转义（★ v1.8.8：标签筛选用，避免 % _ \ 被当通配符） */
    public static function likeEsc($s)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $s);
    }

    /** 取原始上游响应（代理用） */
    public static function getRaw($source,$type,$id)
    {
        $st = DB::pdo()->prepare("SELECT payload FROM media_items WHERE source=? AND type=? AND source_id=? LIMIT 1");
        $st->execute([$source, $type, $id]);
        $row = $st->fetch();
        return $row ? json_decode($row['payload'], true) : null;
    }

    /** 是否在有效期内（未过期则代理直接返回） */
    public static function isFresh($source,$type,$id)
    {
        $st = DB::pdo()->prepare("SELECT refresh_at FROM media_items WHERE source=? AND type=? AND source_id=? LIMIT 1");
        $st->execute([$source, $type, $id]);
        $row = $st->fetch();
        if (!$row) return false;
        if ($row['refresh_at'] === null) return true;
        return strtotime($row['refresh_at']) > time();
    }

    /** 该条目是否已存在（同步去重用；同源同 ID 视为同一条） */
    public static function exists($source,$type,$id)
    {
        $st = DB::pdo()->prepare("SELECT 1 FROM media_items WHERE source=? AND type=? AND source_id=? LIMIT 1");
        $st->execute([$source, $type, $id]);
        return (bool) $st->fetchColumn();
    }

    /**
     * 是否已存在「同名（+同年）」的条目 —— 用于跨源去重，避免站内出现两遍同一部作品。
     * @param string $excludeSource 排除本源（本源同 ID 由 exists() 负责，这里只查别的源）
     */
    public static function existsSameTitle($title,$year,$excludeSource = '')
    {
        $title = trim((string) $title);
        if ($title === '') { return false; }
        $pdo    = DB::pdo();
        $params = [$title, $title];
        $sql    = "SELECT 1 FROM media_items WHERE (title=? OR original_title=?)";
        if ($excludeSource !== '') {
            $sql .= " AND source<>?";
            $params[] = $excludeSource;
        }
        if ($year !== null && $year !== '' && (int) $year > 0) {
            // 同年才判重，避免「同名不同年」被误杀；年份允许多算 1 年（首播/上映年常差 1）
            $sql .= " AND year IS NOT NULL AND ABS(year - ?) <= 1";
            $params[] = (int) $year;
        }
        $sql .= " LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * 写入/更新一条（归一化字段 + 原始 payload）
     * @return int 该条目的本地 id（0 = 写入后查不到，异常时抛 PDOException）
     */
    public static function put($source,$type,$id, array $n, array $payload,$ttl)
    {
        $pdo    = DB::pdo();
        $now    = date('Y-m-d H:i:s');
        $refresh = date('Y-m-d H:i:s', time() + $ttl * 86400);
        $st = $pdo->prepare("INSERT INTO media_items
            (source,source_id,type,title,original_title,year,rating,vote_count,genres,poster,backdrop,overview,payload,cached_at,refresh_at,updated_at)
            VALUES(:s,:sid,:t,:title,:ot,:year,:rating,:vc,:genres,:poster,:back,:ov,:payload,:now,:refresh,:now2)
            ON DUPLICATE KEY UPDATE
            title=VALUES(title),original_title=VALUES(original_title),year=VALUES(year),
            rating=VALUES(rating),vote_count=VALUES(vote_count),genres=VALUES(genres),
            poster=VALUES(poster),backdrop=VALUES(backdrop),overview=VALUES(overview),
            payload=VALUES(payload),cached_at=VALUES(cached_at),refresh_at=VALUES(refresh_at),updated_at=VALUES(updated_at)");
        $st->execute([
            ':s'      => $source, ':sid' => $id, ':t' => $type,
            ':title'  => (isset($n['title']) ? $n['title'] : ''), ':ot' => (isset($n['original_title']) ? $n['original_title'] : ''),
            ':year'   => (isset($n['year']) ? $n['year'] : null), ':rating' => (isset($n['rating']) ? $n['rating'] : null), ':vc' => (isset($n['vote_count']) ? $n['vote_count'] : null),
            ':genres' => json_encode((isset($n['genres']) ? $n['genres'] : [])),
            ':poster' => (isset($n['poster']) ? $n['poster'] : ''), ':back' => (isset($n['backdrop']) ? $n['backdrop'] : ''),
            ':ov'     => (isset($n['overview']) ? $n['overview'] : ''),
            ':payload'=> json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':now'    => $now, ':refresh' => $refresh, ':now2' => $now,
        ]);

        // 回读本地 id：同步时用它只给「新增条目」生成静态页
        $find = $pdo->prepare("SELECT id FROM media_items WHERE source=? AND type=? AND source_id=? LIMIT 1");
        $find->execute([$source, $type, $id]);
        return (int) $find->fetchColumn();
    }

    /** 统一搜索（跨源） */
    public static function searchItems($q,$type,$source,$page,$per)
    {
        $pdo = DB::pdo();
        $where = []; $params = [];
        if ($q !== '') { $where[] = "(title LIKE ? OR original_title LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($type !== '') { $where[] = "type=?"; $params[] = $type; }
        if ($source !== '') { $where[] = "source=?"; $params[] = $source; }
        $sql = "SELECT id,source,source_id,type,title,original_title,year,rating,genres,poster,backdrop,overview,api_calls,clicks FROM media_items";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY rating DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = ($page - 1) * $per;
        $st = $pdo->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR); }
        $st->execute();
        return $st->fetchAll();
    }

    /** 列表（后台/统一列表） */
    public static function listItems($type,$page,$per,$q = '')
    {
        $pdo = DB::pdo();
        $where = []; $params = [];
        if ($type !== '') { $where[] = "type=?"; $params[] = $type; }
        if ($q !== '') { $where[] = "(title LIKE ? OR original_title LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = "SELECT id,source,source_id,type,title,original_title,year,rating,genres,poster,backdrop,overview,download_url,api_calls,clicks FROM media_items";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY updated_at DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = ($page - 1) * $per;
        $st = $pdo->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR); }
        $st->execute();
        return $st->fetchAll();
    }

    public static function countItems($type,$q = '',$tag = '')
    {
        $pdo = DB::pdo();
        $where = []; $params = [];
        if ($type !== '') { $where[] = "type=?"; $params[] = $type; }
        if ($q !== '') { $where[] = "(title LIKE ? OR original_title LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
        /* ★ v1.8.8：与 listBySort 保持一致的标签筛选口径 */
        if ($tag !== '') { $where[] = "genres LIKE ?"; $params[] = '%"' . self::likeEsc($tag) . '"%'; }
        $sql = "SELECT COUNT(*) c FROM media_items";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $st = $pdo->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v, \PDO::PARAM_STR); }
        $st->execute();
        return self::countOf($st);
    }

    public static function allGenres()
    {
        $st = DB::pdo()->query("SELECT genres FROM media_items");
        $set = [];
        foreach ($st->fetchAll() as $r) {
            $gs = json_decode($r['genres'], true) ?: [];
            foreach ($gs as $g) { if ($g) $set[$g] = true; }
        }
        return array_keys($set);
    }

    /**
     * 热门标签（★ v1.8.8）：聚合全部条目的 genres，按「该标签下所有条目的 clicks 之和」降序。
     * 首页顶部标签云用（默认取前 50 个）。
     * 返回：array( array('tag'=>'动作','clicks'=>128,'items'=>6), ... )
     */
    public static function hotTags($limit = 50)
    {
        $limit = (int) $limit;
        if ($limit < 1) { $limit = 50; }
        $st  = DB::pdo()->query("SELECT genres,clicks FROM media_items");
        $agg = array();
        foreach ($st->fetchAll() as $r) {
            $gs = json_decode((isset($r['genres']) ? (string) $r['genres'] : ''), true);
            if (!is_array($gs)) { $gs = array(); }
            $clk = (int) (isset($r['clicks']) ? $r['clicks'] : 0);
            foreach ($gs as $g) {
                $g = trim((string) $g);
                if ($g === '') { continue; }
                if (!isset($agg[$g])) { $agg[$g] = array('tag' => $g, 'clicks' => 0, 'items' => 0); }
                $agg[$g]['clicks'] += $clk;
                $agg[$g]['items']++;
            }
        }
        $list = array_values($agg);
        usort($list, array(__CLASS__, 'cmpHotTag'));
        return array_slice($list, 0, $limit);
    }

    /** hotTags() 的降序比较器（PHP 5.6 兼容：不使用飞船运算符 <=> ） */
    public static function cmpHotTag($a, $b)
    {
        if ($a['clicks'] !== $b['clicks']) { return ($a['clicks'] > $b['clicks']) ? -1 : 1; }
        if ($a['items']  !== $b['items'])  { return ($a['items']  > $b['items'])  ? -1 : 1; }
        return strcmp($a['tag'], $b['tag']);
    }

    public static function stats()
    {
        $pdo = DB::pdo();
        $total = self::countOf($pdo->query("SELECT COUNT(*) c FROM media_items"));
        $byType = []; $bySource = [];
        foreach ($pdo->query("SELECT type, COUNT(*) c FROM media_items GROUP BY type")->fetchAll() as $r) { $byType[$r['type']] = (int) $r['c']; }
        foreach ($pdo->query("SELECT source, COUNT(*) c FROM media_items GROUP BY source")->fetchAll() as $r) { $bySource[$r['source']] = (int) $r['c']; }
        return ['total' => $total, 'by_type' => $byType, 'by_source' => $bySource];
    }

    public static function getRowById($id)
    {
        $st = DB::pdo()->prepare("SELECT * FROM media_items WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function updateById($id, array $fields)
    {
        $allowed = ['title', 'original_title', 'year', 'rating', 'vote_count', 'overview', 'poster', 'backdrop', 'download_url', 'genres', 'extra', 'type'];
        $sets = []; $params = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $v = $fields[$k];
            if ($k === 'genres' || $k === 'extra') { $v = self::normalizeJson($v); }
            $sets[] = "$k=?"; $params[] = $v;
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $st = DB::pdo()->prepare("UPDATE media_items SET " . implode(',', $sets) . ", updated_at=NOW() WHERE id=?");
        return $st->execute($params);
    }

    /** 把 genres/extra 规整为合法 JSON 字符串（数组/对象→编码，已是字符串则原样） */
    private static function normalizeJson($v)
    {
        if (is_array($v) || is_object($v)) { return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
        if (is_string($v) && $v !== '' && in_array(substr($v, 0, 1), ['[', '{', '"'], true)) {
            json_decode($v);
            if (json_last_error() === JSON_ERROR_NONE) { return $v; }
        }
        return '[]';
    }

    public static function insertManual(array $n)
    {
        $pdo = DB::pdo();
        $now = date('Y-m-d H:i:s');
        $genres = self::normalizeJson((isset($n['genres']) ? $n['genres'] : []));
        $extra  = self::normalizeJson((isset($n['extra']) ? $n['extra'] : []));
        $st = $pdo->prepare("INSERT INTO media_items
            (source,source_id,type,title,original_title,year,rating,vote_count,genres,poster,backdrop,overview,download_url,extra,payload,cached_at,refresh_at,updated_at)
            VALUES(:s,:sid,:t,:title,:ot,:year,:rating,:vc,:genres,:poster,:back,:ov,:dl,:extra,'{}',:now,NULL,:now)");
        $st->execute([
            ':s'    => 'manual', ':sid' => 'manual_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6), ':t' => (isset($n['type']) ? $n['type'] : 'movie'),
            ':title'=> (isset($n['title']) ? $n['title'] : ''), ':ot' => (isset($n['original_title']) ? $n['original_title'] : ''),
            ':year' => (isset($n['year']) ? $n['year'] : null), ':rating' => (isset($n['rating']) ? $n['rating'] : null), ':vc' => (isset($n['vote_count']) ? $n['vote_count'] : null),
            ':genres' => $genres,
            ':poster' => (isset($n['poster']) ? $n['poster'] : ''), ':back' => (isset($n['backdrop']) ? $n['backdrop'] : ''),
            ':ov'   => (isset($n['overview']) ? $n['overview'] : ''), ':dl' => (isset($n['download_url']) ? $n['download_url'] : ''), ':extra' => $extra,
            ':now'  => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function deleteById($id)
    {
        $st = DB::pdo()->prepare("DELETE FROM media_items WHERE id=?");
        return $st->execute([$id]);
    }

    public static function syncLog()
    {
        return DB::pdo()->query("SELECT * FROM sync_log ORDER BY id DESC LIMIT 50")->fetchAll();
    }

    /** API 调用次数 +1（代理/统一接口返回某条目详情时调用） */
    public static function bumpApiCall($source,$type,$id)
    {
        $st = DB::pdo()->prepare("UPDATE media_items SET api_calls = api_calls + 1 WHERE source=? AND type=? AND source_id=?");
        $st->execute([$source, $type, $id]);
    }

    /** 点击次数 +1（内页埋点 track.php 调用） */
    public static function bumpClick($id)
    {
        $st = DB::pdo()->prepare("UPDATE media_items SET clicks = clicks + 1 WHERE id=?");
        $st->execute([$id]);
    }

    /** 取某条目的两项计数（公开端点用） */
    public static function metric($id)
    {
        $st = DB::pdo()->prepare("SELECT api_calls, clicks FROM media_items WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ? ['api_calls' => (int) $r['api_calls'], 'clicks' => (int) $r['clicks']]
                  : ['api_calls' => 0, 'clicks' => 0];
    }

    /** 按指定指标排序的列表（api_calls / clicks / rating / updated_at） */
    public static function listBySort($sort,$type,$page,$per,$q = '',$tag = '')
    {
        $allowed = ['api_calls', 'clicks', 'rating', 'updated_at'];
        $order   = in_array($sort, $allowed, true) ? $sort : 'clicks';
        $pdo = DB::pdo();
        $where = []; $params = [];
        if ($type !== '') { $where[] = 'type=?'; $params[] = $type; }
        if ($q !== '') { $where[] = '(title LIKE ? OR original_title LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        /* ★ v1.8.8：按标签筛选。genres 存的是 JSON 数组，用 "标签" 精确匹配元素，避免子串误命中 */
        if ($tag !== '') { $where[] = 'genres LIKE ?'; $params[] = '%"' . self::likeEsc($tag) . '"%'; }
        $sql = "SELECT id,source,source_id,type,title,original_title,year,rating,genres,poster,backdrop,overview,api_calls,clicks FROM media_items";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY $order DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = ($page - 1) * $per;
        $st = $pdo->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR); }
        $st->execute();
        return $st->fetchAll();
    }

    /** 全部条目 id（静态生成用） */
    public static function allIds()
    {
        return DB::pdo()->query("SELECT id FROM media_items ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function writeSyncLog($task, array $detail,$count)
    {
        $st = DB::pdo()->prepare("INSERT INTO sync_log (task,detail,items,created_at) VALUES (?,?,?,NOW())");
        $st->execute([$task, json_encode($detail, JSON_UNESCAPED_UNICODE), $count]);
    }

    /* ==================== 链接失效检查 ==================== */

    /** 待检查的条目：优先取「从未检测」或「检测最旧」的，含下载链接，每次取一批 */
    public static function itemsWithLinks($limit = 40)
    {
        $st = DB::pdo()->prepare("SELECT m.id, m.download_url
            FROM media_items m
            LEFT JOIN (SELECT item_id, MAX(checked_at) AS last FROM link_checks GROUP BY item_id) lc
                   ON lc.item_id = m.id
            WHERE m.download_url IS NOT NULL AND m.download_url<>'' AND m.download_url<>'[]'
            ORDER BY (lc.last IS NULL) DESC, lc.last ASC
            LIMIT ?");
        $st->bindValue(1, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** 含下载链接的条目总数 */
    public static function countItemsWithLinks()
    {
        $st = DB::pdo()->query("SELECT COUNT(*) c FROM media_items
            WHERE download_url IS NOT NULL AND download_url<>'' AND download_url<>'[]'");
        return self::countOf($st);
    }

    /** 尚未检测 / 检测结果过期的条目数（批量检查进度用） */
    public static function countPendingLinkChecks($staleMinutes = 5)
    {
        $st = DB::pdo()->prepare("SELECT COUNT(*) c
            FROM media_items m
            LEFT JOIN (SELECT item_id, MAX(checked_at) AS last FROM link_checks GROUP BY item_id) lc
                   ON lc.item_id = m.id
            WHERE m.download_url IS NOT NULL AND m.download_url<>'' AND m.download_url<>'[]'
              AND (lc.last IS NULL OR lc.last < DATE_SUB(NOW(), INTERVAL ? MINUTE))");
        $st->bindValue(1, $staleMinutes, \PDO::PARAM_INT);
        $st->execute();
        return self::countOf($st);
    }

    /** 覆盖写入某条目的检测结果 */
    public static function saveLinkChecks($itemId, array $rows)
    {
        $pdo = DB::pdo();
        $pdo->prepare("DELETE FROM link_checks WHERE item_id=?")->execute([$itemId]);
        $ins = $pdo->prepare("INSERT INTO link_checks (item_id,label,url,status,http_code,checked_at)
            VALUES (?,?,?,?,?,NOW())");
        foreach ($rows as $r) {
            $ins->execute([
                $itemId,
                (string) ((isset($r['label']) ? $r['label'] : '')),
                (string) ((isset($r['url']) ? $r['url'] : '')),
                !empty($r['ok']) ? 1 : 0,
                (int) ((isset($r['code']) ? $r['code'] : 0)),
            ]);
        }
    }

    /** 读取某条目的最新检测结果 */
    public static function getLinkChecks($itemId)
    {
        $st = DB::pdo()->prepare("SELECT id,item_id,label,url,status,http_code,checked_at
            FROM link_checks WHERE item_id=? ORDER BY id");
        $st->execute([$itemId]);
        return $st->fetchAll();
    }

    /** 列表页用的状态汇总：item_id => [ok,total,dead] */
    public static function linkStatusMap()
    {
        $st = DB::pdo()->query("SELECT item_id, SUM(status) AS ok, COUNT(*) AS total, SUM(1-status) AS dead
            FROM link_checks GROUP BY item_id");
        $map = [];
        foreach ($st->fetchAll() as $r) {
            $map[(int) $r['item_id']] = [
                'ok'    => (int) $r['ok'],
                'dead'  => (int) $r['dead'],
                'total' => (int) $r['total'],
            ];
        }
        return $map;
    }

    /** 取 COUNT(*) 结果（兼容 PHP 5.6：不用 ?? 与「链式调用后直接下标」写法） */
    private static function countOf($st)
    {
        $row = $st->fetch();
        return (int) (is_array($row) && isset($row['c']) ? $row['c'] : 0);
    }


    /**
     * 自动刮削：为 poster 或 backdrop 为空的条目补充图片 URL
     */
    public static function autoScrapeImages(string $type = '', int $limit = 50): int
    {
        $pdo = DB::pdo();

        $where = [];
        $params = [];
        if ($type !== '') {
            $where[] = 'type = ?';
            $params[] = $type;
        }
        $where[] = '(poster = \'\' OR poster IS NULL OR backdrop = \'\' OR backdrop IS NULL)';

        /* ★ v1.9.4 fix：这里必须把 poster / backdrop 一起查出来，
           否则下面判断「该字段是否为空」时永远拿不到值（未定义索引），
           既会刷 PHP Notice，也无法判断是否真的需要补图。 */
        $sql = 'SELECT id, source, source_id, type, poster, backdrop FROM media_items';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return 0;
        }

        require_once __DIR__ . '/../adapters/Tmdb.php';
        $tmdb = new \Adapters\Tmdb();

        $updated = 0;
        foreach ($items as $item) {
            try {
                $itemId = (int) $item['id'];
                $sourceId = $item['source_id'];
                $itemType = $item['type'];

                if ($item['source'] !== 'tmdb') {
                    continue;
                }

                $tmdbType = 'movie';
                if ($itemType === 'tv' || $itemType === 'series') {
                    $tmdbType = 'tv';
                } elseif ($itemType === 'anime') {
                    $tmdbType = 'tv';
                }

                $raw = $tmdb->fetchRaw($tmdbType, $sourceId);
                if (!$raw || (!isset($raw['poster_path']) && !isset($raw['backdrop_path']))) {
                    continue;
                }

                $cfg = Config::get('tmdb');
                $imgBase = $cfg['image_base'];

                $poster = '';
                $backdrop = '';
                if (!empty($raw['poster_path'])) {
                    $poster = $imgBase . 'w500' . $raw['poster_path'];
                }
                if (!empty($raw['backdrop_path'])) {
                    $backdrop = $imgBase . 'original' . $raw['backdrop_path'];
                }

                $updates = [];
                $updateParams = [];
                if (empty($item['poster']) || $item['poster'] === ' ' || strpos($item['poster'], 'http') !== 0) {
                    $updates[] = 'poster = :poster';
                    $updateParams[':poster'] = $poster;
                }
                if (empty($item['backdrop']) || $item['backdrop'] === ' ' || strpos($item['backdrop'], 'http') !== 0) {
                    $updates[] = 'backdrop = :backdrop';
                    $updateParams[':backdrop'] = $backdrop;
                }

                if (!empty($updates)) {
                    $updateSql = 'UPDATE media_items SET ' . implode(', ', $updates) . ' WHERE id = :id';
                    $updateParams[':id'] = $itemId;
                    $updStmt = $pdo->prepare($updateSql);
                    foreach ($updateParams as $k => $v) {
                        $updStmt->bindValue($k, $v);
                    }
                    $updStmt->execute();
                    $updated++;
                }

                usleep(200000);

            } catch (Exception $e) {
                error_log('[autoScrape] 条目 ' . $item['id'] . ' 失败: ' . $e->getMessage());
            }
        }

        return $updated;
    }}
