<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/**
 * 极简 HTTP 客户端：curl GET + 自动重试 + 429 退避
 */
class Http
{
    public static function get($url, array $headers = [],$retries = 3)
    {
        $attempt = 0;
        $backoff = 1;
        while ($attempt < $retries) {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'MediaLibraryAPI/1.0',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                if ($attempt < $retries) { sleep($backoff); $backoff = min($backoff * 2, 8); continue; }
                throw new \RuntimeException("HTTP request failed: $err ($url)");
            }
            if ($code === 429) {
                if ($attempt < $retries) { sleep($backoff); $backoff = min($backoff * 2, 8); continue; }
            }
            $data = json_decode($body, true);
            if (!is_array($data)) {
                if ($attempt < $retries) { sleep($backoff); $backoff = min($backoff * 2, 8); continue; }
                throw new \RuntimeException("Invalid JSON from $url");
            }
            return ['code' => $code, 'data' => $data];
        }
        throw new \RuntimeException("HTTP request failed after retries: $url");
    }
}
