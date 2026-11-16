<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-apitoken" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0;font-size:15px">API 调用令牌</h3>
        <span class="hint">把资料库 API 开放给外部系统/个人调用时，在这里给每个调用者发一个独立令牌 —— 可分别限制每日调用次数与来源 IP，并查看「谁在什么时候调用了什么」。</span>
      </div>
      <div class="ext-note" style="margin-top:11px">
        调用方式：请求头 <code>X-Api-Token: 你的令牌</code>，或 URL 参数 <code>?token=你的令牌</code><br>
        示例：<code id="apiDemo">GET /api/v1/search?q=庆余年&amp;token=xxxx</code>
        <button class="ghost sm" style="margin-left:8px" onclick="copyApiDemo()">复制示例</button>
      </div>
      <div class="stat-grid" id="tkStats" style="margin-top:13px"></div>
      <div id="tkSchema" style="display:none;margin-top:12px"></div>
      <div class="toolbar">
        <button onclick="openTokenAdd()">+ 新建令牌</button>
        <button class="ghost" onclick="loadTokens()">刷新</button>
        <button class="ghost" onclick="switchApiSub('tokens')">令牌列表</button>
        <button class="ghost" onclick="switchApiSub('logs')">调用记录</button>
      </div>
      <p class="hint" style="margin:10px 0 0">点「+ 新建令牌」直接生成，<b>不需要填任何东西</b>；生成后到列表行点「设置」再调整名称、每日调用上限与 IP 白名单。</p>
    </div>

    <div class="card" id="tkListCard">
      <div class="tablewrap">
        <table>
          <thead><tr><th>名称</th><th>令牌</th><th>状态</th><th>今日/限额</th><th>累计</th><th>最后调用</th><th>操作</th></tr></thead>
          <tbody id="tkRows"></tbody>
        </table>
      </div>
    </div>

    <div class="card" id="tkLogCard" style="display:none">
      <div class="toolbar">
        <input type="text" id="logStart" placeholder="开始日期 2026-09-01">
        <input type="text" id="logEnd" placeholder="结束日期 2026-09-30">
        <select id="logToken" onchange="loadApiLogs()"></select>
        <button onclick="loadApiLogs()">查询</button>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>时间</th><th>令牌</th><th>接口</th><th>方法</th><th>IP</th><th>耗时</th></tr></thead>
          <tbody id="logRows"></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="ghost sm" onclick="logPage(-1)">上一页</button>
        <span id="logPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="logPage(1)">下一页</button>
      </div>
    </div>
  </div>
