/* ==========================================================================
   万能资料库 · 后台管理 JS · 概览：标签 / 人物 / 统计 / 访问
   由 assets/js/admin.js 按功能拆分而来（原第 73–464 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
async function loadSchema(){
  const d = await api('/api/v1/categories');
  if(!d.success) return;
  SCHEMA = d.data;
  PAN_LIST = (d.data && d.data.pan_types && d.data.pan_types.length) ? d.data.pan_types : PAN_LIST;
  const sel = document.getElementById('e_type');
  sel.innerHTML = '';
  for(const k in SCHEMA.labels){
    const o=document.createElement('option'); o.value=k; o.textContent=SCHEMA.labels[k]; sel.appendChild(o);
  }
}
let TAG_ACTIVE='';
async function renderTags(force){
  const btn = document.getElementById('tagRefreshBtn');
  if(force && btn){ btn.classList.add('loading'); btn.disabled = true; }
  try {
    const d = await api('/api/v1/admin/tags' + (force ? '?force=1' : ''));
    if(!d.success){ alert(d.error||'加载失败'); return; }
    const tags = d.data.items||[];
    /* ★ v4.4.1：统计行 = 标签 + 导演 + 演员 全量（与页面卡片总数一致，用户反馈「不止 N 个」） */
    TAG_COUNTS.tags = tags.length; updateTagStatLine(force);
    /* v3.6.8：剧情不再单独分组；人名标签已由后端过滤（只在热门导演/演员区块出现） */
    let h='';
    for(const t of tags){
      h+='<div class="tag-card" onclick="openTagDetail(\''+t.tag.replace(/'/g,"\\'")+'\')">'
       +'<span class="tag-name">'+esc(t.tag)+'</span>'
       +'<span class="tag-count">'+t.items+'</span>'
       +'</div>';
    }
    if(!tags.length) h='<div class="tag-list-empty">暂无常规标签，请先在条目中添加 genres 标签</div>';
    document.getElementById('tagGrid').innerHTML=h;
  } finally {
    if(btn){ btn.classList.remove('loading'); btn.disabled = false; }
  }
}
/* ★ v4.4.1：标签页统计行 —— 三类卡片数量合计 */
const TAG_COUNTS = { tags: 0, dir: 0, act: 0 };
function updateTagStatLine(force){
  const el = document.getElementById('tagStatLine');
  if(!el) return;
  el.textContent = '共 '+(TAG_COUNTS.tags + TAG_COUNTS.dir + TAG_COUNTS.act)+' 项'
    +'（标签 '+TAG_COUNTS.tags+' · 导演 '+TAG_COUNTS.dir+' · 演员 '+TAG_COUNTS.act+'）'
    + (force ? ' · 已强制刷新' : '');
}
/* ★ v4.4.1：热门导演/演员 —— 与标签网格同款卡片（名字 + 数量徽章）
   ★ v4.4.1：恢复本区块（用户确认保留）；点人名改为与标签一致的就地展开（不再跳条目管理）
   ★ v4.6.9：加 force 参数（绕过 600 秒人物缓存），空态时渲染诊断面板 */
async function renderPeople(force){
  try {
    const d = await api('/api/v1/admin/hot-people' + (force ? '?force=1' : ''));
    if(!d.success){ return; }
    const hp = d.data || {directors:[],actors:[]};
    const fill = (listId, rowId, arr, kind) => {
      const list = document.getElementById(listId), row = document.getElementById(rowId);
      if(!list || !row){ return; }
      if(!arr || !arr.length){ row.style.display='none'; list.innerHTML=''; return; }
      let h='';
      for(const p of arr){
        const n = (p.name||'').replace(/'/g,"\\'");
        h+='<div class="tag-card" style="cursor:pointer" title="'+esc(n)+'：'+(p.items||0)+' 部 · 累计点击 '+(p.clicks||0)+' 次"'
         +' onclick="openPersonItems(\''+n+'\',\''+kind+'\')">'
         +'<span class="tag-name">'+esc(n)+'</span>'
         +'<span class="tag-count">'+(p.items||0)+'</span>'
         +'</div>';
      }
      list.innerHTML=h;
      row.style.display='block';
    };
    fill('peopleListDir','peopleRowDir', hp.directors, 'director');
    fill('peopleListAct','peopleRowAct', hp.actors, 'actor');
    TAG_COUNTS.dir = (hp.directors||[]).length;
    TAG_COUNTS.act = (hp.actors||[]).length;
    updateTagStatLine();
    const e=document.getElementById('peopleEmpty');
    const empty = !((hp.directors||[]).length || (hp.actors||[]).length);
    if(e){ e.style.display = empty ? 'block' : 'none'; }
    /* ★ v4.6.9：只在「人物云为空」时把诊断摊开，避免干扰正常使用 */
    renderPeopleDiag(hp.diag, empty);
  } catch(_e){ /* 人物区块加载失败不影响标签页主体 */ }
}
/* ★ v4.6.9：人物数据诊断面板 —— 四个计数直接告诉你「导演/演员为什么是 0」 */
function renderPeopleDiag(diag, empty){
  const card=document.getElementById('peopleDiagCard'), box=document.getElementById('peopleDiagBox');
  if(!card||!box) return;
  if(!empty || !diag){ card.style.display='none'; return; }
  card.style.display='block';
  const n=function(v){ return (v===null||v===undefined)?'?':(v|0); };
  let tip='';
  if(!diag.index_table)            tip='人物索引表尚未建立 —— 点「重建人物索引」即可（不影响条目数据）。';
  else if(n(diag.index_rows)===0)  tip='人物索引表是空的 —— 点「重建人物索引」从条目的演职员信息重新生成。';
  else if(n(diag.active_with_people)===0) tip='已上架条目里<b>没有演职员信息</b>：v4.6.9 起同步/保存都不会再抹掉人名，但<em>已经丢失的数据不会自动长回来</em> —— 需在「同步采集」或「自动刮削」里重新拉一次详情回填。';
  else if(n(diag.pending_with_people)>0)  tip='人名只出现在<b>条目暂存</b>里（'+n(diag.pending_with_people)+' 条）—— 暂存条目不计入统计，刮削通过后才会进这里。';
  else tip='索引与条目都有数据，若仍为空请点「🔄 强制刷新」重查（可排除缓存）。';
  box.innerHTML='<div class="hint" style="line-height:2">'
    + '人物索引表：'+(diag.index_table?'已建立':'未建立')+'　·　索引行数：<b>'+n(diag.index_rows)+'</b><br>'
    + '已上架条目：<b>'+n(diag.active_items)+'</b> 条　·　其中带演职员信息：<b>'+n(diag.active_with_people)+'</b> 条<br>'
    + '条目暂存里带演职员信息：<b>'+n(diag.pending_with_people)+'</b> 条'
    + '</div><div class="hint" style="margin-top:8px;color:var(--mut)">'+tip+'</div>';
}
/* ★ v4.6.9：一键重建人物索引（不用 SSH 跑 tools/ 脚本） */
async function rebuildPersonIndex(btn){
  const msg=document.getElementById('peopleDiagMsg');
  if(btn){ btn.disabled=true; }
  if(msg){ msg.textContent='重建中…'; }
  try{
    const d=await api('/api/v1/admin/rebuild-person-index',{method:'POST',body:JSON.stringify({})});
    if(!d.success){ if(msg) msg.textContent='重建失败：'+(d.error||'未知错误'); return; }
    if(msg){ msg.textContent='✓ 已重建，写入 '+(d.data&&d.data.rows?d.data.rows:0)+' 行索引'; }
    await renderTags(true); await renderPeople(true);
  }catch(e){ if(msg) msg.textContent='重建失败：'+(e&&e.message?e.message:String(e)); }
  finally{ if(btn){ btn.disabled=false; } }
}
/* ★ v4.6.9：标签页「🔄 强制刷新」= 常规标签 + 热门导演/演员 一起绕过缓存重查 */
async function refreshTagPage(){
  await renderTags(true);
  await renderPeople(true);
}
/* ★ v4.4.1：点导演/演员 → 与标签一致的就地展开作品列表（此前跳条目管理，用户判定为错误行为） */
function openPersonItems(name, kind){
  openTagDetail(name, kind || 'actor');
}
async function openTagDetail(tag, kind){
  TAG_ACTIVE=tag;
  /* ★ v4.3.13：进入某个标签只看该标签 —— 常规标签整组、热门导演/演员一并隐藏，返回时恢复 */
  document.getElementById('tagGroupWrap').style.display='none';
  document.getElementById('peopleCloud').style.display='none';
  document.getElementById('tagGrid').style.display='none';
  document.querySelector('.tag-hint').style.display='none';
  document.getElementById('tagDetailCard').style.display='block';
  /* ★ v4.4.1：标题区分标签 / 导演 / 演员 */
  document.getElementById('tagDetailTitle').textContent =
    kind === 'director' ? ('导演「'+tag+'」的作品')
    : kind === 'actor'  ? ('演员「'+tag+'」的作品')
    : ('标签「'+tag+'」下的条目');
  document.getElementById('tagDetailHint').textContent='加载中…';
  /* ★ v4.6.0 修「点标签/人物进去 0 条」：
     此前无论点常规标签还是点热门导演/演员，一律用 tag= 查，而 kind 参数只被用来改标题、
     取数时压根没用 —— 人物名（尤其 actors 里排在第 7 位之后的）并不在 genres 里，查出来必然 0 条。
     现在：kind 为 director/actor 时走 person=+kind=（精确查 person_index），
     常规标签才走 tag=。per 传 100 一次看全。 */
  const isPerson = (kind === 'director' || kind === 'actor');
  const url = isPerson
    ? '/api/v1/admin/items?page=1&person='+encodeURIComponent(tag)+'&kind='+encodeURIComponent(kind)+'&per=100'
    : '/api/v1/admin/items?page=1&tag='+encodeURIComponent(tag)+'&per=100';
  const d=await api(url);
  if(!d.success){ document.getElementById('tagDetailHint').textContent='加载失败'; alert(d.error||'加载失败'); return; }
  document.getElementById('tagDetailHint').textContent='共 '+(d.data.total||0)+' 条';
  let h='';
  const items=d.data.items||[];
  if(!items.length){ h='<div class="tag-list-empty">该标签下暂无条目</div>'; }
  else{
    for(const it of items){
      const lb=SCHEMA?(SCHEMA.labels[it.type]||it.type):it.type;
      const sub=[esc(it.original_title||''),lb,it.year?String(it.year):''].filter(Boolean).join(' · ');
      h+='<div class="tag-item" onclick="window.open(\'/uisc/'+it.id+'.html\',\'_blank\')" title="'+esc(it.title||'')+'">'
       +(it.poster?'<img class="thumb" src="'+it.poster+'">':'')
       +'<div class="ti-txt"><b>'+esc(it.title||'')+'</b><span class="hint">'+sub+'</span></div>'
       +'</div>';
    }
  }
  document.getElementById('tagItemList').innerHTML=h;
}
function closeTagDetail(){
  TAG_ACTIVE='';
  document.getElementById('tagGroupWrap').style.display='';
  document.getElementById('peopleCloud').style.display='';
  document.getElementById('tagGrid').style.display='grid';
  document.querySelector('.tag-hint').style.display='block';
  document.getElementById('tagDetailCard').style.display='none';
}
function goItemsWithTag(){
  if(!TAG_ACTIVE) return;
  curTag=TAG_ACTIVE;
  curCat='';
  curPage=1;
  document.getElementById('q').value='';
  document.querySelectorAll('#cats .cat').forEach(x=>x.classList.remove('active'));
  switchTab('items');
}
function switchTab(t){
  document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active', x.dataset.tab===t));
  document.querySelectorAll('.tabpane').forEach(x=>x.style.display = (x.id==='tab-'+t)?'block':'none');
  /* ★ v5.4.5：右侧内容顶栏标题同步切换 */
  var TAB_TITLE = {overview:'概览',items:'条目管理',pending:'条目暂存',tags:'标签项目',site:'站点设置',request:'资源请求',apitoken:'API 调用',scrape:'自动刮削',matching:'条目匹配',local:'资源导入'};
  var ct = document.getElementById('ctTitle');
  if(ct && TAB_TITLE[t]) ct.textContent = TAB_TITLE[t];
  /* 移动端：点完导航收起左侧抽屉 */
  if(window.innerWidth < 900){ var s=document.getElementById('adminSide'); if(s) s.classList.remove('open'); }
  if(t==='overview'){ loadStats(); loadTopItems(); loadOverviewStats(); loadVisitStats(); }
  if(t==='items') loadItems();
  if(t==='pending') loadPending();
  /* ★ v4.9.6：「同步采集」已降级为站点设置的二级页签（data-stab="sync"），
     不再是顶层 tab ⇒ 这里不再由 switchTab 触发 loadSync()。 */
  if(t==='site') loadSite();
  if(t==='request') loadReq();
  if(t==='apitoken') loadTokens();
  if(t==='scrape') renderScrapePanel();
  if(t==='matching') loadMatching();
  /* ★ v5.2.9：「用户中心」页签已取消，用户列表在「资源请求 → 注册用户」里。 */
  if(t==='tags'){ renderTags(); renderPeople(); }
  if(t==='local') loadLocal();
}
async function loadStats(){
  const d = await api('/api/v1/admin/stats');
  if(!d.success){ alert(d.error||'加载失败'); return; }
  const s=d.data; let h='';
  /* ★ v4.8.9：这里【只画类型分布 / 来源分布】。
     「总条目」「暂存」两项已移除 —— 它们与下方「内容总览统计」里的
     「正式条目 / 暂存待刮削」完全重复（同源数据、口径也一致），
     概览页出现两遍同样的数字只是噪音。
     ★ v5.2.2：来源名加中文映射，不再直接暴露 manual / hongguoduanju 这类内部标识。 */
  const SRC_LABEL = {manual:'手动录入', tmdb:'TMDB', hongguoduanju:'红果短剧', pansou:'PanSou', feishu:'飞书导入'};
  for(const k in s.by_type) h+=`<div class="stat"><b>${s.by_type[k]}</b><span>${SCHEMA?SCHEMA.labels[k]:k}</span></div>`;
  for(const k in s.by_source) h+=`<div class="stat"><b>${s.by_source[k]}</b><span>来源:${esc(SRC_LABEL[k]||k)}</span></div>`;
  const el=document.getElementById('stats');
  if(!el) return;
  if(!h) h='<div class="top-empty">暂无数据</div>';
  el.innerHTML=h;
}
let TOP_CAT='';
function filterTopItems(cat){
  TOP_CAT=cat;
  document.querySelectorAll('#topCats .cat').forEach(x=>x.classList.toggle('active', x.dataset.cat===cat));
  loadTopItems();
}
async function loadTopItems(){
  const url='/api/v1/admin/items?page=1&sort=clicks&per=50&type='+encodeURIComponent(TOP_CAT);
  const d=await api(url);
  if(!d.success){ document.getElementById('topItemsList').innerHTML='<div class="top-empty">加载失败</div>'; return; }
  const items=(d.data.items||[]).slice(0,50);
  if(!items.length){ document.getElementById('topItemsList').innerHTML='<div class="top-empty">暂无点击数据</div>'; return; }
  let h='';
  items.forEach((it,i)=>{
    const rk=i+1;
    const rkCls='top-rank'+(rk<=3?(' top-rank-'+rk):'');
    h+='<div class="top-item" onclick="window.open(\'/uisc/'+it.id+'.html\',\'_blank\')" title="'+esc(it.title||'')+'"><span class="'+rkCls+'">'+rk+'</span><span class="top-name">'+esc(it.title||'')+'</span></div>';
  });
  document.getElementById('topItemsList').innerHTML=h;
}

/* ★ v4.6.3：概览页「内容总览统计」渲染
   分三组：条目总览 / 数据完整度 / 流量热度。
   任何一个分组接口失败都只影响自己那一组，不影响概览页其它区块。

   ★ v5.2.2 概览瘦身（用户：「有些重复的，有些不需要的，只留有用的」）：
     ① 条目总览去掉「年份跨度」「标签总数」「人物总数」——
        年份跨度对运营无决策价值；标签/人物总数在「标签项目」页签已有，概览页重复。
     ② 流量与热度去掉「零点击条目」「条目均点击」——两者都是纯派生值：
        零点击 = 正式条目 − 有点击条目；均点击 = 点击总量 ÷ 正式条目，看前两项即可算出。
     ③ 「近 7/30 天新增」口径修正：此前后端 COUNT 没带 status 过滤，
        暂存（pending）条目也计进「新增」，导致新增数可以大于正式条目数（现网 10605 vs 683）。
        后端已改为只统计 active，与「正式条目」同口径。 */
function statCell(v, label){
  return '<div class="stat"><b>'+esc(String(v))+'</b><span>'+esc(label)+'</span></div>';
}
function renderOverviewStats(s){
  const num = (n) => (Number(n)||0).toLocaleString('en-US');
  const sgn = (n) => (Number(n)>0 ? '+'+num(n) : num(n));

  // ① 条目总览（只留能直接支撑决策的 5 项）
  let m='';
  m += statCell(num(s.total),'正式条目');
  m += statCell(num(s.pending),'暂存待刮削');
  m += statCell(sgn(s.week_new),'近 7 天新增');
  m += statCell(sgn(s.month_new),'近 30 天新增');
  m += statCell((Number(s.avg_rating)||0).toFixed(1),'平均评分');
  document.getElementById('ovStatsMain').innerHTML=m;

  // ② 数据完整度（缺失数越低越好，附占比提示）
  const tot = Math.max(1, Number(s.total)||0);
  const pct = (n) => Math.round((Number(n)||0)/tot*100);
  let q='';
  q += statCell(num(s.no_poster)+'（'+pct(s.no_poster)+'%）','缺海报');
  q += statCell(num(s.no_overview)+'（'+pct(s.no_overview)+'%）','缺简介');
  q += statCell(num(s.no_rating)+'（'+pct(s.no_rating)+'%）','缺评分');
  q += statCell(num(s.no_genres)+'（'+pct(s.no_genres)+'%）','缺标签');
  q += statCell(num(s.no_download)+'（'+pct(s.no_download)+'%）','未匹配网盘');
  document.getElementById('ovStatsQuality').innerHTML=q;

  // ③ 流量与热度（去掉两个派生值，只留原始计数）
  let t='';
  t += statCell(num(s.total_clicks),'点击总量');
  t += statCell(num(s.clicked),'有点击条目');
  t += statCell(num(s.total_calls),'API 调用总量');
  document.getElementById('ovStatsTraffic').innerHTML=t;
}
/* ================================================================
 * ★ v4.9.7 自建访问统计（替代第三方统计代码）
 *
 * 口径（与 CacheStore::visitStats 一致，改动时两边要同步）：
 *   PV = 页面浏览次数；UV = 当天去重后的访客数（同一人当天反复浏览只算 1）
 *   设备/渠道来自 page_views_daily 的按天分类计数；热门页/入口页来自近 7 天明细
 * ================================================================ */
const VISIT_SRC_LABEL  = {direct:'直接访问', search:'搜索引擎', social:'社交/分享', cross:'外部链接'};
const VISIT_DEVICE_LABEL= {mobile:'手机', desktop:'电脑', tablet:'平板', bot:'爬虫', unknown:'未知'};
async function loadVisitStats(){
  const ids=['vsMain','vsTrend','vsSrc','vsDevice','vsTopPaths','vsEntry'];
  ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">统计加载中…</div>'; });
  const d=await api('/api/v1/admin/visit_stats?days=30');
  if(!d||!d.success){ ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">访问统计加载失败</div>'; }); return; }
  const s=d.data||{};
  /* 表还没建出来（老站点首次部署、DB 用户无建表权限）⇒ 明确提示，而不是显示一堆 0 */
  if(s.table_ok===false){
    ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">访问统计表未创建：请到宝塔 → 数据库 → 该库用户 → 勾选「所有权限」后刷新本页</div>'; });
    return;
  }
  try{ renderVisitStats(s); }
  catch(e){ ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">访问统计数据异常</div>'; }); }
}
function renderVisitStats(s){
  const y=s.yesterday||{pv:0,uv:0}, t=s.today||{pv:0,uv:0};
  const delta=(a,b)=> (b>0 ? (a>b?'+'+Math.round((a-b)/b*100):(a<b?Math.round((a-b)/b*100)+'%':'0%'))
                                : (a>0?'（昨日无数据）':'0%'));
  const set=(id,html)=>{ const el=document.getElementById(id); if(el) el.innerHTML=html; };

  set('vsMain', statGrid([
    ['今日 PV', t.pv, delta(t.pv,y.pv)+' 对比昨日'],
    ['今日 UV', t.uv, delta(t.uv,y.uv)+' 对比昨日'],
    ['30 天 PV', s.total_pv||0, '全部页面浏览次数'],
    ['30 天 UV', s.total_uv||0, '按天去重后累加'],
    ['当前在线', s.online||0, '近 5 分钟独立访客'],
  ]));

  /* 趋势：用纯 CSS 柱状图，不引图表库（后台已在用 QRCode 等外链脚本，不再加负担） */
  const tr=s.trend||[];
  const max=Math.max.apply(null, tr.map(x=>x.pv||0).concat([1]));
  set('vsTrend', '<div class="vs-trend">' + tr.map(x=>{
    const h=Math.round((x.pv||0)/max*100);
    const du=Math.round((x.uv||0)/max*100);
    const tip=x.day+'　PV '+(x.pv||0)+'　UV '+(x.uv||0);
    return '<div class="vs-bar" title="'+tip+'"><i style="height:'+h+'%"></i><b style="height:'+du+'%"></b></div>';
  }).join('') + '</div><div class="hint" style="margin-top:6px">深色=PV，浅色=UV；最后一根是今天。鼠标悬停看具体数字。</div>');

  set('vsSrc', barList(s.src, VISIT_SRC_LABEL));
  const dev=Object.assign({}, s.device||{});
  const os =Object.assign({}, s.os||{});
  set('vsDevice', barList(dev, VISIT_DEVICE_LABEL) +
      (Object.keys(os).length ? '<div style="height:10px"></div>' + barList(os, {}, '系统') : ''));

  set('vsTopPaths', pathList(s.top_paths, ['pv','uv']));
  set('vsEntry',    pathList(s.entry,   ['c']));

  /* ★ v5.0.7：全 0 时别只显示一堆「暂无数据」——那等于让站长自己猜。
     直接给一张排查清单，并指路到自检页第 12 组（逐环实测哪一环断了）。 */
  if (!(s.total_pv||0) && !(t.pv||0) && !(s.online||0)) {
    const tip = '<div class="vs-help">'
      + '<b>统计一条都没记上？按这个顺序查：</b>'
      + '<ol>'
      + '<li>先自己访问一次前台首页，等 10 秒后刷新本页（若还是 0，看第 2 条）</li>'
      + '<li>到「站点设置 → 环境自检」看 <b>第 12 组「访问统计埋点」</b>，'
        + '那一组会逐环实测：脚本文件在不在、静态页有没有埋点、/track 通不通、表能不能写</li>'
      + '<li>如果第 12 组全绿但这里仍是 0，多半是前面挂了 CDN / WAF 把 <code>/track</code> 请求拦了</li>'
      + '<li>★ 换过包之后没重生静态页的话，详情页永远统计不到（静态页是预生成的老文件）</li>'
      + '</ol></div>';
    ['vsSrc','vsDevice','vsTopPaths','vsEntry'].forEach(id=>{
      const el=document.getElementById(id);
      if(el && el.innerHTML.indexOf('top-empty')>=0){ el.innerHTML = tip; }
    });
  }
}
function statGrid(rows){
  return rows.map(r=>'<div class="stat"><b>'+esc(String(r[1]))+'</b><span>'+esc(String(r[0]))+'</span>'
    + (r[2]?'<em>'+esc(String(r[2]))+'</em>':'')+'</div>').join('');
}
/* 计数条：label + 条形 + 数值 */
function barList(map, labels, title){
  const keys=Object.keys(map||{});
  if(!keys.length) return '<div class="top-empty">暂无数据</div>';
  const max=Math.max.apply(null, keys.map(k=>map[k]).concat([1]));
  let h = title ? '<div class="hint" style="margin:0 0 6px;font-size:12.5px">'+esc(title)+'</div>' : '';
  h += keys.map(k=>{
    const lb = labels[k] || k;
    const w = Math.round(map[k]/max*100);
    return '<div class="vs-row"><span class="vs-lb">'+esc(lb)+'</span>'
         + '<span class="vs-track"><i style="width:'+w+'%"></i></span>'
         + '<span class="vs-num">'+esc(String(map[k]))+'</span></div>';
  }).join('');
  return h;
}
function pathList(rows, keys){
  if(!rows||!rows.length) return '<div class="top-empty">暂无数据</div>';
  return '<div class="vs-list">' + rows.map(r=>{
    const pv = (r.pv!==undefined? r.pv : r.c);
    const uv = (r.uv!==undefined? '　UV '+r.uv : '');
    return '<div class="vs-item"><span class="vs-path">'+esc(String(r.path||''))+'</span>'
         + '<span class="vs-num">'+esc(String(pv))+uv+'</span></div>';
  }).join('') + '</div>';
}

async function loadOverviewStats(){
  const ids=['ovStatsMain','ovStatsQuality','ovStatsTraffic'];
  ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">统计加载中…</div>'; });
  const d=await api('/api/v1/admin/overview_stats');
  if(!d||!d.success){ ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">统计加载失败</div>'; }); return; }
  try{ renderOverviewStats(d.data||{}); }
  catch(e){ ids.forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML='<div class="top-empty">统计数据异常</div>'; }); }
}

