媒资资料库（media-library）· GitHub 推送说明
================================================
生成时间：2026-09-24 20:24
仓库地址：https://github.com/songliannv/media-library
可见性　：Public（公开）
所有者　：songliannv
当前版本：v3.8.0（2026-09-28）


一、仓库定位（长期规则）
------------------------------------------------
本仓库是【源码快照】：仓库内源码 = 混淆后的最新发布版本（当前 v3.8.0）。
最新明文源码在本地【原始源码/】，是唯一的改动来源，不入 GitHub。

GitHub 上只放两类东西：
  ① 发布版本/** 全树（混淆后源码，仓库根 = 站点根，克隆即可部署）
  ② 几份 TXT / MD 说明文档

zip 不进 main 分支 —— 打包产物只作为 GitHub Release 的 assets 上传
（用 .workbuddy/tools/gh_release.py）。


二、本次改了什么（v2.2.3 · 三合一增强）
------------------------------------------------
① 修：详情页演员阵容不显示
   v2.1.1 起 Tmdb::normalize 已从 credits 抽取演员到 extra.cast，
   但 Views::castBlock 读的是 payload.credits.cast —— 两条路径不通，
   前台永远看不到演员。v2.2.3 改成优先读 extra.cast，保留 payload 兜底。
   升级后需后台「生成静态页」刷新已生成的 /uisc/*.html 内页。

② 新增：每小时自动采集可配置
   后台「同步采集」页新增两个开关：
     · 每小时自动采集（开/关）—— 关闭后 cron 计划任务会跳过
     · 每小时采集条数（默认 5，1~50）—— 仅作用于计划任务，
       不影响后台「立即同步」用的「每源条数」
   宝塔计划任务照旧每小时跑一次 cron/sync_hourly.php，
   现在会根据后台配置自动决定采不采、采多少条。

③ 新增：标签详情路由 /tag/{slug}
   详情页里的标签胶囊改为可点击链接，点进去列出该标签绑定的全部条目（跨源）。
   例如 /tag/剧情、/tag/%E7%9F%AD%E5%89%A7、/tag/Sci-Fi%20%26%20Fantasy。
   与首页「?tag=xxx」共用同一套查询，只是 URL 永久、易 SEO。
   首页标签云链接也统一从 /?tag=xxx 改为 /tag/xxx。


三、仓库结构（单一分支 main）
------------------------------------------------
分支    内容                                        文件数  commit
-----   -----------------------------------------   ------  ------------
main    混淆源码全树 + TXT / MD 文档               62      e9cef82

仓库根 = 站点根（宝塔面板直接指向，clone 完就能跑）。
不再维护 source 分支：明文源码留在本地「原始源码/」，不入 GitHub。

  拿源码：
      git clone https://github.com/songliannv/media-library.git
      cd media-library
      # 直接把这个目录配成站点根


四、main 分支里有什么（62 个 blob）
------------------------------------------------
根目录文件：
  .gitignore
  .htaccess
  CHANGELOG.md          —— v1.0.0 ~ v2.2.3 完整历史
  DEPLOY.md             —— 宝塔面板部署手册
  README.md             —— 项目概览、快速开始、目录结构
  home.php
  index.php             —— ML_APP_VERSION = '2.2.0'
  install.php
  track.php
  user.php
  功能说明.txt
  安装说明.txt
  资料索引.md

子目录（文件数）：
  adapters/      4      —— Tmdb / HongguoDuanju（v2.1.2 全量重写）/ Douban / RAWG 等
  admin/         1
  api/           2      —— unified.php 新增 crosslink 两个端点
  config/        3      —— sample / install.lock 占位
  core/          23     —— 含 v2.1.3 新增的 CrossLink.php
  cron/          5
  sql/           9
  tools/         2
  uploads/       1

不在 main 里（有意排除）：
  · zip 打包产物 —— 走 GitHub Release assets（用 gh_release.py 上传）
  · 明文源码 —— 保留在本地「原始源码/」
  · 诊断/排障工具 —— 放在本地「软件素材/」，不进仓库也不进发布包
      __diag.php / __emergency_reset.php / __migrate_189.php / check.php
  · 敏感文件 —— config/config.php / install.lock / uisc/ / uisc-assets/


五、发布版本三条硬规则（长期有效）
------------------------------------------------
① 不含任何测试 / 诊断脚本
   __diag.php、__emergency_reset.php、__migrate_189.php、check.php 等排障工具
   一律放本地「软件素材/」，需要时单独上传、用完即删；
   不得出现在 发布版本/、发布包/、升级包/ 里，也不得进 GitHub main 分支。

② PHP 全部经过免费防破解混淆
   工具：.workbuddy/obfuscate_194.mjs（Node + php-parser 自研，免费，不买商业混淆器）
   手段：去注释 + 压缩空白 + 局部变量改名（仅函数 / 方法 / 闭包作用域内）
   不动：类名 / 函数名 / 方法名 / 常量 / 字符串 / 类属性名
   本次结果：46 个 PHP 文件混淆、45 个局部变量改名、注释全清

③ 明文 → 混淆只能全量覆盖升级，不能逐文件增量
   不能把明文与混淆文件混在一起（会把混淆文件里的旧代码暴露给明文版本）
   升级包内容 = 整包内容 + 两份说明；不是 diff 补丁


六、本地目录布局（五目录归档，扁平化）
------------------------------------------------
  F:/Loomy Workspace/万能资源库/（2026-09-26 起为接管主库；原 F:/WorkBuddy/万能资料库 转为只读历史归档）
  ├── 原始源码/                       ← 唯一改动来源（明文），不入库
  │     ├── adapters/  api/  admin/  core/  cron/
  │     ├── config/  sql/  tools/  preview/
  │     ├── index.php  home.php  install.php  track.php  user.php
  │     └── DEPLOY.md  README.md  安装说明.txt  功能说明.txt
  ├── 发布版本/                       ← 【本次上传到 GitHub 的目录】
  │     └── adapters/ admin/ api/ config/ core/ cron/ sql/ tools/ uploads/
  │         + index.php / home.php / install.php / track.php / user.php / .htaccess
  │         共 56 个 PHP / 配置文件，全部已混淆（46 个 PHP）
  ├── 发布包/                         ← 整包 zip（v1.0.0 ~ v2.2.3，共 21 个）
  │     └── media-library-api-v2.2.3.zip    181.5 KB
  ├── 升级包/                         ← 增量升级包
  │     └── media-library-api-update-v2.2.1-to-v2.2.3.zip    185.8 KB
  ├── 软件素材/                       ← 诊断 / 排障 / 预览素材（不入库）
  ├── 资料索引.md                     ← 总索引
  └── GitHub推送说明.txt              ← 本文件

五个业务目录一律不套项目名子目录，文件直接放在目录下：
  · 对：发布包/media-library-api-v2.2.3.zip
  · 错：发布包/media-library-api/media-library-api-v2.2.3.zip


七、Release v2.2.3（本次新增）
------------------------------------------------
地址　　　：https://github.com/songliannv/media-library/releases/tag/v2.2.3
Release ID：395654918
目标 commit：e9cef82c4a48...（main HEAD，v2.2.3 源码快照）
附件　　　：2 个
  media-library-api-v2.2.3.zip                        185,904 B  181.5 KB
      下载：https://github.com/songliannv/media-library/releases/download/v2.2.3/media-library-api-v2.2.3.zip
  media-library-api-update-v2.2.1-to-v2.2.3.zip       190,289 B  185.8 KB
      下载：https://github.com/songliannv/media-library/releases/download/v2.2.3/media-library-api-update-v2.2.1-to-v2.2.3.zip

zip 只走 Release assets，不进 main 分支（之前 v2.1.3 的 2 个 zip 早已移除）。


八、校验结果（推送后实测，全部通过）
------------------------------------------------
· 仓库可访问、可见性 Public、默认分支 main                              —— 通过
· 远端分支：仅 main（source 已删除）                                    —— 通过
· main 远端 blob 数 = 62                                                —— 通过
· main 里没有 zip                                                       —— 通过
· 关键文件远端存在（逐一比对）                                          —— 通过
    README.md / CHANGELOG.md / DEPLOY.md                                —— 存在
    安装说明.txt / 功能说明.txt / 资料索引.md                          —— 存在
    core/Views.php（v2.2.3 修改 castBlock + tag 胶囊链接化）             —— 存在
    core/Settings.php（v2.2.3 新增 sync_hourly_* 两个键）                —— 存在
    cron/sync_hourly.php（v2.2.3 读 Settings 决定跑与不跑）              —— 存在
    index.php（v2.2.3 新增 /tag/{slug} 路由）                             —— 存在
    core/CrossLink.php（v2.1.3 新增）                                    —— 存在
    adapters/HongguoDuanju.php（v2.1.2 重写）                             —— 存在
· Release v2.2.3 已创建，id=395654918，附 2 个 zip 资产                 —— 通过
· 敏感文件不在仓库：config.php / install.lock / uisc/                   —— 通过
· 排障脚本不在仓库：__diag.php / check.php / __emergency_reset.php      —— 通过
· PHP 混淆校验：46 文件、45 改名、注释清理                              —— 通过
· validate_php_194.mjs（50/50 pass）                                    —— 通过
· validate_js_194b.mjs（admin/index.php 2/2 pass）                       —— 通过
· validate_contract_194.mjs（Tmdb / HongguoDuanju 签名兼容）             —— 通过

推送到 GitHub 的通道：
  本次 git push 成功（网络正常，未走 Git Data API 兜底）。
  commit sha 与本地一致：e9cef82c4a48...


九、以后怎么更新
------------------------------------------------
工作区里有幂等同步脚本，改完源码后直接跑：

    python .workbuddy/tools/sync-source-to-github.py          # 同步并推送
    python .workbuddy/tools/sync-source-to-github.py --dry    # 只看有没有变化

它会：把 发布版本/** 全树 + 7 份 TXT/MD 文档构建成 main；
先试 git push，不通就自动降级到 GitHub Git Data API；
不再维护 source 分支，也不再往 main 里塞 zip。

创建 Release（附 zip 资产）：
    python .workbuddy/tools/gh_release.py <tag> <commit> <title> <body_file> <zip1> [zip2 ...]

例如：
    python .workbuddy/tools/gh_release.py v2.2.3 main "v2.2.3 · 三合一增强" \
           .workbuddy/tools/release_v220_body.md \
           发布包/media-library-api-v2.2.3.zip \
           升级包/media-library-api-update-v2.2.1-to-v2.2.3.zip

注意：gh_release.py 的 target 参数传分支名（如 main）而不是短 SHA，
否则 GitHub API 会返回 "tag_name is not a valid tag" 422。

发版四步走（v2.2.3 起固定）：
  1. 改「原始源码/」明文 → 升 index.php / install.php / README.md /
     安装说明.txt / 功能说明.txt / CHANGELOG.md 的版本号到 vX.Y.Z
  2. 跑混淆：node .workbuddy/obfuscate_194.mjs 原始源码 发布版本 "$EXCL"
     （EXCL 里排除 preview / uisc / config.php / check.php / __diag.php 等）
  3. 打包：python .workbuddy/build_release_194.py
     （先改脚本顶部 VER / OLD_VER，再跑）
  4. 推送 + Release：
     python .workbuddy/tools/sync-source-to-github.py
     python .workbuddy/tools/gh_release.py vX.Y.Z main "标题" body.md zip1 zip2


十、命令速查
------------------------------------------------
克隆源码        git clone https://github.com/songliannv/media-library.git
看远端分支      git ls-remote https://github.com/songliannv/media-library.git
Release 列表    https://github.com/songliannv/media-library/releases
下载 v2.2.3 包  https://github.com/songliannv/media-library/releases/download/v2.2.3/media-library-api-v2.2.3.zip
下载升级包      https://github.com/songliannv/media-library/releases/download/v2.2.3/media-library-api-update-v2.2.1-to-v2.2.3.zip
取某个文件      https://raw.githubusercontent.com/songliannv/media-library/main/home.php
仓库主页        https://github.com/songliannv/media-library
