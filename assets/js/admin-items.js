/* ==========================================================================
   万能资料库 · 后台管理 JS · 条目：列表 / 匹配 / 增删改 / 同步触发
   由 assets/js/admin.js 按功能拆分而来（原第 593–1001 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
async function loadItems(){
  const q=document.getElementById('q').value;
  const d=await api(`/api/v1/admin/items?page=${curPage}&type=${curCat}&q=${encodeURIComponent(q)}&tag=${encodeURIComponent(curTag)}`);
  if(!d.success){ alert(d.error||'加载失败'); return; }
  // 拉取链接状态汇总
  let map={};
  try{ const m=await api('/api/v1/admin/link_status_map'); if(m.success) map=m.data.map||{}; }catch(e){}
  let h='';
  d.data.items.forEach(it=>{
    const lb = SCHEMA ? (SCHEMA.labels[it.type]||it.type) : it.type;
    const st = linkCell(map[it.id]);
    h+=`<tr>
      <td>${it.poster?`<img class="thumb" src="${it.poster}">`:''}</td>
      <td>${esc(it.title||'')}<br><span class="hint">${(it.original_title||'')}</span></td>
      <td>${lb}</td><td>${esc(it.source||'')}</td><td>${it.year||''}</td><td>${it.rating==null?'':it.rating}</td>
      <td>${st}</td>
      <td><button class="ghost sm" onclick="checkOne(${it.id})">查</button>
          <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
          <button class="danger sm" onclick="del(${it.id})">删</button></td></tr>`;
  });
  document.getElementById('itemRows').innerHTML=h;
  let info=`第 ${curPage} 页 / 共 ${d.data.total} 条`;
  if(curTag) info=`标签「${curTag}」· ${info}`;
  document.getElementById('pageInfo').innerText=info;
}
function linkCell(s){
  if(!s) return '<span class="st st-none">未检测</span>';
  const dl = s.dead||0;
  if(dl>0) return `<span class="st st-dead">✗ 失效 ${dl}</span>`;
  return '<span class="st st-ok">✓ 有效</span>';
}
function pageItems(d){ if(curPage+d<1) return; curPage+=d; loadItems(); }

/* ===== v3.2.3 条目匹配页 ===== */
let mcPage = 1;
let mcTab = 'unmatched';   // unmatched | matched
let mcAllCheckedDead = 0; // 全局失效条目数（含本页之外的估算，来自 /api/v1/admin/link_status_map）
/* ★ v5.1.9：自动匹配「一键跑完」的运行态。mcAutoStop 由按钮自己翻转，
   同名声明的状态在多次开关之间复用，避免全局污染。 */
let mcAutoRunning = false;
let mcAutoStop = false;

function switchMatchTab(t){
  mcTab = (t === 'matched') ? 'matched' : 'unmatched';
  mcPage = 1;
  document.querySelectorAll('#mcSubtabs .mc-tab').forEach(b=>b.classList.toggle('active', b.dataset.mctab===mcTab));
  loadMatching();
}

/* ★ v5.1.9：全自动匹配 —— 循环调用 match_auto 直到 remaining=0 或中途手动停止。
   每批 20 条；已尝试过仍无结果的条目会进冷却期（默认 7 天）不再重复请求，
   所以无论待匹配有多少条，按钮点一次就会一直推进到全部试完。 */
async function runMatchAuto(){
  const btn = document.getElementById('mcAutoBtn');
  if(!btn) return;
  if(mcAutoRunning){ mcAutoStop = true; btn.innerText = '正在停止…'; return; }
  if(!confirm('将使用当前 PanSou 配置，自动为所有待匹配条目搜索并补入下载地址。\n\n'
    + '· 高置信度结果直接写入；低置信度进「待确认」\n'
    + '· 每批 20 条，直到全部试完；再次点击按钮可停止\n'
    + '· 已尝试过仍无结果的条目 7 天内不再重复请求\n\n确定开始？')) return;
  const old = btn.innerText;
  mcAutoRunning = true; mcAutoStop = false;
  btn.disabled = true; btn.innerText = '准备中…';
  let total = {checked:0, written:0, queued:0, nomatch:0, links:0, rounds:0, errors:0};
  try {
    for(;;){
      const d = await api('/api/v1/admin/match_auto',{method:'POST',body:JSON.stringify({limit:20})});
      if(!d.success){ alert(d.error||'自动匹配失败'); break; }
      const r = d.data || {};
      total.checked += (r.checked|0); total.written += (r.written|0);
      total.queued += (r.queued|0); total.nomatch += (r.nomatch|0);
      total.links += (r.links|0); total.rounds++;
      total.errors += (r.errors && r.errors.length) ? r.errors.length : 0;
      const rem = (r.remaining|0);
      btn.innerText = '匹配中… 已试 ' + total.checked + ' 条 · 剩余 ' + rem;
      await loadMatching();
      if(r.done || (r.checked|0) === 0 || mcAutoStop) break;
    }
    let msg = '自动匹配结束（共 ' + total.rounds + ' 批）：\n'
      + '· 检查 ' + total.checked + ' 条\n'
      + '· 写入 ' + total.written + ' 条（新增链接 ' + total.links + ' 个）\n'
      + '· 待确认 ' + total.queued + ' 条 · 无匹配 ' + total.nomatch + ' 条';
    if(total.errors) msg += '\n· 出错 ' + total.errors + ' 条（多为上游抖动，7 天后自动重试）';
    if(mcAutoStop) msg += '\n\n（已手动停止，可再次点击继续）';
    alert(msg);
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  } finally {
    mcAutoRunning = false; mcAutoStop = false;
    btn.disabled = false; btn.innerText = old;
    loadMatching();
  }
}

/* v3.4.6：单条重匹配（自动匹配失败后可用于补单条） */
async function matchOne(id){
  const btn = event && event.currentTarget;
  if(!btn) return;
  if(!confirm('使用当前 PanSou 配置，为 #'+id+' 单独搜索并补入下载地址？\n\n若已有链接，将覆盖。')) return;
  const old = btn.innerText;
  btn.disabled = true;
  btn.innerText = '匹配中…';
  try {
    const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({ids:[id],limit:1,refresh:true})});
    if(!d.success){ alert(d.error||'匹配失败'); return; }
    const r = d.data || {};
    alert('匹配完成：检查 '+(r.checked||0)+' 条，写入 '+(r.written||0)+' 条，待确认 '+(r.queued||0)+' 条，无匹配 '+(r.nomatch||0)+' 条');
    await loadMatching();
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  } finally {
    btn.disabled = false;
    btn.innerText = old;
  }
}

async function loadMatching(){
  const q=document.getElementById('mcQ').value;
  const url=`/api/v1/admin/matching?tab=${mcTab}&page=${mcPage}&per=20&q=${encodeURIComponent(q)}`;
  const d=await api(url);
  if(!d.success){ alert(d.error||'加载失败'); return; }  // 顶部三个统计卡片
  const c = d.data.counts||{};
  const unmatched = (c.unmatched|0);
  const matched   = (c.matched|0);
  const deadItems = (d.data.tab==='matched') ? countDeadInPage(d.data.items) : estimateDeadFromMap();
  document.getElementById('mcUnmatched').innerText = unmatched;
  document.getElementById('mcMatched').innerText   = matched;
  document.getElementById('mcDead').innerText      = deadItems;
  document.getElementById('mcCntUnmatched').innerText = unmatched;
  document.getElementById('mcCntMatched').innerText   = matched;

  const tb = document.getElementById('mcRows');
  if(!d.data.items.length){
    const msg = (mcTab==='unmatched')
      ? '<b>🎉</b>太棒了！所有条目都已匹配上网盘链接，去「已匹配」页查看失效情况'
      : '<b>🕓</b>还没有匹配网盘链接的条目；用「+ 新增项目」或从「同步采集」拉热门后人工补链接';
    tb.innerHTML = `<div class="mc-rows-empty">${msg}</div>`;
  } else {
    let h='';
    d.data.items.forEach(it=>{
      const lb = SCHEMA ? (SCHEMA.labels[it.type]||it.type) : it.type;
      const st = (mcTab==='unmatched')
        ? '<span class="st-mc unmatched">🕓 待匹配</span>'
        : matchStatusCell(it);
      const btns = mcTab==='unmatched'
        ? `<div class="btn-row">
             <button class="ghost sm mc-match-one" onclick="matchOne(${it.id})" title="用 PanSou 配置单条重匹配（自动匹配失败后可用于补单条）">🔗 匹配</button>
             <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
           </div>`
        : `<div class="btn-row">
             <button class="ghost sm" onclick="checkOne(${it.id})">检测</button>
             <button class="ghost sm mc-match-one" onclick="matchOne(${it.id})" title="用 PanSou 配置单条重匹配（可覆盖已有链接）">🔗 匹配</button>
             <button class="ghost sm" onclick="openEdit(${it.id})">编辑</button>
           </div>`;
      h += `<div class="mc-card">
        ${it.poster?`<img class="thumb" src="${it.poster}">`:`<span class="thumb mc-thumb-ph"></span>`}
        <div class="mc-main">
          <div class="mc-title" title="${esc(it.title||'')}">${esc(it.title||'')}</div>
          <div class="hint mc-sub">${esc(it.original_title||'')}</div>
          <div class="mc-meta"><span class="mc-cat">${lb}</span>${st}</div>
          <div class="mc-acts">${btns}</div>
        </div>
      </div>`;
    });
    tb.innerHTML = h;
  }
  document.getElementById('mcPageInfo').innerText = `第 ${d.data.page} 页 · 共 ${d.data.total} 条`;
  /* ★ v5.2.7：顺带把「自动匹配」运行状态拉回来（状态卡） */
  mcLoadAuto();
}

/* ★ v5.2.7：自动匹配运行状态卡片。
   背景：后台「待匹配」数 = 全部没有链接的条目，而自动匹配把条目试过一遍、
   多数没匹配上时这个数字**根本不会动** —— 看着就像没生效。
   这里把「在不在跑 / 上次干了什么 / PanSou 配没配 / 还剩多少能试」摊开。 */
async function mcLoadAuto(){
  const body = document.getElementById('mcAutoBody');
  const st   = document.getElementById('mcAutoState');
  const dot  = document.getElementById('mcAutoDot');
  if(!body) return;
  try{
    const d = await api('/api/v1/admin/match_auto_status');
    if(!d || !d.success){
      if(st) st.textContent = '读取失败';
      body.innerHTML = '<div class="hint">读取失败：' + esc((d && d.error) || '未知错误') + '</div>';
      return;
    }
    const s = d.data || {};
    const lr = s.last_run;
    const t  = s.tried || {};
    const ps = s.pansou || {};
    let cls = 'gray', head = '未运行', msg = '';
    if(!ps.enable){
      cls = 'bad'; head = 'PanSou 未启用';
      msg = '「站点设置 → 接口配置」里没有启用 PanSou —— 自动匹配会**静默跳过**（脚本找不到接口就直接退出）。';
    }else if(!lr){
      cls = 'gray'; head = '未运行';
      msg = '宝塔里还没跑起来，或命令路径不对。<br>命令在「站点设置 → 系统设置 → 计划任务（宝塔面板）」里可整段复制。';
    }else{
      const when = lr.created_at || lr.at || '';
      const base = '上次 ' + when
                 + (typeof lr.checked==='number' ? (' · 检查 ' + lr.checked + ' 条') : '')
                 + (typeof lr.written==='number' ? (' · 写入 ' + lr.written + ' 条') : '')
                 + (typeof lr.nomatch==='number' ? (' · 无匹配 ' + lr.nomatch + ' 条') : '')
                 + (typeof lr.queued==='number' ? (' · 待确认 ' + lr.queued + ' 条') : '')
                 + (lr.remaining!==undefined ? (' · 还能试 ' + lr.remaining + ' 条') : '');
      if(lr.status==='done'){ cls='ok'; head='运行正常'; msg=base; }
      else if(lr.status==='disabled'){ cls='bad'; head='已被关闭'; msg='后台把自动匹配关了（cron.match_auto_enabled=0）。'; }
      else if(lr.status==='unavailable'){ cls='bad'; head='PanSou 不可用'; msg='上次运行失败：' + esc(lr.message||'（无）'); }
      else if(lr.status==='empty'){ cls='amber'; head='本轮无候选'; msg=base + '<br>消息：' + esc(lr.message||'（无）'); }
      else { cls='bad'; head='运行出错'; msg=base + '<br>消息：' + esc(lr.message||'（无）'); }
    }
    if(st) st.textContent = head;
    if(dot) dot.className = 'sc-auto-dot ' + cls;
    let h = '<div class="sc-auto-line">' + (msg || '') + '</div>';
    h += '<div class="sc-auto-line">运行次数：近 1 小时 <b>' + (s.runs_1h||0) + '</b> 次 · 近 24 小时 <b>' + (s.runs_24h||0) + '</b> 次'
       + ' · 每批 <b>' + (s.per_run||20) + '</b> 条 · 失败后 <b>' + (s.retry_days||7) + '</b> 天内不再重复请求</div>';
    h += '<div class="sc-auto-line">累计已尝试 <b>' + (t.total||0) + '</b> 条：写入 <b>' + (t.write||0) + '</b> · 待确认 <b>' + (t.queue||0)
       + ' · 无匹配 <b>' + (t.nomatch||0) + '</b> · 本地复用 <b>' + (t.local||0) + '</b> · 出错 <b>' + (t.error||0) + '</b></div>';
    h += '<div class="sc-auto-line">现在还能试的条目 <b>' + (s.remaining||0) + '</b> 条（全部没链接 ' + (s.unmatched||0) + ' 条，'
       + '其中已在冷却期内、这轮不会再试的 = 差值）· PanSou：'
       + (ps.enable ? ('已启用 · ' + esc(ps.mode||'') + ' · 置信度阈值 ' + (ps.match||0)) : '<b style="color:var(--dead)">未启用</b>') + '</div>';
    h += '<div class="sc-auto-line">低置信度结果不会直接写入，而是进「站点设置 → 接口配置 → 待确认队列」；'
       + '想让匹配直接落库，把那里的「写入策略」改成<b>搜到就写</b>，或把置信度阈值调低。</div>';
    body.innerHTML = h;
  }catch(e){
    if(st) st.textContent = '读取失败';
    body.innerHTML = '<div class="hint">读取失败：' + esc(String(e && e.message || e)) + '</div>';
  }
}

function countDeadInPage(items){
  let n=0;
  (items||[]).forEach(it=>{ if((it.links_dead|0) > 0) n++; });
  return n;
}

// 未匹配页时：从 link_status_map 估算失效条目总数（本页看不到失效分布，只能给全局值）
function estimateDeadFromMap(){
  if(mcAllCheckedDead){ return mcAllCheckedDead; }
  // 惰性拉一次，本页不需要就跳过
  try{
    api('/api/v1/admin/link_status_map').then(m=>{
      if(!m.success) return;
      const map = m.data.map||{};
      let n=0;
      Object.keys(map).forEach(k=>{ if((map[k].dead|0)>0) n++; });
      mcAllCheckedDead = n;
      document.getElementById('mcDead').innerText = n;
    }).catch(()=>{});
  }catch(e){}
  return '-';
}

// 已匹配页的状态徽章：显示 有效 X / 失效 Y / 检测时间
function matchStatusCell(it){
  const ok = (it.links_ok|0);
  const dead = (it.links_dead|0);
  const total = (it.links_total|0);
  const last = it.last_checked || '';
  let cls = 'unmatched', txt = '🕓 未检测';
  if(total>0 && dead>0){ cls='dead'; txt=`✗ 失效 ${dead}/${total}`; }
  else if(total>0 && dead===0){ cls='ok'; txt=`✓ 有效 ${ok}/${total}`; }
  const tip = last ? `title="上次检测：${esc(last)}"` : '';
  return `<span class="st-mc ${cls}" ${tip}>${txt}</span>`;
}

function mcPageItems(d){ if(mcPage+d<1) return; mcPage+=d; loadMatching(); }

// 手动触发一次全量链接检测（走批量 check_links）
async function matchCheckAll(){
  const btn = document.getElementById('mcCheckBtn');
  if(!confirm('将逐个检测所有已匹配条目的下载链接，可能耗时较久，确定继续？')) return;
  btn.disabled = true; const old = btn.innerText; btn.innerText = '检测中…';
  let remaining=1, round=0, ok=0, dead=0;
  try {
    while(remaining>0 && round<40){
      round++;
      const d=await api('/api/v1/admin/check_links',{method:'POST',body:JSON.stringify({limit:40})});
      if(!d.success){ alert(d.error||'检查失败'); break; }
      ok+=d.data.ok|0; dead+=d.data.dead|0; remaining=d.data.remaining|0;
      if(remaining===0 || (d.data.checked|0)===0) break;
    }
    alert('检测完成：有效 '+ok+' 条，失效 '+dead+' 条');
  } catch(e){ alert(e&&e.message?e.message:String(e)); }
  btn.disabled = false; btn.innerText = old;
  loadMatching();
}

// 渲染分类扩展字段
function renderExtra(type, values){
  const box=document.getElementById('extraFields'); box.innerHTML='';
  const fs = (SCHEMA && SCHEMA.extra_fields[type]) ? SCHEMA.extra_fields[type] : [];
  if(!fs.length){ box.innerHTML='<div class="ext-note">该分类暂无额外字段，想加集数/导演等可在 core/Categories.php 配置。</div>'; return; }
  let h='<div class="ext-note">'+ (SCHEMA.labels[type]||type) +'专属字段：</div>';
  fs.forEach(f=>{
    h+=`<label>${f.label}</label><input id="x_${f.k}" placeholder="${f.ph||''}" value="${(values&&values[f.k]!=null)?esc(values[f.k]):''}">`;
  });
  box.innerHTML=h;
}
function openAdd(){
  editId=0; LINK_STATUS={}; editExtraBase={};
  ['e_title','e_original_title','e_year','e_rating','e_genres','e_poster','e_backdrop','e_overview']
    .forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  const sel=document.getElementById('e_type'); sel.value = curCat || 'movie';
  document.getElementById('dlRows').innerHTML='';
  addDlRow(PAN_LIST[0]||'夸克','', null);
  renderExtra(sel.value, {});
  document.getElementById('modalTitle').innerText='新增项目';
  document.getElementById('modal').style.display='flex';
}
async function openEdit(id){
  editId=id; const d=await api('/api/v1/admin/item/'+id);
  if(!d.success){ alert(d.error); return; }
  const it=d.data;
  // 链接状态映射
  LINK_STATUS={};
  (it.link_checks||[]).forEach(c=>{ LINK_STATUS[c.url] = { ok:(parseInt(c.status,10)===1), msg:(c.msg||''), code:(c.http_code|0) }; });
  document.getElementById('e_type').value=it.type||'movie';
  document.getElementById('e_title').value=it.title||'';
  document.getElementById('e_original_title').value=it.original_title||'';
  document.getElementById('e_year').value=it.year||'';
  document.getElementById('e_rating').value=it.rating==null?'':it.rating;
  try{ const gs=JSON.parse(it.genres||'[]'); document.getElementById('e_genres').value=Array.isArray(gs)?gs.join(', '):''; }catch(e){ document.getElementById('e_genres').value=''; }
  document.getElementById('e_poster').value=it.poster||'';
  document.getElementById('e_backdrop').value=it.backdrop||'';
  // 下载链接：解析 JSON 数组 [{"label","url"}] 或旧版纯文本
  const links = parseDownloads(it.download_url||'');
  document.getElementById('dlRows').innerHTML='';
  if(links.length){ links.forEach(l=>addDlRow(l.label||(PAN_LIST[0]||'夸克'), l.url||'', statusOf(l.url))); } else { addDlRow(PAN_LIST[0]||'夸克','', null); }
  document.getElementById('e_overview').value=it.overview||'';
  let extra={}; try{ extra=JSON.parse(it.extra||'{}')||{}; }catch(e){ extra={}; }
  editExtraBase=extra; // v3.3.7：保存编辑前快照，saveEdit 用它保留 cast/trailer/videos 等非 schema key
  renderExtra(it.type||'movie', extra);
  document.getElementById('modalTitle').innerText='编辑 #'+id;
  document.getElementById('modal').style.display='flex';
}
function statusOf(url){ return (url && url in LINK_STATUS) ? LINK_STATUS[url] : null; }
function closeModal(){ document.getElementById('modal').style.display='none'; }

/* ---- 修改密码 / 邮箱（右上角按钮） ---- */
function openPwd(){
  const m=document.getElementById('pwdModal');
  if(!m) return;
  ['pw_old','pw_new','pw_new2'].forEach(function(id){ const el=document.getElementById(id); if(el) el.value=''; });
  const msg=document.getElementById('pwMsg'); if(msg){ msg.innerText=''; msg.style.color=''; }
  m.style.display='flex';
}
function closePwd(){ const m=document.getElementById('pwdModal'); if(m) m.style.display='none'; }
async function savePwd(){
  const msg=document.getElementById('pwMsg');
  const em   = (document.getElementById('pw_email').value||'').trim();
  const oldp = document.getElementById('pw_old').value||'';
  const n1   = document.getElementById('pw_new').value||'';
  const n2   = document.getElementById('pw_new2').value||'';
  const fail = function(t){ if(msg){ msg.style.color='var(--dead)'; msg.innerText=t; } };
  const payload = { email: em };
  if(oldp || n1 || n2){
    if(!oldp){ return fail('请输入当前密码。'); }
    if(n1.length<6){ return fail('新密码至少 6 位。'); }
    if(n1!==n2){ return fail('两次输入的新密码不一致。'); }
    payload.old=oldp; payload.new=n1; payload.new2=n2;
  }
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='保存中…'; }
  const d = await api('/api/v1/admin/password',{method:'POST',body:JSON.stringify(payload)});
  if(!d.success){ return fail(d.error||'修改失败'); }
  if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ '+((d.data&&d.data.msg)?d.data.msg:'已保存'); }
  setTimeout(closePwd, 1200);
}
async function saveEdit(){
  const type=document.getElementById('e_type').value;
  const genres=document.getElementById('e_genres').value.split(',').map(s=>s.trim()).filter(Boolean);
  // v3.3.7：以 editExtraBase 为底，保留 cast/trailer/videos/stills/popularity 等非 schema key
  //   —— 否则用户随便改个字段保存，TMDB 采集的演员头像/预告片数据会被全部擦掉
  const extra={...(editExtraBase||{})};
  const fs=(SCHEMA&&SCHEMA.extra_fields[type])?SCHEMA.extra_fields[type]:[];
  fs.forEach(f=>{
    const el=document.getElementById('x_'+f.k);
    if(el){
      const v=el.value.trim();
      if(v){ extra[f.k]=v; } else { delete extra[f.k]; }   // 用户清空则移除
    }
  });
  const body={
    type,
    title:document.getElementById('e_title').value.trim(),
    original_title:document.getElementById('e_original_title').value.trim(),
    year:document.getElementById('e_year').value||null,
    rating:document.getElementById('e_rating').value||null,
    genres:JSON.stringify(genres),
    poster:document.getElementById('e_poster').value.trim(),
    backdrop:document.getElementById('e_backdrop').value.trim(),
    download_images:document.getElementById('e_downloadImages')?.checked ? '1' : '0',
    download_url:collectDownloads(),
    overview:document.getElementById('e_overview').value,
    extra:JSON.stringify(extra),
  };
  if(!body.title){ alert('标题必填'); return; }
  const d = editId
    ? await api('/api/v1/admin/item/'+editId,{method:'PUT',body:JSON.stringify(body)})
    : await api('/api/v1/admin/item',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ alert(d.error); return; }
  const id = editId ? editId : (d.data.id||0);
  if(id){ await api('/api/v1/admin/build',{method:'POST',body:JSON.stringify({id})}).catch(()=>{}); }
  closeModal(); loadItems();
}
async function del(id){ if(!confirm('确认删除 #'+id+' ？')) return; const d=await api('/api/v1/admin/item/'+id,{method:'DELETE'}); if(d.success) loadItems(); else alert(d.error); }
/**
 * 采集入口：网页按钮与计划任务走同一套引擎（core/Sync.php）。
 * force=true 忽略「最小间隔」；dry=true 只探测接口不入库。
 */
async function triggerSync(force, btn){
  const st  = document.getElementById('syncState');
  const box = document.getElementById('syncReport');
  if(btn) btn.disabled = true;
  if(st)  st.textContent = (force ? '正在强制采集' : '正在采集') + '（按各源官方要求限流，可能要十几秒）…';
  if(box) box.innerHTML = '<div class="hint">正在请求上游…请勿关闭页面。</div>';
  const d = await api('/api/v1/admin/sync', {method:'POST', body: JSON.stringify({force: !!force})});
  if(btn) btn.disabled = false;
  if(st) st.textContent = '';
  if(!d.success){
    if(box) box.innerHTML = '<div class="ext-note note-err">✗ 采集失败：'+esc(d.error)+'</div>';
    return;
  }
  renderSyncReport(d.data);
  loadStats(); loadItems(); loadSyncPlan(); loadSyncRecent();
}
async function dryRun(btn){
  const box = document.getElementById('syncReport');
  if(btn) btn.disabled = true;
  const d = await api('/api/v1/admin/sync', {method:'POST', body: JSON.stringify({dry:true, force:true})});
  if(btn) btn.disabled = false;
  if(!d.success){ if(box) box.innerHTML='<div class="ext-note note-err">✗ 预演失败：'+esc(d.error)+'</div>'; return; }
  renderSyncReport(d.data, true);
}
/** 把采集报告画成一张清楚的表（而不是一句 alert） */
function renderSyncReport(r, isDry){
  const box = document.getElementById('syncReport');
  if(!box || !r) return;
  let h = '';
  if(r.skipped){
    h += `<div class="ext-note note-warn">⏸ 本次跳过：${esc(r.reason||'')}</div>`;
  } else if(!r.ok){
    h += `<div class="ext-note note-err">✗ 失败：${esc(r.reason||'未知原因')}</div>`;
  } else {
    /* ★ v5.3.3：把「inserted=0」从「采集完成」里明确拆出来 ——
       旧版一律显示「✓ 采集完成」，用户看到"同步完成"却在页面上找不到任何数据。
       现在按场景给三档提示：
       · 预演（dry）         → 🔍
       · inserted>0          → ✓（正常）
       · inserted=0 但跑了   → ⚠（明确告知未新增）*/
    const tone = (isDry||r.inserted>0) ? 'note-ok' : 'note-warn';
    const mark = isDry ? '🔍 预演完成（未入库）' : (r.inserted>0 ? '✓ 采集完成' : '⚠ 采集完成但 0 条新增');
    let extra = '';
    if(!isDry && r.inserted>0){
      const vis = (r.visible_after_sync===undefined || r.visible_after_sync===null) ? r.inserted : r.visible_after_sync;
      extra = `，实际可见 <b>${vis}</b> 条`;
      if(vis < r.inserted){
        extra += `<span style="color:#b91c1c">（有 ${r.inserted - vis} 条入库但未显示，请检查该条目的 status 是否为 'active'）</span>`;
      }
    }
    h += `<div class="ext-note ${tone}">${mark}：
      抓到 <b>${r.fetched}</b> 条 → 新增 <b>${r.inserted}</b> 条，重复跳过 <b>${r.skipped_dup}</b> 条${r.skipped_low?('，低于票数门槛 '+r.skipped_low+' 条'):''}；
      上游请求 <b>${r.requests}</b> 次，静态页 <b>${r.built}</b> 个，耗时 ${((r.ms||0)/1000).toFixed(1)}s。${extra}
      ${r.reason?('<br>提示：'+esc(r.reason)):''}</div>`;
  }
  h += '<table style="margin-top:10px"><thead><tr><th>数据源</th><th>接口</th><th>抓到</th><th>新增</th><th>重复跳过</th><th>备注</th></tr></thead><tbody>';
  (r.sources||[]).forEach(s=>{
    const eps = (s.endpoints||[]);
    if(!eps.length){
      h += `<tr><td>${esc(s.label||s.source)}</td><td class="hint">—</td><td>${s.fetched||0}</td><td>${s.inserted||0}</td><td>${s.skipped_dup||0}</td><td class="hint">${esc(s.msg||'')}</td></tr>`;
    } else {
      eps.forEach((ep,i)=>{
        h += `<tr>`+
          (i===0?`<td rowspan="${eps.length}">${esc(s.label||s.source)}</td>`:'')+
          `<td class="hint">${esc(ep.path)}${ep.error?(' <span style="color:#b91c1c">✗ '+esc(ep.error)+'</span>'):''}</td>`+
          (i===0?`<td rowspan="${eps.length}">${s.fetched||0}</td><td rowspan="${eps.length}">${s.inserted||0}</td><td rowspan="${eps.length}">${s.skipped_dup||0}</td>`:'')+
          (i===0?`<td rowspan="${eps.length}" class="hint">${esc(s.msg||'')}</td>`:'')+
        `</tr>`;
      });
    }
  });
  h += '</tbody></table>';
  box.innerHTML = h;
}

