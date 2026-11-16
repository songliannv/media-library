<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-site" class="tabpane" style="display:none">
    <div class="card">
      <div class="subtabs" id="siteSubtabs">
        <!-- ★ v4.9.5：原顶层「系统设置」tab 已移到这里，成为第一个二级页签 -->
        <button class="stab" data-stab="sys"    onclick="switchSiteTab('sys')">系统设置</button>
        <!-- ★ v4.9.6：原顶层「同步采集」tab 已移到这里，排在系统设置之后 -->
        <button class="stab" data-stab="sync"   onclick="switchSiteTab('sync')">同步采集</button>
        <button class="stab active" data-stab="basic"  onclick="switchSiteTab('basic')">基础设置</button>
        <button class="stab" data-stab="seo"    onclick="switchSiteTab('seo')">SEO 设置</button>
        <!-- ★ v5.2.5：「前台显示」页签整组取消（明暗 / 榜单 / 最新列表 / 顶部外链
             全部退场，前台行为改为代码内固定；详见 core/Settings.php::groupMaps 注释） -->
        <button class="stab" data-stab="pan"    onclick="switchSiteTab('pan')">添加网盘 · Cookie获取地址</button>
        <button class="stab" data-stab="pansou" onclick="switchSiteTab('pansou')">接口配置</button>
        <button class="stab" data-stab="req"    onclick="switchSiteTab('req')">资源请求</button>
        <button class="stab" data-stab="mail"   onclick="switchSiteTab('mail')">邮件服务</button>
      </div>

      <!-- ★ v4.9.5：系统设置区（从原 tab-settings 整块搬来）。
           按钮区与站点设置的分开：系统设置保存走 saveSettings()（提交
           tmdb_api_key / adapters / pan_types 三项），站点设置走 saveSite()
           （提交 SITE_DRAFT 增量）。两套 API 语义不同，混用会互相覆盖。 -->
      <div id="sysBox" style="display:none">
        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
          <h3 style="margin:0;font-size:15px">系统设置</h3>
          <span class="hint">改动保存到数据库 <b>app_settings</b> 表并立即生效，<b>优先级高于 config/config.php</b>；留空 = 沿用 config.php 里的值。</span>
        </div>
        <div id="setBox" style="margin-top:14px"><div class="hint">正在加载…</div></div>
        <div class="row">
          <button class="ghost" onclick="resetAllSettings()">全部恢复为 config.php 的值</button>
          <button onclick="saveSettings()">保存设置</button>
        </div>
        <div id="setMsg" class="set-msg"></div>
      </div>

      <!-- ★ v4.9.6：同步采集区（从原 tab-sync 整块搬来，5 张卡片原样保留）。
           采集参数走 saveSyncParams() / SYNC_DRAFT，与站点设置的 SITE_DRAFT 是两套，
           与系统设置的 SET 也不同 ⇒ 三个区域互斥显示，各自保存按钮独立。 -->
      <div id="syncPane" style="display:none">
        <div class="card">
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0;font-size:15px">采集（同步热门）</h3>
            <span class="hint">只取各源「最热门 / 最多人看」的榜；<b>已采集过的一律跳过</b>；按各源官方要求限流，单次条数与请求数都有上限。</span>
          </div>
          <div class="row" style="justify-content:flex-start;margin-top:12px">
            <button id="btnSync" onclick="triggerSync(false, this)">▶ 立即采集</button>
            <button class="ghost" id="btnSyncForce" onclick="triggerSync(true, this)">强制采集（忽略最小间隔）</button>
            <button class="ghost" id="btnSyncDry" onclick="dryRun(this)">预演（只探测不入库）</button>
            <span id="syncState" class="hint"></span>
          </div>
          <div id="syncReport" style="margin-top:12px"></div>
        </div>

        <div class="card">
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0;font-size:15px">采集参数</h3>
            <span class="hint">保存到数据库 <b>app_settings</b>，立即生效；<b>后台网页与每小时计划任务共用这一套参数</b>。</span>
          </div>
          <div id="syncParams" style="margin-top:12px"><div class="hint">正在加载…</div></div>
          <div class="row">
            <button class="ghost" onclick="resetSyncParams()">恢复默认</button>
            <button onclick="saveSyncParams()">保存采集参数</button>
          </div>
          <div id="syncMsg" class="set-msg"></div>
        </div>

        <div class="card">
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0;font-size:15px">各源「官方要求」对照本次限制</h3>
            <span class="hint">下面每个数字都来自对应站点的接口文档；我们只会比它更保守，不会更激进。</span>
          </div>
          <div id="syncPlan" style="margin-top:12px"><div class="hint">正在加载…</div></div>
        </div>

        <div class="card">
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0;font-size:15px">计划任务（宝塔面板）</h3>
            <span class="hint">★ v5.2.0 起只要配下面 <b>2 条</b>：统一入口 + 每分钟刮削，8 个维护任务全部由统一入口自动调度。命令行脚本已加守卫，<b>用网址访问会 404</b>。</span>
          </div>
          <div id="cronBox" style="margin-top:12px"><div class="hint">正在加载…</div></div>
        </div>

        <div class="card">
          <h3 style="margin:0 0 10px;font-size:15px">最近采集记录</h3>
          <div id="syncRecent"><div class="hint">正在加载…</div></div>
        </div>
      </div>

      <div id="sitePane">
      <div id="siteBox"><div class="hint">加载中…</div></div>
      <div class="inline" style="margin-top:18px">
        <button id="siteSaveBtn" onclick="saveSite()">保存设置</button>
        <button class="ghost" onclick="resetSite()">恢复为配置文件的值</button>
        <button class="ghost" onclick="loadSeoPreview()">刷新 SEO 预览</button>
        <span id="siteMsg" class="hint"></span>
      </div>
      </div>
    </div>
  </div>

