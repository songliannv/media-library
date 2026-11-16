/* ==========================================================================
   万能资料库 · 后台管理 JS · 基础：接口封装 / 全局状态
   由 assets/js/admin.js 按功能拆分而来（原第 1–72 行）。
   加载顺序统一由 admin/index.php 控制；本文件不单独引用。
   ========================================================================== */
// 站点根（兼容 域名根目录 与 子目录部署；去掉结尾 /admin）
// 注意：不能写成 (x || '/')，否则根目录部署时会拼出 "//api/v1/..."（协议相对，会打到别的域名）
const API = (function(){
  let p = location.pathname;
  const adminSeg = '/' + ((document.body && document.body.getAttribute('data-admin-dir')) || 'admin');
  const at = p.lastIndexOf(adminSeg);
  if (at >= 0) p = p.slice(0, at);
  return p.replace(/\/+$/, '');
})();
let curPage = 1, editId = null, curCat = '', curTag = '', SCHEMA = null, PAN_LIST = ['夸克','迅雷','光鸭','百度','UC'];
let PAN_TYPES = {1:'夸克',2:'百度',3:'迅雷',4:'阿里',5:'123网盘',6:'115',7:'UC',8:'蓝奏',9:'磁力',10:'天翼',11:'和彩云',12:'光鸭',baidu:'百度',aliyun:'阿里',quark:'夸克',guangya:'光鸭',tianyi:'天翼',uc:'UC',mobile:'移动','115':'115',pikpak:'PikPak',xunlei:'迅雷','123':'123网盘',magnet:'磁力',ed2k:'电驴',other:'其他'};
let LINK_STATUS = {}; // url => {ok:bool, msg:string, code:int} | null（未检测）
let editExtraBase = {}; // 编辑前 extra 的完整快照 —— saveEdit 用它保留 cast/trailer/videos 等非 schema key

/**
 * 统一的接口调用：**永不抛异常**。
 * 以前是 fetch(...).then(r=>r.json())——一旦服务端返回的是 HTML
 * （PHP 致命错误页 / 502 / 504 网关超时），r.json() 会 reject，
 * 调用方的 await 直接中断，按钮看起来就是"点了没反应"（只在控制台留红字）。
 * 现在任何异常/非 JSON 一律转成 {success:false, error:"…"}，页面必定给出提示。
 */
async function api(path, opts={}){
  var method = (opts.method || 'GET').toUpperCase();
  var headers = Object.assign({}, opts.headers||{});
  if(method !== 'GET'){
    var isForm = (typeof FormData !== 'undefined' && opts.body instanceof FormData);
    if(!isForm){
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      /* 非字符串（对象）body 自动 JSON 化；已传字符串 / FormData 则不处理 */
      if(opts.body !== undefined && opts.body !== null && typeof opts.body !== 'string'){
        opts.body = JSON.stringify(opts.body);
      }
    }
    /* 写操作自动注入 CSRF 令牌（X-CSRF-Token），后端 api/unified.php 强制校验 */
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta){ headers['X-CSRF-Token'] = csrfMeta.getAttribute('content') || ''; }
  }
  opts.headers = headers;
  opts.credentials = 'same-origin';   /* 带上登录会话 Cookie */
  let r;
  try {
    r = await fetch(API + path, opts);
  } catch(e) {
    return {success:false, error:'网络请求失败：'+((e&&e.message)?e.message:e)+'（检查站点能否访问、伪静态是否生效）'};
  }
  let txt = '';
  try { txt = await r.text(); } catch(e) { txt = ''; }
  let d = null;
  try { d = txt ? JSON.parse(txt) : {}; } catch(e) { d = null; }
  if(d === null || typeof d !== 'object'){
    let hint = '';
    if(r.status===502 || r.status===504) hint = '（网关超时／上游错误：本次操作耗时过长，或 PHP 报错被面板拦下）';
    else if(r.status===500) hint = '（服务器内部错误：多为 PHP 致命错误，可打开 check.php 看第 7 组语法检查）';
    else if(r.status===404) hint = '（接口不存在：站点需配置 Nginx 伪静态，见部署文档第四节）';
    else if(r.status===403) hint = '（被拒绝：未登录管理员账号，或被面板安全规则拦截）';
    const peek = String(txt||'').replace(/\s+/g,' ').slice(0,180);
    return {success:false, error:'服务器返回了非 JSON 内容（HTTP '+r.status+'）'+hint+(peek?('：'+peek):''), _http:r.status};
  }
  if(d.success===undefined){ d.success = (r.status>=200 && r.status<300); }
  d._http = r.status;
  if(!d.success && !d.error){ d.error = 'HTTP '+r.status; }
  return d;
}
/** 按钮忙碌态：跑长任务时禁用按钮并显示进度，避免"点了没反应"的错觉 */
async function busy(btn, text, fn){
  if(!btn){ return fn(); }
  const old = btn.textContent, was = btn.disabled;
  btn.disabled = true; btn.textContent = text;
  try { return await fn(); }
  finally { btn.disabled = was; btn.textContent = old; }
}

