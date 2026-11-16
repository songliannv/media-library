<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
<aside class="admin-side" id="adminSide">
  <div class="side-brand">
    <div class="brand-main">媒资资料库</div>
    <div class="brand-sub">管理后台 <span class="sver">Ver <?php echo htmlspecialchars((string)ML_APP_VERSION,ENT_QUOTES,'UTF-8');?></span></div>
  </div>
  <nav class="side-nav">
    <div class="tab active" data-tab="overview" onclick="switchTab('overview')"><span class="nav-ic">📊</span>概览</div>
    <div class="tab" data-tab="items" onclick="switchTab('items')"><span class="nav-ic">🎬</span>条目管理</div>
    <div class="tab" data-tab="pending" onclick="switchTab('pending')"><span class="nav-ic">📥</span>条目暂存</div>
    <div class="tab" data-tab="tags" onclick="switchTab('tags')"><span class="nav-ic">🏷️</span>标签项目</div>
    <div class="tab" data-tab="site" onclick="switchTab('site')"><span class="nav-ic">⚙️</span>站点设置</div>
    <div class="tab" data-tab="request" onclick="switchTab('request')"><span class="nav-ic">📨</span>资源请求</div>
    <div class="tab" data-tab="apitoken" onclick="switchTab('apitoken')"><span class="nav-ic">🔑</span>API 调用</div>
    <div class="tab" data-tab="scrape" onclick="switchTab('scrape')"><span class="nav-ic">🤖</span>自动刮削</div>
    <div class="tab" data-tab="matching" onclick="switchTab('matching')"><span class="nav-ic">🔗</span>条目匹配</div>
    <div class="tab" data-tab="local" onclick="switchTab('local')"><span class="nav-ic">📁</span>资源导入</div>
    <div class="tab" data-tab="source" onclick="switchTab('source')"><span class="nav-ic">🔗</span>源码对接</div>
    <!-- ★ v5.2.9：「用户中心」已取消 —— 与「资源请求 → 注册用户」功能重复；
         用户详情（📋 详细）迁到资源请求页的用户列表里。 -->
  </nav>
  <div class="side-foot">
    <div class="me"><span class="hint">当前登录</span><b><?php echo htmlspecialchars((string)$ME['username'],ENT_QUOTES,'UTF-8');?></b></div>
    <form method="post" style="margin:0" action="<?php echo htmlspecialchars($ADMIN_URL,ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(ml_ad_csrf(),ENT_QUOTES,'UTF-8');?>">
      <input type="hidden" name="logout" value="1">
      <button type="submit" class="logout-btn">退出登录</button>
    </form>
  </div>
</aside>
