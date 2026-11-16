/* ==========================================================================
   万能资料库 · 后台管理 JS · 安全防护 / 明暗主题
   由 assets/js/admin.js 按功能拆分而来（原第 2266–2355 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
// ===== 安全防护（安装器自锁状态 / 敏感文件暴露面）=====
function secBadge(ok, level){
  if(level==='fail') return '<span class="sec-badge f">需处理</span>';
  if(level==='info') return '<span class="sec-badge n">提示</span>';
  return ok ? '<span class="sec-badge y">已防护</span>' : '<span class="sec-badge n">待处理</span>';
}
function secRow(ok, name, detail, hint, level){
  return `<div class="sec-row">
    <div class="sec-bd">${secBadge(ok, level)}</div>
    <div style="flex:1">
      <div class="sec-nm">${esc(name)}</div>
      <div class="sec-dt">${esc(detail||'')}</div>
      ${hint?`<div class="hint" style="display:block;margin-top:5px">${esc(hint)}</div>`:''}
    </div></div>`;
}
async function loadSecurity(){
  const box = document.getElementById('secBox');
  box.innerHTML = '<div class="hint">正在检测…</div>';
  const d = await api('/api/v1/admin/security');
  if(!d.success){ box.innerHTML = '<div class="hint">'+esc(d.error||'加载失败')+'</div>'; return; }
  const g = d.data.guard || {};
  let h = '';
  h += `<div class="ext-note note-ok">
    🔒 <b>后台入口已改为「账号 + 密码」登录</b>：不再是"填个令牌就能进"，管理员账号在安装时创建。
    安装相关路径、敏感目录、内部 PHP 文件被直接请求时统一返回 404。</div>`;

  /* v3.5.7：按站长要求移除「网页版安装器 install.php」提示行 —— 安装锁状态
     已由下方 config/install.lock 行体现，残留文件告警（install_ghosts）保留。 */
  if(g.install_ghosts && g.install_ghosts.length){
    h += secRow(false, '安装类残留文件', g.install_ghosts.join('、'), '建议一并删除', 'fail');
  }

  h += secRow(!!g.cli_installer, '命令行安装器 tools/install-cli.php',
      g.cli_installer ? '就位（仅命令行可执行，公网访问 404；与网页安装器共用同一套内核）' : '未上传（不影响运行；网页安装器 install.php 同样能装）',
      g.cli_installer ? '重装或换服务器时，在宝塔终端执行：php tools/install-cli.php' : '需要时从发布包上传 tools/ 目录',
      g.cli_installer ? '' : 'info');

  h += secRow(!!g.lock, '安装锁 config/install.lock',
      g.lock ? '存在（已标记安装完成）' : '不存在（站点能正常访问可忽略）', '');

  const dg = g.dir_guards || {}; const miss = [];
  for(const k in dg){ if(!dg[k]) miss.push(k); }
  h += secRow(miss.length===0, '敏感目录拒访文件',
      miss.length===0 ? 'config / sql / core / adapters / cron / tools 均已放置拒访 index.php'
                      : ('缺少：'+miss.join('、')),
      miss.length===0 ? '' : '从发布包对应目录补传 index.php');

  const sm = g.shield_missing || [];
  h += secRow(sm.length===0, '内部 PHP 文件反直接访问守卫',
      sm.length===0 ? ((g.shield_total||0)+' 个内部文件均已带守卫（直接用网址请求会返回 404）')
                    : ('未加守卫：'+sm.slice(0,6).join('、')),
      sm.length===0 ? '' : '用发布包覆盖对应文件即可');

  h += secRow(!!g.htaccess, '.htaccess（Apache 用）',
      g.htaccess ? '存在（Apache 自动生效；Nginx 请用宝塔伪静态规则）' : '不存在（Nginx 环境无需本文件）',
      '', g.htaccess ? '' : 'info');

  box.innerHTML = h;
}
function openSelfCheck(){
  window.open(API + '/check.php', '_blank');
}

// ===== 明暗主题：跟随系统 / 亮色 / 暗色 三态循环，选择记在浏览器本地 =====
const THEME_ORDER = ['auto','light','dark'];
function themePref(){
  try{
    const v = localStorage.getItem('ml_admin_theme');
    if(v === 'auto' || v === 'light' || v === 'dark') return v;
  }catch(e){}
  return 'auto';
}
function applyTheme(){
  const p = themePref();
  const sysDark = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches);
  const dark = (p === 'dark') || (p === 'auto' && sysDark);
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  const b = document.querySelector('.themebtn');
  if(b){
    b.textContent = (p === 'auto') ? '🌗' : (dark ? '☀' : '🌙');
    b.title = '明暗：' + (p === 'auto' ? '跟随系统' : (p === 'light' ? '亮色' : '暗色'))
            + '（点击切换到' + (p === 'auto' ? '亮色' : (p === 'light' ? '暗色' : '跟随系统')) + '）';
  }
}
function toggleTheme(){
  const cur = themePref();
  const next = THEME_ORDER[(THEME_ORDER.indexOf(cur) + 1) % THEME_ORDER.length];
  try{ localStorage.setItem('ml_admin_theme', next); }catch(e){}
  applyTheme();
}

/* ★ v5.4.5：移动端左侧栏抽屉开合（☰ 按钮触发；桌面端无效） */
function toggleSidebar(){
  var s = document.getElementById('adminSide');
  if(s) s.classList.toggle('open');
}
