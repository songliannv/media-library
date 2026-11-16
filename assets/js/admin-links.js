/* ==========================================================================
   万能资料库 · 后台管理 JS · 下载链接与失效检查 / 设置常量
   由 assets/js/admin.js 按功能拆分而来（原第 1325–1434 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

// ===== 下载链接（多条网盘，类型来自 config/pan_types.php，可升级扩展）=====
function panOptions(list, sel){
  list = (list && list.length) ? list : PAN_LIST;
  let h=''; list.forEach(t=>{ h+=`<option value="${esc(t)}" ${t===sel?'selected':''}>${esc(t)}</option>`; }); return h;
}
function pill(st){
  // st 兼容三种形态：null/true/false（旧） 与 {ok,msg,code}（新）
  let ok=null, msg='';
  if(st && typeof st==='object'){ ok = !!st.ok; msg = st.msg||''; }
  else if(st===true || st===false){ ok = st; }
  if(ok===true){
    const tip = msg ? ' title="'+esc(msg)+'"' : '';
    return '<span class="st st-ok">✓ 有效'+tip+'</span>';
  }
  if(ok===false){
    // 失效时把检测原因（如"分享已失效"/"提取码错误"）显示为标题提示，鼠标悬停可见
    const tip = msg ? ' title="'+esc(msg)+'"' : '';
    return '<span class="st st-dead">✗ 失效'+tip+'</span>';
  }
  return '<span class="st st-none">未检测</span>';
}
function addDlRow(label, url='', status=null){
  label = label || (PAN_LIST[0]||'夸克');
  const wrap=document.getElementById('dlRows');
  const row=document.createElement('div'); row.className='dl-row';
  row.innerHTML=`<span class="dl-st">${pill(status)}</span>`
    +`<select class="dl-type">${panOptions(PAN_LIST, label)}</select>`
    +`<input class="dl-url" placeholder="粘贴链接，如 https://... 或 ed2k://..." value="${esc(url)}">`
    +`<button type="button" class="ghost" onclick="this.parentNode.remove()">删</button>`;
  wrap.appendChild(row);
}
function collectDownloads(){
  const rows=document.querySelectorAll('#dlRows .dl-row');
  const links=[];
  rows.forEach(r=>{ const url=r.querySelector('.dl-url').value.trim(); if(url) links.push({label:r.querySelector('.dl-type').value, url}); });
  return JSON.stringify(links);
}
function parseDownloads(raw){
  raw=(raw||'').trim(); if(!raw) return [];
  if(raw[0]==='['){ try{ const a=JSON.parse(raw); if(Array.isArray(a)) return a.map(e=> e&&typeof e==='object'? {label:e.label||'下载',url:e.url||''} : {label:'下载',url:String(e)}); }catch(e){} }
  return raw.split(/\r?\n/).map(s=>s.trim()).filter(Boolean).map(u=>({label:'下载',url:u}));
}

// ===== 链接失效检查 =====
async function checkOne(id){
  const btns=document.querySelectorAll('#itemRows button'); btns.forEach(b=>b.disabled=true);
  const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({id})});
  btns.forEach(b=>b.disabled=false);
  if(!d.success){ alert(d.error||'检查失败'); return; }
  const rs=d.data.results||[]; const dead=rs.filter(r=>!r.ok).length;
  // 逐条列出检测结果，把网盘接口返回的中文原因（如"分享已失效""提取码错误""内容违规"）直接透出，
  // 免得用户看到"失效"却不知是啥情况。
  let lines=['检测完成：共 '+rs.length+' 条，有效 '+(rs.length-dead)+' 条，失效 '+dead+' 条',''];
  rs.forEach((r,i)=>{
    const tag = r.ok ? '✓ 有效' : '✗ 失效';
    const why = (r.msg||'').trim();
    lines.push((i+1)+'. ['+tag+'] '+(r.label||'下载')+(why?(' — '+why):''));
  });
  alert(lines.join('\n'));
  loadItems();
}
async function checkAll(){
  if(!confirm('将逐个检测所有已填写下载链接的条目，可能耗时较久，确定继续？')) return;
  let remaining=1, round=0, ok=0, dead=0;
  while(remaining>0 && round<40){
    round++;
    const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({limit:40})});
    if(!d.success){ alert(d.error||'检查失败'); break; }
    ok+=d.data.ok||0; dead+=d.data.dead||0; remaining=d.data.remaining||0;
    if(remaining===0 || (d.data.checked||0)===0) break; // 无进展则停止，避免空转
  }
  alert('全部检测完成：有效 '+ok+' 条，失效 '+dead+' 条');
  loadItems();
}

// ===== 系统设置（API Key / 数据源 / 网盘类型）=====
let SET = null;        // 后端返回的设置视图
let PAN_DRAFT = [];    // 网盘类型草稿（保存时才提交）
let TXT_DRAFT = null;  // 编辑中的文本值（重绘页面时保留，避免刚打的字被清空）
let AD_DRAFT  = null;  // 编辑中的数据源勾选
let SET_TS    = {};    // 数据源连通状态：{tmdb:{ok,label,detail}, hongguoduanju:{…}}
const SRCS = [
  {kind:'tmdb',    name:'TMDB'},
  {kind:'hongguoduanju', name:'红果短剧（国产短剧）'}
];

const TXT_KEYS = ['tmdb_api_key'];
const AD_KEYS  = ['tmdb','hongguoduanju'];

function mask(s){
  s = String(s||'');
  if(!s) return '（未填写）';
  if(s.length<=10) return s.slice(0,2)+'•••';
  return s.slice(0,4)+'••••'+s.slice(-4);
}
function flag(override){
  return override ? '<span class="set-flag on">已在后台覆盖</span>'
                  : '<span class="set-flag off">来自 config.php</span>';
}
/** 把当前表单里的输入收进草稿（重绘前调用） */
function collectDraft(){
  const t = {};
  TXT_KEYS.forEach(k=>{ const el=document.getElementById('s_'+k); if(el) t[k]=el.value; });
  TXT_DRAFT = t;
  const a = [];
  AD_KEYS.forEach(k=>{ const el=document.getElementById('a_'+k); if(el&&el.checked) a.push(k); });
  AD_DRAFT = a;
}
