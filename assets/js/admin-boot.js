/* ==========================================================================
   万能资料库 · 后台管理 JS · 启动：主题应用 / 系统主题监听 / 首屏初始化
   由 assets/js/admin.js 按功能拆分而来（原第 2356–2374 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
applyTheme();
if(window.matchMedia){
  try{
    const mq = window.matchMedia('(prefers-color-scheme:dark)');
    const onSys = function(){ if(themePref() === 'auto') applyTheme(); };
    if(mq.addEventListener) mq.addEventListener('change', onSys);
    else if(mq.addListener) mq.addListener(onSys);
  }catch(e){}
}

(async function(){
  /* 已通过账号登录（PHP 侧已校验），这里只做初始化加载 */
  try { await loadSchema(); } catch(e) {}
  loadStats();
  /* ★ v4.6.3：概览页是默认 Tab，初始加载就要把「内容总览统计」拉起来，
     否则首次进来该区块空白（只有切一次 Tab 才会显示）。 */
  loadOverviewStats();
})();

