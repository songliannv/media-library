<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
<div id="tab-source" class="tabpane" style="display:none">
  <div class="card">
    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
      <h3 style="margin:0;font-size:15px">源码对接 / 源站管理</h3>
      <span class="hint">主站（万能资料库等）作为 CDN 回源站点；按访客地区自主判断资源切换，首次判定后记录，同地区下次直接命中。</span>
    </div>

    <!-- 1. 源站配置（只负责添加/保存） -->
    <div style="margin-top:16px">
      <h4 style="margin:0 0 8px;font-size:13px;color:#64748b">🔧 源站（Origin）配置</h4>
      <div class="row" style="margin-top:8px">
        <div style="flex:1;min-width:260px">
          <label class="form-label">源站地址（Origin URL，例如 https://zikan.zyfx.wang）</label>
          <input type="url" id="srcOrigin" placeholder="留空则不强制指定" class="form-input">
        </div>
        <div style="flex:0 0 auto;min-width:120px;display:flex;align-items:end;gap:8px">
          <button class="btn" onclick="saveSourceConfig()">保存源站</button>
          <button class="btn ghost" onclick="loadSourceConfig()">刷新</button>
        </div>
      </div>
      <div id="srcCfgMsg" class="set-msg" style="margin-top:8px"></div>
      <div class="hint" style="margin-top:6px">保存后写入 stats/source_config.json，不改动数据库；也可在 config.php 的 origin 字段填写。</div>
    </div>

    <!-- 2. 地区 - 源站/资源 映射（只负责添加，点击“添加数据”弹出新页面/弹窗） -->
    <div style="margin-top:20px">
      <h4 style="margin:0 0 8px;font-size:13px;color:#64748b">🗺️ 地区 - 源站/资源 映射</h4>
      <span class="hint">首个访问某地区的访客，由 IP 判定地区并记录映射；下次同地区直接调用该地区对应的源站/资源。</span>
      <div class="row" style="margin-top:12px;gap:8px">
        <div style="flex:0 0 auto">
          <button class="btn btn-primary" onclick="openAddRegionModal()">添加数据</button>
        </div>
      </div>
      <div id="srcRegionList" style="margin-top:12px"></div>
      <div class="hint" style="margin-top:6px">示例：CN-BJ → https://bj.origin.com；CN → https://cn.origin.com；-- → https://default.origin.com（兜底）。</div>
    </div>

    <!-- 3. 回源连通性测试（一个按键进入新页面，一键测试全部站点） -->
    <div style="margin-top:20px">
      <h4 style="margin:0 0 8px;font-size:13px;color:#64748b">🔗 回源连通性测试</h4>
      <span class="hint">对全部已配置的源站/资源发送 HEAD 请求，探测是否可达及响应耗时。</span>
      <div class="row" style="margin-top:12px;gap:8px">
        <div style="flex:0 0 auto;min-width:180px">
          <button class="btn btn-primary" onclick="openTestPage()">进入测试页面</button>
        </div>
        <div style="flex:0 0 auto;min-width:200px">
          <button class="btn btn-danger" onclick="testAllOrigins()">一键测试全部站点</button>
        </div>
      </div>
      <div id="srcTestAllResult" style="margin-top:12px"></div>
    </div>

    <!-- 4. 一键清理全部缓存 -->
    <div style="margin-top:20px">
      <h4 style="margin:0 0 8px;font-size:13px;color:#dc2626">🧹 一键清理全部缓存</h4>
      <span class="hint">清理 CDN 缓存目录下全部站点的缓存文件。清理后，所有站点首次访问将重新回源。</span>
      <div style="margin-top:10px">
        <button class="btn btn-danger" onclick="clearAllCache(this)" id="btnClearCache">一键清理全部缓存</button>
        <span id="srcClearMsg" class="set-msg" style="margin-left:12px"></span>
      </div>
      <div class="hint" style="margin-top:6px">操作会删除 stats/ 目录下全部缓存相关文件，不影响源站数据。</div>
    </div>
  </div>

  <!-- 添加数据弹窗（新页面效果） -->
  <div id="srcAddModal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="closeAddRegionModal()"></div>
    <div class="modal-box">
      <div class="modal-header">
        <h4 style="margin:0;font-size:15px">➕ 添加地区 - 源站映射</h4>
        <button class="modal-close" onclick="closeAddRegionModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <label class="form-label">地区代码</label>
          <input type="text" id="addRegion" placeholder="例如 CN-BJ / CN / US / --" class="form-input">
        </div>
        <div class="form-row" style="margin-top:10px">
          <label class="form-label">源站/资源地址</label>
          <input type="url" id="addRegionOrigin" placeholder="例如 https://bj.cdn.example.com" class="form-input">
        </div>
        <div id="addRegionMsg" class="set-msg" style="margin-top:10px"></div>
      </div>
      <div class="modal-footer">
        <button class="btn ghost" onclick="closeAddRegionModal()">取消</button>
        <button class="btn btn-primary" onclick="saveAddRegion()">保存</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ---------- 源码对接 / 源站管理：前端逻辑 ---------- */
const SRC_API = '/api/v1/admin/source';

async function srcJson(path, opts = {}) {
  const headers = { 'Content-Type': 'application/json; charset=utf-8' };
  const token = document.querySelector('meta[name="csrf-token"]');
  if (token) headers['X-CSRF-Token'] = token.getAttribute('content');
  const res = await fetch(SRC_API + path, {
    method: opts.method || 'GET',
    headers,
    body: opts.body ? JSON.stringify(opts.body) : undefined,
    credentials: 'same-origin',
  });
  const txt = await res.text();
  let data;
  try { data = JSON.parse(txt); } catch (e) { data = { success: false, error: txt }; }
  if (!data.success) throw new Error(data.error || '请求失败');
  return data;
}

function $el(id) { return document.getElementById(id); }
function msg(id, text, ok) {
  const e = $el(id);
  if (!e) return;
  e.textContent = text;
  e.className = 'set-msg' + (ok ? ' ok' : ' warn');
  e.style.display = text ? 'block' : 'none';
}

/* ---- 1. 源站配置 ---- */
async function loadSourceConfig() {
  try {
    const data = await srcJson('/config');
    $el('srcOrigin').value = data.origin || '';
    msg('srcCfgMsg', '已加载源站配置', true);
  } catch (e) { msg('srcCfgMsg', '加载失败：' + e.message, false); }
}

async function saveSourceConfig() {
  const origin = $el('srcOrigin').value.trim();
  try {
    await srcJson('/config', { method: 'POST', body: { origin } });
    msg('srcCfgMsg', '源站地址已保存（stats/source_config.json）', true);
  } catch (e) { msg('srcCfgMsg', '保存失败：' + e.message, false); }
}

/* ---- 2. 地区映射（只负责添加，弹出新页面/弹窗） ---- */
async function loadRegionList() {
  try {
    const data = await srcJson('/regions');
    const map = data.map || {};
    const box = $el('srcRegionList');
    if (!box) return;
    if (Object.keys(map).length === 0) { box.innerHTML = '<p class="empty">暂无地区映射，点击“添加数据”新增</p>'; return; }
    let html = '<table class="table"><thead><tr><th style="width:22%">地区</th><th>源站/资源地址</th><th style="width:16%">操作</th></tr></thead><tbody>';
    for (const [region, origin] of Object.entries(map)) {
      html += `<tr>
        <td>${escapeHtml(region)}</td>
        <td>${escapeHtml(origin)}</td>
        <td><button class="btn btn-danger btn-sm" onclick="delSourceRegion('${escapeJs(region)}')" style="padding:4px 8px">删除</button></td>
      </tr>`;
    }
    html += '</tbody></table>';
    box.innerHTML = html;
  } catch (e) { $el('srcRegionList').innerHTML = '<p class="warn">加载失败：' + escapeHtml(e.message) + '</p>'; }
}

function openAddRegionModal() {
  $el('addRegion').value = '';
  $el('addRegionOrigin').value = '';
  $el('addRegionMsg').style.display = 'none';
  $el('srcAddModal').style.display = 'block';
  $el('addRegion').focus();
}

function closeAddRegionModal() {
  $el('srcAddModal').style.display = 'none';
}

async function saveAddRegion() {
  const region = $el('addRegion').value.trim();
  const origin = $el('addRegionOrigin').value.trim();
  if (!region || !origin) { msg('addRegionMsg', '地区和源站/资源均不能为空', false); return; }
  try {
    await srcJson('/region', { method: 'POST', body: { region, origin } });
    closeAddRegionModal();
    await loadRegionList();
  } catch (e) { msg('addRegionMsg', '添加失败：' + e.message, false); }
}

async function delSourceRegion(region) {
  if (!confirm('确定删除地区映射：' + region + '？')) return;
  try {
    await srcJson('/region', { method: 'DELETE', body: { region } });
    await loadRegionList();
  } catch (e) { msg('srcRegionList', '删除失败：' + e.message, false); }
}

/* ---- 3. 回源连通性测试（进入新页面 + 一键测试全部站点） ---- */
function openTestPage() {
  /* 点击后跳转到独立测试页面（新页面） */
  const url = '/admin/test-origin.php';
  window.open(url, '_blank');
}

async function testAllOrigins() {
  const box = $el('srcTestAllResult');
  box.innerHTML = '<p class="hint">正在获取全部源站并测试连通性，请稍候…</p>';
  try {
    /* 获取全局源站 */
    let origins = [];
    try {
      const cfg = await srcJson('/config');
      if (cfg.origin) origins.push({ name: '全局源站（Origin）', url: cfg.origin });
    } catch (e) { /* 忽略 */ }

    /* 获取全部地区映射 */
    try {
      const regions = await srcJson('/regions');
      const map = regions.map || {};
      for (const [region, origin] of Object.entries(map)) {
        if (origin) origins.push({ name: '地区 ' + region, url: origin });
      }
    } catch (e) { /* 忽略 */ }

    if (origins.length === 0) {
      box.innerHTML = '<p class="warn">未发现任何可测试的源站/资源地址，请先在“源站配置”或“地区映射”中添加。</p>';
      return;
    }

    /* 依次测试每个源站 */
    let html = '<table class="table"><thead><tr><th style="width:26%">名称</th><th>源站/资源 URL</th><th style="width:14%">HTTP 状态</th><th style="width:16%">响应耗时</th><th style="width:18%">结果</th></tr></thead><tbody>';
    let okCount = 0, failCount = 0;
    for (const item of origins) {
      try {
        const data = await srcJson('/test', { method: 'POST', body: { url: item.url } });
        const status = data.status || 0;
        const timeMs = data.time_ms || 0;
        const isOk = status >= 200 && status < 400;
        if (isOk) { okCount++; } else { failCount++; }
        html += `<tr>
          <td>${escapeHtml(item.name)}</td>
          <td>${escapeHtml(item.url)}</td>
          <td>${status}</td>
          <td>${timeMs} ms</td>
          <td><span class="badge ${isOk ? 'badge-cdn' : 'origin'}">${isOk ? '正常' : '异常'}</span></td>
        </tr>`;
      } catch (e) {
        failCount++;
        html += `<tr>
          <td>${escapeHtml(item.name)}</td>
          <td>${escapeHtml(item.url)}</td>
          <td>—</td>
          <td>—</td>
          <td><span class="badge origin">失败：${escapeHtml(e.message || '未知错误')}</span></td>
        </tr>`;
      }
    }
    html += '</tbody></table>';
    box.innerHTML =
      `<div class="set-msg ok" style="margin-bottom:8px">测试完成：正常 ${okCount} 个，异常/失败 ${failCount} 个，共 ${origins.length} 个源站。</div>` + html;
  } catch (e) {
    box.innerHTML = '<p class="warn">测试失败：' + escapeHtml(e.message) + '</p>';
  }
}

/* ---- 4. 一键清理全部缓存（复用已有 cache_clear 端点）---- */
async function clearAllCache(btn) {
  if (!confirm('确定要清理全部站点的 CDN 缓存吗？此操作不可恢复，清理后所有站点首次访问将重新回源。')) return;
  if (btn) { btn.disabled = true; btn.textContent = '清理中…'; }
  try {
    const res = await fetch('/api/v1/admin/cache_clear', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      credentials: 'same-origin',
    });
    const txt = await res.text();
    let data;
    try { data = JSON.parse(txt); } catch (e) { data = { success: false, error: txt }; }
    if (!data.success) throw new Error(data.error || '清理失败');
    const files = data.files ?? 0;
    const multi = data.multi ? '，多级缓存已清空' : '';
    msg('srcClearMsg', '已清理全部站点缓存，删除了 ' + files + ' 个缓存文件' + multi, true);
  } catch (e) {
    msg('srcClearMsg', '清理失败：' + e.message, false);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '一键清理全部缓存'; }
  }
}

/* ---------- 工具 ---------- */
function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeJs(s) {
  return String(s).replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
}

/* ---------- 首次加载 ---------- */
loadSourceConfig();
loadRegionList();
</script>