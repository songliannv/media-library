<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-scrape" class="tabpane" style="display:none">
    <div class="card">
      <h2>🎬 自动刮削与资料补全</h2>
      <div class="hint" style="margin:-4px 0 12px">把缺图片、缺资料（简介 / 导演 / 演员 / 年份 / 评分 / 类型）的条目<b>慢慢补齐</b>：
        补图走 TMDB；资料先查 TMDB，查不到的（国产短剧、老片、网络电影）再用<b>百度百科</b>补简介。
        <b>点下面的数字可以看到详细列表。</b></div>

      <!-- ★ v5.2.6：自动任务运行状态（回答「自动明明开着，怎么没效果」） -->
      <div class="sc-auto" id="scAuto">
        <div class="sc-auto-head">
          <b>⏱ 自动任务（每分钟）</b>
          <span class="sc-auto-dot" id="scAutoDot"></span>
          <span class="sc-auto-state" id="scAutoState">读取中…</span>
          <button type="button" class="ghost sm" onclick="scLoadAuto()">刷新状态</button>
        </div>
        <div class="sc-auto-body" id="scAutoBody"></div>
        <!-- ★ v5.2.6：自动任务参数就地可调（上千条待处理时，1 条/分钟要跑 7 天） -->
        <div class="sc-auto-cfg">
          <label>每分钟处理</label>
          <input type="number" id="scPerMinute" min="1" max="20" value="1"> 条
          <label>失败阈值</label>
          <input type="number" id="scFailTries" min="1" max="20" value="1"> 次后让位（默认首次失败）
          <label>冷却</label>
          <input type="number" id="scCooldownDays" min="1" max="90" value="7"> 天
          <button type="button" class="ghost sm" onclick="scSaveAutoCfg()">保存</button>
          <span class="hint" id="scAutoCfgMsg"></span>
        </div>
      </div>

      <!-- ★ v5.4.4：连续采集（常驻守护）控制面板 -->
      <div class="card">
        <h2>🔁 连续采集（常驻守护）</h2>
        <div class="hint" style="margin:-4px 0 12px">
          不再「定时一批批采集」，而是<b>常驻运行、一页页一个个不停歇地采集</b>：
          按 ID 升序逐条遍历，每条先补图、再补资料，速度可控；扫完回到开头循环，已采集的自动跳过，永不停止。
          守护由「每分钟计划任务」自动托管拉起，无需手动 nohup；点下面按钮可即时启停。
        </div>

        <div class="sc-auto" id="scLoop">
          <div class="sc-auto-head">
            <b>🔁 连续采集守护</b>
            <span class="sc-auto-dot" id="scLoopDot"></span>
            <span class="sc-auto-state" id="scLoopState">读取中…</span>
            <button type="button" class="ghost sm" onclick="scLoadLoop()">刷新状态</button>
          </div>
          <div class="sc-auto-body" id="scLoopBody"></div>
          <div class="sc-auto-cfg">
            <label><input type="checkbox" id="scLoopEnabled"> 启用连续采集</label>
            <label><input type="checkbox" id="scLoopMinutely"> 同时保留旧「每分钟批量」</label>
            <label>每条间隔 <input type="number" id="scLoopDelay" min="0" max="5000" value="350" style="width:88px"> 毫秒（0 = 不限速）</label>
            <button type="button" class="ghost sm" onclick="scSaveLoopCfg()">保存配置</button>
            <span class="hint" id="scLoopCfgMsg"></span>
          </div>
          <div class="sc-auto-cfg" style="margin-top:8px">
            <button type="button" onclick="scLoopStart()">▶ 立即启动</button>
            <button type="button" class="ghost" onclick="scLoopStop()">⏹ 停止</button>
          </div>
        </div>
      </div>

      <!-- ★ v5.0.9：三个可点统计卡（待处理 / 已处理 / 未处理） -->
      <div class="sc-stats" id="scStatsImages">
        <button type="button" class="sc-stat" onclick="scList('images','pending')">
          <b id="scPendNum">-</b><span>待处理（缺图）</span><em>点击看清单</em>
        </button>
        <button type="button" class="sc-stat" onclick="scList('images','done')">
          <b id="scDoneNum">-</b><span>已处理（图齐全）</span><em>点击看清单</em>
        </button>
        <button type="button" class="sc-stat sc-warn" onclick="scList('images','unprocessed')">
          <b id="scUnpNum">-</b><span>未处理（还没补成过）</span><em>点击看清单</em>
        </button>
      </div>

      <div class="local-sub">
        <div class="local-3col">
          <div class="form-row" style="margin:0">
            <label>类型筛选</label>
            <select id="scrapeType" onchange="scReloadStats('images'); scReloadStats('enrich');">
              <option value="">全部</option>
              <option value="movie">电影</option>
              <option value="tv">电视剧</option>
              <option value="anime">动漫</option>
              <option value="short">短剧</option>
              <option value="variety">综艺</option>
              <option value="game">游戏</option>
              <option value="book">书籍</option>
              <option value="music">音乐</option>
              <option value="other">其他</option>
            </select>
          </div>
          <div class="form-row" style="margin:0">
            <label>每次处理数量（10 ~ 500）</label>
            <input type="number" id="scrapeLimit" value="50" min="10" max="500">
          </div>
          <div class="form-row" style="margin:0">
            <label>失败数量（最近一次）</label>
            <div id="scrapeFailed" class="scrape-stat zero">0</div>
          </div>
        </div>
        <div class="local-actions">
          <button id="scrapeGo" onclick="startScrape()">▶ 开始刮削（补图）</button>
          <button class="ghost" onclick="clearScrapeResult()">清除结果</button>
          <button class="ghost" onclick="scReloadStats('images')">刷新统计</button>
        </div>
        <div id="scrapeStatus" class="scrape-status"></div>
      </div>
      <div id="scrapeLog" class="scrape-log" style="display:none"></div>
    </div>

    <!-- ★ v5.0.9：资料补全（简介 / 导演 / 演员 / 年份 / 评分 / 类型） -->
    <div class="card">
      <h2>📝 资料补全</h2>
      <div class="hint" style="margin:-4px 0 12px">给<b>已上架的老条目</b>也补上简介、导演、演员这些资料。
        数据源：<b>TMDB 优先</b>（一次拿全简介 + 演职员 + 年份 + 评分 + 类型），
        TMDB 查不到时<b>回退百度百科</b>补简介；百科只补简介，不会硬解析演员（避免把错的演职员写进库）。</div>

      <div class="sc-stats" id="scStatsEnrich">
        <button type="button" class="sc-stat" onclick="scList('enrich','pending')">
          <b id="scEPendNum">-</b><span>待处理（缺资料）</span><em>点击看清单</em>
        </button>
        <button type="button" class="sc-stat" onclick="scList('enrich','done')">
          <b id="scEDoneNum">-</b><span>已处理（资料齐全）</span><em>点击看清单</em>
        </button>
        <button type="button" class="sc-stat sc-warn" onclick="scList('enrich','unprocessed')">
          <b id="scEUnpNum">-</b><span>未处理（没补成过）</span><em>点击看清单</em>
        </button>
      </div>

      <div class="local-sub">
        <div class="sc-fields" id="scFields">
          <label class="sc-chk"><input type="checkbox" value="overview" checked>简介</label>
          <label class="sc-chk"><input type="checkbox" value="director" checked>导演</label>
          <label class="sc-chk"><input type="checkbox" value="cast" checked>演员</label>
          <label class="sc-chk"><input type="checkbox" value="year" checked>年份</label>
          <label class="sc-chk"><input type="checkbox" value="rating" checked>评分</label>
          <label class="sc-chk"><input type="checkbox" value="genres" checked>类型</label>
        </div>
        <div class="local-actions">
          <button id="enrichGo" onclick="startEnrich()">▶ 开始补资料</button>
          <span class="hint" id="enrichLimitWrap">每次处理
            <input type="number" id="enrichLimit" value="50" min="1" max="500" style="width:88px"> 条</span>
          <button class="ghost" onclick="scReloadStats('enrich')">刷新统计</button>
        </div>
        <div id="enrichStatus" class="scrape-status"></div>
      </div>
      <div id="enrichLog" class="scrape-log" style="display:none"></div>
    </div>
  </div>

  <!-- ★ v5.0.9：明细列表弹窗（统计数字点进去看的就是它） -->
  <div class="modal" id="scListModal">
    <div class="box">
      <h3 id="scListTitle">清单</h3>
      <div class="hint" id="scListHint" style="margin:-6px 0 10px"></div>
      <div id="scListBody" style="max-height:62vh;overflow:auto"></div>
      <div class="row" style="margin-top:16px;justify-content:flex-end">
        <button type="button" class="ghost" onclick="scListClose()">关闭</button>
      </div>
    </div>
  </div>
