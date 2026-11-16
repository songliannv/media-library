/* ==========================================================================
   万能资料库 · 后台管理 JS · 网盘：二维码登录 / 目录选择
   由 assets/js/admin.js 按功能拆分而来（原第 3082–3239 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
let PANQR={pan:null,timer:null,state:null,busy:false};
function panQrStatus(t,color){
  const el=document.getElementById('panQrStatus'); if(!el) return;
  el.innerText=t||''; el.style.color=color||'';
}
async function panQrStart(code){
  PANQR.pan=code; PANQR.state=null;
  clearInterval(PANQR.timer); PANQR.timer=null;
  const m=document.getElementById('panQrModal');
  if(!m) return;
  m.style.display='flex';
  document.getElementById('panQrTitle').innerText=((PAN_ACCOUNTS[code]||{}).name||code)+' · 扫码登录';
  document.getElementById('panQrHint').innerText='';
  document.getElementById('panQrImg').innerHTML='<div class="hint">生成二维码中…</div>';
  panQrStatus('');
  const d=await api('/api/v1/admin/pan_qr_create',{method:'POST',body:{pan:code}});
  if(!d.success){
    document.getElementById('panQrImg').innerHTML='';
    panQrStatus('❌ '+(d.error||'发起扫码失败'),'#c0392b');
    return;
  }
  PANQR.state=d.data.state;
  const box=document.getElementById('panQrImg');
  if(d.data.mode==='image'){
    /* 百度：服务端直接返回二维码图片（data URI） */
    box.innerHTML='<div style="background:#fff;padding:10px;border-radius:12px"><img src="'+d.data.content+'" style="display:block;width:200px;height:200px" alt="二维码"></div>';
  } else {
    /* 夸克 / UC / 阿里：本地生成二维码（内容在浏览器里，不经第三方） */
    box.innerHTML='<div id="panQrQr" style="background:#fff;padding:10px;border-radius:12px"></div>';
    try{
      new QRCode(document.getElementById('panQrQr'),{text:d.data.content,width:200,height:200,correctLevel:QRCode.CorrectLevel.M});
    }catch(e){
      box.innerHTML='<div class="hint">二维码生成失败，请用右侧「获取Cookie」手动粘贴</div>';
      return;
    }
  }
  const app=(PAN_QR[code]&&PAN_QR[code].app)||'对应网盘 App';
  document.getElementById('panQrHint').innerText='请用'+app+'的「扫一扫」扫描二维码，然后在手机上确认登录。二维码 5 分钟左右过期。';
  panQrStatus('等待扫码…');
  PANQR.timer=setInterval(panQrTick,2500);
}
async function panQrTick(){
  if(!PANQR.pan||PANQR.busy) return;
  PANQR.busy=true;
  try{
    const d=await api('/api/v1/admin/pan_qr_poll',{method:'POST',body:{pan:PANQR.pan,state:PANQR.state}});
    if(!d.success){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('❌ '+(d.error||'轮询失败'),'#c0392b'); return; }
    const s=d.data.status||'waiting';
    if(s==='waiting'){ panQrStatus('等待扫码…'); }
    else if(s==='scanned'){ panQrStatus('已扫码，请在手机上确认'); }
    else if(s==='expired'){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('二维码已过期，请点「刷新二维码」','#d69e00'); }
    else if(s==='error'){ clearInterval(PANQR.timer); PANQR.timer=null; panQrStatus('❌ '+(d.data.note||'换取凭据失败'),'#c0392b'); }
    else if(s==='confirmed'){
      clearInterval(PANQR.timer); PANQR.timer=null;
      const key='pan_'+PANQR.pan+'_cookie', v=d.data.cookie||'';
      const el=document.getElementById('S_'+key);
      if(el) el.value=v;
      const pci=document.getElementById('pci_'+PANQR.pan);
      if(pci) pci.innerText='Cookie 已填写（'+v.length+' 字符）· 刚扫码获取';
      panQrStatus('✅ 登录成功，已回填到 Cookie 框','#1e9e6a');
      const msg=document.getElementById('siteMsg');
      if(msg){ msg.innerText='扫码成功：「'+((PAN_ACCOUNTS[PANQR.pan]||{}).name||PANQR.pan)+'」凭据已回填，请点页面底部「保存设置」保存。'; msg.style.color='#1e9e6a'; }
      setTimeout(panQrClose,1300);
    }
  } finally { PANQR.busy=false; }
}
function panQrRefresh(){ if(PANQR.pan) panQrStart(PANQR.pan); }
function panQrClose(){
  clearInterval(PANQR.timer); PANQR.timer=null; PANQR.pan=null; PANQR.state=null;
  const m=document.getElementById('panQrModal'); if(m) m.style.display='none';
}

/* ---- 网盘目录选择器（★ v5.0.6）：账号（扫码/粘贴 Cookie）保存后，
   浏览网盘文件夹，选中后把「路径」写进上方的转存目录输入框 ----
   PANDIR 状态：pan=网盘代号 / key=目标设置键（pan_xx_dir / pan_xx_tmp）
   stack=[{id,name}] 当前路径（不含根目录）；id 只是「进去看看」的凭据，
   最终写入输入框的是名字拼出来的路径（/资源/电影），适配器两种形态都认。 */
let PANDIR={pan:null,key:null,stack:[],busy:false};
function panDirClose(){
  const m=document.getElementById('panDirModal'); if(m) m.style.display='none';
  PANDIR.pan=null; PANDIR.key=null; PANDIR.stack=[];
}
async function panDirPick(code,key){
  PANDIR.pan=code; PANDIR.key=key; PANDIR.stack=[]; PANDIR.busy=false;
  const m=document.getElementById('panDirModal'); if(!m) return;
  m.style.display='flex';
  document.getElementById('panDirTitle').innerText=((PAN_ACCOUNTS[code]||{}).name||code)+' · 选择目录';
  document.getElementById('panDirHint').innerText='点文件夹进入下一层；选好后点「选用当前目录」。';
  panDirRenderCrumb();
  await panDirLoad('0');
}
function panDirPath(){ return '/'+PANDIR.stack.map(c=>c.name).join('/'); }
function panDirRenderCrumb(){
  const el=document.getElementById('panDirCrumb'); if(!el) return;
  let h='<a data-i="-1">根目录</a>';
  PANDIR.stack.forEach((c,i)=>{
    h+=' <span class="sep">/</span> '+(i===PANDIR.stack.length-1
      ? '<b>'+esc(c.name)+'</b>'
      : '<a data-i="'+i+'">'+esc(c.name)+'</a>');
  });
  el.innerHTML=h;
  el.querySelectorAll('a[data-i]').forEach(function(a){ a.addEventListener('click',function(){ panDirGoto(parseInt(a.getAttribute('data-i'),10)); }); });
  const cur=document.getElementById('panDirCur');
  if(cur){
    cur.innerHTML='将写入目录：<b>'+esc(PANDIR.stack.length?panDirPath():'（网盘根目录，留空）')+'</b>';
  }
}
function panDirGoto(i){
  PANDIR.stack = (i<0) ? [] : PANDIR.stack.slice(0,i+1);
  const top = PANDIR.stack.length ? PANDIR.stack[PANDIR.stack.length-1].id : '0';
  panDirRenderCrumb();
  panDirLoad(top);
}
async function panDirLoad(fid){
  if(!PANDIR.pan||PANDIR.busy) return;
  PANDIR.busy=true;
  const box=document.getElementById('panDirList');
  if(box) box.innerHTML='<div class="hint" style="padding:18px;text-align:center">加载中…</div>';
  try{
    const d=await api('/api/v1/admin/pan_files',{method:'POST',body:{pan:PANDIR.pan,fid:fid||'0'}});
    if(!d.success){
      if(box) box.innerHTML='<div class="hint" style="padding:18px;text-align:center;color:var(--dead)">'+esc(d.error||'获取目录失败')+'</div>';
      return;
    }
    const list=(d.data&&d.data.list)||[];
    let h='';
    list.forEach(it=>{
      if(!it.dir) return; /* 只列文件夹（文件选了也没意义） */
      const nm=esc(it.name||('（未命名 '+String(it.id).slice(0,8)+'）'));
      h+='<div class="pan-dir-row" data-id="'+esc(String(it.id))+'" data-name="'+esc(it.name||'')+'">'
        +'<span class="ico">📁</span><span class="nm">'+nm+'</span><span class="go">进入 ›</span></div>';
    });
    if(!h) h='<div class="hint" style="padding:18px;text-align:center">这一层没有子文件夹</div>';
    if(box){
      box.innerHTML=h;
      box.querySelectorAll('.pan-dir-row').forEach(r=>r.addEventListener('click',()=>{
        panDirEnter(r.getAttribute('data-id'),r.getAttribute('data-name')||('（未命名 '+String(r.getAttribute('data-id')).slice(0,8)+'）'));
      }));
    }
  }catch(e){
    if(box) box.innerHTML='<div class="hint" style="padding:18px;text-align:center;color:var(--dead)">加载失败：'+esc(e.message||e)+'</div>';
  } finally { PANDIR.busy=false; }
}
function panDirEnter(id,name){
  PANDIR.stack.push({id:id,name:name});
  panDirRenderCrumb();
  panDirLoad(id);
}
function panDirConfirm(){
  if(!PANDIR.key){ panDirClose(); return; }
  const path=panDirPath(), has=PANDIR.stack.length>0;
  const el=document.getElementById('S_'+PANDIR.key);
  if(el) el.value = has ? path : '';
  const nm=((PAN_ACCOUNTS[PANDIR.pan]||{}).name||PANDIR.pan);
  panDirClose();
  const msg=document.getElementById('siteMsg');
  if(msg){ msg.style.color='var(--ok)'; msg.innerText='已为「'+nm+'」填入目录：'+(has?path:'（网盘根目录）')+'，记得点页面底部「保存设置」。'; }
}
