<?php
namespace Core;

/* 安全守卫：本文件只应被入口（index.php / check.php / cron / CLI）引入。
   若有人直接用网址请求本文件，会返回 404 —— 见 core/Guard.php。 */
require_once __DIR__ . '/Guard.php';
ml_guard_shield(__FILE__);

/* 依赖自己兜住，便于单独引入 */
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/Site.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Views.php';

/**
 * 前台「注册 / 登录 / 资源请求」三个页面的渲染层
 * ==================================================================
 * 复用 core/Views.php 的顶栏、页脚与整套 CSS 变量（--maxw / --pad /
 * --card / --line / --txt / --mut …），所以：
 *   · 站点改「基础设置 / SEO / 前端模板 / 明暗模式」，这几个页面自动跟着变；
 *   · 手机上沿用同一套断点与安全区处理，不会出现"后台自适应了、前台没跟上"。
 *
 * 表单一律用原生 form POST（POST → 重定向 → GET，即 PRG 模式），
 * 不依赖 JavaScript，也不会有「点了没反应」那类静默失败；
 * 只有「站内是否已有这条资源」的即时提示用了 fetch，取不到就静默隐藏。
 *
 * 兼容 PHP 5.6 ~ 8.2。
 */
class UserViews
{
    public static function esc($s)
    {
        return Views::esc($s);
    }

    /** 资源类型中文标签 */
    private static function label($t)
    {
        return Categories::label($t);
    }

    /** 资源类型下拉选项 */
    private static function typeOptions($sel)
    {
        $sel = (string) $sel;
        $h = '<option value="">不限 / 其他</option>';
        foreach (Categories::TYPES as $t) {
            $h .= '<option value="' . self::esc($t) . '"'
                . ($t === $sel ? ' selected' : '') . '>' . self::esc(self::label($t)) . '</option>';
        }
        return $h;
    }

    /* ================================================================ */
    /* 页面外壳                                                          */
    /* ================================================================ */

    /**
     * 这几个页面属于功能性页面，默认加 noindex（不参与收录），
     * 但保留 follow，让爬虫照常顺着链接走。
     */
    public static function head($title, $desc = '', $noindex = true)
    {
        $info = Site::info();
        $site = $info['name'];
        $full = ($title !== '' ? $title . ' - ' . $site : $site);

        $h = '<title>' . self::esc($full) . '</title>' . "\n";
        if ($desc !== '') {
            $h .= '<meta name="description" content="' . self::esc($desc) . '">' . "\n";
        }
        $h .= $noindex
            ? '<meta name="robots" content="noindex,follow">' . "\n"
            : '<meta name="robots" content="index,follow">' . "\n";
        if ($info['favicon'] !== '') {
            $h .= '<link rel="icon" href="' . self::esc(Site::asset($info['favicon'])) . '">' . "\n";
        }
        /* 统计代码：与首页共用一份，站长在「站点设置 → SEO」里填一次即可 */
        $stats = trim((string) Config::sub('seo', 'stats_code', ''));
        if ($stats !== '') {
            $h .= $stats . "\n";
        }
        return $h;
    }

    /** 统一外壳：顶栏 + 内容 + 页脚 */
    public static function shell($title, $inner, $noindex = true, $right = '', $desc = '')
    {
        $head  = self::head($title, $desc, $noindex);
        $top   = Views::topbarHtml($right);
        $foot  = Views::footerHtml();
        $cls   = Views::bodyClass();
        $css   = Views::css() . self::ucss();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
{$head}
<style>{CSS_PLACEHOLDER}</style>
</head>
<body class="{$cls}">
{$top}
<div class="u-wrap">
{$inner}
</div>
{$foot}
</body>
</html>
HTML;
        return str_replace('{CSS_PLACEHOLDER}', $css, $html);
    }

    /** 一次性提示条（表单没填对 / 提交成功，都走这里） */
    public static function flashHtml()
    {
        $f = User::flashGet();
        if (!$f) { return ''; }
        $kind = ($f['kind'] === 'ok') ? 'ok' : (($f['kind'] === 'info') ? 'info' : 'err');
        return '<div class="u-flash ' . $kind . '">' . self::esc($f['msg']) . '</div>';
    }

    /* ================================================================ */
    /* 注册                                                              */
    /* ================================================================ */

    /**
     * @param array $f 回填字段 title/user/email + captcha 题面（失败后重新渲染时用）
     */
    public static function registerHtml(array $f = array())
    {
        $c     = User::cfg();
        $title = '注册账号';
        $right = '<a class="admin" href="/">← 返回主页</a>';

        if (!$c['open']) {
            $inner = '<div class="u-card"><div class="u-lock">'
                . '<div class="ico">🔒</div><h3>暂未开放注册</h3>'
                . '<p>站长关闭了注册入口。已注册的用户可以直接登录。</p>'
                . '<a class="u-btn" href="/user/login">去登录</a>'
                . '</div></div>';
            return self::shell($title, $inner, true, $right);
        }

        $cap = '';
        if ($c['verify']) {
            $q = User::captchaNew();
            $cap = '<div class="u-field"><label>验证码 *</label>'
                 . '<div class="u-cap"><span class="q">' . self::esc($q) . '</span>'
                 . '<input class="u-in" type="text" name="captcha" inputmode="numeric" autocomplete="off" placeholder="填计算结果" required>'
                 . '</div><div class="tip">防止机器人批量注册，算一下就好。</div></div>';
        }

        $auditTip = $c['audit']
            ? '<div class="u-flash info">本站开启了注册审核：提交后需要管理员通过才能登录，请留意站长的回复。</div>'
            : '';

        $u = isset($f['username']) ? (string) $f['username'] : '';
        $e = isset($f['email']) ? (string) $f['email'] : '';

        $form = <<<HTML
{$auditTip}
<form method="post" action="/user/register" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input class="u-hp" type="text" name="ml_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="u-field">
    <label>邮箱 *</label>
    <input class="u-in" type="email" name="email" value="{E}" maxlength="128" placeholder="邮箱即账号，登录与找回密码都用它" required>
    <div class="tip">账号就是邮箱，注册后不可修改；忘记密码时重置链接会发到这里。</div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>密码 *</label>
      <input class="u-in" type="password" name="password" minlength="6" maxlength="64" placeholder="至少 6 位" required>
    </div>
    <div class="u-field">
      <label>确认密码 *</label>
      <input class="u-in" type="password" name="password2" minlength="6" maxlength="64" placeholder="再输一次" required>
    </div>
  </div>
  {CAP}
  <button class="u-btn" type="submit">注册并登录</button>
</form>
<p class="u-alt">已经有账号了？<a href="/user/login">直接登录</a></p>
HTML;

        $form = str_replace(
            array('{CSRF}', '{CAP}'),
            array(self::esc(User::csrf()), $cap),
            $form
        );
        $form = str_replace(array('{U}', '{E}'), array(self::esc($u), self::esc($e)), $form);

        $inner = '<div class="u-card u-narrow">'
            . '<div class="u-head"><h1>注册账号</h1>'
            . '<p>注册后即可在<a href="/request">资源请求</a>页填写你想找的资源，站长会逐条处理。</p></div>'
            . self::flashHtml()
            . $form
            . '</div>';

        return self::shell($title, $inner, true, $right, '注册本站账号，提交你想找的资源。');
    }

    /* ================================================================ */
    /* 登录                                                              */
    /* ================================================================ */

    public static function loginHtml(array $f = array())
    {
        $c     = User::cfg();
        $title = '登录';
        $right = '<a class="admin" href="/">← 返回主页</a>';

        $regLink = $c['open']
            ? '<p class="u-alt">还没有账号？<a href="/user/register">注册一个</a></p>'
            : '<p class="u-alt">本站暂未开放注册，请联系站长开通账号。</p>';

        $u = isset($f['username']) ? (string) $f['username'] : '';

        $form = <<<HTML
<form method="post" action="/user/login" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>邮箱</label>
    <input class="u-in" type="email" name="username" value="{U}" maxlength="128" autocomplete="username" placeholder="注册时的邮箱" required>
  </div>
  <div class="u-field">
    <label>密码</label>
    <input class="u-in" type="password" name="password" maxlength="64" autocomplete="current-password" required>
  </div>
  <button class="u-btn" type="submit">登录</button>
</form>
<p class="u-alt"><a href="/user/forgot">忘记密码？</a></p>
{$regLink}
HTML;

        $form = str_replace('{CSRF}', self::esc(User::csrf()), $form);
        $form = str_replace('{U}', self::esc($u), $form);

        $inner = '<div class="u-card u-narrow">'
            . '<div class="u-head"><h1>登录</h1><p>登录后即可提交与查看你的资源请求。</p></div>'
            . self::flashHtml()
            . $form
            . '</div>';

        return self::shell($title, $inner, true, $right, '登录本站账号，提交你想找的资源。');
    }

    /* ================================================================ */
    /* 找回密码 / 重置密码 / 修改密码                                     */
    /* ================================================================ */

    /** 找回密码页：填用户名或邮箱，发一封带重置链接的邮件 */
    public static function forgotHtml()
    {
        $title = '找回密码';
        $right = '<a class="admin" href="/">← 返回主页</a>';

        if (!Mail::ready()) {
            $inner = '<div class="u-card u-narrow">'
                . '<div class="u-head"><h1>找回密码</h1></div>'
                . self::flashHtml()
                . '<div class="u-lock"><div class="ico">📮</div>'
                . '<h3>本站还没有配置邮件服务</h3>'
                . '<p>站长尚未开启 SMTP 发信，暂时无法自助找回密码。<br>请直接联系站长重置，给您带来不便敬请谅解。</p>'
                . '<a class="u-btn ghost" href="/user/login">返回登录</a>'
                . '</div></div>';
            return self::shell($title, $inner, true, $right, '找回账号密码。');
        }

        $form = <<<HTML
<form method="post" action="/user/forgot" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input class="u-hp" type="text" name="ml_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="u-field">
    <label>注册邮箱</label>
    <input class="u-in" type="email" name="account" maxlength="128" placeholder="注册时用的邮箱" required>
    <div class="tip">系统会把「设置新密码」的链接发到这个邮箱，链接 60 分钟内有效。</div>
  </div>
  <button class="u-btn" type="submit">发送找回邮件</button>
</form>
<p class="u-alt">想起来了？<a href="/user/login">返回登录</a></p>
HTML;
        $form = str_replace('{CSRF}', self::esc(User::csrf()), $form);

        $inner = '<div class="u-card u-narrow">'
            . '<div class="u-head"><h1>找回密码</h1><p>填入账号信息，我们把重置链接发到你的注册邮箱。</p></div>'
            . self::flashHtml()
            . $form
            . '</div>';

        return self::shell($title, $inner, true, $right, '找回账号密码。');
    }

    /**
     * 重置密码页（邮件链接落地页 ?token=xxx）
     * @param string $token 链接里的令牌
     * @param bool   $valid 令牌是否还有效
     */
    public static function resetHtml($token, $valid)
    {
        $title = '设置新密码';
        $right = '<a class="admin" href="/">← 返回主页</a>';

        if (!$valid) {
            $inner = '<div class="u-card u-narrow">'
                . '<div class="u-head"><h1>设置新密码</h1></div>'
                . self::flashHtml()
                . '<div class="u-lock"><div class="ico">⏳</div>'
                . '<h3>链接无效或已过期</h3>'
                . '<p>重置链接只能使用一次，且 60 分钟内有效。<br>请重新走一遍「找回密码」获取新链接。</p>'
                . '<a class="u-btn" href="/user/forgot">重新找回密码</a>'
                . '</div></div>';
            return self::shell($title, $inner, true, $right, '设置新密码。');
        }

        $form = <<<HTML
<form method="post" action="/user/reset" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <input type="hidden" name="token" value="{TOKEN}">
  <div class="u-field">
    <label>新密码</label>
    <input class="u-in" type="password" name="password" minlength="6" maxlength="64" placeholder="至少 6 位" required>
  </div>
  <div class="u-field">
    <label>确认新密码</label>
    <input class="u-in" type="password" name="password2" minlength="6" maxlength="64" placeholder="再输一次" required>
  </div>
  <button class="u-btn" type="submit">保存新密码</button>
</form>
HTML;
        $form = str_replace(
            array('{CSRF}', '{TOKEN}'),
            array(self::esc(User::csrf()), self::esc($token)),
            $form
        );

        $inner = '<div class="u-card u-narrow">'
            . '<div class="u-head"><h1>设置新密码</h1><p>链接验证通过，请设置新的登录密码。</p></div>'
            . self::flashHtml()
            . $form
            . '</div>';

        return self::shell($title, $inner, true, $right, '设置新密码。');
    }

    /** 修改密码页（需登录）：旧密码 + 新密码，顺带可绑定 / 更新邮箱 */
    public static function passwordHtml(array $me)
    {
        $title = '修改密码';
        $right = '<a class="admin" href="/">← 返回主页</a>';

        $email = (isset($me['email']) && (string) $me['email'] !== '') ? (string) $me['email'] : '';
        $emailTip = ($email !== '')
            ? '账号邮箱：' . self::esc($email) . '。忘记密码时，重置链接会发到这里。'
            : '<b style="color:#c0392b">还没绑定邮箱</b>——新注册的账号邮箱即账号；老账号建议补绑一个常用邮箱，忘记密码才能自助重置。';

        $form = <<<HTML
<form method="post" action="/user/password" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>账号邮箱</label>
    <input class="u-in" type="email" name="email" value="{EMAIL}" maxlength="128" placeholder="用于登录与找回密码">
    <div class="tip">{EMAIL_TIP}</div>
  </div>
  <div class="u-field">
    <label>当前密码</label>
    <input class="u-in" type="password" name="old" maxlength="64" autocomplete="current-password" placeholder="填现在的登录密码">
    <div class="tip">只想绑定邮箱、不改密码的话，下面两项留空即可。</div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>新密码</label>
      <input class="u-in" type="password" name="new" minlength="6" maxlength="64" autocomplete="new-password" placeholder="至少 6 位">
    </div>
    <div class="u-field">
      <label>确认新密码</label>
      <input class="u-in" type="password" name="new2" minlength="6" maxlength="64" autocomplete="new-password" placeholder="再输一次">
    </div>
  </div>
  <button class="u-btn" type="submit">保存</button>
</form>
<p class="u-alt"><a href="/request">返回资源请求</a></p>
HTML;
        $form = str_replace(
            array('{CSRF}', '{EMAIL}', '{EMAIL_TIP}'),
            array(self::esc(User::csrf()), self::esc($email), $emailTip),
            $form
        );

        $inner = '<div class="u-card u-narrow">'
            . '<div class="u-head"><h1>修改密码</h1>'
            . '<p>改密码只需当前密码确认；邮箱绑定好之后，忘记密码就能自助重置了。</p></div>'
            . self::flashHtml()
            . $form
            . '</div>';

        return self::shell($title, $inner, true, $right, '修改账号密码或绑定邮箱。');
    }

    /* ================================================================ */
    /* 资源请求                                                          */
    /* ================================================================ */

    /**
     * @param array $ctx me / list / total / page / perPage / counts / myList / myTotal
     */
    public static function requestHtml(array $ctx)
    {
        $c     = User::cfg();
        $title = User::pageTitle();
        $me    = isset($ctx['me']) ? $ctx['me'] : null;
        $right = '<a class="admin" href="/">← 返回主页</a>';
        if ($me) {
            $right = '<a class="admin" href="/user/password">修改密码</a>' . $right;
        }

        $head = '<div class="u-head"><h1>' . self::esc($title) . '</h1>';
        $intro = $c['page_intro'] !== ''
            ? $c['page_intro']
            : '想看但站里还没收录的资源，写在这里，站长会定期处理。';
        $head .= '<p>' . User::text($intro) . '</p></div>';

        /* ---- 状态条 ---- */
        $counts = isset($ctx['counts']) ? $ctx['counts'] : array();
        $stMap  = User::reqStatusText();
        $bar = '<div class="u-stats">';
        $bar .= '<span class="u-pill">共 <b>' . (int) (isset($counts['total']) ? $counts['total'] : 0) . '</b> 条</span>';
        foreach ($stMap as $k => $tx) {
            $bar .= '<span class="u-pill">' . self::esc($tx) . ' <b>'
                 . (int) (isset($counts[$k]) ? $counts[$k] : 0) . '</b></span>';
        }
        $bar .= '</div>';

        /* ---- 提交区 ---- */
        $needLogin = ($c['logon'] && !$me);
        $form = '';
        if ($needLogin) {
            $btns = '';
            if ($c['open']) {
                $btns .= '<a class="u-btn" href="/user/register">注册新账号</a> ';
            }
            $btns .= '<a class="u-btn ghost" href="/user/login">已有账号，去登录</a>';
            $form = '<div class="u-lock"><div class="ico">🔐</div>'
                  . '<h3>请先注册并登录</h3>'
                  . '<p>本站的资源请求需要账号才能提交，这样你也能在页面里随时查看处理进度。</p>'
                  . $btns . '</div>';
        } else {
            $contactField = '';
            if ($c['contact']) {
                $pre = ($me && !empty($me['email'])) ? (string) $me['email'] : '';
                $contactField = '<div class="u-field"><label>联系方式（选填）</label>'
                    . '<input class="u-in" type="text" name="contact" value="' . self::esc($pre) . '" maxlength="128"'
                    . ' placeholder="邮箱 / 微信 / QQ，方便找到后通知你"></div>';
            }
            $left = $c['daily'] > 0
                ? '每人每天最多提交 ' . (int) $c['daily'] . ' 条；'
                : '';
            $form = <<<HTML
<form method="post" action="/request" autocomplete="off">
  <input type="hidden" name="csrf" value="{CSRF}">
  <div class="u-field">
    <label>要找的资源名称 *</label>
    <input class="u-in" type="text" id="rq_title" name="title" maxlength="120" placeholder="例如：庆余年 第二季" required>
    <div class="u-hintbox" id="rqHint"></div>
  </div>
  <div class="u-row2">
    <div class="u-field">
      <label>类型</label>
      <select class="u-in" name="type">{TYPES}</select>
    </div>
    <div class="u-field">
      <label>年份（选填）</label>
      <input class="u-in" type="number" name="year" min="1900" max="2100" placeholder="如 2024">
    </div>
  </div>
  {CONTACT}
  <div class="u-field">
    <label>补充说明（选填）</label>
    <textarea class="u-in" name="note" maxlength="500" placeholder="哪个版本 / 清晰度 / 字幕要求 / 其他线索…"></textarea>
    <div class="tip">写清楚一点，站长更容易找对。{$left}不要重复提交同一条。</div>
  </div>
  <button class="u-btn" type="submit">提交请求</button>
</form>
HTML;
            $form = str_replace('{CSRF}', self::esc(User::csrf()), $form);
            $form = str_replace('{TYPES}', self::typeOptions(''), $form);
            $form = str_replace('{CONTACT}', $contactField, $form);
        }

        /* ---- 我的请求 ---- */
        $mine = '';
        if ($me) {
            $mine = '<div class="u-card"><div class="u-head"><h1 style="font-size:16px">我的请求</h1>'
                  . '<p>共 ' . (int) (isset($ctx['myTotal']) ? $ctx['myTotal'] : 0) . ' 条。'
                  . '还没处理的可以自己撤回。</p></div>'
                  . self::reqList(isset($ctx['myList']) ? $ctx['myList'] : array(), true)
                  . '</div>';
        }

        /* ---- 大家的请求（后台可关） ---- */
        $all = '';
        if ($c['public'] && ($me || $c['guest_view'])) {
            $pager = self::pager(
                (int) (isset($ctx['page']) ? $ctx['page'] : 1),
                (int) (isset($ctx['perPage']) ? $ctx['perPage'] : 20),
                (int) (isset($ctx['total']) ? $ctx['total'] : 0)
            );
            $all = '<div class="u-card"><div class="u-head"><h1 style="font-size:16px">大家都在找</h1>'
                 . '<p>站长按提交顺序处理，已找到的会在这里变成「已找到」。</p></div>'
                 . self::reqList(isset($ctx['list']) ? $ctx['list'] : array(), false)
                 . $pager
                 . '</div>';
        }

        $notice = $c['notice'] !== ''
            ? '<div class="u-flash info">' . User::text($c['notice']) . '</div>'
            : '';

        $inner = $head . self::flashHtml() . $bar . $notice
               . '<div class="u-card">' . $form . '</div>'
               . $mine . $all . self::reqScript();

        return self::shell($title, $inner, true, $right, $intro);
    }

    /** 请求列表 */
    private static function reqList(array $rows, $mine)
    {
        if (!$rows) {
            return '<div class="u-empty">'
                . ($mine ? '还没有提交过请求。' : '还没有人提交请求，你可以是第一个。')
                . '</div>';
        }
        $stMap = User::reqStatusText();
        $h = '<div class="u-list">';
        foreach ($rows as $r) {
            $st   = (string) (isset($r['status']) ? $r['status'] : 'pending');
            $tx   = isset($stMap[$st]) ? $stMap[$st] : '待处理';
            $id   = (int) (isset($r['id']) ? $r['id'] : 0);
            $ti   = (string) (isset($r['title']) ? $r['title'] : '');
            $ty   = (string) (isset($r['type']) ? $r['type'] : '');
            $yr   = isset($r['year']) && $r['year'] !== null && $r['year'] !== '' ? (int) $r['year'] : 0;
            $time = (string) (isset($r['created_at']) ? $r['created_at'] : '');

            $meta = array();
            if ($ty !== '')  { $meta[] = self::label($ty); }
            if ($yr > 0)     { $meta[] = $yr; }
            if (!$mine && !empty($r['username'])) { $meta[] = '由 ' . (string) $r['username'] . ' 提交'; }
            if ($time !== '') { $meta[] = substr($time, 0, 16); }

            $note = (string) (isset($r['note']) ? $r['note'] : '');
            $rep  = (string) (isset($r['admin_note']) ? $r['admin_note'] : '');
            $iid  = (int) (isset($r['item_id']) ? $r['item_id'] : 0);

            $h .= '<div class="u-item">';
            $h .= '<div class="t">' . self::esc($ti)
                . ' <span class="u-badge ' . User::statusClass($st) . '">' . self::esc($tx) . '</span>'
                . '</div>';
            if ($meta) {
                $h .= '<div class="m">' . self::esc(implode(' · ', $meta)) . '</div>';
            }
            if ($note !== '') {
                $h .= '<div class="n">' . User::text($note) . '</div>';
            }
            /* 站长的回复：只有本人可见（公开列表里别人的回复就不显示了） */
            if ($mine && $rep !== '') {
                $h .= '<div class="u-reply"><b>站长回复：</b>' . User::text($rep) . '</div>';
            }
            if ($iid > 0) {
                $h .= '<div class="m">站内已收录：<a href="/uisc/' . $iid . '.html" target="_blank" rel="noopener">'
                    . '点这里查看 →</a></div>';
            }
            if ($mine && $st === 'pending') {
                $h .= '<form method="post" action="/request" style="margin-top:10px"'
                    . ' onsubmit="return confirm(\'确定撤回这条请求吗？\')">'
                    . '<input type="hidden" name="csrf" value="' . self::esc(User::csrf()) . '">'
                    . '<input type="hidden" name="do" value="del">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<button class="u-btn ghost sm" type="submit">撤回</button>'
                    . '</form>';
            }
            $h .= '</div>';
        }
        return $h . '</div>';
    }

    /** 分页 */
    private static function pager($page, $per, $total)
    {
        $pages = max(1, (int) ceil($total / max(1, $per)));
        if ($pages <= 1) { return ''; }
        $h = '<div class="u-pager">';
        $h .= ($page > 1)
            ? '<a href="/request?p=' . ($page - 1) . '">上一页</a>'
            : '<span>上一页</span>';
        $h .= '<span>第 ' . $page . ' / ' . $pages . ' 页</span>';
        $h .= ($page < $pages)
            ? '<a href="/request?p=' . ($page + 1) . '">下一页</a>'
            : '<span>下一页</span>';
        return $h . '</div>';
    }

    /**
     * 站内查重提示脚本（可有可无：接口失败就静默不显示）。
     * 注意：PHP heredoc 里"美元花括号"会被当变量插值，所以这里全用字符串拼接，不写 JS 模板字符串。
     */
    private static function reqScript()
    {
        return <<<HTML
<script>
(function(){
  var el = document.getElementById('rq_title');
  var box = document.getElementById('rqHint');
  if(!el || !box){ return; }
  var timer = null;
  function hide(){ box.className = 'u-hintbox'; box.innerHTML = ''; }
  el.addEventListener('input', function(){
    var v = (el.value || '').trim();
    if(timer) { clearTimeout(timer); }
    if(v.length < 2){ hide(); return; }
    timer = setTimeout(function(){
      fetch('/request?probe=' + encodeURIComponent(v))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(!d || !d.success || !d.data || !d.data.items || !d.data.items.length){ hide(); return; }
          var its = d.data.items, s = '站内可能已经有这些，先看看是不是你要的：';
          for(var i=0;i<its.length;i++){
            s += ' <a href="' + its[i].url + '" target="_blank" rel="noopener">' + its[i].title + '</a>';
          }
          box.className = 'u-hintbox on';
          box.innerHTML = s;
        })
        .catch(function(){ hide(); });
    }, 350);
  });
})();
</script>
HTML;
    }

    /* ================================================================ */
    /* 页面专用样式（全部走 CSS 变量，自动跟随明暗主题）                   */
    /* ================================================================ */

    public static function ucss()
    {
        return <<<CSS
/* ---------- 注册 / 登录 / 资源请求 页面 ---------- */
.u-wrap{max-width:var(--maxw);margin:0 auto;padding:22px var(--pad) 0}
.u-narrow{max-width:520px;margin-left:auto;margin-right:auto}
.u-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:22px;margin-bottom:16px;color:var(--txt)}
.u-head{margin-bottom:16px}
.u-head h1{font-size:20px;margin:0 0 7px;letter-spacing:.3px}
.u-head p{color:var(--mut);font-size:13.5px;line-height:1.95;margin:0}
.u-head a{color:var(--pri);font-weight:600}
.u-field{margin-bottom:14px}
.u-field>label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--txt)}
.u-field .tip{color:var(--mut);font-size:12px;margin-top:6px;line-height:1.7}
.u-in{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px;font-size:14px;background:var(--bg);color:var(--txt);outline:none;transition:border-color .15s,box-shadow .15s;-webkit-appearance:none;appearance:none}
.u-in:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(91,91,214,.14)}
textarea.u-in{min-height:92px;resize:vertical;line-height:1.75;font-family:inherit}
select.u-in{background-image:none}
.u-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.u-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 24px;border:0;border-radius:10px;background:linear-gradient(120deg,var(--pri),var(--pri2));color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:.15s;text-decoration:none;line-height:1.4}
.u-btn:hover{filter:brightness(1.06);box-shadow:var(--shadow-h)}
.u-btn.ghost{background:transparent;border:1px solid var(--line);color:var(--txt);font-weight:500}
.u-btn.ghost:hover{background:var(--bg);box-shadow:none;filter:none}
.u-btn.sm{padding:7px 14px;font-size:13px;font-weight:500}
.u-alt{color:var(--mut);font-size:13px;margin:16px 0 0;text-align:center}
.u-alt a{color:var(--pri);font-weight:600}
.u-flash{border-radius:12px;padding:12px 15px;font-size:13.5px;line-height:1.85;margin-bottom:14px;border:1px solid transparent}
.u-flash.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.u-flash.err{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.u-flash.info{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.u-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.u-pill{background:var(--card);border:1px solid var(--line);border-radius:999px;padding:6px 13px;font-size:12.5px;color:var(--mut)}
.u-pill b{color:var(--txt)}
.u-list{display:flex;flex-direction:column;gap:10px}
.u-item{border:1px solid var(--line);border-radius:12px;padding:14px 16px;background:var(--bg)}
.u-item .t{font-weight:700;font-size:14.5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;word-break:break-word}
.u-item .m{color:var(--mut);font-size:12.5px;margin-top:7px;line-height:1.85}
.u-item .m a{color:var(--pri)}
.u-item .n{font-size:13px;margin-top:8px;line-height:1.85;opacity:.94;word-break:break-word}
.u-badge{font-size:11.5px;font-weight:700;border-radius:999px;padding:3px 10px;white-space:nowrap}
.u-badge.pending{background:#fef3c7;color:#92400e}
.u-badge.found{background:#dcfce7;color:#166534}
.u-badge.notfound{background:#f1f5f9;color:#475569}
.u-badge.replied{background:#dbeafe;color:#1e40af}
.u-badge.closed{background:#f3f4f6;color:#6b7280}
.u-reply{margin-top:9px;padding:10px 13px;border-left:3px solid var(--pri);background:var(--card);border-radius:0 9px 9px 0;font-size:13px;line-height:1.85}
.u-empty{color:var(--mut);font-size:13.5px;padding:26px 8px;text-align:center}
.u-hintbox{display:none;margin-top:9px;font-size:13px;line-height:2;border-radius:11px;padding:11px 13px;background:var(--bg);border:1px dashed var(--line);color:var(--mut)}
.u-hintbox.on{display:block}
.u-hintbox a{color:var(--pri);font-weight:600;margin-right:10px;white-space:nowrap}
.u-lock{text-align:center;padding:26px 12px}
.u-lock .ico{font-size:34px;line-height:1}
.u-lock h3{margin:12px 0 8px;font-size:16px}
.u-lock p{color:var(--mut);font-size:13.5px;line-height:1.95;margin:0 0 18px}
.u-cap{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.u-cap .q{background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:10px 15px;font-size:15px;font-weight:800;letter-spacing:1.5px;color:var(--pri);white-space:nowrap}
.u-cap .u-in{flex:1;min-width:130px}
.u-hp{position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;opacity:0;overflow:hidden}
.u-pager{display:flex;gap:8px;justify-content:center;align-items:center;margin:18px 0 2px;flex-wrap:wrap}
.u-pager a,.u-pager span{border:1px solid var(--line);border-radius:9px;padding:7px 14px;font-size:13px;background:var(--bg);color:var(--txt)}
.u-pager span:last-child,.u-pager span:first-child{color:var(--mut)}
.u-pager a:hover{border-color:var(--pri);color:var(--pri)}

/* 顶部用户区（顶栏里的登录 / 注册 / 我的请求） */
.uarea{display:flex;align-items:center;gap:8px;white-space:nowrap}
.uarea .uname{color:#fff;font-size:13px;opacity:.92;max-width:120px;overflow:hidden;text-overflow:ellipsis}

/* ≤900：两栏改单栏 */
@media (max-width:900px){
  .u-wrap{padding-top:18px}
}
/* ≤560：表单两列改一列，按钮铺满（拇指更好点） */
@media (max-width:560px){
  .u-card{padding:17px 15px;border-radius:14px}
  .u-head h1{font-size:18px}
  .u-row2{grid-template-columns:1fr;gap:0}
  .u-btn{width:100%}
  .u-btn.sm{width:auto}
  .u-in{font-size:16px}          /* 16px 可阻止 iOS 聚焦时整页放大 */
  .u-lock{padding:18px 4px}
}
@media (max-width:380px){
  .u-head h1{font-size:17px}
  .u-pill{padding:5px 10px;font-size:12px}
}
/* 触屏：去掉 hover 粘滞（手机上点完不会"卡住浮起"） */
@media (hover:none){
  .u-btn:hover{filter:none;box-shadow:none}
  .u-btn.ghost:hover{background:transparent}
  .u-pager a:hover{border-color:var(--line);color:var(--txt)}
}
@media (prefers-reduced-motion:reduce){
  .u-btn,.u-in{transition:none}
}
/* 打印：去掉顶栏页脚，卡片边框简化 */
@media print{
  .topbar,.mainnav,.foot,.u-pager,.u-btn{display:none!important}
  .u-card{box-shadow:none;border-color:#ddd}
}
/* 暗色：简约模板强制暗色 */
.tpl-simple.mode-dark .u-flash.ok{background:#10321f;color:#6ee7b7;border-color:#1c4a33}
.tpl-simple.mode-dark .u-flash.err{background:#3a1a1c;color:#fca5a5;border-color:#5a2a2d}
.tpl-simple.mode-dark .u-flash.info{background:#152238;color:#93c5fd;border-color:#1e3a5f}
.tpl-simple.mode-dark .u-badge.pending{background:#3a2f12;color:#fbbf24}
.tpl-simple.mode-dark .u-badge.found{background:#10321f;color:#6ee7b7}
.tpl-simple.mode-dark .u-badge.notfound{background:#26304a;color:#c3cad8}
.tpl-simple.mode-dark .u-badge.replied{background:#152238;color:#93c5fd}
.tpl-simple.mode-dark .u-badge.closed{background:#232b3c;color:#9aa3b8}
.tpl-simple.mode-dark .u-in{background:#131a29}
.tpl-simple.mode-dark .u-item,.tpl-simple.mode-dark .u-pill,.tpl-simple.mode-dark .u-pager a,.tpl-simple.mode-dark .u-pager span{background:#1b2233}
.tpl-simple.mode-dark .u-reply{background:#1b2233}
/* 暗色：跟随系统（经典与简约模板通用） */
@media (prefers-color-scheme:dark){
  body.mode-auto .u-flash.ok{background:#10321f;color:#6ee7b7;border-color:#1c4a33}
  body.mode-auto .u-flash.err{background:#3a1a1c;color:#fca5a5;border-color:#5a2a2d}
  body.mode-auto .u-flash.info{background:#152238;color:#93c5fd;border-color:#1e3a5f}
  body.mode-auto .u-badge.pending{background:#3a2f12;color:#fbbf24}
  body.mode-auto .u-badge.found{background:#10321f;color:#6ee7b7}
  body.mode-auto .u-badge.notfound{background:#26304a;color:#c3cad8}
  body.mode-auto .u-badge.replied{background:#152238;color:#93c5fd}
  body.mode-auto .u-badge.closed{background:#232b3c;color:#9aa3b8}
  body.mode-auto .u-in{background:#131a29}
  body.mode-auto .u-item,body.mode-auto .u-pill,body.mode-auto .u-pager a,body.mode-auto .u-pager span{background:#182034}
  body.mode-auto .u-reply{background:#182034}
}
/* 刘海屏 / 底部横条安全区（放在本文件样式表最后） */
@supports(padding:max(0px)){
  .u-wrap{padding-left:max(var(--pad),env(safe-area-inset-left));padding-right:max(var(--pad),env(safe-area-inset-right))}
}
CSS;
    }
}
