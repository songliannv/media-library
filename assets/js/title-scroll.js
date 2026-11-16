(function(){
  function mark(){
    var els=document.querySelectorAll('.c-title');
    for(var i=0;i<els.length;i++){
      var el=els[i], t=el.querySelector('.t-in');
      if(!t) continue;
      el.classList.toggle('ml', t.scrollWidth>el.clientWidth);
    }
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', mark); }
  else { mark(); }
  window.addEventListener('resize', mark);
  setTimeout(mark, 250); setTimeout(mark, 900);
  /* ★ v4.9.0：暴露给 pagecache.js —— 翻页零等待切换后新卡片还没测过
     标题是否溢出，需重跑一次标记（否则长标题不会获得悬停滚动效果）。
     暴露后仍保留上面的 resize / setTimeout 自愈。 */
  window.__mlTitleMark = mark;
})();
