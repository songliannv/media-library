/* ==========================================================================
   万能资料库 · 后台管理 JS · 采集：条目暂存 / 同步采集页
   由 assets/js/admin.js 按功能拆分而来（原第 1002–1324 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
/* ===== 同步采集页（参数 / 官方限制对照 / 计划任务 / 最近记录）===== */
let SYNC_PLAN = null, SYNC_META = null, SYNC_DRAFT = null;

function syncGroupKeys(){
  const keys = [];
  const m = SYNC_META || {};
  for(const k in m){ if(m[k] && m[k].group==='sync'){ keys.push(k); } }
  return keys;
}

/* ============ v3.8.6：条目暂存管理 ============ */
let PENDING_PAGE=1, PENDING_PER=20;
async function loadPending(){
  const q=document.getElementById('pq').value||'';
  const d=await api('/api/v1/admin/pending?page='+PENDING_PAGE+'&per='+PENDING_PER+'&q='+encodeURIComponent(q));
  if(!d.success){ alert(d.error||'加载失败'); return; }
  const items=d.data.items||[];
  const total=d.data.total||0;
  document.getElementById('pendingCount').textContent='共 '+total+' 条暂存';
  document.getElementById('pendingPageInfo').textContent='第 '+PENDING_PAGE+' 页';
  let h='';
  if(!items.length){
    h='<tr><td colspan="6" style="text-align:center;color:var(--hint);padding:30px">暂无暂存条目</td></tr>';
  } else {
    for(const it of items){
      const lb=SCHEMA?(SCHEMA.labels[it.type]||it.type):it.type;
      const src=it.source||'';
      let panType='', panName='—';
      try{
        const ex=JSON.parse(it.extra||'{}');
        panType=ex.pan_type||'';
        panName=ex.pan_name||(panType?(PAN_TYPES[panType]||panType):'—');
        /* v3.9.7：如果 panType 是 99 或 'other'，尝试从 download_url 推断网盘类型 */
        if((panType==99||panType==99||panType=='other'||panName=='其他')&&it.download_url){
          const url=it.download_url.toLowerCase();
          if(url.includes('guangyapan.com')||url.includes('guangya.com')){ panType=12; panName='光鸭'; }
          else if(url.includes('quark.cn')){ panType=1; panName='夸克'; }
          else if(url.includes('baidu.com')){ panType=2; panName='百度'; }
          else if(url.includes('xunlei.com')){ panType=3; panName='迅雷'; }
          else if(url.includes('aliyun.com')||url.includes('alipan.com')){ panType=4; panName='阿里'; }
          else if(url.includes('123pan.com')){ panType=5; panName='123网盘'; }
          else if(url.includes('115.com')||url.includes('115cdn.com')){ panType=6; panName='115'; }
          else if(url.includes('uc.cn')){ panType=7; panName='UC'; }
          else if(url.includes('lanzou')||url.includes('lanzoux')){ panType=8; panName='蓝奏'; }
          else if(url.includes('189.cn')||url.includes('caiyun.139.com')){ panType=10; panName='天翼'; }
          else if(url.includes('magnet:')){ panType=9; panName='磁力'; }
        }
      }catch(e){}
      const srcLabel=(lb?lb+' · ':'')+(src==='manual'?'本地导入':(src==='pansou'?'PanSou':src));
      const t=it.updated_at?it.updated_at.substring(0,16).replace('T',' '):'';
      h+='<tr>'
        +'<td style="width:60px">'+(it.poster?'<img class="thumb" src="'+it.poster+'">':'<div class="thumb-ph">无图</div>')+'</td>'
        +'<td><b>'+esc(it.title||'')+'</b><br><span class="hint">'+esc(it.original_title||'')+'</span></td>'
        +'<td>'+esc(srcLabel)+'</td>'
        +'<td>'+esc(panName)+'</td>'
        +'<td>'+esc(t)+'</td>'
        +'<td style="white-space:nowrap">'
        +'<button class="sm" onclick="promoteItem('+it.id+',this)">✓ 转为正式</button> '
        +'<button class="ghost sm" onclick="delPending('+it.id+',this)">删除</button>'
        +'</td></tr>';
    }
  }
  document.getElementById('pendingRows').innerHTML=h;
}
function pagePending(d){ PENDING_PAGE+=d; if(PENDING_PAGE<1)PENDING_PAGE=1; loadPending(); }
async function promoteItem(id,btn){
  btn.disabled=true;
  const d=await api('/api/v1/admin/pending_promote',{method:'POST',body:JSON.stringify({id:id})});
  if(d.success){ btn.textContent='✓ 已转化'; setTimeout(loadPending,400); }
  else { alert(d.error||'操作失败'); btn.disabled=false; }
}
async function delPending(id,btn){
  if(!confirm('确定删除这条暂存条目？')) return;
  btn.disabled=true;
  const d=await api('/api/v1/admin/pending_delete',{method:'POST',body:JSON.stringify({id:id})});
  if(d.success){ btn.textContent='✓ 已删除'; setTimeout(loadPending,400); }
  else { alert(d.error||'操作失败'); btn.disabled=false; }
}

async function loadSync(){
  await loadSyncPlan();
  await loadSyncRecent();
}
async function loadSyncPlan(){
  const pbox = document.getElementById('syncParams');
  const box  = document.getElementById('syncPlan');
  const cbox = document.getElementById('cronBox');
  const rbox = document.getElementById('syncRecent');
  if(pbox) pbox.innerHTML = '<div class="hint">正在加载…</div>';
  const [plan, set] = await Promise.all([ api('/api/v1/admin/sync_plan'), api('/api/v1/admin/settings') ]);
  if(!plan.success){
    /* ★ v2.1.0 fix：原来只在 pbox 里写错误，box/cbox/rbox 三个卡片永远停在"正在加载…"。
       用户如果没滚到最上面就看不到 pbox 里的报错，只看到三块空白，以为"卡住了"。
       这里把同一个错误文案同时写进所有相关容器，确保无论用户滚动到哪个位置都能看见。 */
    const err = '<div class="hint">⚠ ' + esc(plan.error||'加载失败') + '</div>';
    if(pbox) pbox.innerHTML = err;
    if(box)  box.innerHTML  = err;
    if(cbox) cbox.innerHTML = err;
    if(rbox) rbox.innerHTML = err;
    return;
  }
  SYNC_PLAN = plan.data;
  if(set.success && set.data && set.data.meta){ SYNC_META = set.data.meta; }
  renderSyncParams();
  renderSyncPlanTable();
  renderCronBox();
  /* 顺手把"最近采集记录"也一起填了，免得 loadSync() 里再等一次 */
  loadSyncRecent();
}
function renderSyncParams(){
  const pbox = document.getElementById('syncParams');
  if(!pbox) return;
  const m = SYNC_META || {};
  const keys = syncGroupKeys();
  if(!keys.length){
    pbox.innerHTML = '<div class="hint">未能读到参数定义（请确认站点已上传最新的 core/Settings.php）。</div>';
    return;
  }
  let h = '';
  keys.forEach(k=>{
    const mm = m[k]||{};
    const cur = (SYNC_DRAFT && SYNC_DRAFT[k]!=null) ? SYNC_DRAFT[k] : syncEffective(k, mm);
    let ctrl = '';
    if(mm.type==='select'){
      const opts = mm.options||{};
      ctrl = `<select id="sy_${k}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" onchange="onSyncParamChange()">` +
        Object.keys(opts).map(o=>`<option value="${esc(o)}" ${String(o)===String(cur)?'selected':''}>${esc(opts[o])}</option>`).join('') +
      `</select>`;
    } else {
      ctrl = `<input type="number" id="sy_${k}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" min="${mm.min!=null?mm.min:0}" max="${mm.max!=null?mm.max:''}" value="${esc(cur)}" onchange="onSyncParamChange()" style="width:110px">`
           + (mm.unit?`<span class="hint" style="margin-left:6px">${esc(mm.unit)}</span>`:'');
    }
    h += `<div class="set-sec">
      <h4>${esc(mm.label||k)}</h4>
      <div class="tip">${esc(mm.tip||'')}</div>
      <div class="inline">${ctrl}</div>
    </div>`;
  });
  pbox.innerHTML = h;
}
/** 取某个采集参数的当前生效值（plan.params 用的是 sync 段子键） */
function syncEffective(settingKey, meta){
  const map = {sync_limit:'limit',sync_max_pages:'max_pages',sync_max_requests:'max_requests',
    sync_window:'window',sync_order:'order',sync_min_votes:'min_votes',sync_dedupe:'dedupe',
    sync_skip_same_title:'skip_same_title',sync_interval:'interval',sync_build:'build',
    sync_hourly_enabled:'hourly_enabled',sync_hourly_limit:'hourly_limit'};
  const child = map[settingKey];
  const p = (SYNC_PLAN && SYNC_PLAN.params) ? SYNC_PLAN.params : {};
  if(child && p[child]!=null) return String(p[child]);
  return '';
}
function onSyncParamChange(){ collectSyncDraft(); }
function collectSyncDraft(){
  const d = {};
  syncGroupKeys().forEach(k=>{ const el=document.getElementById('sy_'+k); if(el) d[k]=el.value; });
  SYNC_DRAFT = d;
}
function renderSyncPlanTable(){
  const box = document.getElementById('syncPlan');
  if(!box || !SYNC_PLAN) return;
  const p = SYNC_PLAN.params||{};
  let h = `<div class="hint" style="margin-bottom:8px">
    本次口径：<b>${p.order==='hot'?'只要最热':(p.order==='popular'?'只要最多人看':'最热 + 最多人看（自动去重）')}</b>
    · 时间窗 <b>${p.window==='day'?'今日':'本周'}</b>
    · 每源最多 <b>${p.limit}</b> 条
    · 单次请求总上限 <b>${p.max_requests}</b> 次
    · 实际将发出 <b>${SYNC_PLAN.requests}</b> 次请求
    · 最小间隔 <b>${p.interval}</b> 分钟
    ${SYNC_PLAN.minutes_since==null ? '（尚未采集过）' : ('（距上次 '+SYNC_PLAN.minutes_since+' 分钟）')}
  </div>`;
  h += '<table><thead><tr><th>数据源</th><th>本次要打的接口（随机页码）</th><th>官方限制与我们自设的上限</th><th>本次条数</th><th>请求间隔</th></tr></thead><tbody>';
  (SYNC_PLAN.sources||[]).forEach(s=>{
    const eps = (s.endpoints||[]).map(e=>`<div style="font-family:Consolas,monospace;font-size:12px">${esc(e.path)}</div><div class="hint" style="margin-bottom:4px">${esc(e.label)}</div>`).join('');
    h += `<tr>
      <td><b>${esc(s.label)}</b><div class="hint"><a href="${esc(s.docs)}" target="_blank" rel="noopener" style="color:inherit">接口文档</a></div></td>
      <td>${eps||'<span class="hint">—</span>'}<div class="hint" style="margin-top:4px">★ 页码为<b>随机抽取</b>，每次同步都不同（此处是本次预演抽到的）</div></td>
      <td><div class="hint" style="line-height:1.8">${esc(s.quota)}<br>单页上限：${s.page_size>0?s.page_size:'—'} 条 · 本系统每源上限：<b>${s.limit}</b> 条</div></td>
      <td>${s.limit} 条</td>
      <td>${s.delay_ms} ms / 请求</td>
    </tr>`;
  });
  h += '</tbody></table>';
  h += `<div class="hint" style="margin-top:8px">说明：<b>重复就跳过</b> —— 同源同 ID 已存在直接跳过${p.skip_same_title?('；同名同年（跨源）也跳过'):''}
    ；${p.dedupe==='update'?'已存在的条目会刷新评分/热度字段':'不刷新已存在条目的数据'}。
    低于 <b>${p.min_votes}</b> 票的条目不入库。</div>`;
  box.innerHTML = h;
}
function renderCronBox(){
  const box = document.getElementById('cronBox');
  if(!box || !SYNC_PLAN) return;
  /* ★ v5.2.0：改为展示 cronCommands() 返回的完整清单 —— 只有 2 条必配命令，
     run.php 统一入口按时间槽内部调度 8 个维护任务；老版本这里只给一条 sync_hourly，
     照着配会漏掉全部维护任务。cron_plan 缺失时回退到旧的单条展示。 */
  const p = SYNC_PLAN.cron_plan;
  if(!p || !(p.commands||[]).length){
    const cmd = SYNC_PLAN.cron_cmd || 'php /www/wwwroot/你的站点/cron/run.php --quiet';
    box.innerHTML = `<div class="inline">
      <input id="cronCmd" type="text" readonly value="${esc(cmd)}" style="min-width:420px">
      <button type="button" class="ghost" onclick="copyCron()">复制命令</button>
    </div>`;
    return;
  }
  let h = '<div style="display:flex;flex-direction:column;gap:12px">';
  p.commands.forEach((c, i) => {
    h += `<div>
      <div class="hint" style="margin-bottom:4px"><b>${esc(c.name)}</b> · ${esc(c.freq)}</div>
      <div class="inline">
        <input id="cronCmd${i}" type="text" readonly value="${esc(c.cmd)}" style="min-width:420px">
        <button type="button" class="ghost" onclick="copyCron(${i})">复制命令</button>
      </div>
      <div class="hint" style="margin-top:4px">${esc(c.desc)}</div>
    </div>`;
  });
  h += '</div>';
  h += `<ol class="hint" style="margin:12px 0 0 18px;line-height:2">
    <li>宝塔面板 → 左侧「<b>计划任务</b>」→ 添加任务，任务类型选「<b>Shell 脚本</b>」</li>
    <li>任务名称照上面填（如「媒资库-统一维护入口」），执行周期照上面选，脚本内容填对应的命令</li>
    <li><b>两条都要配</b>，然后各点一次「执行」看输出是否正常</li>
  </ol>`;
  if((p.slots||[]).length){
    h += '<table style="margin-top:12px"><thead><tr><th>时隙</th><th>频率</th><th>作用</th></tr></thead><tbody>';
    p.slots.forEach(s => {
      h += `<tr><td><code>${esc(s.key)}</code></td><td>${esc(s.freq)}</td><td>${esc(s.desc)}</td></tr>`;
    });
    h += '</tbody></table>';
    h += `<div class="hint" style="margin-top:6px">上表由统一入口 <b>run.php</b> 内部自动调度，无需单独配计划任务。`
      + `需要手动补跑某一项：<code>${esc((p.php_bin||'php') + ' -d opcache.enable_cli=0')} 站点目录/cron/run.php --task=时隙名</code>`
      + `（例如 <code>--task=trending</code>）；用 <code>--dry-run</code> 可只预览不执行。</div>`;
  }
  if((p.manual||[]).length){
    h += '<div class="hint" style="margin-top:12px"><b>保持手动 / 按需</b>（不要挂高频计划任务）：';
    p.manual.forEach(m => {
      h += `<div style="margin-top:4px"><code>${esc(m.cmd)}</code> — ${esc(m.desc)}</div>`;
    });
    h += '</div>';
  }
  h += `<div class="hint" style="margin-top:12px">
    命令里的 php 建议用绝对路径（本机当前检测到：<code>${esc(SYNC_PLAN.php_bin||p.php_bin||'php')}</code>），
    这样计划任务不会因为 PATH 不同而找不到命令。<br>
    <code>-d opcache.enable_cli=0</code> 用于彻底关闭 CLI 下的 opcache，避免宝塔日志里反复打印
    "JIT is incompatible with third party extensions" 噪音（与代码无关，是运行方式问题）。
  </div>`;
  box.innerHTML = h;
}
function copyCron(i){
  const el = document.getElementById('cronCmd' + (i === undefined ? '' : i));
  if(!el) return;
  el.removeAttribute('readonly'); el.select(); el.setSelectionRange(0, 9999);
  let ok = false;
  try { ok = document.execCommand('copy'); } catch(e) { ok = false; }
  el.setAttribute('readonly','readonly');
  if(!ok && navigator.clipboard){ navigator.clipboard.writeText(el.value); ok = true; }
  const m = document.getElementById('syncMsg');
  if(m){ m.style.color = ok ? 'var(--ok)' : 'var(--dead)'; m.innerText = ok ? '✓ 命令已复制到剪贴板' : '复制失败，请手动选中复制'; }
}
async function loadSyncRecent(){
  const box = document.getElementById('syncRecent');
  if(!box) return;
  /* ★ v2.1.0 fix：SYNC_PLAN 没准备好（接口失败 / 还没跑完）时，
     原来直接 return 让"最近采集记录"卡在"正在加载…"，现在显式提示。 */
  if(!SYNC_PLAN){ box.innerHTML = '<div class="hint">采集参数加载失败，暂无法显示采集记录（先解决上方参数卡片里的错误）</div>'; return; }
  const rs = SYNC_PLAN.recent || [];
  if(!rs.length){ box.innerHTML = '<div class="hint">还没有采集记录。点上面的「立即采集」或配好计划任务后就会出现在这里。</div>'; return; }
  let h = '<table><thead><tr><th>时间</th><th>触发方式</th><th>新增</th><th>结果</th><th>各源</th></tr></thead><tbody>';
  rs.forEach(r=>{
    const srcs = (r.sources||[]).map(s=>`${esc(s.source)}:${s.inserted}/跳过${s.skipped_dup}`).join(' · ');
    const kind = r.task==='sync:hourly' ? '计划任务' : (r.task==='sync:manual' ? '后台手动' : esc(r.task));
    h += `<tr>
      <td class="hint">${esc(r.created_at)}</td>
      <td>${kind}</td>
      <td>${r.items}</td>
      <td>${r.ok?'<span class="st st-ok">✓</span>':'<span class="st st-dead">✗</span>'}<span class="hint"> ${esc(String(r.reason||'').slice(0,60))}</span></td>
      <td class="hint">${srcs||'—'}</td>
    </tr>`;
  });
  h += '</tbody></table>';
  box.innerHTML = h;
}
async function saveSyncParams(){
  collectSyncDraft();
  const body = Object.assign({}, SYNC_DRAFT||{});
  const msg = document.getElementById('syncMsg');
  msg.style.color = 'var(--mut)'; msg.innerText = '保存中…';
  const d = await api('/api/v1/admin/settings', {method:'POST', body: JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText = d.error||'保存失败'; return; }
  msg.style.color = 'var(--ok)'; msg.innerText = '✓ 已保存，网页采集与每小时计划任务都已按新参数执行';
  SYNC_DRAFT = null;
  await loadSyncPlan();
}
async function resetSyncParams(){
  if(!confirm('把采集参数恢复为默认（每源 20 条 / 今日 / 最热+最多人看 / 最小间隔 55 分钟…）？')) return;
  const body = {};
  syncGroupKeys().forEach(k=>{ body[k] = ''; });
  const msg = document.getElementById('syncMsg');
  const d = await api('/api/v1/admin/settings', {method:'POST', body: JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText = d.error||'操作失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复默认';
  SYNC_DRAFT = null;
  await loadSyncPlan();
}
async function buildStatic(){ const d=await api('/api/v1/admin/build',{method:'POST'}); alert(d.success?('已生成 '+d.data.built+' 个静态页'):(d.error||'失败')); }
/* ★ v4.4.4：一键清除全部缓存（CacheStore 文件缓存 + Cache 多级缓存），数据改动后立即生效 */
async function clearAllCache(btn){
  if(btn){ btn.disabled=true; btn.textContent='清除中…'; }
  const d = await api('/api/v1/admin/cache_clear',{method:'POST'});
  if(btn){ btn.disabled=false; btn.textContent='清除缓存'; }
  if(!d.success){ alert(d.error||'清除失败'); return; }
  alert('已清除 '+d.data.files+' 个文件缓存'+(d.data.multi?'，多级缓存已同步清空':'')+'。各页面数据将在下次加载时自动重建。');
}
/* ★ v4.3.11：网盘自动转存 —— 把条目里「别人的」夸克/UC 分享转存到站长网盘并改写为自己的链接。
   每次最多处理 3 个链接（上游 API 往返多，防超时）；大批量请配宝塔计划任务 cron/pan_transfer.php。 */
async function runPanTransfer(btn){
  if(btn){ btn.disabled=true; btn.textContent='转存中…'; }
  const d = await api('/api/v1/admin/pan_transfer_run',{method:'POST',body:{limit:3}});
  if(btn){ btn.disabled=false; btn.textContent='转存网盘链接'; }
  if(!d.success){ alert(d.error||'转存失败'); return; }
  if(d.data.msg){ alert(d.data.msg); return; }
  const lines=(d.data.details||[]).map(x=>'#'+x.item+' ['+x.pan+'] '+(x.error?('失败：'+x.error):('→ '+x.new)));
  alert('本次转存完成：成功 '+d.data.ok+' · 失败 '+d.data.fail+' · 跳过 '+d.data.skip
    +(lines.length?('\n'+lines.join('\n')):'')
    +'\n（每次最多处理 3 个；剩余的会自动继续，或配计划任务 cron/pan_transfer.php 批量跑）');
  loadItems();
}
