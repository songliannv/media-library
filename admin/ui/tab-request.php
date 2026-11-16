<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-request" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">资源请求</h3>
        <span class="hint">注册用户在前台「资源请求」页填写的求片内容，在这里逐条处理；回复只有提交者本人能看到。</span>
      </div>
      <div class="status-bar" id="reqBar" style="margin-top:12px"><span class="pill">加载中…</span></div>
      <div class="subtabs" id="reqSubtabs">
        <button class="stab active" data-rstab="list"  onclick="switchReqTab('list')">请求列表</button>
        <button class="stab"        data-rstab="users" onclick="switchReqTab('users')">注册用户</button>
      </div>
      <div class="toolbar">
        <input type="text" id="reqQ" placeholder="搜索资源名称 / 说明…" onkeydown="if(event.key==='Enter')loadReqList()">
        <select id="reqStatus" onchange="loadReqList()"></select>
        <button onclick="loadReqList()">查询</button>
        <button class="ghost" onclick="loadReq()">刷新</button>
      </div>
      <div id="reqBox"><div class="hint">加载中…</div></div>
      <div class="pager">
        <button class="ghost sm" onclick="reqPage(-1)">上一页</button>
        <span id="reqPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="reqPage(1)">下一页</button>
      </div>
    </div>
  </div>

  <!-- ★ v5.2.9：用户详情滑出面板（v3.2.5 起在「用户中心」，v5.2.9 用户中心取消后
       整体迁到「资源请求 → 注册用户」页，列表里的「📋 详细」按钮调它） -->
  <div class="user-modal-mask" id="userModalMask" onclick="closeUserDetail()"></div>
  <div class="user-modal" id="userModal">
    <div class="um-head">
      <div class="uc-avatar" id="umAvatar">U</div>
      <div class="um-title">
        <b id="umUsername">用户</b>
        <span class="sub" id="umSub"></span>
      </div>
      <button class="um-close" onclick="closeUserDetail()" title="关闭">×</button>
    </div>
    <div class="um-body" id="umBody"><div class="hint">加载中…</div></div>
  </div>

