/* ==========================================================================
   万能资料库 · 后台管理 JS · 资源请求 / 系统设置
   由 assets/js/admin.js 按功能拆分而来（原第 1931–2265 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
/* ================================================================== */
/* 资源请求 / 注册用户（一级 Tab）                                      */
/*   请求列表：改状态 + 写回复 + 删除；注册用户：通过 / 禁用 / 重置密码 / 删除 */
/* ================================================================== */
const REQ_ST  = { pending:'待处理', found:'已找到', notfound:'暂无资源', replied:'已回复', closed:'已关闭' };
const REQ_UST = { '1':'正常', '0':'已禁用', '2':'待审核' };
let REQ_TAB = 'list', REQ_PAGE = 1, REQ_UPAGE = 1, REQ_TOTAL = 0, REQ_UTOTAL = 0;

function switchReqTab(tab){
  REQ_TAB = tab;
  document.querySelectorAll('#reqSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.rstab===tab));
  document.getElementById('reqQ').value = '';
  syncReqToolbar();
  if(tab==='users'){ REQ_UPAGE = 1; loadUserList(); } else { REQ_PAGE = 1; loadReqList(); }
}
/** 同一套工具条，按当前子页切换占位符与状态选项 */
function syncReqToolbar(){
  const users = (REQ_TAB === 'users');
  document.getElementById('reqQ').placeholder = users ? '搜索用户名 / 邮箱…' : '搜索资源名称 / 说明…';
  const map = users ? REQ_UST : REQ_ST;
  let s = '<option value="">全部状态</option>';
  for(const k in map){ s += '<option value="'+k+'">'+esc(map[k])+'</option>'; }
  document.getElementById('reqStatus').innerHTML = s;
}
async function loadReq(){
  const bar = document.getElementById('reqBar');
  const d = await api('/api/v1/admin/req_stats');
  if(!d.success){ bar.innerHTML = '<span class="pill">'+esc(d.error||'加载失败')+'</span>'; return; }
  const s = d.data.stats||{}, c = d.data.counts||{};
  let h = '';
  h += '<span class="pill">注册用户：<b>'+(s.users||0)+'</b></span>';
  h += '<span class="pill">待审核：<b>'+(s.users_wait||0)+'</b></span>';
  h += '<span class="pill">请求总数：<b>'+(c.total||0)+'</b></span>';
  h += '<span class="pill">待处理：<b>'+(c.pending||0)+'</b></span>';
  h += '<span class="pill">今日新增：<b>'+(s.today||0)+'</b></span>';
  if(d.data.tables === false){
    h += '<span class="pill" style="color:var(--dead)">⚠️ 数据表创建失败，请检查数据库用户是否有建表权限</span>';
  }
  h += '<a class="ghost" href="/request" target="_blank" style="text-decoration:none">前台页面 ↗</a>';
  h += '<button class="ghost" onclick="loadReq()">刷新</button>';
  bar.innerHTML = h;
  syncReqToolbar();
  if(REQ_TAB === 'users'){ loadUserList(); } else { loadReqList(); }
}
async function loadReqList(){
  const box = document.getElementById('reqBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const q = document.getElementById('reqQ').value, st = document.getElementById('reqStatus').value;
  const d = await api(`/api/v1/admin/req_list?page=${REQ_PAGE}&status=${encodeURIComponent(st)}&q=${encodeURIComponent(q)}`);
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const items = d.data.items||[];
  REQ_TOTAL = d.data.total||0;
  document.getElementById('reqPageInfo').innerText = '第 '+REQ_PAGE+' 页 / 共 '+REQ_TOTAL+' 条';
  if(!items.length){ box.innerHTML = '<div class="hint">没有符合条件的请求。</div>'; return; }
  let h = '';
  items.forEach(r=>{
    const meta = [];
    if(r.type) meta.push(esc(r.type));
    if(r.year) meta.push(r.year);
    meta.push('由 '+esc(r.username || '（未登录提交）')+' 于 '+esc(String(r.created_at||'').slice(0,16))+' 提交');
    h += `<div class="req-row">
      <div class="hd">
        <span class="tt">#${r.id} ${esc(r.title||'')}</span>
        <span class="ubadge ${esc(r.status||'pending')}">${esc(REQ_ST[r.status]||r.status||'')}</span>
        <span class="hint">${meta.join(' · ')}</span>
      </div>`;
    if(r.note)    h += `<div class="nt">${esc(r.note)}</div>`;
    if(r.contact) h += `<div class="mt">联系方式：<b>${esc(r.contact)}</b></div>`;
    let opts = '';
    for(const k in REQ_ST){ opts += `<option value="${k}" ${k===r.status?'selected':''}>${REQ_ST[k]}</option>`; }
    h += `<div class="ops">
        <select id="rs_${r.id}">${opts}</select>
        <textarea id="rn_${r.id}" placeholder="给用户看的回复（只有提交者本人能看到）">${esc(r.admin_note||'')}</textarea>
        <div class="opbtns">
          <button onclick="saveReq(${r.id},this)">保存</button>
          <button class="danger" onclick="delReq(${r.id},this)">删除</button>
        </div>
      </div></div>`;
  });
  box.innerHTML = h;
}
async function loadUserList(){
  const box = document.getElementById('reqBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const q = document.getElementById('reqQ').value, st = document.getElementById('reqStatus').value;
  const d = await api(`/api/v1/admin/user_list?page=${REQ_UPAGE}&status=${encodeURIComponent(st)}&q=${encodeURIComponent(q)}`);
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const items = d.data.items||[];
  REQ_UTOTAL = d.data.total||0;
  document.getElementById('reqPageInfo').innerText = '第 '+REQ_UPAGE+' 页 / 共 '+REQ_UTOTAL+' 位用户';
  if(!items.length){ box.innerHTML = '<div class="hint">没有符合条件的用户。</div>'; return; }
  let h = '<div class="tablewrap"><table><thead><tr>'
        + '<th>ID</th><th>用户名</th><th>邮箱</th><th>状态</th><th>提交数</th>'
        + '<th>注册时间</th><th>最后登录</th><th>操作</th></tr></thead><tbody>';
  items.forEach(u=>{
    const st = String(u.status);
    let ops = '';
    if(st !== '1') ops += `<button class="ghost sm" onclick="setUser(${u.id},1,this)">通过/启用</button> `;
    if(st !== '0') ops += `<button class="ghost sm" onclick="setUser(${u.id},0,this)">禁用</button> `;
    ops += `<button class="ghost sm" onclick="resetPwd(${u.id},this)">重置密码</button> `;
    /* ★ v5.2.9：「删」前面加一个「📋 详细」按钮 ——
       用户中心取消后，用户详情（基本信息 / 关联 API 令牌 / 访问资源历史）
       迁到本页，这个按钮就是它的入口。 */
    ops += `<button class="ghost sm" onclick="openUserDetail(${u.id})">📋 详细</button> `;
    /* ★ v5.2.9：编辑用户（改用户名 / 邮箱 / 是否管理员）同样从用户中心迁来。 */
    ops += `<button class="ghost sm" onclick="editUser(${u.id})">编辑</button> `;
    ops += `<button class="danger sm" onclick="delUser(${u.id},this)">删</button>`;
    h += `<tr>
      <td>${u.id}</td>
      <td>${esc(u.username||'')}</td>
      <td>${esc(u.email||'—')}</td>
      <td><span class="ubadge u${st}">${esc(REQ_UST[st]||st)}</span></td>
      <td>${u.req_count||0}</td>
      <td>${esc(String(u.created_at||'').slice(0,16))}</td>
      <td>${esc(String(u.last_login||'—').slice(0,16))}</td>
      <td>${ops}</td></tr>`;
  });
  box.innerHTML = h + '</tbody></table></div>';
}
function reqPage(step){
  if(REQ_TAB === 'users'){
    const max = Math.max(1, Math.ceil(REQ_UTOTAL/30));
    REQ_UPAGE = Math.min(max, Math.max(1, REQ_UPAGE + step));
    loadUserList();
  } else {
    const max = Math.max(1, Math.ceil(REQ_TOTAL/30));
    REQ_PAGE = Math.min(max, Math.max(1, REQ_PAGE + step));
    loadReqList();
  }
}
async function saveReq(id, btn){
  const st = document.getElementById('rs_'+id).value;
  const nt = document.getElementById('rn_'+id).value;
  await busy(btn, '保存中…', async ()=>{
    const d = await api('/api/v1/admin/req_save', {method:'POST', body: JSON.stringify({id:id, status:st, admin_note:nt})});
    if(!d.success){ alert(d.error||'保存失败'); return; }
    loadReq();
  });
}
async function delReq(id, btn){
  if(!confirm('删除这条资源请求？删除后不可恢复。')) return;
  await busy(btn, '删除中…', async ()=>{
    const d = await api('/api/v1/admin/req_del', {method:'POST', body: JSON.stringify({id:id})});
    if(!d.success){ alert(d.error||'删除失败'); return; }
    loadReq();
  });
}
async function setUser(id, st, btn){
  await busy(btn, '处理中…', async ()=>{
    const d = await api('/api/v1/admin/user_save', {method:'POST', body: JSON.stringify({id:id, status:st})});
    if(!d.success){ alert(d.error||'操作失败'); return; }
    loadReq();
    loadUserList();
  });
}
async function resetPwd(id, btn){
  const p = prompt('给这位用户设置新密码（至少 6 位）：');
  if(p === null) return;
  if(p.length < 6){ alert('密码至少 6 位'); return; }
  await busy(btn, '处理中…', async ()=>{
    const d = await api('/api/v1/admin/user_save', {method:'POST', body: JSON.stringify({id:id, new_password:p})});
    if(!d.success){ alert(d.error||'重置失败'); return; }
    alert('密码已重置，请把新密码告诉该用户。');
  });
}
async function delUser(id, btn){
  if(!confirm('删除这位用户？他提交过的请求会保留（记录里仍有用户名）。')) return;
  await busy(btn, '删除中…', async ()=>{
    const d = await api('/api/v1/admin/user_del', {method:'POST', body: JSON.stringify({id:id})});
    if(!d.success){ alert(d.error||'删除失败'); return; }
    loadReq();
  });
}

async function loadSettings(){
  const box = document.getElementById('setBox');
  const d = await api('/api/v1/admin/settings');
  if(!d.success){ box.innerHTML='<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  SET = d.data;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  renderSettings();
  const m=document.getElementById('setMsg'); m.innerText=''; m.style.color='';
}
function renderSettings(){
  const m = SET.meta, it = SET.items;
  let h='';

  /* 数据源连通状态栏：一次把 TMDB / 红果短剧 两个源试一遍 */
  h += `<div class="status-bar">
    <span class="pill">TMDB：<b id="st_tmdb">${SET_TS.tmdb?SET_TS.tmdb.label:'未测试'}</b></span>    <span class="pill">红果短剧：<b id="st_hongguoduanju">${SET_TS.hongguoduanju?SET_TS.hongguoduanju.label:'未测试'}</b></span>
    <button type="button" class="ghost" id="btnTestAll" onclick="testAllSources()">一键测试全部数据源</button>
    <span class="hint">红果短剧（动漫）免 Key，可直接测；结果只是「本站服务器能否连上该站点」的探测，不影响已入库数据。</span>
  </div>`;

  if(SET.table_ok === false){
    h += `<div class="ext-note note-warn">⚠️ 设置表 <b>${esc(SET.table||'app_settings')}</b> 创建失败：当前数据库用户可能没有建表权限。
      请到宝塔 → 数据库 → 该库的用户 → 权限勾选「所有权限」，或手动导入 <b>sql/install.sql</b> 后重试。</div>`;
  }

  // 文本项：TMDB Key
  TXT_KEYS.forEach(k=>{
    const mm = m[k]||{}, ii = it[k]||{};
    const val = (TXT_DRAFT && TXT_DRAFT[k]!=null) ? TXT_DRAFT[k] : ii.value;
    const kind = (k==='tmdb_api_key') ? 'tmdb' : '';
    h += `<div class="set-sec">
      <h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
      <div class="tip">${esc(mm.tip||'')}</div>
      <div class="inline">
        <input type="text" id="s_${k}" value="${esc(val)}" placeholder="${esc(ii.file)}">
        ${kind ? `<button type="button" class="ghost" onclick="testKey('${kind}', true)">测试此 Key</button>` : ''}
      </div>
      <div class="fileval">config.php 里的值：${esc(mask(ii.file))}</div>
    </div>`;
  });

  // 数据源开关：每个源后面都挂一个「测试连通」（红果短剧 免 key 也能测）
  const ma = m.adapters||{}, ia = it.adapters||{};
  const cur = AD_DRAFT ? AD_DRAFT : (ia.value||[]);
  h += `<div class="set-sec"><h4>${esc(ma.label)} ${flag(ia.override)}</h4>
    <div class="tip">${esc(ma.tip||'')}</div>`;
  const opts = ma.options||{};
  for(const k in opts){
    const on = cur.indexOf(k)>=0;
    const kind = (k==='tmdb') ? 'tmdb' : (k==='hongguoduanju' ? 'hongguoduanju' : '');
    const ts = (kind && SET_TS[kind]) ? SET_TS[kind] : null;
    h += `<div class="src-row">
      <label class="chk"><input type="checkbox" id="a_${k}" value="${esc(k)}" ${on?'checked':''}>${esc(opts[k])}</label>
      ${kind ? `<button type="button" class="ghost sm" onclick="testKey('${kind}')">测试连通</button>
        <span class="src-state" id="ss_${kind}">${ts?esc(ts.detail||ts.label):(kind==='hongguoduanju'?'免 Key，可直接测':'需先填 Key')}</span>` : ''}
    </div>`;
  }
  h += `<div class="fileval">config.php 里的值：${esc((ia.file||[]).join(' / ')||'（空）')}</div></div>`;

  // 网盘类型（chip 增删）
  const mp = m.pan_types||{}, ip = it.pan_types||{};
  const chips = PAN_DRAFT.length
    ? PAN_DRAFT.map((t,i)=>`<span class="chip">${esc(t)}<b title="删除" onclick="rmPan(${i})">×</b></span>`).join('')
    : '<span class="hint">（暂无，点下面「添加」新增）</span>';
  h += `<div class="set-sec"><h4>${esc(mp.label)} ${flag(ip.override)}</h4>
    <div class="tip">${esc(mp.tip||'')}</div>
    <div class="chips" id="panChips">${chips}</div>
    <div class="inline">
      <input type="text" id="s_panNew" placeholder="输入网盘名后回车，如 123网盘" onkeydown="if(event.key==='Enter'){event.preventDefault();addPan();}">
      <button type="button" class="ghost" onclick="addPan()">添加</button>
      <button type="button" class="ghost" onclick="setPanDefault()">恢复默认</button>
    </div>
    <div class="fileval">当前生效：${esc((ip.value||[]).join(' / ')||'（空）')}　·　config/pan_types.php 里：${esc((ip.file||[]).join(' / ')||'（无该文件）')}</div>
  </div>`;

  document.getElementById('setBox').innerHTML = h;
}
function addPan(){
  const el = document.getElementById('s_panNew');
  let v = (el.value||'').trim().replace(/[,，、;；]+/g,' ').trim();
  if(!v) return;
  if(PAN_DRAFT.indexOf(v)>=0){ alert('「'+v+'」已存在'); el.value=''; return; }
  collectDraft();                 // 先保住已输入的 Key
  PAN_DRAFT.push(v);
  renderSettings();
  const box=document.getElementById('s_panNew'); if(box){ box.value=''; box.focus(); }
}
function rmPan(i){ collectDraft(); PAN_DRAFT.splice(i,1); renderSettings(); }
function setPanDefault(){ collectDraft(); PAN_DRAFT = ['夸克','迅雷','光鸭','百度','UC']; renderSettings(); }
async function saveSettings(){
  const msg = document.getElementById('setMsg');
  collectDraft();
  const body = {
    tmdb_api_key: (TXT_DRAFT['tmdb_api_key']||'').trim(),
    adapters: (AD_DRAFT||[]).slice(),
    pan_types: PAN_DRAFT.slice()
  };
  msg.style.color='var(--mut)'; msg.innerText='保存中…';
  const d = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'保存失败'; return; }
  SET.items = d.data.items || SET.items;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  msg.style.color='var(--ok)'; msg.innerText='✓ 已保存并生效';
  renderSettings();
  loadSchema();   // 网盘类型可能变了，刷新「下载链接」下拉
}
async function resetAllSettings(){
  if(!confirm('把所有设置恢复为 config/config.php 里的值？（不影响条目数据）')) return;
  const msg = document.getElementById('setMsg');
  const d = await api('/api/v1/admin/settings_reset',{method:'POST',body:JSON.stringify({all:1})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'操作失败'; return; }
  SET.items = d.data.items || SET.items;
  PAN_DRAFT = (SET.items.pan_types.value||[]).slice();
  TXT_DRAFT = null; AD_DRAFT = null;
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复为 config.php 里的值';
  renderSettings(); loadSchema();
}
/**
 * 测试某个数据源能否连通。
 *  - 点数据源那一行 / 状态栏的按钮：useDraft 省略，直接用「已保存」的配置测；
 *  - 点 Key 输入框旁的「测试此 Key」：useDraft=true，先拿框里正在编辑的值测（不落库）。
 * 之所以要区分，是因为以前无论点哪里都会去读输入框；TMDB 的输入框
 * 初始值就是已保存的值，看似能用；而 红果短剧 从来没有输入框，于是压根没按钮。
 */
async function testKey(kind, useDraft){
  const id = (kind==='tmdb') ? 's_tmdb_api_key' : '';
  const el = id ? document.getElementById(id) : null;
  const key = useDraft ? (el ? el.value.trim() : '') : '';
  setTestState(kind, null, '测试中…', 'mut');
  const d = await api('/api/v1/admin/settings_test',{method:'POST',body:JSON.stringify({kind:kind, key:key})});
  if(!d.success){ setTestState(kind, false, d.error||'测试失败', 'dead'); return false; }
  const r = d.data || {};
  const detail = (r.ok?'✓ 可用':'✗ 不可用') + ' · HTTP ' + (r.code||0) + (r.msg ? (' · '+r.msg) : '');
  setTestState(kind, !!r.ok, detail, r.ok?'ok':'dead');
  return !!r.ok;
}
/** 一键把三个源依次测一遍（红果短剧 免 key 也会测），带进度与总结果 */
async function testAllSources(){
  const btn = document.getElementById('btnTestAll');
  const msg = document.getElementById('setMsg');
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='正在依次测试 TMDB / 红果短剧 …（连不上时每个源最多等 10 秒）'; }
  let ok=0, fail=0;
  await busy(btn, '测试中…', async ()=>{
    for(const s of SRCS){
      const good = await testKey(s.kind, false);
      if(good) ok++; else fail++;
    }
  });
  if(msg){
    msg.style.color = (fail===0) ? 'var(--ok)' : (ok>0 ? 'var(--mut)' : 'var(--dead)');
    msg.innerText += '\n';
    msg.innerText = '测试完成：' + ok + ' 个可用' + (fail ? ('，' + fail + ' 个不可用（看上方各源后面的说明）') : '，全部连通 ✓');
  }
}
/** 把测试结果同时写回：状态栏小标签 + 数据源行内说明 */
function setTestState(kind, ok, detail, level){
  const st = SET_TS[kind] || (SET_TS[kind] = {});
  st.ok = ok; st.detail = detail;
  st.label = (ok===null) ? '测试中…' : (ok ? '连通' : '异常');
  const bar = document.getElementById('st_'+kind);
  if(bar){ bar.textContent = st.label; bar.className = 'src-'+(level||''); }
  const ss = document.getElementById('ss_'+kind);
  if(ss){ ss.textContent = detail || ''; ss.className = 'src-state src-'+(level||''); }
}

/* ==========================================================================
   ★ v5.2.9：用户详情滑出面板（从「用户中心」整体迁来）
   —— 用户中心与「资源请求 → 注册用户」功能重复，v5.2.9 起取消用户中心，
      详情能力落到本页：列表「操作」列的「📋 详细」按钮调它。
   ========================================================================== */
/* ★ v3.2.5：用户详情面板；v3.2.7：用户名即邮箱，去掉冗余邮箱行 */
async function openUserDetail(id) {
  const mask = document.getElementById('userModalMask');
  const modal = document.getElementById('userModal');
  const body = document.getElementById('umBody');
  mask.style.display = 'block';
  modal.style.display = 'flex';
  document.getElementById('umUsername').textContent = '加载中...';
  document.getElementById('umSub').textContent = '';
  document.getElementById('umAvatar').textContent = 'U';
  body.innerHTML = '<div class="hint">加载中...</div>';
  try {
    const d = await api('/api/v1/admin/user_history?id=' + id);
    if (!d.success) { body.innerHTML = '<div class="err">' + esc(d.error || '加载失败') + '</div>'; return; }
    const u = d.data.user || {};
    const tokens = d.data.tokens || [];
    const logs = d.data.logs || [];
    const isAdm = String(u.is_admin) === '1';
    const statusClass = u.status == 1 ? 'ok' : 'bad';
    const statusText = u.status == 1 ? '正常' : '禁用';
    const roleText = isAdm ? '⭐ 管理员' : '普通用户';
    const initial = (u.username || '?').slice(0, 1).toUpperCase();
    document.getElementById('umAvatar').textContent = esc(initial);
    document.getElementById('umUsername').textContent = u.username || '未知用户';
    document.getElementById('umSub').textContent = roleText + ' · 状态：' + statusText;
    let h = '';
    /* 基本信息（v3.2.7：用户名即邮箱，合并显示） */
    h += '<div class="um-section"><h4>👤 用户信息</h4><div class="um-kv">'
      + '<span class="k">用户名/邮箱</span><span class="v">' + esc(u.username || '-') + '</span>'
      + '<span class="k">角色</span><span class="v">' + esc(roleText) + '</span>'
      + '<span class="k">状态</span><span class="v"><span class="pill ' + statusClass + '">' + statusText + '</span></span>'
      + '<span class="k">注册时间</span><span class="v">' + esc(u.created_at || '-') + '</span>'
      + '<span class="k">最后登录</span><span class="v">' + esc(u.last_login || '-') + '</span>'
      + '<span class="k">最后 IP</span><span class="v">' + esc(u.ip || '-') + '</span>'
      + '<span class="k">资源请求数</span><span class="v">' + (u.req_count || 0) + '</span>'
      + '</div></div>';
    /* 令牌概览 */
    h += '<div class="um-section"><h4>🔑 关联 API 令牌（' + tokens.length + '）</h4>';
    if (tokens.length === 0) {
      h += '<div class="um-empty">该用户尚未关联任何 API 令牌</div>';
    } else {
      tokens.forEach(t => {
        const tSt = t.status == 1 ? 'ok' : 'bad';
        const tStTxt = t.status == 1 ? '启用' : '禁用';
        h += '<div style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px;font-size:12.5px">'
          + '<div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px">'
          + '<b>' + esc(t.name || ('令牌 #' + t.id)) + '</b>'
          + '<span class="pill ' + tSt + '" style="font-size:11px">' + tStTxt + '</span>'
          + '</div>'
          + '<div style="color:var(--mut);font-size:11.5px">累计调用 ' + (t.usage_count || 0) + ' 次 · 最后使用 ' + esc(t.last_used || '-') + '</div>'
          + '</div>';
      });
    }
    h += '</div>';
    /* 访问资源历史 */
    h += '<div class="um-section"><h4>📜 访问资源历史（最近 ' + logs.length + ' 条）</h4>';
    if (logs.length === 0) {
      h += '<div class="um-empty">暂无 API 调用记录<br><span style="font-size:12px;color:var(--mut)">该用户可能未通过 API 访问过资源，或历史已过期清理</span></div>';
    } else {
      h += '<div class="um-logs">';
      logs.forEach(l => {
        const isErr = l.response_code && l.response_code >= 400;
        h += '<div class="um-log">'
          + '<div class="ln1">'
          + '<span class="ep">' + esc(l.endpoint || '-') + '</span>'
          + '<span class="code' + (isErr ? ' err' : '') + '">' + (l.response_code || 200) + '</span>'
          + '</div>'
          + '<div class="ln2">'
          + '<span>' + esc(l.token_name || '-') + '</span>'
          + '<span>' + esc(l.created_at || '-') + ' · ' + (l.cost_ms || 0) + 'ms</span>'
          + '</div>'
          + '</div>';
      });
      h += '</div>';
    }
    h += '</div>';
    body.innerHTML = h;
  } catch (e) {
    body.innerHTML = '<div class="err">加载失败：' + esc(e.message) + '</div>';
  }
}
function closeUserDetail() {
  document.getElementById('userModalMask').style.display = 'none';
  document.getElementById('userModal').style.display = 'none';
}

/* ★ v5.2.9：编辑用户（从「用户中心」迁来）—— 改用户名 / 邮箱 / 是否管理员。
   用户中心取消后，这个能力落到本页的用户列表里，避免后台失去该功能。 */
async function editUser(id) {
  const d = await api('/api/v1/admin/user_list');
  if (!d.success) { alert('加载失败'); return; }
  /* ★ v3.1.1 fix：接口返回的字段是 items（原来写成 users，永远找不到人 →「用户不存在」） */
  const user = (d.data.items || []).find(u => u.id == id);
  if (!user) { alert('用户不存在'); return; }
  const isAdm = String(user.is_admin) === '1';
  const newUsername = prompt('用户名:', user.username);
  if (newUsername === null) return;
  /* 管理员的 email 字段存的是系统标记，不当作真实邮箱来改 */
  let newEmail = '';
  if (!isAdm) {
    newEmail = prompt('邮箱:', user.email || '');
    if (newEmail === null) return;
  }
  const isAdmin = confirm('设为管理员？\n（当前：' + (isAdm ? '管理员' : '普通用户') + '）');
  const payload = { id: id, username: newUsername, is_admin: isAdmin ? 1 : 0 };
  if (!isAdm) { payload.email = newEmail; }
  const saveD = await api('/api/v1/admin/user_save', {method:'POST', body:JSON.stringify(payload)});
  if (!saveD.success) { alert(saveD.error || '保存失败'); return; }
  alert('用户已更新');
  loadUserList();
}
