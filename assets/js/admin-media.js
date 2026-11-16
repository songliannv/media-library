/* ==========================================================================
   万能资料库 · 后台管理 JS · 刮削 / 资料补全 / 资源导入
   由 assets/js/admin.js 按功能拆分而来（原第 2559–3081 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
function renderScrapePanel() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="ext-note">💤 就绪 · 选好类型和数量后点「开始刮削」</div>';
  /* ★ v5.0.9：进入页签就把两组统计拉回来（数字点进去看列表的前提） */
  scReloadStats('images');
  scReloadStats('enrich');
  /* ★ v5.2.6：同时把「自动任务（每分钟）」的运行状态拉回来 */
  scLoadAuto();
  /* ★ v5.4.4：同时把「连续采集守护」的运行状态拉回来 */
  scLoadLoop();
}

/* ★ v5.2.6：自动任务运行状态卡片。
   用途：把「自动到底有没有在跑 / 上次干了什么 / 为什么待处理数不动」直接摆在页面上，
   不用去翻宝塔计划任务日志。数据来自 sync_log 里 task='cron:scrape_minutely' 的记录。 */
async function scLoadAuto(){
  const body = document.getElementById('scAutoBody');
  const st   = document.getElementById('scAutoState');
  const dot  = document.getElementById('scAutoDot');
  if(!body) return;
  try{
    const d = await api('/api/v1/admin/item?scrape_auto_status=1');
    if(!d || !d.success){
      if(st) st.textContent = '读取失败';
      body.innerHTML = '<div class="hint">读取失败：' + esc((d && d.error) || '未知错误') + '</div>';
      return;
    }
    const s = d.data || {};
    const lr = s.last_run;
    const cd = s.cooldown || {};
    const pd = s.pending || {};
    let cls = 'gray', head = '未运行', msg = '';
    if(!lr){
      msg = '宝塔里还没有「每分钟」任务，或命令路径不对。<br>命令在「站点设置 → 系统设置 → 计划任务（宝塔面板）」里可整段复制。';
    }else{
      const ph = (lr.phase==='enrich') ? '补资料' : '补图';
      const when = lr.created_at || lr.finished_at || lr.started_at || '';
      const base = '上次 ' + when + ' · ' + ph
                 + (lr.item_id ? (' · 条目 #' + lr.item_id) : '')
                 + (typeof lr.scraped==='number' ? (' · 成功 ' + lr.scraped + ' 条') : '')
                 + (lr.failed ? (' · 失败 ' + lr.failed + ' 条') : '')
                 + (lr.elapsed_ms ? (' · 耗时 ' + (Math.round(lr.elapsed_ms/100)/10) + ' 秒') : '');
      if(lr.status==='done'){ cls='ok'; head='运行正常'; msg=base; }
      else if(lr.status==='busy'){ cls='amber'; head='有其它任务占用'; msg=base + '<br>消息：' + esc(lr.message||''); }
      else if(lr.status==='empty'){ cls='ok'; head='队列已清空'; msg=base; }
      else if(lr.status==='failed'){ cls='amber'; head='上次没补成'; msg=base + '<br>消息：' + esc(lr.message||'（无）'); }
      else { cls='bad'; head='运行出错'; msg=base + '<br>消息：' + esc(lr.message||'（无）'); }
    }
    if(st) st.textContent = head;
    if(dot) dot.className = 'sc-auto-dot ' + cls;
    let h = '<div class="sc-auto-line">' + (msg || '') + '</div>';
    h += '<div class="sc-auto-line">运行次数：近 1 小时 <b>' + (s.runs_1h||0) + '</b> 次 · 近 24 小时 <b>' + (s.runs_24h||0) + '</b> 次'
       + ' · 每分钟处理 <b>' + (s.per_minute||1) + '</b> 条</div>';
    h += '<div class="sc-auto-line">待处理：补图 <b>' + (pd.images||0) + '</b> 条 · 补资料 <b>' + (pd.enrich||0) + '</b> 条'
       + ' · 失败冷却中：补图 <b>' + (cd.images||0) + '</b> 条 · 补资料 <b>' + (cd.enrich||0) + '</b> 条</div>';
    h += '<div class="sc-auto-line">失败冷却规则：同一条失败 <b>' + (s.fail_tries||1) + '</b> 次 → 冷却 <b>' + (s.cooldown_days||7) + '</b> 天让位'
       + '（避免一条查不到上游的条目把整条队列堵死）· TMDB API Key：'
       + (s.tmdb_key ? '<b style="color:var(--ok)">已配置</b>' : '<b style="color:var(--dead)">未配置（补图/补资料会直接报错）</b>') + '</div>';
    body.innerHTML = h;
    /* 回填参数输入框（保存后也走这里刷新） */
    const pm = document.getElementById('scPerMinute');
    const ft = document.getElementById('scFailTries');
    const cd2 = document.getElementById('scCooldownDays');
    if(pm) pm.value = s.per_minute || 1;
    if(ft) ft.value = s.fail_tries || 1;
    if(cd2) cd2.value = s.cooldown_days || 7;
  }catch(e){
    if(st) st.textContent = '读取失败';
    body.innerHTML = '<div class="hint">读取失败：' + esc(String(e && e.message || e)) + '</div>';
  }
}

/** 保存自动任务参数（每分钟条数 / 失败阈值 / 冷却天数） */
async function scSaveAutoCfg(){
  const msg = document.getElementById('scAutoCfgMsg');
  const pm  = parseInt((document.getElementById('scPerMinute')||{}).value||'1',10) || 1;
  const ft  = parseInt((document.getElementById('scFailTries')||{}).value||'1',10) || 1;
  const cd  = parseInt((document.getElementById('scCooldownDays')||{}).value||'7',10) || 7;
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='保存中…'; }
  const d = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify({
    cron_scrape_per_minute: pm, cron_scrape_fail_tries: ft, cron_scrape_cooldown_days: cd
  })});
  if(!d || !d.success){
    if(msg){ msg.style.color='var(--dead)'; msg.innerText=(d&&d.error)||'保存失败'; }
    return;
  }
  if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ 已保存，下次运行即生效'; }
  scLoadAuto();
}

/* ============ ★ v5.4.4 连续采集守护面板 ============ */

/** 拉取连续采集守护状态并渲染（轮次 / 已采集 / 已跳过 / 进度 / 速度 / 启停态） */
async function scLoadLoop(){
  const body = document.getElementById('scLoopBody');
  const st   = document.getElementById('scLoopState');
  const dot  = document.getElementById('scLoopDot');
  if(!body) return;
  try{
    const d = await api('/api/v1/admin/item?scrape_loop_status=1');
    if(!d || !d.success){
      if(st) st.textContent = '读取失败';
      body.innerHTML = '<div class="hint">读取失败：' + esc((d && d.error) || '未知错误') + '</div>';
      return;
    }
    const s = d.data || {};
    const running = !!s.running;
    if(st) st.textContent = running ? '运行中' : '已停止';
    if(dot) dot.className = 'sc-auto-dot ' + (running ? 'ok' : 'gray');
    let h = '';
    h += '<div class="sc-auto-line">状态：<b>' + (running ? '运行中' : '已停止') + '</b>'
       + (s.pid ? (' · 进程 PID <b>' + s.pid + '</b>') : '') + '</div>';
    h += '<div class="sc-auto-line">轮次（来回循环第几遍）：<b>' + (s.round||0) + '</b> 轮'
       + ' · 已采集（确实补到内容）：<b>' + (s.collected||0) + '</b> 条'
       + ' · 已跳过（已齐全）：<b>' + (s.skipped||0) + '</b> 条</div>';
    h += '<div class="sc-auto-line">累计处理：<b>' + (s.processed||0) + '</b> 条 · 当前条目：<b>'
       + (s.current_id||0) + '</b> · 每条间隔 <b>' + (s.delay_ms||0) + '</b> 毫秒</div>';
    h += '<div class="sc-auto-line">本轮开始于：' + esc(s.started_at||'—') + ' · 最近活动：' + esc(s.last_at||'—') + '</div>';
    body.innerHTML = h;
    /* 回填配置开关与速度 */
    const en  = document.getElementById('scLoopEnabled');
    const mn  = document.getElementById('scLoopMinutely');
    const dl  = document.getElementById('scLoopDelay');
    if(en) en.checked = !!s.enabled;
    if(mn) mn.checked = !!s.minutely_enabled;
    if(dl) dl.value = (s.delay_ms!==undefined ? s.delay_ms : 350);
  }catch(e){
    if(st) st.textContent = '读取失败';
    body.innerHTML = '<div class="hint">读取失败：' + esc(String(e && e.message || e)) + '</div>';
  }
}

/** 保存连续采集配置（开关 + 速度） */
async function scSaveLoopCfg(){
  const msg = document.getElementById('scLoopCfgMsg');
  const en  = document.getElementById('scLoopEnabled');
  const mn  = document.getElementById('scLoopMinutely');
  const dl  = document.getElementById('scLoopDelay');
  const enabled = en ? (en.checked ? '1' : '0') : '0';
  const minutely = mn ? (mn.checked ? '1' : '0') : '0';
  const delay = dl ? (parseInt(dl.value,10) || 0) : 350;
  if(msg){ msg.style.color='var(--mut)'; msg.innerText='保存中…'; }
  try{
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({
      scrape_loop_save:true,
      cron_scrape_loop_enabled: enabled,
      cron_scrape_minutely_enabled: minutely,
      cron_scrape_loop_delay_ms: delay
    })});
    if(!d || !d.success){
      if(msg){ msg.style.color='var(--dead)'; msg.innerText=(d&&d.error)||'保存失败'; }
      return;
    }
    if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ 已保存并' + (enabled==='1'?'启动':'停止'); }
    scLoadLoop();
  }catch(e){
    if(msg){ msg.style.color='var(--dead)'; msg.innerText='请求失败：' + esc(String(e && e.message || e)); }
  }
}

/** 立即启动守护（清停止标记 + 保活） */
async function scLoopStart(){
  const msg = document.getElementById('scLoopCfgMsg');
  try{
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({
      scrape_loop_save:true,
      cron_scrape_loop_enabled:'1',
      cron_scrape_loop_delay_ms:(parseInt((document.getElementById('scLoopDelay')||{}).value,10)||350)
    })});
    if(!d || !d.success){ if(msg){ msg.style.color='var(--dead)'; msg.innerText=(d&&d.error)||'启动失败'; } return; }
    if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ 已启动，守护将在数秒内拉起'; }
    scLoadLoop();
  }catch(e){
    if(msg){ msg.style.color='var(--dead)'; msg.innerText='请求失败：' + esc(String(e && e.message || e)); }
  }
}

/** 立即停止守护（写停止标记 + 关闭开关，避免看门狗每分钟重启） */
async function scLoopStop(){
  const msg = document.getElementById('scLoopCfgMsg');
  try{
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({ scrape_loop_stop:true })});
    if(!d || !d.success){ if(msg){ msg.style.color='var(--dead)'; msg.innerText=(d&&d.error)||'停止失败'; } return; }
    if(msg){ msg.style.color='var(--ok)'; msg.innerText='✓ 已停止，守护会在下一轮迭代退出'; }
    scLoadLoop();
  }catch(e){
    if(msg){ msg.style.color='var(--dead)'; msg.innerText='请求失败：' + esc(String(e && e.message || e)); }
  }
}

/* ============ ★ v5.0.9 刮削统计与明细列表 ============ */

/** 拉取并回填某组的「待处理 / 已处理 / 未处理」三个数字 */
async function scReloadStats(kind){
  const ids = kind==='enrich'
    ? ['scEPendNum','scEDoneNum','scEUnpNum']
    : ['scPendNum','scDoneNum','scUnpNum'];
  try{
    const d = await api('/api/v1/admin/item?scrape_stats=1&kind='+encodeURIComponent(kind)
              +'&type='+encodeURIComponent((document.getElementById('scrapeType')||{}).value||''));
    if(!d||!d.success){ ids.forEach(i=>{const e=document.getElementById(i); if(e) e.textContent='-';}); return; }
    const s = d.data||{};
    if(s.table_ok===false){
      ids.forEach(i=>{const e=document.getElementById(i); if(e){ e.textContent='?'; e.title='scrape_logs 表未创建：请到宝塔把该库用户权限改为「所有权限」后刷新'; }});
      return;
    }
    const map = kind==='enrich'
      ? [s.pending, s.done, s.unprocessed]
      : [s.pending, s.done, s.unprocessed];
    ids.forEach((id,i)=>{ const e=document.getElementById(id); if(e){ e.textContent=String(map[i]===undefined?0:map[i]); }});
    /* 把最近一次概况挂在标题上，鼠标悬停可见 */
    const lr = s.last_run;
    const box = document.getElementById(kind==='enrich'?'scStatsEnrich':'scStatsImages');
    if(box && lr){
      box.title = '上次运行 ' + (lr.started_at||'') + ' · 目标 ' + lr.total + ' 条 · 成功 ' + lr.scraped
                + ' · 失败 ' + lr.failed + ' · 耗时 ' + Math.round((lr.duration_ms||0)/1000) + ' 秒（累计 ' + (s.runs||0) + ' 次）';
    }
  }catch(e){
    ids.forEach(i=>{const el=document.getElementById(i); if(el) el.textContent='-';});
  }
}

/** 打开明细列表弹窗（kind=images|enrich，view=pending|done|unprocessed|last） */
async function scList(kind, view){
  const m = document.getElementById('scListModal');
  if(!m) return;
  m.style.display='flex';
  const body = document.getElementById('scListBody');
  const title= document.getElementById('scListTitle');
  const hint = document.getElementById('scListHint');
  const kn = (kind==='enrich') ? '资料补全' : '补图刮削';
  body.innerHTML='<div class="top-empty">加载中…</div>';

  if(view==='last'){
    title.textContent = kn + ' · 最近一次逐条明细';
    hint.textContent = '这是最近一次跑批的逐条结果（成功补了哪些字段 / 失败原因）';
    try{
      const d = await api('/api/v1/admin/item?scrape_detail=1&kind='+encodeURIComponent(kind));
      if(!d||!d.success){ body.innerHTML='<div class="top-empty">加载失败</div>'; return; }
      const s = d.data||{};
      if(!s.ok){ body.innerHTML='<div class="top-empty">'+esc(s.error||'还没有刮削记录')+'</div>'; return; }
      const lg = s.log||{};
      hint.textContent = '开始 ' + (lg.started_at||'') + ' · 目标 ' + lg.target_total + ' · 成功 ' + lg.scraped
        + ' · 失败 ' + lg.failed + ' · 耗时 ' + Math.round((lg.duration_ms||0)/1000) + ' 秒'
        + (lg.fields ? ' · 补字段 ' + esc(lg.fields) : '');
      const arr = s.detail||[];
      if(!arr.length){ body.innerHTML='<div class="top-empty">这次没有逐条明细</div>'; return; }
      body.innerHTML = '<table class="sc-table"><thead><tr><th>#</th><th>标题</th><th>结果</th><th>数据源</th><th>补到的字段 / 原因</th></tr></thead><tbody>'
        + arr.map(r=>'<tr><td>'+esc(String(r.id||''))+'</td>'
          + '<td class="sc-td-title">'+esc(String(r.title||''))+'</td>'
          + '<td>'+(r.ok?'<span class="sc-ok">成功</span>':'<span class="sc-bad">失败</span>')+'</td>'
          + '<td>'+esc(String(r.via||''))+'</td>'
          + '<td>'+esc(r.ok ? ((r.fields&&r.fields.length)?r.fields.join('/'):'（无新增）') : String(r.error||'')) +'</td>'
          + '</tr>').join('') + '</tbody></table>';
    }catch(e){ body.innerHTML='<div class="top-empty">加载失败：'+esc(e.message)+'</div>'; }
    return;
  }

  const VN = {pending:'待处理清单（还没补的那些条目）', done:'已处理清单（字段已齐全）', unprocessed:'未处理清单（本次没补成）'};
  title.textContent = kn + ' · ' + (VN[view]||view);
  hint.textContent = '按当前「类型筛选」统计；每次最多列 200 条';
  try{
    /* 统计卡的三种视图都能由 pending 清单派生：done = total-pending，unprocessed = pending-历史成功数 */
    const d = await api('/api/v1/admin/item?scrape_pending=1&kind='+encodeURIComponent(kind)
              +'&type='+encodeURIComponent((document.getElementById('scrapeType')||{}).value||'')
              +'&limit=200');
    if(!d||!d.success){ body.innerHTML='<div class="top-empty">加载失败</div>'; return; }
    const arr = (d.data&&d.data.list)||[];
    if(view==='done'){
      /* 「已处理」是「字段齐全」的条目 —— 服务端没单独给清单，
         这里明确提示而不是列一份错的清单糊弄站长。 */
      const st = await api('/api/v1/admin/item?scrape_stats=1&kind='+encodeURIComponent(kind)
              +'&type='+encodeURIComponent((document.getElementById('scrapeType')||{}).value||''));
      const n = (st&&st.success&&st.data)?st.data.done:0;
      body.innerHTML='<div class="top-empty">共 <b style="color:var(--pri)">'+n+'</b> 个条目的资料已齐全，无需处理。<br>'
        + '（这一类不单列清单 —— 它们本来就没有缺失项。）</div>';
      return;
    }
    if(view==='unprocessed'){
      const st = await api('/api/v1/admin/item?scrape_stats=1&kind='+encodeURIComponent(kind)
              +'&type='+encodeURIComponent((document.getElementById('scrapeType')||{}).value||''));
      const n = (st&&st.success&&st.data)?(st.data.unprocessed||0):0;
      body.innerHTML='<div class="top-empty">当前待处理里，有 <b style="color:var(--pri)">'+n+'</b> 条<b>还没成功补过</b>。<br>'
        + '下面先列出最近 200 条待处理条目（按 id 倒序，新入库的在最前面）。</div>'
        + '<table class="sc-table"><thead><tr><th>#</th><th>标题</th><th>状态</th><th>来源</th><th>缺什么</th></tr></thead><tbody>'
        + (arr.length ? arr.map(r=>'<tr><td>'+esc(String(r.id))+'</td>'
            +'<td class="sc-td-title">'+esc(String(r.title))+'</td>'
            +'<td>'+esc(r.status)+'</td><td>'+esc(r.source)+'</td>'
            +'<td>'+esc(scMissText(kind,r))+'</td></tr>').join('')
          : '<tr><td colspan="5" style="text-align:center;color:var(--mut)">没有待处理条目，全部齐了 🎉</td></tr>')
        + '</tbody></table>';
      return;
    }
    /* view === 'pending' */
    if(!arr.length){ body.innerHTML='<div class="top-empty">没有待处理条目，全部齐了 🎉</div>'; return; }
    body.innerHTML = '<table class="sc-table"><thead><tr><th>#</th><th>标题</th><th>状态</th><th>来源</th><th>缺什么</th></tr></thead><tbody>'
      + arr.map(r=>'<tr><td>'+esc(String(r.id))+'</td>'
        +'<td class="sc-td-title">'+esc(String(r.title))+'</td>'
        +'<td>'+esc(r.status)+'</td><td>'+esc(r.source)+'</td>'
        +'<td>'+esc(scMissText(kind,r))+'</td></tr>').join('')
      + '</tbody></table>';
  }catch(e){ body.innerHTML='<div class="top-empty">加载失败：'+esc(e.message)+'</div>'; }
}

/** 把「缺哪些字段」翻成人话 */
function scMissText(kind, r){
  const m = [];
  if(kind==='enrich'){
    if(!r.has_overview) m.push('简介');
    if(!r.has_year)     m.push('年份');
  }else{
    if(!r.has_poster)   m.push('海报');
    if(!r.has_backdrop) m.push('背景图');
  }
  return m.length ? m.join('、') : '—';
}

function scListClose(){
  const m = document.getElementById('scListModal');
  if(m) m.style.display='none';
}

/* ============ ★ v5.0.9 资料补全 ============ */

function scSelectedFields(){
  const box = document.getElementById('scFields');
  if(!box) return ['overview','director','cast','year','rating','genres'];
  const out = Array.from(box.querySelectorAll('input[type=checkbox]'))
                 .filter(c=>c.checked).map(c=>c.value);
  return out.length ? out : ['overview'];
}

async function startEnrich(){
  const status = document.getElementById('enrichStatus');
  const log    = document.getElementById('enrichLog');
  const btn    = document.getElementById('enrichGo');
  const type   = document.getElementById('scrapeType').value;
  const limit  = parseInt(document.getElementById('enrichLimit').value) || 50;
  const fields = scSelectedFields();

  btn.disabled = true;
  status.innerHTML = '<div class="ext-note note-warn">⏳ 正在补资料… 本次最多处理 '+limit+' 条，请勿关闭页面</div>';
  log.style.display='block';
  log.innerHTML = '<b>[开始]</b> 类型='+(type||'全部')+' · 数量='+limit+' · 字段='+fields.join('/')+'<br>';

  try{
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({
      auto_enrich:true, type:type, limit:limit, fields:fields, scope:'all'
    })});
    if(!d||!d.success){
      status.innerHTML='<div class="ext-note note-err">✗ 补全失败：'+esc(d&&d.error||'未知错误')+'</div>';
      log.innerHTML += '<b>[失败]</b> '+esc(d&&d.error||'未知错误')+'<br>';
      return;
    }
    const r = d.data||{};
    if(r.error && !r.scraped && !r.failed){
      status.innerHTML='<div class="ext-note note-err">✗ '+esc(r.error)+'</div>';
      log.innerHTML += '<b>[失败]</b> '+esc(r.error)+'<br>';
      return;
    }
    const det = r.detail||[];
    status.innerHTML='<div class="ext-note note-ok">✓ 补全完成！扫描 '+r.total+' 条，成功补全 '+r.scraped+' 条，失败 '+r.failed+' 条</div>';
    log.innerHTML += '<b>[完成]</b> 扫描 '+r.total+' · 成功 '+r.scraped+' · 失败 '+r.failed+' · 跳过 '+r.skipped+'<br>';
    /* 逐条明细进日志，方便站长直接看哪条没补上、为什么 */
    if(det.length){
      log.innerHTML += det.slice(0,60).map(x=> esc(String(x.id))+' 《'+esc(String(x.title||''))+'》 '
        + (x.ok ? ('✓ '+(x.via||'')+' → '+(x.fields||[]).join('/')) : ('✗ '+(x.error||''))) +'<br>').join('');
      if(det.length>60) log.innerHTML += '…… 其余 '+(det.length-60)+' 条已存进历史，点上方「未处理」卡可查看<br>';
    }
    scReloadStats('enrich');
  }catch(e){
    status.innerHTML='<div class="ext-note note-err">✗ 请求失败：'+esc(e.message)+'</div>';
    log.innerHTML += '<b>[错误]</b> '+esc(e.message)+'<br>';
  }finally{
    btn.disabled=false;
  }
}

async function startScrape() {
  const status = document.getElementById('scrapeStatus');
  const log = document.getElementById('scrapeLog');
  const btn = document.getElementById('scrapeGo');
  const type = document.getElementById('scrapeType').value;
  const limit = parseInt(document.getElementById('scrapeLimit').value) || 50;

  btn.disabled = true;
  status.innerHTML = '<div class="ext-note note-warn">⏳ 正在刮削… 本次处理 ' + limit + ' 条，请勿关闭页面</div>';
  log.style.display = 'block';
  log.innerHTML = '<b>[开始]</b> 类型=' + (type || '全部') + ' · 数量=' + limit + '<br>';

  try {
    /* ★ v5.0.9：scope:'all' —— 按用户要求全部条目都算待处理（含以前上架的老条目） */
    const d = await api('/api/v1/admin/item', {method:'POST', body:JSON.stringify({auto_scrape:true, type:type, limit:limit, scope:'all'})});
    if (!d.success) {
      status.innerHTML = '<div class="ext-note note-err">✗ 刮削失败：' + esc(d.error || '未知错误') + '</div>';
      log.innerHTML += '<b>[失败]</b> ' + esc(d.error || '未知错误') + '<br>';
      return;
    }
    /* v3.9.5：TMDB API Key 未配置时，后端返回 data.error */
    if (d.data.error) {
      status.innerHTML = '<div class="ext-note note-err">✗ ' + esc(d.data.error) + '</div>';
      log.innerHTML += '<b>[失败]</b> ' + esc(d.data.error) + '<br>';
      return;
    }

    const scraped = d.data.scraped || 0;
    const failed = d.data.failed || 0;
    const total = d.data.total || (scraped + failed);
    /* ★ v3.5.7：把最近一次失败数量回填到面板统计框（0 灰 / >0 红） */
    const fe = document.getElementById('scrapeFailed');
    if (fe) { fe.textContent = String(failed); fe.className = 'scrape-stat ' + (failed > 0 ? 'bad' : 'zero'); }
    let msg = '✓ 刮削完成！处理 ' + total + ' 条，成功补充 ' + scraped + ' 张图片，失败 ' + failed + ' 条';
    /* v3.9.5：显示失败原因详情（最多 5 条） */
    if (failed > 0 && d.data.errors && d.data.errors.length) {
      msg += '，最近失败原因：' + d.data.errors.join('；');
    }
    status.innerHTML = '<div class="ext-note note-ok">' + msg + '</div>';
    log.innerHTML += '<b>[完成]</b> 处理 ' + total + ' 条，成功 ' + scraped + ' 条，失败 ' + failed + ' 条<br>';
    if (failed > 0 && d.data.errors && d.data.errors.length) {
      log.innerHTML += '<b>[失败详情]</b> ' + d.data.errors.join('；') + '<br>';
    }

    if (document.getElementById('tab-items').style.display !== 'none') {
      loadItems();
    }
    /* ★ v5.0.9：跑完刷新统计数字，并给日志加一个「看逐条明细」的入口 */
    scReloadStats('images');
    log.innerHTML += '<b>[明细]</b> <a href="javascript:scList(\'images\',\'last\')">点此查看本次逐条明细</a><br>';
  } catch (e) {
    status.innerHTML = '<div class="ext-note note-err">✗ 请求失败：' + esc(e.message) + '</div>';
    log.innerHTML += '<b>[错误]</b> ' + esc(e.message) + '<br>';
  } finally {
    btn.disabled = false;
  }
}

function clearScrapeResult() {
  document.getElementById('scrapeStatus').innerHTML = '<div class="ext-note">💤 就绪</div>';
  const log = document.getElementById('scrapeLog');
  log.style.display = 'none';
  log.innerHTML = '';
  /* ★ v3.5.7：清除结果时失败数量统计一并归零 */
  const fe = document.getElementById('scrapeFailed');
  if (fe) { fe.textContent = '0'; fe.className = 'scrape-stat zero'; }
}

/* ============ 资源导入（v3.1.2） ============ */
let LP_ITEMS = [], LE_ITEMS = [];
/* v3.2.4：预览筛选状态（panType / matched / status） */
let LP_FILTER = {pan:'', matched:'', status:''};
let LE_FILTER = {pan:'', matched:'', status:''};
/* v3.2.4：链接检测结果缓存 url => {ok,msg,pan} */
let LP_STATUS = {}, LE_STATUS = {};
const LOCAL_FALLBACK_TYPES = {movie:'电影',tv:'电视剧',anime:'动漫',short:'短剧',variety:'综艺',game:'游戏',book:'书籍',music:'音乐',other:'其他'};
function localTypeOptions(sel){
  sel = sel || document.getElementById('lpType');
  if(!sel) return;
  sel.innerHTML = '';
  const labels = (SCHEMA && SCHEMA.labels) ? SCHEMA.labels : LOCAL_FALLBACK_TYPES;
  for(const k in labels){
    const o=document.createElement('option'); o.value=k; o.textContent=labels[k]; sel.appendChild(o);
  }
}
function loadLocal(){
  localTypeOptions(document.getElementById('lpType'));
  localTypeOptions(document.getElementById('lmType'));
  localTypeOptions(document.getElementById('leType'));
}
function switchLocalTab(name){
  document.querySelectorAll('#localSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.stab===name));
  const titles = {paste:'📋 粘贴解析', manual:'✏️ 手动输入', excel:'📊 Excel 导入'};
  document.getElementById('localModalTitle').textContent = titles[name] || '资源导入';
  document.getElementById('local-paste').style.display  = (name==='paste')  ? 'block' : 'none';
  document.getElementById('local-manual').style.display = (name==='manual') ? 'block' : 'none';
  document.getElementById('local-excel').style.display  = (name==='excel')  ? 'block' : 'none';
  document.getElementById('localOverlay').style.display = 'flex';
}
function closeLocalModal(){
  document.getElementById('localOverlay').style.display = 'none';
}
function renderLocalPreview(resultId, countId, rowId, items, holder, filterId){
  const box = document.getElementById(resultId);
  const row = document.getElementById(rowId);
  const frow = document.getElementById(filterId);
  const F = (holder === 'LP_ITEMS') ? LP_FILTER : LE_FILTER;
  const S = (holder === 'LP_ITEMS') ? LP_STATUS  : LE_STATUS;
  if(!items.length){
    box.innerHTML = '<div class="hint">未识别到资源</div>';
    row.style.display='none';
    if(frow) frow.style.display='none';
    return;
  }
  // 记录原始索引，筛选后删除时仍能定位正确位置
  items.forEach((it, i) => { it.__idx = i; });
  // 聚合网盘类型（按 pan_type，无则按 pan_name）
  const pans = {};
  items.forEach(it=>{
    const p = (it.pan_type || it.pan_name || 'other');
    const n = (it.pan_name || '其它');
    pans[p] = pans[p] || {name:n, count:0};
    pans[p].count++;
  });
  // 状态计数
  const cAll = items.length;
  let cUsed=0, cFresh=0, cOk=0, cDead=0, cUn=0;
  items.forEach(it=>{
    if(it.matched) cUsed++; else cFresh++;
    const s = S[it.url||''];
    if(s && s.ok===true) cOk++;
    else if(s && s.ok===false) cDead++;
    else cUn++;
  });
  // 渲染筛选栏
  if(frow){
    // 小工具：生成 onclick 里的 JSON 片段，双引号转成 &quot; 以免破坏 HTML 属性
    const q = JSON.stringify;
    const escQ = s => s.replace(/"/g,'&quot;');
    const patch = p => escQ(q(p));
    let fh = '<div class="fg">📄 网盘：'
      + '<button class="'+(F.pan===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({pan:''})+')">全部<span class="cnt">'+cAll+'</span></button>';
    Object.keys(pans).forEach(k=>{
      const n = pans[k].name || '其它';
      fh += '<button class="'+(F.pan===k?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({pan:k})+')">'+esc(n)+'<span class="cnt">'+pans[k].count+'</span></button>';
    });
    fh += '</div><div class="fg">📌 匹配：'
      + '<button class="'+(F.matched===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:''})+')">全部<span class="cnt">'+cAll+'</span></button>'
      + '<button class="'+(F.matched==='yes'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:'yes'})+')">未用过<span class="cnt">'+cFresh+'</span></button>'
      + '<button class="'+(F.matched==='no'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({matched:'no'})+')">已在用<span class="cnt">'+cUsed+'</span></button>'
      + '</div><div class="fg">🔎 失效：'
      + '<button class="'+(F.status===''?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:''})+')">全部<span class="cnt">'+cAll+'</span></button>'
      + '<button class="'+(F.status==='ok'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'ok'})+')">有效<span class="cnt">'+cOk+'</span></button>'
      + '<button class="'+(F.status==='dead'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'dead'})+')">失效<span class="cnt">'+cDead+'</span></button>'
      + '<button class="'+(F.status==='un'?'active':'')+'" onclick="setLocalFilter(\''+holder+'\','+patch({status:'un'})+')">未检测<span class="cnt">'+cUn+'</span></button>'
      + '</div>';
    frow.innerHTML = fh;
    frow.style.display='flex';
  }
  // 按筛选过滤
  const filtered = items.filter(it=>{
    if(F.pan && (it.pan_type || it.pan_name || 'other') !== F.pan) return false;
    if(F.matched==='yes' && it.matched) return false;
    if(F.matched==='no' && !it.matched) return false;
    if(F.status){
      const s = S[it.url||''];
      if(F.status==='ok' && !(s && s.ok===true)) return false;
      if(F.status==='dead' && !(s && s.ok===false)) return false;
      if(F.status==='un' && s) return false;
    }
    return true;
  });
  // 渲染表格（v3.2.9：改为卡片列表，只显示资源名称 + 是否已匹配）
  let h = '<div class="lpv-title">✅ 识别结果 · 共 <b>'+filtered.length+'</b> 条（筛选前 '+items.length+' 条）</div>';
  if(!filtered.length){
    h += '<div class="hint" style="padding:14px 0;text-align:center">当前筛选条件下没有资源，换一个筛选试试。</div>';
  } else {
    h += '<div class="lpv-cards">';
    let n=0;
    filtered.forEach(it=>{
      n++;
      const usedClass = it.matched ? 'used' : 'fresh';
      const usedText = it.matched ? '✅ 已在用' : '🆕 未用过';
      h += '<div class="lpv-card">'
        + '<div class="lpv-card-head">'
        + '<span class="lpv-card-idx">'+n+'</span>'
        + '<div class="lpv-card-main">'
        + '<b class="lpv-card-title">'+esc(it.title||'(未命名)')+'</b>'
        + '<span class="lpv-card-url">'+esc(it.url||'')+'</span>'
        + '</div>'
        + '<span class="st-lc '+usedClass+'">'+usedText+'</span>'
        + '<button class="ghost sm" onclick="localRemoveRow(\''+holder+'\','+it.__idx+')">删</button>'
        + '</div>'
        + '</div>';
    });
    h += '</div>';
  }
  box.innerHTML = h;
  document.getElementById(countId).textContent = filtered.length;
  row.style.display = 'flex';
}
function setLocalFilter(holder, patch){
  const F = (holder === 'LP_ITEMS') ? LP_FILTER : LE_FILTER;
  Object.assign(F, patch);
  const items = (holder === 'LP_ITEMS') ? LP_ITEMS : LE_ITEMS;
  const id = (holder === 'LP_ITEMS')
    ? {r:'lpResult', c:'lpCount', w:'lpImportRow', f:'lpFilterRow'}
    : {r:'leResult', c:'leCount', w:'leImportRow', f:'leFilterRow'};
  renderLocalPreview(id.r, id.c, id.w, items, holder, id.f);
}

/* v3.2.4：批量检测预览列表里的链接是否失效（走 6 家网盘官方分享接口） */
async function localCheckLinks(holder){
  const items = (holder === 'LP_ITEMS') ? LP_ITEMS : LE_ITEMS;
  const S = (holder === 'LP_ITEMS') ? LP_STATUS : LE_STATUS;
  const btnId = (holder === 'LP_ITEMS') ? 'lpCheckBtn' : 'leCheckBtn';
  const id = (holder === 'LP_ITEMS')
    ? {r:'lpResult', c:'lpCount', w:'lpImportRow', f:'lpFilterRow'}
    : {r:'leResult', c:'leCount', w:'leImportRow', f:'leFilterRow'};
  if(!items.length){ alert('没有可检测的资源'); return; }
  const btn = document.getElementById(btnId);
  if(btn){ btn.disabled = true; btn.textContent = '检测中…'; }
  const urls = items.map(it=>it.url||'').filter(u=>u!=='');
  // 单次最多 40 条，分批调用避免超时
  const results = {};
  const BATCH = 20;
  try {
    for(let i=0; i<urls.length; i+=BATCH){
      const batch = urls.slice(i, i+BATCH);
      const d = await api('/api/v1/admin/local_check_links', {method:'POST', body:{urls:batch, limit:batch.length}});
      if(!d.success){ alert(d.error||'检测失败'); break; }
      (d.data.results||[]).forEach(r=>{ results[r.url] = {ok:r.ok, msg:r.msg, pan:r.pan, code:r.code}; });
      if(btn) btn.textContent = '检测中… '+(Math.min(i+BATCH, urls.length))+'/'+urls.length;
    }
  } catch(e){
    alert(e && e.message ? e.message : String(e));
  }
  Object.assign(S, results);
  if(btn){ btn.disabled = false; btn.textContent = '🔎 检测失效'; }
  renderLocalPreview(id.r, id.c, id.w, items, holder, id.f);
  const ok = Object.keys(results).filter(k=>results[k].ok===true).length;
  const dead = Object.keys(results).filter(k=>results[k].ok===false).length;
  alert('检测完成：共 '+Object.keys(results).length+' 条，有效 '+ok+' 条，失效 '+dead+' 条');
}
function localRemoveRow(holder, i){
  if(holder==='LP_ITEMS'){ LP_ITEMS.splice(i,1); renderLocalPreview('lpResult','lpCount','lpImportRow', LP_ITEMS, 'LP_ITEMS', 'lpFilterRow'); }
  else { LE_ITEMS.splice(i,1); renderLocalPreview('leResult','leCount','leImportRow', LE_ITEMS, 'LE_ITEMS', 'leFilterRow'); }
}
async function localParse(){
  const text = document.getElementById('lpText').value.trim();
  if(!text){ alert('请先粘贴分享内容'); return; }
  const d = await busy(null, '解析中…', async ()=> await api('/api/v1/admin/local_parse',{method:'POST',body:{text}}));
  if(!d.success){ alert(d.error); return; }
  LP_ITEMS = d.data.items || [];
  LP_STATUS = {};  // 重新解析后清空旧的检测状态
  LP_FILTER = {pan:'', matched:'', status:''};
  renderLocalPreview('lpResult','lpCount','lpImportRow', LP_ITEMS, 'LP_ITEMS', 'lpFilterRow');
}
function localParseClear(){
  LP_ITEMS=[]; LP_STATUS={}; LP_FILTER={pan:'', matched:'', status:''};
  document.getElementById('lpResult').innerHTML='';
  document.getElementById('lpImportRow').style.display='none';
  const lpfr=document.getElementById('lpFilterRow'); if(lpfr){ lpfr.innerHTML=''; lpfr.style.display='none'; }
  document.getElementById('lpText').value='';
  closeLocalModal();
}
async function localImportParsed(){
  if(!LP_ITEMS.length){ alert('没有可导入的资源'); return; }
  const type = document.getElementById('lpType').value;
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:LP_ITEMS, type}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  localParseClear();
}
async function localManualSubmit(){
  const title = document.getElementById('lmTitle').value.trim();
  const url = document.getElementById('lmUrl').value.trim();
  if(!title || !url){ alert('资源名称和地址必填'); return; }
  const item = {
    title: title,
    url: url,
    type: document.getElementById('lmType').value,
    poster: document.getElementById('lmPoster').value.trim(),
    overview: document.getElementById('lmOverview').value.trim(),
    genres: document.getElementById('lmGenres').value.trim()
  };
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:[item]}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  ['lmTitle','lmUrl','lmPoster','lmOverview','lmGenres'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  closeLocalModal();
}
async function localExcelParse(){
  const fileInput = document.getElementById('leFile');
  if(!fileInput.files || !fileInput.files.length){ alert('请先选择 Excel / CSV 文件'); return; }
  const fd = new FormData();
  fd.append('file', fileInput.files[0]);
  const d = await busy(null, '解析中…', async ()=> await api('/api/v1/admin/local_excel',{method:'POST', body: fd}));
  if(!d.success){ alert(d.error); return; }
  LE_ITEMS = d.data.items || [];
  LE_STATUS = {};  // 重新解析后清空旧的检测状态
  LE_FILTER = {pan:'', matched:'', status:''};
  renderLocalPreview('leResult','leCount','leImportRow', LE_ITEMS, 'LE_ITEMS', 'leFilterRow');
}
function localExcelClear(){
  LE_ITEMS=[]; LE_STATUS={}; LE_FILTER={pan:'', matched:'', status:''};
  document.getElementById('leResult').innerHTML='';
  document.getElementById('leImportRow').style.display='none';
  const lefr=document.getElementById('leFilterRow'); if(lefr){ lefr.innerHTML=''; lefr.style.display='none'; }
  document.getElementById('leFile').value='';
  closeLocalModal();
}
async function localExcelImport(){
  if(!LE_ITEMS.length){ alert('没有可导入的记录'); return; }
  const type = document.getElementById('leType').value;
  const d = await api('/api/v1/admin/local_import',{method:'POST',body:{items:LE_ITEMS, type}});
  if(!d.success){ alert(d.error); return; }
  var msg='导入完成：已添加到条目暂存 '+d.data.imported+' 条，跳过重复 '+d.data.skipped+' 条，失败 '+d.data.failed+' 条';
  if(d.data.errors&&d.data.errors.length){ msg+='\n\n失败详情（前5条）：\n'+d.data.errors.slice(0,5).join('\n'); if(d.data.errors.length>5) msg+='\n…共 '+d.data.errors.length+' 条失败'; }
  alert(msg);
  localExcelClear();
}

/* ---- 网盘扫码登录（v3.1.4）：弹二维码 → 2.5s 轮询 → 成功自动回填 Cookie 框 ---- */
