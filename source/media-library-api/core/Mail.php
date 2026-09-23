<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/* 依赖自己兜住：让本文件也能被 admin / user.php / check.php 单独引入。 */
require_once __DIR__ . '/Config.php';

/**
 * 邮件发送（SMTP）
 * ==================================================================
 * 目的：给「找回密码 / 重置密码」发信。**不依赖任何第三方库**（宝塔上多数站点
 * 装不了 composer），纯 PHP 用 stream_socket_client 手写 SMTP 会话。
 *
 * 支持：
 *   · 加密方式：ssl（465，直连 TLS）/ tls（587，先明文再 STARTTLS）/ none（25）
 *   · 认证：AUTH LOGIN（绝大多数邮箱：QQ / 163 / 阿里云 / 腾讯企业邮 / Gmail 等）
 *   · 中文主题与正文：Subject 用 =?UTF-8?B?..?= 编码；正文 base64 编码后传输
 *   · HTML 邮件（找回密码信就是 HTML），同时带一份纯文本兜底
 *
 * 配置来源：后台「站点设置 → 邮件服务」保存到 app_settings，键前缀 mail_*，
 *           叠加后落在 Config 的 $cfg['mail'] 段（见 Core\Settings::mailMap()）。
 *
 * 兼容 PHP 5.6 ~ 8.2（不用 ?? / 返回类型 / 标量类型提示 / \Throwable / ?-> / match）
 */
class Mail
{
    /** 上一次 SMTP 会话日志（成功或失败都记，供后台「测试发信」展示） */
    private static $lastLog = '';

    /** 读取邮件配置（后台覆盖 > config.php 的 mail 段 > 内置默认） */
    public static function cfg()
    {
        return array(
            'enable'    => self::flag(Config::sub('mail', 'enable', 0)),
            'host'      => trim((string) Config::sub('mail', 'host', '')),
            'port'      => (int) Config::sub('mail', 'port', 465),
            'secure'    => strtolower(trim((string) Config::sub('mail', 'secure', 'ssl'))),
            'user'      => trim((string) Config::sub('mail', 'user', '')),
            'pass'      => (string) Config::sub('mail', 'pass', ''),
            'from'      => trim((string) Config::sub('mail', 'from', '')),
            'from_name' => trim((string) Config::sub('mail', 'from_name', '')),
            'timeout'   => max(5, (int) Config::sub('mail', 'timeout', 15)),
        );
    }

    /** 1/0/on/yes 当布尔（'0' 是合法值，不能用 empty 判断） */
    private static function flag($v)
    {
        if (is_bool($v)) { return $v; }
        $s = strtolower(trim((string) $v));
        return in_array($s, array('1', 'on', 'true', 'yes', 'y'), true);
    }

    /** 是否已具备发信条件（开关打开 + 必填项齐全） */
    public static function ready()
    {
        $c = self::cfg();
        return ($c['enable'] && $c['host'] !== '' && $c['user'] !== '' && $c['pass'] !== '' && $c['from'] !== '');
    }

    /** 未就绪时给出人话原因（后台展示用） */
    public static function whyNotReady()
    {
        return self::whyNotReadyOf(self::cfg());
    }

    /** 同上，但针对一份给定的配置（后台「用草稿值测试」时用） */
    private static function whyNotReadyOf($c)
    {
        if (!$c['enable'])            { return '邮件服务未启用（请到「站点设置 → 邮件服务」打开开关）。'; }
        if ($c['host'] === '')        { return '还没填 SMTP 服务器地址。'; }
        if ($c['user'] === '')        { return '还没填 SMTP 账号。'; }
        if ($c['pass'] === '')        { return '还没填 SMTP 密码 / 授权码。'; }
        if ($c['from'] === '')        { return '还没填发件人邮箱。'; }
        return '';
    }

    /** 上一次会话日志 */
    public static function lastLog()
    {
        return self::$lastLog;
    }

    /**
     * 发一封邮件。
     * @param array|null $ov 可选的配置覆盖（后台用「还没保存」的草稿值测试时传），
     *                       形如 array('host'=>..,'port'=>..,'user'=>..,'pass'=>..,'from'=>..)
     * @return array ok / msg
     */
    public static function send($to, $subject, $html, $text = '', $ov = null)
    {
        $c = self::cfg();
        if (is_array($ov)) {
            foreach ($ov as $k => $v) {
                if ($v === null || $v === '') { continue; }
                $c[$k] = $v;
            }
            $c['port']   = (int) $c['port'];
            $c['enable'] = self::flag($c['enable']);
        }
        $why = self::whyNotReadyOf($c);
        if ($why !== '') {
            self::$lastLog = $why;
            return array('ok' => false, 'msg' => $why);
        }
        $to = trim((string) $to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastLog = '收件邮箱不合法：' . $to;
            return array('ok' => false, 'msg' => '收件邮箱不合法。');
        }
        if ($text === '') {
            $text = trim(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), "\n", (string) $html)));
        }

        $fromName = ($c['from_name'] !== '') ? $c['from_name'] : $c['from'];
        $data = self::buildMessage($c['from'], $fromName, $to, $subject, $html, $text);

        $r = self::smtp($c, $c['from'], $to, $data);
        if (!$r['ok']) {
            return array('ok' => false, 'msg' => '发送失败：' . $r['msg']);
        }
        return array('ok' => true, 'msg' => '邮件已发送到 ' . $to . '。');
    }

    /** 给后台「测试发信」用：发一封简短的测试邮件 */
    public static function test($to, $ov = null)
    {
        $site = (string) Config::sub('site', 'name', '媒资资料库');
        $now  = date('Y-m-d H:i:s');
        $html = '<div style="font-family:-apple-system,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;font-size:14px;line-height:1.9;color:#1b1f2a">'
              . '<p>这是一封来自 <b>' . self::h($site) . '</b> 的测试邮件。</p>'
              . '<p>能收到它，说明后台的 SMTP 邮件服务配置<b>正确可用</b>，「找回密码」即可正常发信。</p>'
              . '<p style="color:#94a0b8;font-size:12.5px">发送时间：' . $now . '</p>'
              . '</div>';
        return self::send($to, '【' . $site . '】SMTP 测试邮件', $html, '', $ov);
    }

    /* ================================================================ */
    /* 组装 MIME 邮件                                                    */
    /* ================================================================ */

    private static function buildMessage($from, $fromName, $to, $subject, $html, $text)
    {
        $eol = "\r\n";
        $bFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $bSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $boundary = '=_ml_' . md5(uniqid((string) mt_rand(), true));
        $head  = 'From: ' . $bFrom . $eol;
        $head .= 'To: <' . $to . '>' . $eol;
        $head .= 'Subject: ' . $bSubj . $eol;
        $head .= 'Date: ' . date('r') . $eol;
        $head .= 'Message-ID: <' . md5(uniqid((string) mt_rand(), true)) . '@' . self::hostOf($from) . '>' . $eol;
        $head .= 'MIME-Version: 1.0' . $eol;
        $head .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . $eol;

        $body  = '--' . $boundary . $eol;
        $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($text), 76, $eol);
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($html), 76, $eol);
        $body .= '--' . $boundary . '--';

        return $head . $eol . $body;
    }

    private static function hostOf($mail)
    {
        $p = strpos((string) $mail, '@');
        return ($p === false) ? 'localhost' : substr((string) $mail, $p + 1);
    }

    /* ================================================================ */
    /* SMTP 会话                                                         */
    /* ================================================================ */

    /**
     * @return array ok / msg
     */
    private static function smtp($c, $from, $to, $data)
    {
        $log = array();
        $secure = $c['secure'];
        if (!in_array($secure, array('ssl', 'tls', 'none'), true)) { $secure = 'ssl'; }

        $host = $c['host'];
        $port = (int) $c['port'];
        if ($port < 1) { $port = ($secure === 'ssl') ? 465 : (($secure === 'tls') ? 587 : 25); }

        $remote = (($secure === 'ssl') ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(array('ssl' => array(
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        )));

        $fp = @stream_socket_client($remote, $errno, $errstr, (int) $c['timeout'], STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            self::$lastLog = '连接 ' . $remote . ' 失败：' . $errstr . '（' . $errno . '）';
            return array('ok' => false, 'msg' => self::$lastLog);
        }
        stream_set_timeout($fp, (int) $c['timeout']);

        $fail = function ($why) use ($fp) {
            @fclose($fp);
            self::$lastLog = $why;
            return array('ok' => false, 'msg' => $why);
        };

        /* 1. 问候 */
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('服务器未就绪：' . $r['line']); }

        $ehloHost = self::hostOf($from);
        if ($ehloHost === '') { $ehloHost = 'localhost'; }

        /* 2. EHLO */
        self::cmd($fp, 'EHLO ' . $ehloHost, $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) {
            /* 有些老服务器只认 HELO */
            self::cmd($fp, 'HELO ' . $ehloHost, $log);
            $r = self::readResp($fp, $log);
            if (!$r['ok']) { return $fail('EHLO/HELO 失败：' . $r['line']); }
        }

        /* 3. STARTTLS（tls 模式） */
        if ($secure === 'tls') {
            self::cmd($fp, 'STARTTLS', $log);
            $r = self::readResp($fp, $log);
            if (!$r['ok']) { return $fail('服务器拒绝 STARTTLS：' . $r['line']); }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                return $fail('STARTTLS 握手失败（服务器可能只支持特定 TLS 版本）。');
            }
            self::cmd($fp, 'EHLO ' . $ehloHost, $log);
            self::readResp($fp, $log);
        }

        /* 4. AUTH LOGIN */
        self::cmd($fp, 'AUTH LOGIN', $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('服务器不支持 AUTH LOGIN：' . $r['line']); }
        self::cmd($fp, base64_encode($c['user']), $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('SMTP 账号被拒：' . $r['line']); }
        self::cmd($fp, base64_encode($c['pass']), $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('SMTP 密码 / 授权码不对：' . $r['line']); }

        /* 5. 信封 */
        self::cmd($fp, 'MAIL FROM:<' . $from . '>', $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('MAIL FROM 被拒：' . $r['line']); }

        self::cmd($fp, 'RCPT TO:<' . $to . '>', $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('收件人被拒（' . $to . '）：' . $r['line']); }

        /* 6. DATA */
        self::cmd($fp, 'DATA', $log);
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('DATA 被拒：' . $r['line']); }

        /* 正文：点号行转义 + 以 <CRLF>.<CRLF> 结束 */
        $payload = preg_replace('/^\./m', '..', (string) $data);
        @fwrite($fp, $payload . "\r\n.\r\n");
        $log[] = 'C: [message body, ' . strlen($payload) . ' bytes]';
        $r = self::readResp($fp, $log);
        if (!$r['ok']) { return $fail('邮件正文被拒：' . $r['line']); }

        self::cmd($fp, 'QUIT', $log);
        @fclose($fp);

        self::$lastLog = implode("\n", $log);
        return array('ok' => true, 'msg' => 'OK');
    }

    /** 写一条命令并记日志 */
    private static function cmd($fp, $line, &$log)
    {
        $log[] = 'C: ' . $line;
        @fwrite($fp, $line . "\r\n");
    }

    /**
     * 读一条（或多行）SMTP 响应。
     * 多行响应形如「250-XXX」，最后一行是「250 XXX」（第 4 个字符为空格）。
     * 判定成功：状态码首字符为 2 或 3。
     * @return array ok / line
     */
    private static function readResp($fp, &$log)
    {
        $last = '';
        for ($i = 0; $i < 50; $i++) {
            $line = @fgets($fp, 1024);
            if ($line === false) {
                $log[] = 'S: (连接中断)';
                return array('ok' => false, 'line' => $last !== '' ? $last : '连接中断 / 超时');
            }
            $line = rtrim($line, "\r\n");
            $log[] = 'S: ' . $line;
            $last = $line;
            /* 第 4 个字符是 '-' 说明还有后续行 */
            if (strlen($line) >= 4 && $line[3] === '-') { continue; }
            break;
        }
        $code = substr($last, 0, 1);
        return array('ok' => ($code === '2' || $code === '3'), 'line' => $last);
    }

    /* ================================================================ */
    /* 邮件模板                                                          */
    /* ================================================================ */

    private static function h($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 找回密码邮件（HTML + 纯文本）。
     * @return array html / text
     */
    public static function resetTpl($site, $username, $link, $minutes)
    {
        $site = ($site !== '') ? $site : '媒资资料库';
        $minutes = max(1, (int) $minutes);

        $html = '<div style="font-family:-apple-system,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;font-size:14px;line-height:1.9;color:#1b1f2a;max-width:560px">'
              . '<p>你好，<b>' . self::h($username) . '</b>：</p>'
              . '<p>我们收到了你在 <b>' . self::h($site) . '</b> 的「找回密码」请求。点击下面的按钮即可设置新密码：</p>'
              . '<p style="margin:22px 0">'
              . '<a href="' . self::h($link) . '" style="display:inline-block;background:#5b5bd6;color:#fff;text-decoration:none;padding:12px 26px;border-radius:10px;font-weight:600">设置新密码</a>'
              . '</p>'
              . '<p style="color:#64748b;font-size:13px">按钮点不动？把下面这条链接复制到浏览器打开：<br>'
              . '<a href="' . self::h($link) . '" style="color:#5b5bd6;word-break:break-all">' . self::h($link) . '</a></p>'
              . '<p style="color:#94a0b8;font-size:12.5px;border-top:1px solid #e6eaf3;padding-top:12px;margin-top:20px">'
              . '此链接 ' . $minutes . ' 分钟内有效，且只能使用一次。若非你本人操作，请忽略本邮件，你的密码不会被更改。</p>'
              . '</div>';

        $text = "你好，" . $username . "：\n\n"
              . "我们收到了你在「" . $site . "」的找回密码请求。请在 " . $minutes . " 分钟内打开下面的链接设置新密码：\n\n"
              . $link . "\n\n"
              . "此链接只能使用一次。若非你本人操作，请忽略本邮件。\n";

        return array('html' => $html, 'text' => $text);
    }
}
