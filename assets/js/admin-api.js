/* ==========================================================================
   万能资料库 · 后台管理 JS · API 调用：令牌与调用日志
   由 assets/js/admin.js 按功能拆分而来（原第 465–592 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
/* ============ API 调用令牌（v1.7.0）============ */
let TK_LIST=[], TK_PAGE=1, LOG_PAGE=1, API_SUB='tokens';
function switchApiSub(which){
  API_SUB=which;
  document.getElementById('tkListCard').style.display = (which==='tokens')?'block':'none';
  document.getElementById('tkLogCard').style.display  = (which==='logs')?'block':'none';
  if(which==='logs') loadApiLogs();
}
function copyApiDemo(){
  const el=document.getElementById('apiDemo');
  const txt=(el?el.innerText:'');
  if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(()=>alert('已复制：'+txt),()=>alert(txt)); }
  else { alert(txt); }
}
async function loadTokens(){
  /* ★ v5.3.5：先体检令牌表结构，出问题直接在页面顶部说清楚，
     不再让用户对着「Unknown column」这种 SQL 报错发呆。 */
  try{
    const sc=await api('/api/v1/admin/token_schema');
    const box=document.getElementById('tkSchema');
    if(box){
      if(sc.success && sc.data && sc.data.ok){
        box.style.display='none'; box.innerHTML='';
      } else {
        const msg=(sc.success&&sc.data&&sc.data.message)?sc.data.message:(sc.error||'令牌表结构异常');
        const miss=(sc.success&&sc.data&&sc.data.missing&&sc.data.missing.length)?('缺列：'+sc.data.missing.join('、')):'';
        box.style.display='block';
        box.innerHTML=`<div class="ext-note note-err" style="margin:0">⚠ 令牌表结构异常 —— ${esc(msg)}${miss?('<br>'+esc(miss)):''}</div>`;
      }
    }
  }catch(e){}
  const st=await api('/api/v1/admin/token_stats');
  if(st.success){
    const s=st.data;
    document.getElementById('tkStats').innerHTML=
      `<div class="stat"><b>${s.total_tokens||0}</b><span>令牌总数</span></div>`+
      `<div class="stat"><b>${s.active_tokens||0}</b><span>启用中</span></div>`+
      `<div class="stat"><b>${s.today_calls||0}</b><span>今日调用</span></div>`+
      `<div class="stat"><b>${s.yesterday_calls||0}</b><span>昨日调用</span></div>`+
      `<div class="stat"><b>${s.total_calls||0}</b><span>累计调用</span></div>`+
      `<div class="stat"><b>${s.success_rate||0}%</b><span>成功率</span></div>`;
  }
  const d=await api(`/api/v1/admin/token_list?page=${TK_PAGE}`);
  const box=document.getElementById('tkRows');
  if(!d.success){ box.innerHTML=`<tr><td colspan="7" class="hint">${esc(d.error||'加载失败')}</td></tr>`; return; }
  TK_LIST=d.data.items||[];
  if(!TK_LIST.length){ box.innerHTML='<tr><td colspan="7" class="hint">还没有令牌。点上方「+ 新建令牌」给调用者发一个。</td></tr>'; refreshLogTokenSel(); return; }
  let h='';
  TK_LIST.forEach(k=>{
    const on=(String(k.status)==='1');
    const quota=(Number(k.daily_limit)>0)?(' / '+k.daily_limit):' / 不限';
    h+=`<tr>
      <td><b>${esc(k.name||'')}</b></td>
      <td><code class="tkv" title="只展示前 3 位和后 3 位">${esc(String(k.token_preview||''))}</code></td>
      <td><span class="ubadge ${on?'approved':'rejected'}">${on?'启用':'已禁用'}</span></td>
      <td>${k.today_calls||0}${quota}</td>
      <td>${k.usage_count||0}</td>
      <td>${esc(String(k.last_used||'—').slice(0,16))}</td>
      <td style="white-space:nowrap">
        <button class="ghost sm" onclick="copyTk(${k.id})">复制</button>
        <button class="ghost sm" onclick="openTkSet(${k.id})">设置</button>
        <button class="ghost sm" onclick="toggleTk(${k.id},${on?0:1})">${on?'禁用':'启用'}</button>
        <button class="ghost sm" onclick="delTk(${k.id})">删除</button>
      </td></tr>`;
  });
  box.innerHTML=h;
  refreshLogTokenSel();
}
async function copyTk(id, btn){
  const k=TK_LIST.find(x=>Number(x.id)===Number(id)); if(!k){ return; }
  const v=k.token||k.token_plain||'';
  if(!v){ alert('该令牌没有可复制的明文（旧数据未回写）。'); return; }
  const old=btn?btn.textContent:null;
  if(btn){ btn.disabled=true; btn.textContent='复制中'; }
  try{
    if(navigator.clipboard&&navigator.clipboard.writeText){ await navigator.clipboard.writeText(v); }
    else {
      const ta=document.createElement('textarea');
      ta.value=v;
      ta.style.position='fixed';
      ta.style.opacity='0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
    if(btn){ btn.textContent='已复制'; }
  }catch(e){
    alert('复制失败：'+((e&&e.message)?e.message:e));
    if(btn){ btn.textContent=old||'复制'; }
    return;
  }
  setTimeout(()=>{
    if(btn){ btn.disabled=false; btn.textContent=old||'复制'; }
  },1200);
}
function refreshLogTokenSel(){
  const sel=document.getElementById('logToken');
  if(!sel) return;
  let h='<option value="0">全部令牌</option>';
  TK_LIST.forEach(k=>{ h+=`<option value="${k.id}">${esc(k.name||('#'+k.id))}</option>`; });
  sel.innerHTML=h;
}
async function toggleTk(id,st){
  const d=await api('/api/v1/admin/token_save',{method:'POST',body:JSON.stringify({id:id,status:st})});
  if(!d.success){ alert(d.error||'操作失败'); return; }
  loadTokens();
}
/* ★ v5.3.5：新建令牌不再弹 prompt —— 点一下直接生成，参数去「设置」里调。
   原来的 openTokenAdd() 连续 3 个 prompt()（名称 / 限额 / IP），
   用户明确要求「创建的时候不要出现弹窗，直接生成API，然后在后面加上设置的按键」。 */
async function openTokenAdd(){
  const d=await api('/api/v1/admin/token_add',{method:'POST',body:JSON.stringify({})});
  if(!d.success){ alert(d.error||'创建失败'); return; }
  loadTokens();
  /* 明文只在这里展示一次（同时库里也存了 token_plain，列表可再复制） */
  const el=document.getElementById('tkNewPlain');
  if(el) el.value=(d.data&&d.data.token)?d.data.token:'';
  const m=document.getElementById('tkNewModal');
  if(m) m.style.display='flex';
  setTimeout(()=>{ try{ copyTkNew(); }catch(e){} },150);
}
function closeTkNew(){ const m=document.getElementById('tkNewModal'); if(m) m.style.display='none'; }
function copyTkNew(){
  const el=document.getElementById('tkNewPlain');
  if(!el) return;
  const v=el.value||'';
  if(!v){ alert('没有可复制的令牌'); return; }
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(v).then(()=>alert('已复制令牌：'+v),()=>alert(v));
  } else { alert(v); }
}
/* ★ v5.3.5：设置弹窗 —— 打开时回填当前值，保存走同一个 token_save 接口 */
let TK_SET_ID=0;
function openTkSet(id){
  const k=TK_LIST.find(x=>Number(x.id)===Number(id)); if(!k){ alert('找不到该令牌'); return; }
  TK_SET_ID=id;
  const nm=document.getElementById('tkSetName');   if(nm) nm.value=k.name||'';
  const lim=document.getElementById('tkSetLimit'); if(lim) lim.value=Number(k.daily_limit||0);
  const ip=document.getElementById('tkSetIp');     if(ip) ip.value=k.ip_whitelist||'';
  const en=document.getElementById('tkSetEnabled');if(en) en.checked=(String(k.status)==='1');
  const ti=document.getElementById('tkSetTitle');  if(ti) ti.innerText='令牌设置 · '+(k.name||('#'+id));
  const ms=document.getElementById('tkSetMsg');    if(ms){ ms.innerText=''; ms.style.color=''; }
  const m=document.getElementById('tkSetModal');
  if(m) m.style.display='flex';
}
function closeTkSet(){ const m=document.getElementById('tkSetModal'); if(m) m.style.display='none'; }
async function saveTkSet(){
  const msg=document.getElementById('tkSetMsg');
  if(msg){ msg.innerText='保存中…'; msg.style.color=''; }
  const name  = (document.getElementById('tkSetName')||{}).value||'';
  const limit = parseInt((document.getElementById('tkSetLimit')||{}).value||'0')||0;
  const ip    = (document.getElementById('tkSetIp')||{}).value||'';
  const en    = (document.getElementById('tkSetEnabled')||{}).checked?1:0;
  const d=await api('/api/v1/admin/token_save',{method:'POST',body:JSON.stringify({
    id:TK_SET_ID, name:name, daily_limit:limit, ip_whitelist:ip, status:en
  })});
  if(!d.success){ if(msg){ msg.innerText=(d.error||'保存失败'); msg.style.color='var(--err,#b91c1c)'; } return; }
  closeTkSet();
  loadTokens();
}
/* 兼容旧入口：编辑 = 设置 */
function editTk(id){ openTkSet(id); }
async function delTk(id){
  const k=TK_LIST.find(x=>Number(x.id)===Number(id));
  if(!confirm('确定删除令牌「'+((k&&k.name)||id)+'」？该令牌将立即失效，其调用记录也会一并删除。')) return;
  const d=await api('/api/v1/admin/token_del',{method:'POST',body:JSON.stringify({id:id})});
  if(!d.success){ alert(d.error||'删除失败'); return; }
  loadTokens();
}
async function loadApiLogs(){
  const box=document.getElementById('logRows');
  box.innerHTML='<tr><td colspan="6" class="hint">加载中…</td></tr>';
  const tid=document.getElementById('logToken').value||0;
  const sd=document.getElementById('logStart').value.trim();
  const ed=document.getElementById('logEnd').value.trim();
  const d=await api(`/api/v1/admin/api_logs?page=${LOG_PAGE}&token_id=${tid}&start=${encodeURIComponent(sd)}&end=${encodeURIComponent(ed)}`);
  if(!d.success){ box.innerHTML=`<tr><td colspan="6" class="hint">${esc(d.error||'加载失败')}</td></tr>`; return; }
  const items=d.data.items||[];
  document.getElementById('logPageInfo').innerText='第 '+LOG_PAGE+' 页 / 共 '+(d.data.total||0)+' 条';
  if(!items.length){ box.innerHTML='<tr><td colspan="6" class="hint">这段时间没有调用记录。</td></tr>'; return; }
  let h='';
  items.forEach(r=>{
    h+=`<tr>
      <td>${esc(String(r.created_at||'').slice(0,19))}</td>
      <td>${esc(r.token_name||('#'+r.token_id))}</td>
      <td><code>${esc(r.endpoint||'')}</code></td>
      <td>${esc(r.method||'')}</td>
      <td>${esc(r.ip||'')}</td>
      <td>${r.cost_ms||0} ms</td></tr>`;
  });
  box.innerHTML=h;
}
function logPage(n){
  if(n<0 && LOG_PAGE<=1) return;
  LOG_PAGE+=n; loadApiLogs();
}

// 分类切换
document.getElementById('cats').addEventListener('click', e=>{
  if(e.target.classList.contains('cat')){
    document.querySelectorAll('#cats .cat').forEach(x=>x.classList.toggle('active', x===e.target));
    curCat = e.target.dataset.cat;
    curTag = '';
    curPage = 1; loadItems();
  }
});
