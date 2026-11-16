/* v5.0.7 下载按钮「点击即转存」交互（外链 defer，可缓存）
 * ------------------------------------------------------------------
 * 背景：v5.0.4~v5.0.6 靠后台 cron 预转存，前端对未转存的链接显示灰色
 *   「转存中」占位等它变直链。站长反馈这个体验不行（占位点不动、还得刷
 *   页面），v5.0.7 取消预转存，改成**访客点了才开始转存**。
 *
 * 三条硬要求（与 pageview.js 同源，统计脚本绝不能影响下载）：
 *   1) 渐进增强：按钮本身就是 <a href="/dl/go/1/0" target="_blank">，
 *      **禁用 JS 时服务端同步转存照样跳得动**，这里只是把等待过程显性化。
 *   2) 绝不吞掉点击：拿不到跳转地址就放行默认行为（回落到服务端同步转存），
 *      任何异常都不许把访客卡在页面上。
 *   3) 只拦「尚未转存、点了要现转」的按钮（data-dl-go）：已是我盘直链的按钮不碰，
 *      否则会平白多一次 AJAX、还可能把新标签页打开时机搞乱。
 */
(function () {
  'use strict';

  if (!document || typeof document.addEventListener !== 'function') { return; }

  var HOST   = '/api/v1/dl_go';
  var LOCK   = false;   /* 同时只允许一个转存流程（连点两下会重复转存） */

  /* ---------- 遮罩层（懒建 DOM，成功/失败都要移除） ---------- */
  var maskEl = null;
  function mask() {
    if (maskEl) { return maskEl; }
    var d = document.createElement('div');
    d.className = 'dl-mask';
    /* 用 innerHTML 一次性铺好：本地常量、无用户数据，不存在注入面 */
    d.innerHTML = '<div class="box"><div class="spin"></div>'
                + '<div class="t">正在转存到本站网盘…</div>'
                + '<div class="s">网盘接口较慢，通常 5~15 秒，请不要关闭本页</div></div>';
    (document.body || d).appendChild(d);
    maskEl = d;
    return d;
  }
  function maskText(title, sub, isErr) {
    var m = mask();
    var t = m.querySelector('.t'), s = m.querySelector('.s');
    if (t) { t.textContent = title; }
    if (s) { s.textContent = sub || ''; }
    m.className = isErr ? 'dl-mask err' : 'dl-mask';
  }
  function maskClose() {
    if (maskEl && maskEl.parentNode) { maskEl.parentNode.removeChild(maskEl); }
    maskEl = null;
  }

  /* ---------- 整页跳转兜底：JS 起得来但 fetch 不可用时 ---------- */
  /* ★ v5.0.8：按钮上已无 <em> 状态胶囊（访客不该看到内部状态），
     转存中的提示一律由遮罩承担，这里不再改按钮文案。 */
  function jumpTo(url) {
    if (window.location) { window.location.href = url; }
  }

  function start(a) {
    if (LOCK) { return; }
    var id  = a.getAttribute('data-dl-id');
    var idx = a.getAttribute('data-dl-idx') || 0;
    if (!id) { return; }               /* 交给默认行为 */

    LOCK = true;
    if (a.classList) { a.classList.add('dl-busy'); }
    mask();

    var u = HOST + '?id=' + encodeURIComponent(id) + '&idx=' + encodeURIComponent(idx);

    /* ★ fetch 不可用 / 被代理拦截 ⇒ 直接整页跳 /dl/go（服务端同步转存），
       体验退化成「等几秒后自动跳转」，不会失败。 */
    if (typeof fetch !== 'function') {
      jumpTo('/dl/go/' + encodeURIComponent(id) + '/' + encodeURIComponent(idx));
      return;
    }

    try {
      fetch(u, {
        method: 'GET',
        credentials: 'same-origin',
        mode: 'same-origin',
        cache: 'no-store'
      }).then(function (r) {
        return r.json();
      }).then(function (j) {
        LOCK = false;
        var d = (j && j.success && j.data) ? j.data : null;
        if (d && d.ok && d.url) {
          maskClose();
          /* 转好了：新标签页打开我盘地址（与直链按钮行为一致） */
          var w = window.open ? window.open(d.url, '_blank', 'noopener') : null;
          if (!w) { window.location.href = d.url; }
          /* 就地把按钮改成直链：下次再点秒开，且外观与普通按钮完全一致
             （v5.0.8 起两种按钮同款，无需再摘任何状态样式） */
          a.setAttribute('href', d.url);
          a.removeAttribute('data-dl-go');
          a.classList.remove('dl-busy');
          return;
        }
        var err = (d && d.error) ? d.error : '转存失败，请稍后再试';
        maskText('转存没成功', err, true);
        if (a.classList) { a.classList.remove('dl-busy'); }
        /* 3 秒后自动收起错误遮罩，别一直挡着页面 */
        setTimeout(maskClose, 3000);
      }).catch(function () {
        /* 网络异常 → 回落整页跳转（宁可慢，不可失败） */
        LOCK = false;
        jumpTo('/dl/go/' + encodeURIComponent(id) + '/' + encodeURIComponent(idx));
      });
    } catch (e) {
      LOCK = false;
      jumpTo('/dl/go/' + encodeURIComponent(id) + '/' + encodeURIComponent(idx));
    }
  }

  /* ---------- 事件委托：静态 HTML 里按钮个数不定，绑 body 一次就够 ---------- */
  document.addEventListener('click', function (ev) {
    var el = ev.target;
    /* 最多上溯 6 层找按钮本体（<a> 内可能包着文本节点或 <em> 等子元素） */
    for (var n = 0; el && n < 6; n++) {
      if (el.getAttribute && el.getAttribute('data-dl-go')) {
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || (ev.button && ev.button !== 0)) {
          return;   /* 用户要「新标签打开 / 另存」就别拦，交给默认行为 */
        }
        ev.preventDefault();
        start(el);
        return;
      }
      el = el.parentNode;
    }
  }, true);
})();


/* ★ v5.0.7：详情页 ?dlerr= 转存失败提示
 * ------------------------------------------------------------------
 * 服务端转存失败时 302 回详情页并带 ?dlerr=…（见 index.php /dl/ 分支）。
 * 静态页是预生成的、拿不到 query，所以由这段 JS 从 location.search 读
 * 并弹一条提示，读完立刻用 history.replaceState 清掉 query（否则刷新
 * 又弹一次，也污染分享出去的链接）。 */
(function () {
  'use strict';
  if (!window.location || !window.history || !history.replaceState) { return; }

  var q = window.location.search || '';
  if (!q) { return; }
  var m = q.match(/[?&]dlerr=([^&]*)/);
  if (!m) { return; }

  var msg;
  try { msg = decodeURIComponent(m[1].replace(/\+/g, ' ')); } catch (e) { msg = ''; }
  if (!msg) { return; }

  try {
    history.replaceState({}, '', window.location.pathname);
  } catch (e) { /* 老浏览器清不掉就算了，不影响功能 */ }

  function show() {
    var d = document.createElement('div');
    d.className = 'dl-mask err';
    d.innerHTML = '<div class="box"><div class="t">这个资源暂时转存失败</div>'
                + '<div class="s"></div><div class="s" style="margin-top:12px">'
                + '可能是网盘账号 Cookie 过期了，站长需要到后台「站点设置 → 添加网盘」重新扫码。</div></div>';
    var s = d.querySelectorAll('.s')[0];
    if (s) { s.textContent = msg; }
    /* 点遮罩任意处关闭（转存失败不该逼访客非关不可） */
    d.addEventListener('click', maskClose);
    (document.body || d).appendChild(d);
    setTimeout(function () { if (d.parentNode) { d.parentNode.removeChild(d); } }, 6000);
  }
  function maskClose() {
    var d = document.querySelector('.dl-mask');
    if (d && d.parentNode) { d.parentNode.removeChild(d); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', show);
  } else {
    show();
  }
})();
