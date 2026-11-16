<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-items" class="tabpane" style="display:none">
    <div class="card">
      <div class="cats" id="cats">
        <button class="cat active" data-cat="">全部</button>
        <button class="cat" data-cat="short">短剧</button>
        <button class="cat" data-cat="movie">电影</button>
        <button class="cat" data-cat="tv">电视剧</button>
        <button class="cat" data-cat="anime">动漫</button>
        <button class="cat" data-cat="variety">综艺</button>
        <button class="cat" data-cat="game">游戏</button>
        <button class="cat" data-cat="book">书籍</button>
        <button class="cat" data-cat="music">音乐</button>
        <button class="cat" data-cat="other">其他</button>
      </div>
      <div class="toolbar">
        <input type="text" id="q" placeholder="搜索标题…" onkeydown="if(event.key==='Enter')loadItems()">
        <button onclick="loadItems()">查询</button>
        <button class="ghost" onclick="openAdd()">+ 新增项目</button>
        <!-- ★ v4.3.11：条目管理工具栏「同步热门」按钮下线（用户要求）；采集功能仍在「同步采集」标签页 -->
        <button class="ghost" onclick="runPanTransfer(this)">转存网盘链接</button>
        <button class="ghost" onclick="buildStatic()">生成静态页</button>
        <button class="ghost" onclick="checkAll()">检查全部链接</button>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>封面</th><th>标题</th><th>分类</th><th>来源</th><th>年份</th><th>评分</th><th>下载链接</th><th>操作</th></tr></thead>
          <tbody id="itemRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="pageItems(-1)">上一页</button>
        <span id="pageInfo" class="hint"></span>
        <button class="ghost sm" onclick="pageItems(1)">下一页</button>
      </div>
    </div>
  </div>

