# media-library · GitHub 推送说明

> 生成时间：2026-09-24  
> 仓库地址：https://github.com/songliannv/media-library  
> 可见性：Public（公开）  
> 所有者：songliannv  
> 当前版本：v1.9.4

---

## 一、这次改了什么

### 1. 目录结构重整（v1.9.4 起）
本地归档采用**五目录扁平化**，一律不套 `media-library-api/` 中间层：

| 目录 | 放什么 |
|---|---|
| `原始源码/` | 唯一改动来源（明文可读可改） |
| `发布版本/` | 混淆后的发布版（可直接部署，打开即见） |
| `发布包/` | 整包 zip（首次安装用） |
| `升级包/` | 升级包 zip（给在跑的站点覆盖升级） |
| `软件素材/` | 排障脚本、预览页、截图等 |
| `GitHub/` | **本次新增** —— 专门用于 GitHub 仓库推送的文件 |

**GitHub 仓库结构**：
```
github 仓库根
├── index.php / home.php / install.php / ...（源码全树）
├── GitHub/README.md          ← 不是这个文件！
└── （无 README.md 在根）
```

**等等，上面的描述有误导。实际做法是**：
- `GitHub/` 目录里的内容 = **推送到 GitHub 的内容**
- 推送时把 `GitHub/` 里的文件复制到仓库根，再 commit + push
- 或者修改 `.gitignore` 把 `GitHub/` 排除，直接把 GitHub 里的文件当作仓库根文件

### 2. 发布版本三条硬规则（已写进项目规则）
1. **不含任何测试 / 诊断脚本**：`__diag.php`、`check.php`、`__emergency_reset.php` 等一律放 `软件素材/`
2. **PHP 全部免费防破解混淆**：去注释 + 压缩空白 + 局部变量改名；类名 / 方法名 / 常量 / 字符串 / 类属性名不动
3. **明文 → 混淆只能全量覆盖升级**：不能逐文件增量

### 3. 安装器会话 bug 修复
`install.php` 改用 `ml_session_boot()` 兜底，避免服务器 `session.save_path` 不可写时静默失败 → 令牌永远不匹配 → "页面已过期"。

---

## 二、推送流程

每次发版后，按以下步骤把内容同步到 GitHub：

```bash
cd "F:/WorkBuddy/万能资料库"

# 1. 把 GitHub/ 目录内容复制到 git 仓库根
rsync -av --delete GitHub/ .

# 2. 确认只有源码 + 门面文档，没有本地归档
git status --short | grep -v "^?" | head -20

# 3. 提交并推送
git add -A
git commit -m "v1.9.4: 同步 GitHub 推送内容"
git push origin main
```

**注意**：`.gitignore` 必须排除 `原始源码/` `发布版本/` `发布包/` `升级包/` `软件素材/` `GitHub/` 这六个本地归档目录，只保留源码文件 + 门面文档。

---

## 三、.gitignore 配置

```gitignore
# 本地归档目录（不进仓库）
原始源码/
发布版本/
发布包/
升级包/
软件素材/
GitHub/

# 工作目录
.workbuddy/
资料索引.md
GitHub推送说明.txt
nul

# Python
__pycache__/
*.pyc
*.log

# 系统
Thumbs.db
.DS_Store
```

---

## 四、仓库结构（推送后）

```
media-library/              ← GitHub 仓库根
├── .gitignore
├── README.md               ← 门面文档（告诉用户这是什么）
├── DEPLOY.md               ← 部署文档
├── CHANGELOG.md            ← 版本记录
├── 安装说明.txt             ← 中文安装指南
├── 功能说明.txt             ← 中文功能清单
├── index.php
├── home.php
├── install.php
├── user.php
├── admin/
├── api/
├── core/
├── adapters/
├── config/
├── cron/
├── sql/
├── tools/
└── uploads/
```

**两个分支**：
- `main`：源码全树 + 门面文档（默认，推荐）
- `source`：纯源码全树（无文档，适合只想拿代码的人）

---

## 五、历史变更

| 时间 | 版本 | 变更 |
|---|---|---|
| 2026-09-24 | v1.9.4 | 新建 `GitHub/` 目录；整理推送说明；修 install.php 会话 bug |
| 2026-09-23 | v1.6.4 | 修首页 500 bug（`home.php` 缺命名空间 use） |
| 2026-09-22 | v1.6.3 | 恢复网页安装器 + 加锁 |

---

## 六、联系方式

- GitHub Issues：https://github.com/songliannv/media-library/issues
- 本地归档目录：`F:/WorkBuddy/万能资料库/`
