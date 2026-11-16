/* ==========================================================================
   万能资料库 · 后台管理 JS · 站点设置（分组渲染 / 网盘账号 / SEO）
   由 assets/js/admin.js 按功能拆分而来（原第 1435–1930 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
/* ==================================================================
   站点设置：基础设置 / SEO / 跳转与扫码 / 网盘链接 / 接口配置
   数据全部走 app_settings（与「系统设置」同一张表、同一套白名单），
   键的元信息来自后端 Core\Settings::schema()，前端按 group 分组渲染。
   ================================================================== */
let SITE = null;                 // 设置数据（items + meta）
let SITE_TAB = 'basic';          // 当前二级页
let SITE_DRAFT = {};             // 未保存的输入
let PANSOU_STATE = null;         // 接口连通状态（点「测试连接」后填）

const SITE_GROUPS = {
  basic:  ['site'],
  seo:    ['seo'],
  pan:    ['pan'],
  pansou: ['pansou'],
  req:    ['req'],
  mail:   ['mail']
  /* ★ v5.2.5：「前台显示」页签（data-stab="jump"，group='redirect'）整组取消 ——
     明暗模式 / 榜单样式 / 最新列表 / 顶部外链 全部退场，前台行为改为代码内固定。
     ★ v4.9.5：系统设置（data-stab="sys"）**故意不在这里**。
     它不是「站点字段的一个分组」，而是另一套保存语义（loadSettings/saveSettings，
     提交 tmdb_api_key / adapters / pan_types），且没有对应的 meta.group。
     若把它塞进某个分组，collectSiteDraft() 会用站点字段的取值方式去读它，
     导致 Key / 网盘类型被空值覆盖。 */
};
const SITE_HINTS = {
  basic:  '站点名称、LOGO 与页脚 —— 决定前台顶栏和页脚长什么样。',
  seo:    '首页 / 列表页 / 内页的标题、关键词、描述按这里的模板自动生成；下面是实际效果预览。',
  pan:    '各网盘的账号与转存目录：填好账号（夸克 / UC / 光鸭 / 百度 / 阿里 / 迅雷可扫码登录）后，访客在详情页点「点我去转存」按钮时，系统会把该条目的他人分享当场转存到你的网盘并改写为你自己的分享链接（v4.3.11 起，v5.0.5 扩到 11 网盘适配器框架，v5.0.7 起不再后台预转存，不需要挂转存计划任务）。账号保存后，转存目录可以点「📁 浏览网盘目录」直接到网盘里挑文件夹，也可以手填路径（例：/资源/电影）；留空 = 网盘根目录。',
  pansou: '对接 PanSou 网盘搜索服务：每次采集入库新资源后，自动搜索并补写网盘下载地址。',
  req:    '注册与「资源请求」页的开关：谁能注册、谁能提交、列表是否公开、每人每天能提几条。改完立即生效，前台顶栏与页面文案跟着变。',
  mail:   '找回密码的发信服务（SMTP）：填邮箱的 SMTP 账号与授权码、打开开关，点「发送测试邮件」验证。QQ / 163 / Gmail 需先在邮箱设置里生成「授权码」当密码用；没配好时「忘记密码」会明确提示，不会静默失败。'
};
const PAN_ACCOUNTS = {
  quark:  {name:'夸克网盘', url:'https://pan.quark.cn/'},
  uc:     {name:'UC网盘',   url:'https://pan.uc.cn/'},
  guangya:{name:'光鸭网盘', url:'https://www.guangyapan.com/'},
  baidu:  {name:'百度网盘', url:'https://pan.baidu.com/'},
  aliyun: {name:'阿里云盘', url:'https://www.alipan.com/'},
  xunlei: {name:'迅雷云盘', url:'https://cloud.xunlei.com/'},
  pan115: {name:'115网盘',  url:'https://115.com/'},
  pan123: {name:'123云盘',  url:'https://www.123pan.com/'},
  tianyi: {name:'天翼云盘', url:'https://cloud.189.cn/'},
  yidong: {name:'移动云盘', url:'https://caiyun.139.com/'}
};
/* ★ v5.0.6：支持「📁 浏览网盘目录」的网盘（后端 getFiles/listChildren 已实现，
   扫码登录或粘贴 Cookie 保存后即可浏览）；123 / 磁力没有目录枚举，保持手填。 */
const PAN_DIR_PICK = {
  quark:1, uc:1, guangya:1, baidu:1, aliyun:1, xunlei:1, tianyi:1, yidong:1
};
/* v3.1.4：支持服务端扫码登录的网盘（协议见 core/PanQr.php）；不在表内的继续用「🔗 获取Cookie」手动粘贴 */
/* v4.2.0 曾按"参考心悦源码"回退为 4 家（移除 xunlei/guangya），实际核对心悦源码 extend/netdisk/pan/ 发现：
   迅雷走 XunleiOAuth（xluser-ssl.xunlei.com OAuth Device Flow，RFC 8628 设备码）；
   光鸭走 guangyaQrcode（account.guangyapan.com/v1/auth/device/code），也是 OAuth Device Flow。
   v4.2.1 把它们从白名单移除是错的——它们有真扫码能力，只是拿 access_token 而不是 Cookie。
   v4.2.2 参考心悦源码真实实现补回扫码登录入口：
     - 扫码成功后，PanQr 返回 access_token JSON 到"Cookie 框"（沿用阿里 aliyunPoll 的 JSON 落 Cookie 框模式）；
     - 用户点底部「保存设置」持久化；后续如需对接网盘 API 直接读这个 JSON 的 access_token。
*/
const PAN_QR = {
  quark:  {app:'夸克 App'},
  uc:     {app:'UC 浏览器 / UC 网盘 App'},
  baidu:  {app:'百度网盘 App'},
  aliyun: {app:'阿里云盘 App'},
  xunlei: {app:'迅雷云盘 App（Android）'},
  guangya:{app:'光鸭网盘 App'}
};

/* ★ v4.9.6：站点设置区有【三个互斥区域】——
     sysBox    系统设置    加载 loadSettings()   保存 saveSettings()
     syncPane  同步采集    加载 loadSync()       保存 saveSyncParams()
     sitePane  其余 6 个页签 加载 renderSite()    保存 saveSite()
   三套加载与保存语义各不相同（SET / SYNC_DRAFT / SITE_DRAFT），
   所以必须互斥、且各自按钮独立。
   ★ 抽成函数而不是在 switchSiteTab 里堆 if：加页签时只改这张表，
     漏一处就会出现「两个区域同时显示」或「点了没反应且不报错」。 */
const SITE_PANES = {
  sys:   {box:'sysBox',   pane:null,        load:()=>loadSettings()},
  sync:  {box:null,       pane:'syncPane',  load:()=>loadSync()},
  _site: {box:null,       pane:'sitePane',  load:()=>renderSite()}
};
function showSitePane(tab){
  const cfg = SITE_PANES[tab] || SITE_PANES._site;
  for(const k in SITE_PANES){
    const c = SITE_PANES[k];
    /* 同组内互斥：带 box 的用 box，带 pane 的用 pane，_site 是兜底组 */
    if(c.box){ const e=document.getElementById(c.box);   if(e) e.style.display = (c===cfg) ? 'block' : 'none'; }
    if(c.pane){ const e=document.getElementById(c.pane); if(e) e.style.display = (c===cfg) ? 'block' : 'none'; }
  }
  cfg.load();
}
function switchSiteTab(tab){
  /* ★ v4.9.5：切走之前先把**站点区**的草稿收进 draft，避免切页丢输入。
     ★ 这里刻意【不】判断来源页是 sys 还是 sync —— 那两页用的是各自的 draft
       （TXT_DRAFT/PAN_DRAFT/AD_DRAFT 与 SYNC_DRAFT），但 collectSiteDraft() 只读
       siteKeys() 的元素，在这两页调用它是「读不到任何元素 ⇒ 空转」，无害；
       反过来若写成「来源页不是 basic 才收」，那么
       「基础设置改了东西 → 点系统设置 → 点回基础设置」这条路径上，
       SITE_TAB 是 'sys'、草稿不会被收 ⇒ 用户在站点设置里改的值被静默丢弃。 */
  collectSiteDraft();
  SITE_TAB = tab;
  document.querySelectorAll('#siteSubtabs .stab').forEach(x=>x.classList.toggle('active', x.dataset.stab===tab));
  showSitePane(tab);
}
function siteKeys(){
  const groups = SITE_GROUPS[SITE_TAB] || [];
  const meta = (SITE && SITE.meta) || {};
  const out = [];
  for(const k in meta){ if(groups.indexOf(meta[k].group||'')>=0) out.push(k); }
  return out;
}
async function loadSite(){
  const box = document.getElementById('siteBox');
  box.innerHTML = '<div class="hint">加载中…</div>';
  const d = await api('/api/v1/admin/settings');
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  SITE = d.data;
  SITE_DRAFT = {};
  renderSite();
  const m = document.getElementById('siteMsg'); m.innerText=''; m.style.color='';
}
// 图片地址：支持绝对网址，也支持上传后的 uisc-assets/xxx
function imgSrc(v){
  v = (v||'').trim();
  if(!v) return '';
  if(/^(https?:)?\/\//i.test(v) || v.charAt(0)==='/') return v;
  if(v.indexOf('uisc-assets/')===0) return '/'+v;
  return '/uisc-assets/'+v;
}
function fieldRow(k, mm, ii){
  const val = (SITE_DRAFT[k]!==undefined) ? SITE_DRAFT[k] : ii.value;
  const ty  = mm.type || 'text';
  let input = '';
  if(ty==='textarea'){
    input = `<textarea id="S_${k}" rows="3">${esc(val)}</textarea>`;
  } else if(ty==='int'){
    const mn = (mm.min!==undefined?` min="${mm.min}"`:''), mx = (mm.max!==undefined?` max="${mm.max}"`:'');
    input = `<input type="number" id="S_${k}" value="${esc(val)}"${mn}${mx}> ${mm.unit?`<span class="hint">${esc(mm.unit)}</span>`:''}`;
  } else if(ty==='select'){
    const opts = mm.options||{}; let s='';
    for(const v in opts){ s += `<option value="${esc(v)}" ${String(val)===String(v)?'selected':''}>${esc(opts[v])}</option>`; }
    input = `<select id="S_${k}">${s}</select>`;
  } else if(ty==='image'){
    const src = imgSrc(val);
    input = `<div class="imgpick">
      <span>${src?`<img src="${esc(src)}" alt="">`:'<span class="ph">无图</span>'}</span>
      <input type="text" id="S_${k}" value="${esc(val)}" placeholder="图片网址，或点右侧上传">
      <button type="button" class="ghost" onclick="pickImage('${k}')">上传</button>
      <input type="file" id="F_${k}" accept="image/*" style="display:none" onchange="uploadImage('${k}',this)">
    </div>`;
  } else {
    /* SMTP 密码 / 授权码按密码框渲染，避免被人瞟到 */
    const pwd = (k === 'mail_pass');
    input = `<input type="${pwd?'password':'text'}" id="S_${k}" value="${esc(val)}"${pwd?' autocomplete="new-password" placeholder="邮箱授权码或登录密码"':''}>`;
  }
  return `<div class="set-sec">
    <h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
    <div class="tip">${esc(mm.tip||'')}</div>
    ${input}
  </div>`;
}
function renderSite(){
  const box = document.getElementById('siteBox');
  if(!SITE){ box.innerHTML='<div class="hint">加载失败</div>'; return; }
  /* ★ v4.9.6：系统设置（sys）与同步采集（sync）已降级为二级页签，
     它们各有独立的加载与保存逻辑，不能走站点设置这套。
     这里必须挡住 —— 否则会掉进下面的 else 通用分支，
     用「站点设置」的字段清单去渲染那两个页面，等于渲染出一堆空卡片。
     ★ 统一交给 showSitePane() 处理三区域互斥，避免这里再写一遍 if。 */
  if(SITE_TAB==='sys' || SITE_TAB==='sync'){
    showSitePane(SITE_TAB);
    return;
  }
  const meta = SITE.meta||{}, items = SITE.items||{};
  let h = `<div class="ext-note">${esc(SITE_HINTS[SITE_TAB]||'')}</div>`;
  if(SITE.table_ok===false){
    h += `<div class="ext-note note-warn">⚠️ 设置表创建失败：数据库用户可能没有建表权限，请到宝塔 → 数据库 → 该库用户 → 勾选「所有权限」。</div>`;
  }

  // 接口配置页顶部：状态栏
  if(SITE_TAB==='pansou'){
    const st = PANSOU_STATE;
    h += `<div class="status-bar">
      <span class="pill">接口状态：<b>${st ? (st.ok?'连通':'异常') : '未测试'}</b></span>
      <span class="pill">频道：<b>${st?st.channels:'—'}</b></span>
      <span class="pill">插件：<b>${st?st.plugins:'—'}</b></span>
      <span class="pill">延迟：<b>${st?(st.ms+' ms'):'—'}</b></span>
      <span class="pill">待确认：<b id="pendCount">—</b></span>
      <button class="ghost" onclick="testPansou(this)">测试连接</button>
      <button class="ghost" onclick="clearPansouCache()">清空搜索缓存</button>
    </div>`;
    if(st && st.message){ h += `<div class="ext-note ${st.ok?'note-ok':'note-err'}">${esc(st.message)}</div>`; }
  }

  // 邮件服务页顶部：发送测试邮件（可用还没保存的草稿值先测）
  if(SITE_TAB==='mail'){
    h += `<div class="status-bar">
      <span class="pill">发信状态：<b id="mailState">未测试</b></span>
      <span class="pill">收件人</span>
      <input type="text" id="mailTestTo" placeholder="留空 = 发给发件人邮箱自己" style="max-width:230px">
      <button class="ghost" onclick="testMail(this)">发送测试邮件</button>
    </div>
    <div id="mailTestOut" style="margin:0 0 12px"></div>`;
  }

  const keys = siteKeys();
  if(SITE_TAB==='pan'){

    if(meta['pan_group']) h += fieldRow('pan_group', meta['pan_group'], items['pan_group']||{});
    h += `<div class="pan-2col">`;
    for(const code in PAN_ACCOUNTS){
      const kc='pan_'+code+'_cookie', kd='pan_'+code+'_dir', kt='pan_'+code+'_tmp';
      if(!meta[kc]) continue;
      const pan = PAN_ACCOUNTS[code];
      const cur = (SITE_DRAFT[kc]!==undefined ? SITE_DRAFT[kc] : ((items[kc]||{}).value||''));
      h += `<div class="pan-card">`;
      h += fieldRow(kc, meta[kc], items[kc]||{});
      h += `<div class="pan-cookie-info">
        <span class="hint" id="pci_${code}">Cookie ${cur?('已填写（'+cur.length+' 字符）'):'未填写'}</span>
        ${PAN_QR[code]?`<button type="button" class="ghost sm" onclick="panQrStart('${code}')">📱 扫码登录</button>`:''}
        <a class="pan-card-link" href="${pan.url}" target="_blank" rel="noopener noreferrer">🔗 获取Cookie</a>
      </div>`;
      h += fieldRow(kd, meta[kd], items[kd]||{});
      if(PAN_DIR_PICK[code]) h += `<div class="pan-dir-pick"><button type="button" class="ghost sm" onclick="panDirPick('${code}','${kd}')">📁 浏览网盘目录</button></div>`;
      h += fieldRow(kt, meta[kt], items[kt]||{});
      if(PAN_DIR_PICK[code]) h += `<div class="pan-dir-pick"><button type="button" class="ghost sm" onclick="panDirPick('${code}','${kt}')">📁 浏览网盘目录</button></div>`;
      h += `</div>`;
    }
    h += `</div>`;
  } else if(SITE_TAB==='seo'){
    const half = Math.ceil(keys.length/2);
    h += `<div class="site-2col">`;
    keys.forEach((k,i)=>{ h += fieldRow(k, meta[k]||{}, items[k]||{}); });
    h += `</div>`;
    h += `<div class="set-sec"><h4>效果预览 <span class="hint">（用站内最新一条数据套模板，点上方「刷新 SEO 预览」重算）</span></h4>
      <div id="seoPreview" class="seo-box">点上方「刷新 SEO 预览」查看实际生成的标题 / 关键词 / 描述 / robots.txt</div></div>`;
  } else {
    const list = keys.filter(k=>((meta[k]||{}).type)!=='list');
    const lists = keys.filter(k=>((meta[k]||{}).type)==='list');
    h += `<div class="site-2col">`;
    list.forEach(k=>{ h += fieldRow(k, meta[k]||{}, items[k]||{}); });
    h += `</div>`;
    lists.forEach(k=>{
      const mm = meta[k]||{}, ii = items[k]||{};
      const cur = (SITE_DRAFT[k]!==undefined) ? SITE_DRAFT[k] : (ii.value||[]);
      const opts = mm.options||{};
      let s='';
      for(const v in opts){
        const on = cur.indexOf(v)>=0;
        s += `<label class="chk"><input type="checkbox" class="sl_${k}" value="${esc(v)}" ${on?'checked':''}>${esc(opts[v])}</label>`;
      }
      h += `<div class="set-sec"><h4>${esc(mm.label||k)} ${flag(ii.override)}</h4>
        <div class="tip">${esc(mm.tip||'')}</div><div class="chks">${s}</div></div>`;
    });
  }
  box.innerHTML = h;
  if(SITE_TAB==='pansou'){
    h += `<div class="set-sec"><h4>立即补地址</h4>
      <div class="tip">对「还没有下载地址」的条目立刻跑一次搜索补写。根据「写入策略」决定自动写入还是进待确认队列。</div>
      <div class="inline"><button type="button" onclick="runEnrich()">立即补地址</button></div></div>
    <div class="set-sec"><h4>待确认队列 <span class="hint" id="pendHint"></span></h4>
      <div class="tip">匹配度不够高、按策略暂不自动写入的结果会落到这里。逐条看清楚再「采纳」，比全自动安全。</div>
      <div id="pendingBox"></div>
      <div class="inline"><button type="button" class="ghost" onclick="loadPanSouPending()">刷新队列</button></div></div>`;
    loadPanSouPendingCount();
  }
}
function collectSiteDraft(){
  if(!SITE) return;
  const meta = SITE.meta||{};
  siteKeys().forEach(k=>{
    const mm = meta[k]||{};
    if(mm.type==='list'){
      const arr=[];
      document.querySelectorAll('.sl_'+k+':checked').forEach(x=>arr.push(x.value));
      SITE_DRAFT[k]=arr;
      return;
    }
    const el = document.getElementById('S_'+k);
    if(el) SITE_DRAFT[k]=el.value;
  });
}
async function saveSite(){
  const msg = document.getElementById('siteMsg');
  collectSiteDraft();
  if(!Object.keys(SITE_DRAFT).length){ msg.style.color='var(--mut)'; msg.innerText='没有需要保存的项'; return; }
  // 单独保存一个网盘的三个字段时，其它网盘的值也要带上，避免被清空
  const body = {};
  for(const k in SITE_DRAFT) body[k] = SITE_DRAFT[k];
  msg.style.color='var(--mut)'; msg.innerText='保存中…';
  const d = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(body)});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'保存失败'; return; }
  SITE.items = d.data.items || SITE.items;
  SITE_DRAFT = {};
  msg.style.color='var(--ok)'; msg.innerText='✓ 已保存并生效';
  renderSite();
}
async function resetSite(){
  const keys = siteKeys();
  if(!keys.length) return;
  if(!confirm('把「'+SITE_TAB+'」这一页的设置恢复为 config/config.php 里的值？（不影响条目数据）')) return;
  const msg = document.getElementById('siteMsg');
  msg.style.color='var(--mut)'; msg.innerText='恢复中…';
  for(const k of keys){
    const d = await api('/api/v1/admin/settings_reset',{method:'POST',body:JSON.stringify({key:k})});
    if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'恢复失败'; return; }
    SITE.items = d.data.items || SITE.items;
  }
  SITE_DRAFT = {};
  msg.style.color='var(--ok)'; msg.innerText='✓ 已恢复为 config.php 里的值';
  renderSite();
}
/* ---- 邮件服务：发送测试邮件（先测再存：草稿值一起提交） ---- */
async function testMail(btn){
  collectSiteDraft();
  const body = { to:((document.getElementById('mailTestTo')||{}).value||'').trim() };
  ['mail_enable','mail_host','mail_port','mail_secure','mail_user','mail_pass','mail_from','mail_from_name']
    .forEach(k=>{ if(SITE_DRAFT[k]!==undefined) body[k]=SITE_DRAFT[k]; });
  const st  = document.getElementById('mailState');
  const out = document.getElementById('mailTestOut');
  await busy(btn,'发送中…', async()=>{
    if(st) st.innerText='发送中…';
    if(out) out.innerHTML='<div class="ext-note">正在连接 SMTP 服务器，最长等 1 分钟…</div>';
    const d = await api('/api/v1/admin/mail_test',{method:'POST',body:JSON.stringify(body)});
    if(!d.success){
      if(st) st.innerText='失败';
      if(out) out.innerHTML='<div class="ext-note note-err">'+esc(d.error||'测试失败')+'</div>';
      return;
    }
    const r = d.data||{};
    if(st) st.innerText = r.ok ? '连通 ✓' : '失败';
    let h = '';
    if(r.msg) h += '<div class="ext-note '+(r.ok?'note-ok':'note-err')+'">'+esc(r.msg)+'</div>';
    if(!r.ok && r.log) h += '<div class="ext-note">SMTP 会话日志（排查用）：<pre style="white-space:pre-wrap;margin:4px 0 0;font-size:12px">'+esc(r.log)+'</pre></div>';
    if(out) out.innerHTML = h;
  });
}
function pickImage(k){ const f=document.getElementById('F_'+k); if(f) f.click(); }
async function uploadImage(k, inp){
  const file = inp.files && inp.files[0];
  if(!file) return;
  const msg = document.getElementById('siteMsg');
  if(file.size > 2*1024*1024){ msg.style.color='var(--dead)'; msg.innerText='图片太大了，请压到 2MB 以内'; inp.value=''; return; }
  msg.style.color='var(--mut)'; msg.innerText='上传中…';
  const fd = new FormData(); fd.append('kind', k); fd.append('file', file);
  let d;
  try{
    const r = await fetch(API+'/api/v1/admin/site_upload',{method:'POST',credentials:'same-origin',body:fd});
    const txt = await r.text();
    try{ d = JSON.parse(txt); }catch(e){ d = {success:false, error:'服务器返回了非 JSON（HTTP '+r.status+'）：'+String(txt).slice(0,160)}; }
    if(d && d.success===undefined) d.success = (r.status>=200 && r.status<300);
  }catch(e){ d = {success:false, error:'网络请求失败：'+((e&&e.message)?e.message:e)}; }
  inp.value = '';
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'上传失败'; return; }
  collectSiteDraft();
  SITE_DRAFT[k] = d.data.path;
  msg.style.color='var(--ok)'; msg.innerText='✓ 上传成功，别忘了点「保存设置」';
  renderSite();
}
/* ---- 接口配置：测试 / 试搜 / 补地址 / 缓存 ---- */
/**
 * 测试 PanSou 连通。
 * 之前"点了没反应"的两个原因：① siteMsg 元素在某些渲染路径下取不到，赋值直接抛错、
 * 函数中断（页面上什么都不显示）；② 探测上游可能要十几秒，期间没有任何进度提示，
 * 用户以为按钮坏了。现在：元素兜底 + 中途进度 + 明确的结果/失败提示 + 按钮忙碌态。
 */
async function testPansou(btn){
  const msg = document.getElementById('siteMsg') || setMsgFallback();
  collectSiteDraft();
  const draftLen = Object.keys(SITE_DRAFT).length;
  if(msg){ msg.style.color='var(--mut)'; msg.innerText = draftLen ? '先保存当前改动…' : '正在连接 PanSou（最长可能等十几秒）…'; }
  await busy(btn, '测试中…', async ()=>{
    if(draftLen){
      const d0 = await api('/api/v1/admin/settings',{method:'POST',body:JSON.stringify(SITE_DRAFT)});
      if(!d0.success){ if(msg){ msg.style.color='var(--dead)'; msg.innerText='先保存再测试：'+(d0.error||''); } return; }
      SITE.items = d0.data.items || SITE.items;
      SITE_DRAFT = {};
      renderSite();
    }
    if(msg){ const m2=document.getElementById('siteMsg'); if(m2){ m2.style.color='var(--mut)'; m2.innerText='正在连接 PanSou（最长可能等十几秒）…'; } }
    const d = await api('/api/v1/admin/pansou_test',{method:'POST',body:'{}'});
    const m3 = document.getElementById('siteMsg') || msg;
    if(!d.success){ if(m3){ m3.style.color='var(--dead)'; m3.innerText='✗ '+(d.error||'测试失败'); } return; }
    PANSOU_STATE = d.data;
    if(m3){
      m3.style.color = d.data.ok ? 'var(--ok)' : 'var(--dead)';
      m3.innerText = (d.data.ok?'✓ ':'✗ ') + (d.data.message||(d.data.ok?'可用':'不可用'));
    }
    renderSite();
  });
}
/** 万一页面上找不到 siteMsg（比如标签页结构变了），临时造一个，保证一定给得出提示 */
function setMsgFallback(){
  let box = document.getElementById('siteBox');
  if(!box) return null;
  let el = document.getElementById('siteMsg');
  if(!el){
    el = document.createElement('div');
    el.id = 'siteMsg'; el.className = 'set-msg';
    box.appendChild(el);
  }
  return el;
}
async function searchPansou(){
  const kw = (document.getElementById('pansouKw')||{}).value;
  if(!kw || !kw.trim()){ alert('先填写要试搜的关键词'); return; }
  const out = document.getElementById('pansouResult');
  if(!out){ alert('试搜区域未就绪，请刷新后台页面后重试。'); return; }
  out.innerHTML = '<div class="hint">搜索中…（上游较慢时可能要十几秒）</div>';
  const d = await api('/api/v1/admin/pansou_search',{method:'POST',body:JSON.stringify({kw:kw.trim()})});
  if(!d.success){ out.innerHTML='<div class="hint" style="color:var(--dead)">✗ '+esc(d.error||'搜索失败')+'</div>'; return; }
  const r = d.data || {};
  const types = r.types || [];
  let h = `<div class="hint">共 ${r.total||0} 条${r.cached?'（来自缓存）':''}</div>`;
  if(!types.length){ h += '<div class="hint">没有搜到结果。可以换个关键词，或到 PanSou 里确认频道 / 插件已加载。</div>'; }
  types.forEach(t=>{
    h += `<div class="set-sec"><h4>${esc(t.label)} <span class="hint">${t.count} 条</span></h4>`;
    (t.top||[]).forEach(x=>{ h += `<div class="u" style="font-size:12px;word-break:break-all;color:var(--mut)">${esc(x.note||'（无标题）')}<br>${esc(x.url)}</div>`; });
    h += `</div>`;
  });
  out.innerHTML = h;
}
async function runEnrich(){
  const msg = document.getElementById('siteMsg');
  if(!confirm('对「还没有下载地址」的条目搜一次网盘并补地址？\n\n按当前写入策略处理：仅高度匹配自动写 / 搜到就写 / 一律进待确认。')) return;
  msg.style.color='var(--mut)'; msg.innerText='正在搜索并补地址，条目多时需要一会儿…';
  const d = await api('/api/v1/admin/enrich_run',{method:'POST',body:JSON.stringify({limit:5})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'补地址失败'; return; }
  const r = d.data;
  msg.style.color='var(--ok)';
  msg.innerText = `✓ 检查 ${r.checked} 条：写入 ${r.written} 条（共 ${r.links} 个链接）、待确认 ${r.queued} 条、无匹配 ${r.nomatch} 条，用时 ${r.ms} ms`;
  loadPanSouPending();
}
async function clearPansouCache(){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pansou_cache_clear',{method:'POST',body:'{}'});
  msg.style.color = d.success?'var(--ok)':'var(--dead)';
  msg.innerText = d.success ? ('✓ 已清空搜索缓存（'+d.data.cleared+' 条）') : (d.error||'操作失败');
}
async function loadPanSouPendingCount(){
  const d = await api('/api/v1/admin/pending_list');
  const el = document.getElementById('pendCount');
  if(el && d.success) el.innerText = d.data.count;
}
async function loadPanSouPending(){
  const box = document.getElementById('pendingBox');
  if(!box) return;
  box.innerHTML = '<div class="hint">加载中…</div>';
  const d = await api('/api/v1/admin/pending_list');
  if(!d.success){ box.innerHTML='<div class="hint" style="color:var(--dead)">'+esc(d.error||'加载失败')+'</div>'; return; }
  const list = d.data.list||[];
  const el = document.getElementById('pendCount'); if(el) el.innerText = d.data.count;
  if(!list.length){ box.innerHTML = '<div class="hint">暂无待确认记录 —— 说明自动补地址这一路很干净。</div>'; return; }
  let h='';
  list.forEach(r=>{
    h += `<div class="pending-row">
      <div class="t"><b>${esc(r.title)}</b> <span class="score">匹配 ${r.score}%</span>
        <div class="u">搜索词：${esc(r.keyword)}　·　${r.links.length} 个网盘链接</div>`;
    r.links.forEach(l=>{ h += `<div class="u">[${esc(l.label)}] ${esc(l.url)}</div>`; });
    h += `</div>
      <div style="display:flex;gap:6px;flex-direction:column">
        <button class="ghost" onclick="applyPanSouPending(${r.id})">采纳</button>
        <button class="ghost" onclick="dropPanSouPending(${r.id})">忽略</button>
      </div></div>`;
  });
  box.innerHTML = h;
}
async function applyPanSouPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_apply',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'采纳失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已写入 '+d.data.added+' 个下载链接（下次生成静态页后前台可见）';
  loadPanSouPending();
}
async function dropPanSouPending(id){
  const msg = document.getElementById('siteMsg');
  const d = await api('/api/v1/admin/pending_drop',{method:'POST',body:JSON.stringify({id})});
  if(!d.success){ msg.style.color='var(--dead)'; msg.innerText=d.error||'操作失败'; return; }
  msg.style.color='var(--ok)'; msg.innerText='✓ 已忽略该条';
  loadPanSouPending();
}
async function loadSeoPreview(){
  const box = document.getElementById('seoPreview');
  if(!box) return;
  box.textContent = '正在生成预览…';
  const d = await api('/api/v1/admin/seo_preview');
  if(!d.success){ box.textContent = d.error||'预览失败'; return; }
  const r = d.data;
  let h = '';
  h += `<em>【首页】</em><br>title：${esc(r.home.title)}<br>keywords：${esc(r.home.keywords||'（未设置）')}<br>description：${esc(r.home.description||'（未设置）')}<br><br>`;
  h += `<em>【列表页 · 电影 + 按热度】</em><br>title：${esc(r.list.title)}<br>keywords：${esc(r.list.keywords||'（未设置）')}<br><br>`;
  h += `<em>【内页 · 拿站内最新一条真实数据】</em><br>title：${esc(r.item.title)}<br>keywords：${esc(r.item.keywords||'（未设置）')}<br>description：${esc(r.item.description||'（未设置）')}<br>canonical：${esc(r.item.canonical||'（未设置网站网址）')}<br><br>`;
  h += `<em>【robots.txt】</em><br>${esc(r.robots).replace(/\n/g,'<br>')}`;
  box.innerHTML = h;
}

