<?php
/**
 * 前台用户中心：注册 / 登录 / 退出 / 资源请求
 * ==================================================================
 * index.php 把 `/user*` 与 `/request` 交给本文件，路由如下：
 *
 *   GET  /user/register     注册表单          POST 同址提交
 *   GET  /user/login        登录表单          POST 同址提交
 *   GET  /user/logout       退出登录
 *   GET  /request           资源请求页        POST 提交新请求 / 撤回自己的请求
 *   GET  /request?probe=xx  站内查重（JSON，给表单做即时提示，不写库）
 *
 * 写操作一律「POST → 重定向 → GET」（PRG），所以刷新页面不会重复提交；
 * 所有表单都带 CSRF 令牌，失败时给一次性的中文提示条。
 *
 * 安全：本文件只应被 index.php 引入，直接访问网址会 404（见 core/Guard.php）。
 * 兼容 PHP 5.6 ~ 8.2。
 */

require_once __DIR__ . '/core/Guard.php';
ml_guard_shield(__FILE__);

require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/DB.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/Categories.php';
require_once __DIR__ . '/core/Site.php';
require_once __DIR__ . '/core/User.php';
require_once __DIR__ . '/core/Views.php';
require_once __DIR__ . '/core/UserViews.php';

use Core\User;
use Core\UserViews;
use Core\Json;

/* ---------------- 路由解析 ---------------- */
$__path = parse_url((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), PHP_URL_PATH);
$__path = '/' . trim(str_replace('\\', '/', (string) $__path), '/');
if ($__path === '/') { $__path = '/request'; }
$__seg  = strtolower(trim(substr($__path, 1), '/'));
$__isPost = (strtoupper((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET')) === 'POST');

/**
 * 写操作统一入口：校验 CSRF。失败时记一条提示并返回 false，
 * 调用方负责重定向回原页面（不输出任何 HTML，保证 header 能发出去）。
 */
if (!function_exists('ml_user_post_guard')) {
    function ml_user_post_guard()
    {
        $tok = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!User::csrfOk($tok)) {
            User::flashSet('err', '页面停留太久，安全校验已失效，请重新提交一次。');
            return false;
        }
        return true;
    }
}

/** 取 POST 里的字符串字段（统一 trim，避免各处重复写 isset） */
if (!function_exists('ml_user_post')) {
    function ml_user_post($key, $default = '')
    {
        return isset($_POST[$key]) ? (string) $_POST[$key] : $default;
    }
}

/* ================================================================ */
/* 资源请求页 /request                                               */
/* ================================================================ */
if ($__seg === '' || $__seg === 'request') {

    /* ---- 站内查重：给表单做即时提示（只读，不写库） ---- */
    if (!$__isPost && isset($_GET['probe'])) {
        $kw  = trim((string) $_GET['probe']);
        $out = array();
        if ($kw !== '' && User::len($kw) <= 60) {
            foreach (User::siteMatches($kw, 5) as $m) {
                $out[] = array(
                    'url'   => '/uisc/' . (int) $m['id'] . '.html',
                    /* 标题在这里就先转义好，前端直接拼 HTML 也不会被注入 */
                    'title' => htmlspecialchars((string) $m['title'], ENT_QUOTES, 'UTF-8'),
                );
            }
        }
        Json::ok(array('items' => $out));
    }

    /* ---- 提交 / 撤回 ---- */
    if ($__isPost) {
        $ok = ml_user_post_guard();
        if ($ok) {
            $do = ml_user_post('do');
            if ($do === 'del') {
                $uid = User::uid();
                $id  = (int) ml_user_post('id', '0');
                if ($uid < 1) {
                    User::flashSet('err', '登录状态已失效，请重新登录。');
                } elseif (User::deleteOwnRequest($id, $uid)) {
                    User::flashSet('ok', '已撤回这条请求。');
                } else {
                    User::flashSet('err', '撤回失败：这条请求可能已经被站长处理了。');
                }
            } else {
                $r = User::createRequest($_POST, User::uid());
                if ($r['ok']) {
                    /* 提交成功了，顺手看看站内是不是本来就有 —— 有就直接告诉他 */
                    $hit = User::siteMatches(ml_user_post('title'), 3);
                    $msg = $r['msg'];
                    if ($hit) {
                        $names = array();
                        foreach ($hit as $x) { $names[] = (string) $x['title']; }
                        $msg .= ' 另外，站内可能已收录：' . implode('、', $names) . '，可以先在首页搜一下。';
                    }
                    User::flashSet('ok', $msg);
                } else {
                    User::flashSet('err', $r['msg']);
                }
            }
        }
        header('Location: /request');
        exit;
    }

    /* ---- 页面 ---- */
    $uid  = User::uid();
    $page = max(1, (int) (isset($_GET['p']) ? $_GET['p'] : 1));
    $per  = 20;

    $all  = User::listRequests($page, $per, 0, '', '');
    $mine = ($uid > 0)
        ? User::listRequests(1, 20, $uid, '', '')
        : array('items' => array(), 'total' => 0);

    echo UserViews::requestHtml(array(
        'me'      => User::me(),
        'list'    => $all['items'],
        'total'   => $all['total'],
        'page'    => $page,
        'perPage' => $per,
        'myList'  => $mine['items'],
        'myTotal' => $mine['total'],
        'counts'  => User::statusCounts(0),
    ));
    return;
}

/* ================================================================ */
/* 注册 /user/register                                               */
/* ================================================================ */
if ($__seg === 'user/register') {
    if ($__isPost) {
        $ok = ml_user_post_guard();
        $u  = ml_user_post('username');
        if (!$ok) {
            header('Location: /user/register' . ($u !== '' ? '?u=' . urlencode($u) : ''));
            exit;
        }
        $r = User::register(
            $u,
            ml_user_post('password'),
            ml_user_post('password2'),
            ml_user_post('email'),
            ml_user_post('captcha'),
            ml_user_post('website')          /* 蜜罐：真人看不见这个框 */
        );
        User::flashSet($r['ok'] ? 'ok' : 'err', $r['msg']);
        if ($r['ok']) {
            header('Location: /request');
        } else {
            $q = array();
            if ($u !== '') { $q[] = 'u=' . urlencode($u); }
            $em = ml_user_post('email');
            if ($em !== '') { $q[] = 'e=' . urlencode($em); }
            header('Location: /user/register' . ($q ? ('?' . implode('&', $q)) : ''));
        }
        exit;
    }
    echo UserViews::registerHtml(array(
        'username' => isset($_GET['u']) ? (string) $_GET['u'] : '',
        'email'    => isset($_GET['e']) ? (string) $_GET['e'] : '',
    ));
    return;
}

/* ================================================================ */
/* 登录 /user/login                                                  */
/* ================================================================ */
if ($__seg === 'user/login') {
    /* 已经是登录状态就不用再看登录页了 */
    if (!$__isPost && User::loggedIn()) {
        header('Location: /request');
        exit;
    }
    if ($__isPost) {
        $ok = ml_user_post_guard();
        $u  = ml_user_post('username');
        if (!$ok) {
            header('Location: /user/login' . ($u !== '' ? '?u=' . urlencode($u) : ''));
            exit;
        }
        $r = User::login($u, ml_user_post('password'));
        User::flashSet($r['ok'] ? 'ok' : 'err', $r['msg']);
        if ($r['ok']) {
            header('Location: /request');
        } else {
            header('Location: /user/login' . ($u !== '' ? '?u=' . urlencode($u) : ''));
        }
        exit;
    }
    echo UserViews::loginHtml(array(
        'username' => isset($_GET['u']) ? (string) $_GET['u'] : '',
    ));
    return;
}

/* ================================================================ */
/* 退出 /user/logout                                                 */
/* ================================================================ */
if ($__seg === 'user/logout') {
    User::logout();
    header('Location: /');
    exit;
}

/* 其余 /user/xxx 一律按"不存在"处理（不暴露站点内部结构） */
ml_guard_deny();
