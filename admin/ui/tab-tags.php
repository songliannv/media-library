<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-tags" class="tabpane" style="display:none">
    <div class="tag-hint">
      <button class="tag-refresh" id="tagRefreshBtn" onclick="refreshTagPage()" title="绕过文件缓存，直查数据库重新统计（标签 + 导演/演员一起刷）">🔄 强制刷新</button>
      <b>🏷️ 标签项目</b> — 这里列出全部标签及其对应条目数。点击标签可查看该标签下的所有条目，或在条目管理中按标签筛选。<b>标签列表有 300 秒文件缓存</b>，若与条目实际不符，点右上角「🔄 强制刷新」直查数据库。<br>
      <span class="tag-stat" id="tagStatLine">正在加载…</span>
    </div>
    <!-- ★ v3.6.8：三个项目顺序 —— 常规标签（人名已被过滤）→ 热门导演 → 热门演员 -->
    <!-- ★ v4.4.1：导演/演员区块保留（用户确认「进标签时挡住它们」正是要求的行为）；本轮只修两点：
         ① 统计行「共 N 个标签」改为 标签+导演+演员 全量计数，与页面卡片数一致；
         ② 点导演/演员不再跳条目管理，改为与标签一致的「就地展开作品列表」 -->
    <div class="tag-group" id="tagGroupWrap">
      <div class="tag-group-title">🏷️ 常规标签</div>
      <div class="tag-grid" id="tagGrid"></div>
    </div>
    <!-- 热门导演 / 热门演员：卡片样式与标签网格一致（名字 + 数量徽章） -->
    <div class="people-cloud" id="peopleCloud">
      <div class="people-row" id="peopleRowDir" style="display:none">
        <div class="tag-group-title">🎬 热门导演</div>
        <div class="people-list" id="peopleListDir"></div>
      </div>
      <div class="people-row" id="peopleRowAct" style="display:none">
        <div class="tag-group-title">⭐ 热门演员</div>
        <div class="people-list" id="peopleListAct"></div>
      </div>
      <div class="hint" id="peopleEmpty" style="display:none">暂无演职员数据 —— 同步 / 拉详情后自动充实，老数据可先跑 tools/backfill_people_tags.php 回填。</div>
      <!-- ★ v4.6.9：人物云为空时的诊断面板 —— 直接把四个计数摊开，
           分清「索引没建 / 数据只在暂存 / extra 里人名被清空」，并给一键重建入口 -->
      <div class="card" id="peopleDiagCard" style="display:none;margin-top:12px">
        <h3 style="margin:0 0 6px;font-size:14px">🩺 人物数据诊断</h3>
        <div id="peopleDiagBox"><div class="hint">正在检测…</div></div>
        <div class="row" style="margin-top:10px">
          <button class="ghost sm" onclick="rebuildPersonIndex(this)">重建人物索引</button>
          <button class="ghost sm" onclick="refreshTagPage()">重新检测</button>
          <span class="hint" id="peopleDiagMsg"></span>
        </div>
      </div>
    </div>
    <div class="card" id="tagDetailCard" style="display:none">
      <h3 style="margin:0 0 4px;font-size:15px" id="tagDetailTitle"></h3>
      <span class="hint" id="tagDetailHint"></span>
      <div class="toolbar" style="margin-top:10px">
        <button class="ghost sm" onclick="closeTagDetail()">← 返回标签列表</button>
        <button class="ghost sm" onclick="goItemsWithTag()">在条目管理中查看 →</button>
      </div>
      <div class="tag-list" id="tagItemList"></div>
    </div>
  </div>
  <!-- ★ v4.9.5：「系统设置」原本是顶层独立 tab（与「站点设置」并列），
       用户要求把它移进「站点设置」里 ⇒ 顶层 tab 已删除，
       内容降级为站点设置页签栏里的第一个二级页签 data-stab="sys"。
       ★ 两套保存逻辑保持独立（saveSettings/resetAllSettings 走 app_settings 全量，
         saveSite/resetSite 按 SITE_GROUPS 分组增量），**不要合并** ——
         站点设置的草稿收集 collectSiteDraft() 只认 siteKeys()，
         若把系统字段塞进去会导致 Key/网盘类型被空值覆盖。 -->
