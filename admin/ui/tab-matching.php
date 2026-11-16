<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-matching" class="tabpane" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <h2 style="margin:0;font-size:17px">🔗 条目匹配</h2>
        <span class="hint">按下载链接分两类：未匹配的由「自动匹配」无人值守补链接（每批 20 条，试完即停）；已匹配的自动检测网盘是否失效。<br>
          <b>★ v5.2.7：</b>「待匹配」是<b>所有没链接的条目</b>，自动匹配把条目试过一遍但没匹配上时它不会减少 —— 真正在推进的指标是下方状态卡里的「<b>现在还能试的条目</b>」与「累计已尝试」。</span>
      </div>
      <div class="mc-stats" id="mcStats">
        <div class="mc-stat mc-unmatched" title="全部没有下载链接的条目（含已试过但没匹配上的）—— 这个数只在真正补上链接后才会减少，请看下方状态卡里的「现在还能试的条目」">
          <b id="mcUnmatched">-</b>
          <span>待匹配（没链接）</span>
        </div>
        <div class="mc-stat mc-matched">
          <b id="mcMatched">-</b>
          <span>已匹配</span>
        </div>
        <div class="mc-stat mc-dead" title="已匹配条目中存在失效链接">
          <b id="mcDead">-</b>
          <span>含失效链接</span>
        </div>
      </div>

      <!-- ★ v5.2.7：自动匹配运行状态（回答「自动开了却没效果」） -->
      <div class="sc-auto" id="mcAuto">
        <div class="sc-auto-head">
          <b>⏱ 自动匹配（每 5 分钟）</b>
          <span class="sc-auto-dot" id="mcAutoDot"></span>
          <span class="sc-auto-state" id="mcAutoState">读取中…</span>
          <button type="button" class="ghost sm" onclick="mcLoadAuto()">刷新状态</button>
        </div>
        <div class="sc-auto-body" id="mcAutoBody"></div>
      </div>
      <div class="subtabs" id="mcSubtabs" style="margin-top:14px">
        <button class="mc-tab active" data-mctab="unmatched" onclick="switchMatchTab('unmatched')">
          🕓 待匹配 <span class="cnt" id="mcCntUnmatched">0</span>
        </button>
        <button class="mc-tab" data-mctab="matched" onclick="switchMatchTab('matched')">
          ✅ 已匹配 <span class="cnt" id="mcCntMatched">0</span>
        </button>
        <button class="ghost sm mc-auto-btn" id="mcAutoBtn" onclick="runMatchAuto()" title="自动为所有待匹配条目搜索网盘链接：高置信度直接写入、低置信度进待确认；已尝试无结果的条目 7 天内不再重复请求，试完自动停止（v5.1.9 起支持一键跑完）">🔗 自动匹配</button>
      </div>
      <div class="toolbar" style="margin-top:12px">
        <input type="text" id="mcQ" placeholder="搜索标题…" onkeydown="if(event.key==='Enter'){mcPage=1;loadMatching();}">
        <button onclick="mcPage=1;loadMatching()">查询</button>
        <!-- ★ v4.3.12：按「功能回对应标签页」原则清理重复按钮 ——
             「去同步采集」跨页跳转按钮、「+ 新增项目」重复入口（条目管理页才有）均已下线 -->
        <button class="ghost" id="mcCheckBtn" onclick="matchCheckAll()">🔍 立即检测全部链接</button>
      </div>
      <div class="mc-grid" id="mcRows"></div>
      <div class="pager">
        <button class="ghost sm" onclick="mcPageItems(-1)">上一页</button>
        <span id="mcPageInfo" class="hint"></span>
        <button class="ghost sm" onclick="mcPageItems(1)">下一页</button>
      </div>
    </div>
  </div>
