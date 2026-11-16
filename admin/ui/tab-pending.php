<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-pending" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">条目暂存</h3>
        <span class="hint">PanSou 搜索入库的条目先进暂存，刮削成功后才能进入条目管理对外展示。</span>
      </div>
      <div class="toolbar">
        <input type="text" id="pq" placeholder="搜索暂存条目…" onkeydown="if(event.key==='Enter')loadPending()">
        <button onclick="loadPending()">查询</button>
        <span id="pendingCount" class="hint"></span>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>封面</th><th>标题</th><th>来源</th><th>网盘类型</th><th>入库时间</th><th>操作</th></tr></thead>
          <tbody id="pendingRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="pagePending(-1)">上一页</button>
        <span id="pendingPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="pagePending(1)">下一页</button>
      </div>
    </div>
  </div>

  <!-- ★ v4.9.6：「同步采集」原本是顶层独立 tab（与「站点设置」并列），
       用户要求把它移进「站点设置」里 ⇒ 顶层 tab 与 #tab-sync 面板均已删除，
       内容降级为站点设置页签栏里的二级页签 data-stab="sync"（排在「系统设置」之后）。
       ★ 与 sys 一样是「独立的一套面板」：采集参数走 saveSyncParams()、
         草稿用 SYNC_DRAFT，与站点设置的 SITE_DRAFT 是两套，
         所以**不能**并进 sitePane，必须各自一个互斥容器。 -->

