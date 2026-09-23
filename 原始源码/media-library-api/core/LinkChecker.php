<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 下载链接存活检测
 * 后台「链接失效检查」调用：对每个网盘地址做 HEAD（失败回退 GET）请求，
 * 依据最终 HTTP 状态码判定有效 / 失效，结果写入 link_checks 表供后台展示。
 */
class LinkChecker
{
    /**
     * 检测单个链接。
     * @return array{ok:bool,code:int,msg:string}
     */
    public static function check($url,$timeout = 8)
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'code' => 0, 'msg' => '无效地址'];
        }

        // 第一轮：HEAD
        $r = self::request($url, true, $timeout);
        // HEAD 不被支持 / 被拦截 / 网络错 → 回退 GET（仅取头部，限制体积）
        if ($r['err'] !== '' || in_array($r['code'], [0, 403, 405, 501], true)) {
            $r = self::request($url, false, $timeout);
        }

        if ($r['err'] !== '') {
            return ['ok' => false, 'code' => 0, 'msg' => $r['err']];
        }
        $ok = $r['code'] >= 200 && $r['code'] < 400;
        return ['ok' => $ok, 'code' => $r['code'], 'msg' => $ok ? '' : 'HTTP ' . $r['code']];
    }

    /** @return array{code:int,err:string} */
    private static function request($url,$head,$timeout)
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_NOBODY         => $head,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MediaLibLinkCheck/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => ['Accept: */*', 'Cache-Control: no-cache'],
        ];
        if (!$head) {
            $opts[CURLOPT_RANGE] = '0-2047'; // 只取前 2KB，避免拉取整文件
        }
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'err' => $err];
    }
}
