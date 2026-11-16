/* v4.9.7 页面浏览埋点（外链 defer，可缓存）
 * ------------------------------------------------------------------
 * 为什么用 fetch 而不是 1×1 img：
 *   两者都能上报，但 fetch 能带一个简洁的 JSON body、失败时可静默重试判断，
 *   且不会在 Network 面板里留下一个「图片」条目更清楚。仍保留 img 作为兜底
 *   —— 某些环境（企业代理、极端旧的浏览器）会拦 fetch，此时 img 仍能上报。
 *
 * ★ 三条硬要求：
 *   1) 绝不能阻塞渲染 —— 用 fetch 且不 await，页面先出内容，统计随后补报。
 *   2) 失败必须完全静默 —— 统计挂了不能让访客看到报错或白屏（这正是
 *      第三方统计代码的老毛病，v4.9.1 的 protectThirdParty 就是为它而生）。
 *   3) 一次页面浏览只报一次 —— SPA 式的分页局部切换不重复上报，
 *      否则 PV 会成倍虚高（pagecache.js 换页只换 .grid，不换 location）。
 */
(function () {
  'use strict';

  var ENDPOINT = '/track?t=page';
  var ITEM_ID  = (document.body && document.body.getAttribute('data-item-id')) || 0;
  /* ★ 页面路径只取 pathname，不带查询串：搜索词、来源参数都不是统计需要的，
     存下来反而是隐私负担（服务端也会再剥一次，双保险）。 */
  var PATH = location.pathname || '/';
  var REF  = document.referrer || '';

  function url() {
    return ENDPOINT + '&p=' + encodeURIComponent(PATH) +
           '&id=' + encodeURIComponent(ITEM_ID) +
           '&r=' + encodeURIComponent(REF);
  }

  function send() {
    /* 优先 fetch：带 keepalive，页面正在跳转时请求也能发出去。
       ★ 用 `typeof fetch === 'function'` 判断、且直接调用全局 fetch（不写 window.fetch）——
         少数环境（如某些 webview / 代理改写）里 window 与全局作用域并不完全等价，
         写 window.fetch 会误判成「不支持 fetch」而白走 img 兜底。 */
    try {
      if (typeof fetch === 'function') {
        var p = fetch(url(), {
          method: 'GET',
          keepalive: true,
          credentials: 'same-origin',
          mode: 'same-origin',
          cache: 'no-store'
        });
        /* fetch 一定返回 Promise；万一不是（老环境 polyfill 返回 undefined），
           catch 调用会抛 TypeError —— 那就落到 img。 */
        if (p && typeof p['catch'] === 'function') {
          p['catch'](function () { img(); });
        } else {
          img();
        }
        return;
      }
    } catch (e) { /* 落到 img 兜底 */ }
    img();
  }

  /* img 兜底：任何环境都能发出去，不受 CORS / fetch 拦截影响 */
  function img() {
    try {
      var im = new Image(1);
      im.src = url();
      im.onerror = function () { /* 埋点失败就算了，绝不重试、绝不提示 */ };
      im.width = im.height = 1;
      im.style.position = 'absolute';
      im.style.opacity = '0';
      im.style.pointerEvents = 'none';
      document.body && document.body.appendChild(im);
    } catch (e) { /* 连 img 都失败就彻底放弃 */ }
  }

  /* ★ 页面可见性变化时补报一次「真实停留」不算 PV（口径是 PV 不是停留时长），
     这里什么都不做 —— 保留这个注释是为了防止后来人以为漏了功能而「补上」。 */

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(send, 0);
  } else {
    window.addEventListener('load', function () { setTimeout(send, 0); });
  }
})();

/* ★ v5.0.4 下载按钮点击埋点（media_items.dl_hits）
 * ------------------------------------------------------------------
 * 详情页的下载按钮是**网盘直链**（不再经过站内 /dl/ 中转），所以服务端没有
 * 机会数到点击 —— 由这里在跳转发生的同一瞬间补报一次 /track?t=dl&id=..。
 *
 * 三条硬要求（与上面的 PV 埋点同源）：
 *   1) 绝不能拖慢跳转：只用 sendBeacon（浏览器保证发出、不占连接），退回
 *      fetch(keepalive)；两者都不可用时退回 1×1 img。全程不 await。
 *   2) 失败必须完全静默：任何异常都吞掉，绝不弹提示、绝不改跳转行为。
 *   3) 只数「带 data-dl-id 的下载按钮」：用事件委托（详情页是静态 HTML，
 *      不会为每个按钮单独绑事件）。 */
(function () {
  'use strict';

  /* ★ v5.0.4：极少数非浏览器宿主（老 webview / 单测沙箱）没有 document.addEventListener，
     直接退出即可 —— 统计脚本绝不能在缺 API 的环境里抛错。 */
  if (!document || typeof document.addEventListener !== 'function') { return; }

  function report(a) {
    var id  = a.getAttribute('data-dl-id') || 0;
    var idx = a.getAttribute('data-dl-idx') || 0;
    if (!id) { return; }
    var u = '/track?t=dl&id=' + encodeURIComponent(id) + '&i=' + encodeURIComponent(idx);
    try {
      if (navigator && typeof navigator.sendBeacon === 'function') {
        navigator.sendBeacon(u);
        return;
      }
    } catch (e) { /* 落到 fetch */ }
    try {
      if (typeof fetch === 'function') {
        fetch(u, { method: 'GET', keepalive: true, credentials: 'same-origin', mode: 'same-origin', cache: 'no-store' });
        return;
      }
    } catch (e) { /* 落到 img */ }
    try {
      var im = new Image(1);
      im.src = u;
      im.width = im.height = 1;
      im.style.position = 'absolute';
      im.style.opacity = '0';
      im.style.pointerEvents = 'none';
      document.body && document.body.appendChild(im);
    } catch (e) { /* 彻底放弃：统计不该影响下载 */ }
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target;
    /* 最多上溯 6 层找按钮本体（<a> 里还有 <em>/<span> 等子节点） */
    for (var n = 0; el && n < 6; n++) {
      if (el.getAttribute && el.getAttribute('data-dl-id')) {
        report(el);
        return;
      }
      el = el.parentNode;
    }
  }, true);
})();
