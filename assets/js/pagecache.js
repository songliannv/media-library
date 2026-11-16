/* ================================================================
 * pagecache.js —— 列表页「翻页预取 + 零等待切换」
 *
 * 解决的问题：翻页要等 PHP 查库 + 渲染，实测常有一两秒白屏等待。
 *
 * 做法（纯前端，零后端改动）：
 *   ① 页面空闲时预取下一页 HTML，放进内存 + 浏览器 HTTP 缓存
 *      （服务端 v4.8.7 起已把列表页改为 max-age=60，命中即 0 流量）；
 *   ② 点「上一页 / 下一页」时若内存缓存命中 → 直接替换 .grid 与 .pager，
 *      用 history.pushState 改地址，不做整页跳转 ⇒ 点击到看到 0 等待；
 *   ③ 未命中、目标不是翻页、或任何环节出错 → 立刻退回原生跳转（行为一致）。
 *
 * 为什么只在「非搜索态」启用：
 *   搜索结果页的 header 区块（标签云/筛选标题）会随 q 变化，
 *   只换 .grid 会与页面其它部分不一致 ⇒ 搜索页走原生跳转。
 *   服务端在搜索态不出这段 script（见 Views::pageCacheJs）。
 *
 * 兜底：任何被我们改动过的状态都会在失败时退回原生行为，
 *       不会让用户「点不动翻页」。
 * ================================================================ */
(function () {
  'use strict';

  /* ---------- 配置 ---------- */
  var TTL = 60000;            /* 与服务端 max-age=60 对齐（毫秒） */
  var MAX = 5;                /* 最多缓存 5 页，防止内存膨胀 */
  var mem = {};               /* url -> { t: 时间戳, html: 字符串 } */

  /* ---------- 工具 ---------- */
  function now() { return Date.now(); }

  function prune() {
    var keys = Object.keys(mem);
    /* ① 先按时间戳丢弃过期的 */
    keys.forEach(function (k) {
      if (now() - mem[k].t > TTL) delete mem[k];
    });
    /* ② 再按数量裁剪（保留最新的 MAX 个） */
    var left = Object.keys(mem);
    if (left.length > MAX) {
      left.sort(function (a, b) { return mem[a].t - mem[b].t; });
      for (var i = 0; i < left.length - MAX; i++) delete mem[left[i]];
    }
  }

  /* ---------- 取新页面的「列表 + 分页」片段 ---------- */
  function extract(html) {
    var d = document.implementation.createHTMLDocument('ml-page');
    d.body.innerHTML = html;
    return {
      grid: d.querySelector('.grid'),
      pager: d.querySelector('.pager'),
      /* 供排错/校验用：这页是不是我们要的（至少有一个容器） */
      ok: !!d.querySelector('.grid') || !!d.querySelector('.pager')
    };
  }

  function fetchPage(url) {
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'pagecache' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        mem[url] = { t: now(), html: html };
        prune();
        return html;
      });
  }

  /* ---------- 空闲时预取下一页 ---------- */
  function prefetchNext() {
    var next = document.querySelector('.pager a[data-ml-page]');
    if (!next) return;
    var url = next.getAttribute('href');
    if (!url || mem[url]) return;
    var go = function () {
      /* 页面正在被用户操作 / 已卸载 → 不预取，避免无意义的并发 */
      if (document.hidden) return;
      fetchPage(url).catch(function () { /* 预取失败无需打扰用户 */ });
    };
    if (window.requestIdleCallback) window.requestIdleCallback(go, { timeout: 3000 });
    else setTimeout(go, 1200);
  }

  /* ---------- 换页 ---------- */
  function applyPage(html, url) {
    var parts = extract(html);
    if (!parts.ok || !parts.grid) return false;

    var curGrid = document.querySelector('.grid');
    if (!curGrid) return false;
    curGrid.replaceWith(parts.grid);

    var curPager = document.querySelector('.pager');
    if (curPager && parts.pager) curPager.replaceWith(parts.pager);

    /* 新页面的网格补位符（.ph）是客户端算的，必须让 grid-auto 重新补一次。
       grid-auto.js 把内部 fix() 暴露成 window.__mlGridFix；没有它就退回不补，
       最坏结果是末行不满 —— 不影响可用性。 */
    if (typeof window.__mlGridFix === 'function') {
      try { window.__mlGridFix(); } catch (e) { /* 忽略：仅影响补位 */ }
    }

    /* 标题滚动标记同理（新卡片的标题还没测过是否溢出） */
    if (typeof window.__mlTitleMark === 'function') {
      try { window.__mlTitleMark(); } catch (e) { /* 忽略 */ }
    }

    if (history.pushState) history.pushState({ mlPage: 1 }, '', url);
    window.scrollTo(0, 0);
    return true;
  }

  function goto(url, ev) {
    if (ev) { if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return; ev.preventDefault(); }
    var hit = mem[url];
    if (hit && (now() - hit.t) <= TTL && applyPage(hit.html, url)) {
      prefetchNext();               /* 继续把下一页预上 */
      return;
    }
    /* 没命中：先记住意图，等真实跳转前尽量抓一份（对慢网也有效） */
    if (hit) delete mem[url];
    if (ev) { try { fetchPage(url).catch(function () {}); } catch (e) {} }
    window.location.href = url;      /* 原生跳转兜底 —— 行为与改动前完全一致 */
  }

  /* ---------- 事件绑定 ---------- */
  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0) return;
    var a = ev.target && ev.target.closest ? ev.target.closest('.pager a[data-ml-page]') : null;
    if (!a) return;
    var url = a.getAttribute('href');
    if (!url) return;
    goto(url, ev);
  }, false);

  /* 后退 / 前进 */
  window.addEventListener('popstate', function () {
    window.location.reload();        /* 前进后退整页重载最稳，别自己拼历史栈 */
  }, false);

  /* 页面可见性恢复时补一次预取（移动端切回来常驻很久） */
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) prefetchNext();
  }, false);

  /* ---------- 启动 ---------- */
  function boot() {
    /* 页面只有一个 .pager 且带 data-ml-page 才启用（服务端已在非搜索态才输出本脚本） */
    if (!document.querySelector('.pager a[data-ml-page]')) return;
    prefetchNext();
    /* 滚到底部时再预取一次（用户在当前页已看完，可能要点下一页） */
    window.addEventListener('scroll', function () {
      var nearBottom = (window.innerHeight + window.scrollY) > (document.body.scrollHeight - 600);
      if (nearBottom) prefetchNext();
    }, { passive: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

  /* 暴露给守卫/排错用 */
  window.__mlPageCache = {
    prefetch: prefetchNext,
    stats: function () { return { cached: Object.keys(mem).length, urls: Object.keys(mem) }; }
  };
})();