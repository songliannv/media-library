<?php
if(!defined('ML_APP')){define('ML_APP',true);}if(!defined('ML_ROOT')){define('ML_ROOT',dirname(__DIR__));}header('Content-Type: text/html; charset=utf-8');header('X-Robots-Tag: noindex, nofollow');require_once __DIR__.'/../core/Guard.php';ml_guard_shield(__FILE__);require_once __DIR__.'/../core/Session.php';require_once __DIR__.'/../core/DB.php';require_once __DIR__.'/../core/Config.php';require_once __DIR__.'/../core/User.php';$SESSION_OK=ml_session_boot(User::SESS);function ml_ad_csrf(){if(empty($_SESSION['ml_ad_csrf'])){$_SESSION['ml_ad_csrf']=bin2hex(random_bytes(32));}return(string)$_SESSION['ml_ad_csrf'];}$ME=User::me();if(!$ME||!User::isAdmin()){http_response_code(403);echo'<h1>403 禁止访问</h1><p>需要管理员登录后才能访问本页面。</p>';exit;}?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>回源连通性测试 - 媒资资料库后台</title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, -apple-system, Segoe UI, Microsoft YaHei, sans-serif; background: #f5f7fa; color: #111; margin: 0; }
.topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; display: flex; align-items: center; gap: 16px; }
.topbar h1 { font-size: 16px; margin: 0; color: #0f172a; }
.topbar a { color: #2563eb; text-decoration: none; font-size: 13px; }
.topbar a:hover { text-decoration: underline; }
.topbar .spacer { flex: 1; }
.container { max-width: 1000px; margin: 24px auto; padding: 0 20px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.card h2 { font-size: 18px; margin: 0 0 16px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.msg { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
.msg.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.msg.warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.btn { display: inline-block; padding: 10px 18px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; }
.btn:hover { background: #1d4ed8; }
.btn-secondary { background: #f1f5f9; color: #334155; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #dc2626; }
.btn-danger:hover { background: #b91c1c; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.label { display: block; font-size: 13px; color: #64748b; margin-bottom: 6px; }
input[type=url], input[type=text] { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.row { display: flex; gap: 12px; flex-wrap: wrap; }
.row > * { flex: 1 1 260px; }
.table { width: 100%; border-collapse: collapse; margin-top: 12px; }
.table th { text-align: left; padding: 10px 12px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9; background: #fafbfc; }
.table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
.table tr:hover { background: #f8fafc; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.badge-cdn { background: #dbeafe; color: #1e40af; }
.badge-unk { background: #e2e8f0; color: #475569; }
.empty { color: #94a3b8; font-style: italic; }
.hint { color: #64748b; font-size: 13px; margin-top: 8px; }
pre { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; overflow-x: auto; margin: 10px 0; }
.stat { display: inline-block; margin-right: 16px; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.stat .v { font-size: 20px; font-weight: 700; color: #0f172a; }
.stat .l { font-size: 12px; color: #64748b; margin-top: 4px; }
</style>
</head>
<body>
<div class="topbar">
<h1>回源连通性测试</h1>
<div class="spacer"></div>
<a href="./" class="btn-secondary btn-sm">← 返回后台</a>
<a href="?do=logout" class="btn-danger btn-sm" style="padding:6px 12px;">退出登录</a>
</div>

<div class="container">
<div class="card">
<h2>🔗 一键测试全部站点</h2>
<span class="hint">对全部已配置的源站/资源（全局 Origin + 各地区映射）发送 HEAD 请求，探测是否可达及响应耗时。</span>
<div style="margin-top:12px">
<button class="btn btn-danger" onclick="testAll()">一键测试全部站点</button>
<span id="testMsg" class="set-msg" style="margin-left:12px"></span>
</div>
<div id="testResult" style="margin-top:12px"></div>
</div>

<div class="card">
<h2>🧪 单个 URL 测试</h2>
<span class="hint">对任意源站/资源 URL 发送 HEAD 请求，查看状态码、耗时与响应头。</span>
<div class="row" style="margin-top:12px">
<div style="flex:1;min-width:300px">
<label class="label">源站/资源 URL</label>
<input type="url" id="testUrl" placeholder="https://origin.example.com">
</div>
<div style="flex:0 0 auto;min-width:100px;display:flex;align-items:end">
<button class="btn" onclick="testSingle()">测试连通性</button>
</div>
</div>
<div id="singleResult" style="margin-top:12px"></div>
</div>

<div class="hint">所有测试仅发送 HEAD 请求，不下载内容，不影响源站。结果仅供参考，实际回源以 CDN 行为为准。</div>
</div>

<script>
const API = '/api/v1/admin/source';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function apiJson(path, body) {
  const res = await fetch(API + path, {
    method: body ? 'POST' : 'GET',
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-CSRF-Token': CSRF_TOKEN },
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
  });
  const txt = await res.text();
  let data;
  try { data = JSON.parse(txt); } catch (e) { data = { success: false, error: txt }; }
  if (!data.success) throw new Error(data.error || '请求失败');
  return data;
}

function $el(id) { return document.getElementById(id); }
function setMsg(id, text, ok) {
  const e = $el(id);
  if (!e) return;
  e.textContent = text;
  e.className = 'set-msg' + (ok ? ' ok' : ' warn');
  e.style.display = text ? 'block' : 'none';
}
function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function testAll() {
  const box = $el('testResult');
  const btn = document.querySelector('button[onclick="testAll()"]');
  const oldTxt = btn.textContent;
  btn.disabled = true;
  btn.textContent = '测试中…';
  box.innerHTML = '<p class="hint">正在获取全部源站并测试，请稍候…</p>';
  setMsg('testMsg', '');
  try {
    let origins = [];
    try {
      const cfg = await apiJson('/config');
      if (cfg.origin) origins.push({ name: '全局源站（Origin）', url: cfg.origin });
    } catch (e) { /* 忽略 */ }
    try {
      const regions = await apiJson('/regions');
      const map = regions.map || {};
      for (const [region, origin] of Object.entries(map)) {
        if (origin) origins.push({ name: '地区 ' + region, url: origin });
      }
    } catch (e) { /* 忽略 */ }

    if (origins.length === 0) {
      box.innerHTML = '<p class="warn">未发现任何可测试的源站/资源地址，请先在后台“源码对接 / 源站管理”中添加。</p>';
      return;
    }

    let html = '<table class="table"><thead><tr><th style="width:26%">名称</th><th>源站/资源 URL</th><th style="width:14%">HTTP 状态</th><th style="width:16%">响应耗时</th><th style="width:18%">结果</th></tr></thead><tbody>';
    let okCount = 0, failCount = 0;
    for (const item of origins) {
      try {
        const data = await apiJson('/test', { url: item.url });
        const status = data.status || 0;
        const timeMs = data.time_ms || 0;
        const isOk = status >= 200 && status < 400;
        if (isOk) okCount++; else failCount++;
        html += '<tr>' +
          '<td>' + escapeHtml(item.name) + '</td>' +
          '<td>' + escapeHtml(item.url) + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' + timeMs + ' ms</td>' +
          '<td><span class="badge ' + (isOk ? 'badge-cdn' : 'origin') + '">' + (isOk ? '正常' : '异常') + '</span></td>' +
          '</tr>';
      } catch (e) {
        failCount++;
        html += '<tr>' +
          '<td>' + escapeHtml(item.name) + '</td>' +
          '<td>' + escapeHtml(item.url) + '</td>' +
          '<td>—</td>' +
          '<td>—</td>' +
          '<td><span class="badge origin">失败：' + escapeHtml(e.message || '未知错误') + '</span></td>' +
          '</tr>';
      }
    }
    html += '</tbody></table>';
    setMsg('testMsg', '测试完成：正常 ' + okCount + ' 个，异常/失败 ' + failCount + ' 个，共 ' + origins.length + ' 个源站。', true);
    box.innerHTML = html;
  } catch (e) {
    setMsg('testMsg', '测试失败：' + escapeHtml(e.message), false);
  } finally {
    btn.disabled = false;
    btn.textContent = oldTxt;
  }
}

async function testSingle() {
  const url = $el('testUrl').value.trim();
  if (!url) { setMsg('singleResult', '请输入源站/资源 URL', false); return; }
  const box = $el('singleResult');
  box.innerHTML = '<p class="hint">测试中…</p>';
  setMsg('singleResult', '');
  try {
    const data = await apiJson('/test', { url });
    const status = data.status || 0;
    const timeMs = data.time_ms || 0;
    const isOk = status >= 200 && status < 400;
    let headers = '';
    if (data.headers) {
      headers = '<div style="margin-top:8px"><b>响应头摘要：</b><pre>' + escapeHtml(JSON.stringify(data.headers, null, 2)) + '</pre></div>';
    }
    box.innerHTML =
      '<p class="msg ' + (isOk ? 'ok' : 'warn') + '">状态 ' + status + '，耗时 ' + timeMs + ' ms，' + (isOk ? '连通正常' : '连通异常') + '。</p>' + headers;
  } catch (e) {
    box.innerHTML = '<p class="msg warn">测试失败：' + escapeHtml(e.message) + '</p>';
  }
}
</script>
</body>
</html>
