<?php
if(!defined('ML_ADMIN_UI')&&!defined('ML_ADMIN_UI_CLOSED')){http_response_code(404);exit;}?>
<div class="modal" id="panQrModal">
  <div class="box" style="max-width:380px;text-align:center">
    <h3 id="panQrTitle" style="margin-top:0">扫码登录</h3>
    <div id="panQrImg" style="display:flex;justify-content:center;align-items:center;min-height:216px;margin:10px 0"></div>
    <div id="panQrStatus" style="font-weight:600;min-height:22px"></div>
    <div class="hint" id="panQrHint" style="margin-top:6px"></div>
    <div class="row" style="margin-top:16px">
      <button type="button" class="ghost" onclick="panQrClose()">关闭</button>
      <button type="button" class="ghost" onclick="panQrRefresh()">刷新二维码</button>
    </div>
  </div>
</div>

<div class="modal" id="panDirModal">
  <div class="box" style="max-width:480px">
    <h3 id="panDirTitle" style="margin-top:0">选择网盘目录</h3>
    <div class="pan-dir-crumb" id="panDirCrumb"></div>
    <div class="pan-dir-list" id="panDirList"><div class="hint" style="padding:18px;text-align:center">加载中…</div></div>
    <div class="pan-dir-cur" id="panDirCur"></div>
    <div class="hint" id="panDirHint" style="margin-top:6px"></div>
    <div class="row" style="margin-top:16px">
      <button type="button" class="ghost" onclick="panDirClose()">取消</button>
      <button type="button" onclick="panDirConfirm()">选用当前目录</button>
    </div>
  </div>
</div>

<div class="modal" id="modal">
  <div class="box">
    <h3 id="modalTitle" style="margin-top:0">编辑条目</h3>
    <label>分类（决定新增表单字段）</label>
    <select id="e_type"></select>

    <label>标题 *</label><input id="e_title">
    <label>原标题</label><input id="e_original_title">
    <div class="grid2">
      <div><label>年份</label><input id="e_year" type="number"></div>
      <div><label>评分</label><input id="e_rating" type="number" step="0.1"></div>
    </div>
    <label>类型标签（逗号分隔，如 动作,悬疑）</label><input id="e_genres" placeholder="动作, 悬疑">
    <label>海报 URL</label><input id="e_poster">
    <label>背景图 URL</label><input id="e_backdrop">
    <label>下载链接（可添加多种网盘，如 光鸭 / 夸克；直接粘贴地址，可留空）</label>
    <div id="dlRows"></div>
    <button type="button" class="ghost sm" onclick="addDlRow()">+ 添加下载链接</button>
    <label>简介</label><textarea id="e_overview"></textarea>

    <div id="extraFields"></div>

    <div class="row">
      <button class="ghost" onclick="closeModal()">取消</button>
      <button onclick="saveEdit()">保存</button>
    </div>
    <div class="form-row" style="margin-top:16px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" id="e_downloadImages" value="1">
        <span>自动下载海报/背景图到服务器本地</span>
      </label>
    </div>
  </div>
</div>

<!-- 修改密码 / 邮箱（右上角「修改密码」按钮打开） -->
<div class="modal" id="pwdModal">
  <div class="box" style="max-width:470px">
    <h3 style="margin-top:0">修改密码</h3>
    <p class="hint" style="margin:0 0 4px">改完立即生效，下次请用新密码登录。邮箱用于「找回密码」，建议填写。</p>
    <?php
$__myEmail=(string)$ME['email'];if($__myEmail===\Core\User::ADMIN_TAG){$__myEmail='';}?>
    <label>账号邮箱（找回密码用，可留空）</label>
    <input id="pw_email" type="email" value="<?php echo htmlspecialchars($__myEmail,ENT_QUOTES,'UTF-8');?>" placeholder="you@example.com">
    <label>当前密码（改密码时必填）</label>
    <input id="pw_old" type="password" placeholder="输入当前登录密码" autocomplete="current-password">
    <label>新密码（不改密码就留空）</label>
    <input id="pw_new" type="password" placeholder="至少 6 位" autocomplete="new-password">
    <label>确认新密码</label>
    <input id="pw_new2" type="password" placeholder="再输一次" autocomplete="new-password">
    <div id="pwMsg" class="set-msg"></div>
    <div class="row">
      <button class="ghost" onclick="closePwd()">取消</button>
      <button onclick="savePwd()">保存修改</button>
    </div>
  </div>
</div>

<!-- ★ v5.3.5 API 令牌设置弹窗：新建不再弹 prompt，参数一律在这里调 -->
<div class="modal" id="tkSetModal">
  <div class="box" style="max-width:470px">
    <h3 id="tkSetTitle" style="margin-top:0">令牌设置</h3>
    <label>名称（用途说明）</label>
    <input id="tkSetName" placeholder="例：客户A / 某某App">
    <label>每日调用上限（0 = 不限）</label>
    <input id="tkSetLimit" type="number" min="0" step="1" placeholder="0">
    <label>IP 白名单（逗号分隔，留空 = 不限）</label>
    <input id="tkSetIp" placeholder="例：1.2.3.4, 5.6.7.8">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:14px">
      <input type="checkbox" id="tkSetEnabled" value="1">
      <span>启用该令牌（取消勾选 = 立即失效）</span>
    </label>
    <div id="tkSetMsg" class="set-msg"></div>
    <div class="row">
      <button class="ghost" onclick="closeTkSet()">取消</button>
      <button onclick="saveTkSet()">保存</button>
    </div>
  </div>
</div>

<!-- ★ v5.3.5 API 令牌新建成功：直接展示明文 + 一键复制（明文仅此处可见一次） -->
<div class="modal" id="tkNewModal">
  <div class="box" style="max-width:520px">
    <h3 style="margin-top:0">✅ 令牌已创建</h3>
    <p class="hint" style="margin:0 0 10px">令牌已自动命名。下方是完整明文，<b>请立即复制保存</b>（关闭后仍可在列表点「复制」取回）。</p>
    <input id="tkNewPlain" readonly style="font-family:monospace">
    <div class="row">
      <button class="ghost" onclick="closeTkNew()">关闭</button>
      <button onclick="copyTkNew()">复制令牌</button>
    </div>
  </div>
</div>

<!-- 本地导入弹窗（v3.3.1：点击子标签弹出居中窗口） -->
<div class="local-overlay" id="localOverlay">
  <div class="local-box">
    <div class="local-modal-head">
      <h3 id="localModalTitle" style="margin:0">资源导入</h3>
      <button class="ghost sm" onclick="closeLocalModal()">✕</button>
    </div>
    <!-- 粘贴解析 -->
    <div id="local-paste" class="local-sub">
      <label>粘贴分享内容（夸克 / 百度 / 迅雷 / 磁力等网盘分享文本）</label>
      <textarea id="lpText" rows="8" placeholder="例如：&#10;「电影名」https://pan.quark.cn/s/xxx&#10;资源名 https://pan.baidu.com/s/xxx?pwd=abcd"></textarea>
      <div class="local-type-row">
        <label>默认分类：</label>
        <select id="lpType"></select>
        <span class="hint">无法识别分类的资源会落到这里</span>
      </div>
      <div class="local-actions">
        <button onclick="localParse()">🔍 识别解析</button>
      </div>
      <!-- v4.7.2：操作指令上移到「识别解析」之后，与 Excel 导入标签保持一致 -->
      <div id="lpImportRow" class="local-actions" style="display:none">
        <button id="lpCheckBtn" class="ghost" onclick="localCheckLinks('LP_ITEMS')">🔎 检测失效</button>
        <button onclick="localImportParsed()">📥 导入 <span id="lpCount">0</span> 条</button>
        <button class="ghost" onclick="localParseClear()">清空重来</button>
      </div>
      <div id="lpFilterRow" class="local-filter" style="display:none"></div>
      <div id="lpResult" class="lpv"></div>
    </div>
    <!-- 手动输入 -->
    <div id="local-manual" class="local-sub" style="display:none">
      <div class="form-row"><label>资源名称 *</label><input id="lmTitle" placeholder="例：顶楼 第五季 4K 全集"></div>
      <div class="form-row"><label>资源地址 *</label><input id="lmUrl" placeholder="网盘 / 磁力 / 任意链接，例：https://pan.quark.cn/s/xxx"></div>
      <div class="local-2col">
        <div class="form-row"><label>分类</label><select id="lmType"></select></div>
        <div class="form-row"><label>封面图片 URL</label><input id="lmPoster" placeholder="可选，封面图地址"></div>
      </div>
      <div class="form-row"><label>资源介绍</label><textarea id="lmOverview" rows="3" placeholder="可选，一两句话介绍这个资源"></textarea></div>
      <div class="form-row"><label>关键字</label><input id="lmGenres" placeholder="可选，逗号分隔，如 电影, 高清"></div>
      <div class="local-actions">
        <button onclick="localManualSubmit()">📥 提交入库</button>
      </div>
    </div>
    <!-- Excel 导入 -->
    <div id="local-excel" class="local-sub" style="display:none">
      <div class="ext-note" style="margin:0 0 14px">📋 <b>表格列顺序</b>：第 1 列 资源名称 · 第 2 列 分享链接 · 第 3 列 分类(可空) · 第 4 列 账号(可空) · 第 5 列 简介(可空)。<br>优先使用 .xlsx（也支持 .csv）；旧版 .xls 二进制格式请先在 Excel 里「另存为 .xlsx」。</div>
      <div class="form-row"><label>选择文件</label><input type="file" id="leFile" accept=".xlsx,.xls,.csv"></div>
      <div class="local-type-row" style="margin-top:0">
        <label>默认分类：</label>
        <select id="leType"></select>
        <span class="hint">表格第 3 列为空时使用</span>
      </div>
      <div class="local-actions">
        <button onclick="localExcelParse()">🔍 解析文件</button>
      </div>
      <!-- v4.7.2：操作指令（检测失效 / 导入 N 条 / 清空重来）上移到「解析文件」按钮之后，
           长列表（上千条）时无需滚到底部即可操作，与解析入口同屏可见 -->
      <div id="leImportRow" class="local-actions" style="display:none">
        <button id="leCheckBtn" class="ghost" onclick="localCheckLinks('LE_ITEMS')">🔎 检测失效</button>
        <button onclick="localExcelImport()">📥 导入 <span id="leCount">0</span> 条</button>
        <button class="ghost" onclick="localExcelClear()">清空重来</button>
      </div>
      <div id="leFilterRow" class="local-filter" style="display:none"></div>
      <div id="leResult" class="lpv"></div>
    </div>
  </div>
</div>
