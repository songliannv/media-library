(function(){
  var el = document.getElementById('rq_title');
  var box = document.getElementById('rqHint');
  if(!el || !box){ return; }
  var timer = null;
  function hide(){ box.className = 'u-hintbox'; box.innerHTML = ''; }
  el.addEventListener('input', function(){
    var v = (el.value || '').trim();
    if(timer) { clearTimeout(timer); }
    if(v.length < 2){ hide(); return; }
    timer = setTimeout(function(){
      fetch('/request?probe=' + encodeURIComponent(v))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(!d || !d.success || !d.data || !d.data.items || !d.data.items.length){ hide(); return; }
          var its = d.data.items, s = '站内可能已经有这些，先看看是不是你要的：';
          for(var i=0;i<its.length;i++){
            var a = document.createElement('a');
            a.href = String(its[i].url || '/');
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = String(its[i].title || '');
            box.appendChild(document.createTextNode(' '));
            box.appendChild(a);
          }
          box.className = 'u-hintbox on';
        })
        .catch(function(){ hide(); });
    }, 350);
  });
})();
