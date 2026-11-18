# 仓库约定

Laravel 13（PHP 8.5）+ Vben Admin 5（antdv-next）的中后台脚手架（Starter），前后端分离、同仓库。**目前处于重建中**，下列目录未全部就绪。

## 目录布局

| 目录 | 职责 |
|---|---|
| `server/` | Laravel 纯 API 后端；在该目录下执行 composer / artisan 命令。规范见 `server/CLAUDE.md`（后续创建） |
| `web/` | Vben 前端；在该目录下执行前端包管理命令。规范见 `web/CLAUDE.md`（后续创建） |
| `docs/` | 项目文档，索引见 [`docs/README.md`](docs/README.md) |
| `deploy/` | 部署文件（Nginx / Caddy、Dockerfile、Supervisor 等，后续创建） |
| `.legacy/` | 旧版本代码的只读参考（见下文） |

- [`docs/backend-design.md`](docs/backend-design.md) 是后端设计的**唯一依据**；与其冲突的做法以它为准，需要调整设计时先改文档。
- 子目录的具体规范写在各自的 `CLAUDE.md` 中，本文件只放仓库级约定。

## `.legacy/` 旧代码

- `.legacy/` 是旧版本代码的本地 git worktree，已被忽略，**不会提交**。
- 只能读取参考、搬运思路；**禁止**修改、删除、移动其中任何内容。
- 新代码**禁止**引用 `.legacy/`（路径、自动加载、导入、软链均不允许）；需要的内容按新设计重写。

## 前后端协作

- 前后端只通过接口契约通信，互不引用对方代码。
- 后台接口与 Vben 的约定目前见 `docs/backend-design.md` §6；改动接口（路径、字段、权限码、状态码）时，同步更新 `docs/` 中的契约文档。

## 开发约定

- 后端就绪后，新建文件优先使用 `php artisan core:make-*` 生成命令（`php artisan list core` 查看全部），不手写骨架。
- 不使用任何 git 钩子（husky、lefthook、pre-commit 等）；检查由 CI 与本地命令执行。
- 提交信息遵循 [Conventional Commits](https://www.conventionalcommits.org/zh-hans/)：`feat`、`fix`、`docs`、`refactor`、`test`、`chore` 等，可加作用域，如 `feat(server): ...`、`fix(web): ...`。
- 本脚手架不带品牌名：配置文件名、命令前缀统一使用 `core`（如 `config/core.php`、`core:make-*`）。
