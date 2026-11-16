<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
  <div id="tab-local" class="tabpane" style="display:none">
    <div class="card">
      <div class="local-head">
        <h2>📥 资源导入</h2>
        <div class="subtabs" id="localSubtabs">
          <button class="stab active" data-stab="paste" onclick="switchLocalTab('paste')">📋 粘贴解析</button>
          <button class="stab" data-stab="manual" onclick="switchLocalTab('manual')">✏️ 手动输入</button>
          <button class="stab" data-stab="excel" onclick="switchLocalTab('excel')">📊 Excel 导入</button>
        </div>
      </div>
    </div>
  </div>

