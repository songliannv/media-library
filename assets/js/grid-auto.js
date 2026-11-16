(function(){
  /* 取当前 .grid 的真实列数：
     ① 首选 getComputedStyle 的 gridTemplateColumns —— 浏览器已把 repeat() 解析成
        实际的列宽序列（如 "140px 140px"），其长度即列数，最可靠；
     ② 解析不出（返回 none / 空串）时，退回「量卡片实际占了几个不同的 top 值」。 */
  function colCount(grid){
    var t='';
    try{ t=window.getComputedStyle(grid).gridTemplateColumns||''; }catch(e){ t=''; }
    if(t && t!=='none'){
      var parts=t.split(/\s+/).filter(function(x){return x&&x!=='none';});
      if(parts.length) return parts.length;
    }
    /* 兜底：数一下前若干张卡片的 offsetTop 有几种不同的值 = 实际列数 */
    var cards=grid.querySelectorAll(':scope > .card');
    var tops={},max=0;
    for(var i=0;i<cards.length && i<40;i++){
      var tp=cards[i].offsetTop;
      if(!(tp in tops)){ tops[tp]=1; max++; }
      if(max>1 && i>=max-1) break;
    }
    return max||1;
  }
  function fix(){
    var grids=document.querySelectorAll('.wrap > .grid');
    for(var gi=0;gi<grids.length;gi++){
      var grid=grids[gi];
      var cards=grid.querySelectorAll(':scope > .card');
      /* 静态 NodeList 必须先转真数组：remove() 不会改变 NodeList.length，
         用 while(list.length) 清空是死循环（v4.6.6 修复）。 */
      var phs=Array.prototype.slice.call(grid.querySelectorAll(':scope > .ph'));
      for(var pi=0;pi<phs.length;pi++){
        var old=phs[pi];
        if(old&&old.parentNode){old.parentNode.removeChild(old);}
      }
      if(!cards.length) continue;
      /* ★ v4.7.4：补到「列数的整数倍」，不再用写死的 21（那是 PC 7 列的排数） */
      var cols=colCount(grid);
      var n=(cols-(cards.length%cols))%cols;
      if(n<0) n=0;
      for(var k=0;k<n;k++){
        var d=document.createElement('div');
        d.className='ph';
        grid.appendChild(d);
      }
    }
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', fix); }
  else { fix(); }
  window.addEventListener('resize', fix);
  setTimeout(fix, 250); setTimeout(fix, 900);
  /* ★ v4.9.0：把 fix() 暴露给 pagecache.js —— 翻页零等待切换后，
     新页面的 .grid 是整块替换进来的，末行补位必须重新算一次
     （否则会露出 v4.7.4 修过的「末行排不满、右侧留白」）。
     暴露后仍保留上面的 resize / setTimeout 自愈，不依赖调用方。 */
  window.__mlGridFix = fix;
})();
