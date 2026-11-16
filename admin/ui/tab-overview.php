<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-overview" class="tabpane">
    <!-- ★ v4.8.9：概览页重排 ——
         ① 「点击最多的 50 个条目」提到最上方（用户诉求：这块最常用，却埋在最下面）；
         ② 顶部原有的那排统计卡片与下方「内容总览统计」【重复】（总条目 / 暂存两项完全一致），
            现把前者【独有】的「类型分布 / 来源分布」并入「内容总览统计」卡片，
            重复的「总条目 / 暂存」只保留内容总览统计里那一处（口径更准：正式条目 / 暂存待刮削）。 -->
    <div class="card">
      <h3 style="margin:0 0 4px;font-size:15px">🔥 点击最多的 50 个条目</h3>
      <div class="cats" id="topCats" style="margin-top:8px;margin-bottom:8px">
        <button class="cat active" data-cat="" onclick="filterTopItems('')">全部</button>
        <button class="cat" data-cat="short" onclick="filterTopItems('short')">短剧</button>
        <button class="cat" data-cat="movie" onclick="filterTopItems('movie')">电影</button>
        <button class="cat" data-cat="tv" onclick="filterTopItems('tv')">电视剧</button>
        <button class="cat" data-cat="anime" onclick="filterTopItems('anime')">动漫</button>
        <button class="cat" data-cat="variety" onclick="filterTopItems('variety')">综艺</button>
        <button class="cat" data-cat="game" onclick="filterTopItems('game')">游戏</button>
        <button class="cat" data-cat="book" onclick="filterTopItems('book')">书籍</button>
        <button class="cat" data-cat="music" onclick="filterTopItems('music')">音乐</button>
        <button class="cat" data-cat="other" onclick="filterTopItems('other')">其他</button>
      </div>
      <div class="top-list" id="topItemsList"></div>
    </div>

    <!-- ★ v4.6.3 概览页「内容总览统计」（此前缺失，本版补回）
         ★ v5.2.2 瘦身：去掉年份跨度 / 标签总数 / 人物总数（标签人物在「标签项目」页已有），
           去掉零点击条目 / 条目均点击（均为派生值，看点击总量与有点击条目即可算出）。 -->
    <div class="card">
      <h3 style="margin:0 0 4px;font-size:15px">📊 内容总览统计</h3>
      <div class="hint" style="margin:0 0 10px;font-size:12.5px">条目规模、数据完整度与近期新增情况（只读统计，不影响任何数据）</div>

      <div class="stat-grid" id="ovStatsMain"></div>

      <!-- ★ v4.8.9：从原顶部那排卡片并入（去掉与之重复的「总条目 / 暂存」两项） -->
      <h3 style="margin:18px 0 6px;font-size:14px">📂 类型与来源分布</h3>
      <div class="hint" style="margin:0 0 8px;font-size:12.5px">各类型与各采集源的条目数量分布</div>
      <div class="stat-grid" id="stats"></div>

      <h3 style="margin:18px 0 6px;font-size:14px">🧩 数据完整度</h3>
      <div class="hint" style="margin:0 0 8px;font-size:12.5px">缺失项越多，条目在列表页/详情页的展示越单薄</div>
      <div class="stat-grid" id="ovStatsQuality"></div>

      <h3 style="margin:18px 0 6px;font-size:14px">🔥 流量与热度</h3>
      <div class="stat-grid" id="ovStatsTraffic"></div>
    </div>

    <!-- ★ v4.9.7 概览页「访问统计」（自建第一方统计，替代第三方统计代码）
         ★ 数据来源：page_views_daily（按天聚合）+ page_views 明细（近 7 天 Top）。
         ★ 与上面「流量与热度」的区分：那块是**条目维度**（多少条目有点击、总点击量），
           这块是**站点维度**（多少人来过、从哪来、用什么设备）。 -->
    <div class="card">
      <h3 style="margin:0 0 4px;font-size:15px">📈 访问统计</h3>
      <div class="hint" style="margin:0 0 10px;font-size:12.5px">
        本站自建统计，不依赖任何第三方。数据留在自己库里；明细保留 30 天、趋势保留 400 天（由计划任务自动清理）。
      </div>
      <div class="stat-grid" id="vsMain"></div>

      <h3 style="margin:18px 0 6px;font-size:14px">📈 近 30 天趋势</h3>
      <div id="vsTrend"></div>

      <div class="vs-2col">
        <div>
          <h3 style="margin:18px 0 6px;font-size:14px">🔗 来源渠道</h3>
          <div id="vsSrc"></div>
        </div>
        <div>
          <h3 style="margin:18px 0 6px;font-size:14px">📱 设备与系统</h3>
          <div id="vsDevice"></div>
        </div>
      </div>

      <div class="vs-2col">
        <div>
          <h3 style="margin:18px 0 6px;font-size:14px">🔥 热门页面（近 7 天）</h3>
          <div id="vsTopPaths"></div>
        </div>
        <div>
          <h3 style="margin:18px 0 6px;font-size:14px">🚪 入口页（近 7 天）</h3>
          <div id="vsEntry"></div>
        </div>
      </div>
    </div>
  </div>

