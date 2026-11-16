(function(){
  var box=document.getElementById('mlTagCloud');
  var stage=document.getElementById('mlTcStage');
  if(!box||!stage){return;}
  var allTags=[].slice.call(stage.querySelectorAll('.tc-t'));
  if(!allTags.length){return;}
  var tags=allTags;
  /* 窄屏只留前 24 个：50 个标签在手机上会把首屏占满 */
  function applyMax(){
    var mx=window.innerWidth<680?24:50;
    for(var i=0;i<allTags.length;i++){allTags[i].style.display=(i<mx)?'':'none';}
    tags=allTags.slice(0,mx);
  }
  applyMax();
  function mq1(q){return window.matchMedia?window.matchMedia(q):null;}
  var r1=mq1('(prefers-reduced-motion: reduce)');
  var r2=mq1('(hover:none)');
  var reduce=!!(r1&&r1.matches);
  var touch=!!(r2&&r2.matches);
  var base=[],arr=[],cur='scatter',timer=null,rt=null;

  function shuffle(a){
    for(var i=a.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=a[i];a[i]=a[j];a[j]=t;}
    return a;
  }
  /* 行装箱：不重叠地占满舞台；jitter=true 时加随机抖动（= 不规则散落） */
  var GX=12,GY=12,JX=6,JY=5;   /* 间距 / 抖动幅度：抖动必须小于间距的一半，否则相邻标签会互相压住 */
  function place(list,jitter){
    var W=stage.clientWidth||600,out=[],rowX=0,rowY=0,rowH=0;
    for(var i=0;i<list.length;i++){
      var el=list[i],r=el.getBoundingClientRect();
      var w=Math.ceil(r.width),h=Math.ceil(r.height);
      if(rowX>0&&rowX+w>W){rowY+=rowH+GY;rowX=0;rowH=0;}
      var x=rowX,y=rowY;
      if(jitter){
        x+=(Math.random()*2-1)*JX;
        y+=(Math.random()*2-1)*JY;
        var mx=W-w;
        if(mx<0){mx=0;}
        if(x<0){x=0;}
        if(x>mx){x=mx;}
        if(y<0){y=0;}
      }
      out.push([el,x,y]);
      rowX+=w+GX;
      if(h>rowH){rowH=h;}
    }
    return {pos:out,height:rowY+rowH};
  }
  function apply(ps){
    for(var i=0;i<ps.length;i++){ps[i][0].style.transform='translate3d('+ps[i][1]+'px,'+ps[i][2]+'px,0)';}
  }
  function setClass(on){
    if(!box.classList){return;}
    if(on){box.classList.add('on');}else{box.classList.remove('on');}
  }
  function rebuild(){
    var a=place(tags,false);
    var b=place(shuffle(tags.slice()),true);
    arr=a.pos;
    base=b.pos;
    /* ★ v4.5.9 修「点标签没反应」：原先只按 a.height（不打乱那次）算容器高度，
       而 base 是打乱顺序重新装箱的，行分布可能更高 → 超出部分被 .banner{overflow:hidden}
       裁掉，标签看得见却点不到（每 2.8s 的 drift() 还会继续漂，越界越多）。
       现在取「两组坐标 + 抖动余量」的真实最大底边，并额外留 24px 安全余量。 */
    var maxY=0;
    var i,x,y;
    for(i=0;i<a.pos.length;i++){ y=a.pos[i][2]+a.pos[i][0].offsetHeight; if(y>maxY){maxY=y;} }
    for(i=0;i<b.pos.length;i++){ y=b.pos[i][2]+b.pos[i][0].offsetHeight+JY; if(y>maxY){maxY=y;} }
    stage.style.minHeight=(maxY+24)+'px';
    /* 容器不裁剪：即便极端窄屏算出越界，标签仍可点（只是不重叠得没那么好看） */
    box.style.overflow='visible';
  }
  /* 「乱飞」：每 2.8 秒重新抖动一次坐标，配合 2.8s 过渡 = 持续缓慢漂移 */
  function drift(){
    var out=[];
    var stageH=stage.clientHeight||0;
    for(var i=0;i<base.length;i++){
      var el=base[i][0];
      var x=base[i][1]+(Math.random()*2-1)*4;
      var y=base[i][2]+(Math.random()*2-1)*3;
      if(x<0){x=0;}
      if(y<0){y=0;}
      /* ★ v4.5.9：把漂移钳制在容器内 —— 原先只钳下界，标签会随时间不断下漂出可视区，
         变成「看得见的标签点不到 / 或漂到banner外被裁」。 */
      if(stageH>0){
        var maxX=stage.clientWidth-el.offsetWidth;
        if(maxX>0&&x>maxX){x=maxX;}
        var maxY=stageH-el.offsetHeight;
        if(maxY>0&&y>maxY){y=maxY;}
      }
      out.push([base[i][0],x,y]);
    }
    apply(out);
  }
  function startTimer(){
    if(reduce||timer){return;}
    timer=window.setInterval(function(){if(cur==='scatter'){drift();}},2800);
  }
  function stopTimer(){
    if(timer){window.clearInterval(timer);timer=null;}
  }
  function setMode(m){
    if(m===cur){return;}
    cur=m;
    if(m==='arrange'){
      setClass(true);
      stopTimer();
      apply(arr);
    }else{
      setClass(false);
      apply(base);
      startTimer();
    }
  }

  rebuild();
  if(reduce){
    cur='arrange';
    setClass(true);
    apply(arr);
  }else{
    box.classList.add('tc-init');
    apply(base);
    window.setTimeout(function(){box.classList.remove('tc-init');},60);
    startTimer();
  }
  /* ★ v4.5.9 兜底：万一上面任何一步抛错，标签云退化为「普通可点链接」而不是
     全部堆在左上角互相压住、点不到。静态排列 + 取消裁剪，保证每个标签都能点。 */
  window.setTimeout(function(){
    var ok=false;
    try{ ok=(stage.style.minHeight||'').indexOf('px')>=0; }catch(e){ ok=false; }
    if(!ok){
      for(var i=0;i<allTags.length;i++){
        allTags[i].style.position='static';
        allTags[i].style.transform='none';
      }
      stage.style.minHeight='';
      box.style.overflow='visible';
    }
  },1200);

  /* 鼠标移入 -> 按热度（DOM 顺序即热度降序）排成整齐标签墙；移出 -> 恢复漂浮 */
  box.addEventListener('mouseenter',function(){if(!reduce){setMode('arrange');}});
  box.addEventListener('mouseleave',function(){if(!reduce){setMode('scatter');}});
  /* 触屏设备没有 hover：点空白处切换排列 / 漂浮（点标签本身仍然跳转） */
  if(touch&&!reduce){
    stage.addEventListener('click',function(e){
      var el=e.target;
      while(el&&el!==stage){
        if((' '+el.className+' ').indexOf(' tc-t ')>-1){return;}
        el=el.parentNode;
      }
      setMode(cur==='arrange'?'scatter':'arrange');
    });
  }
  /* 尺寸变化：重新排版（先重排再按当前模式落位，避免错位） */
  window.addEventListener('resize',function(){
    if(rt){window.clearTimeout(rt);}
    rt=window.setTimeout(function(){
      applyMax();
      rebuild();
      apply(cur==='arrange'?arr:base);
    },200);
  });
})();
