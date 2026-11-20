# Laravel + Vben 中后台脚手架

基于 **Laravel 13（PHP 8.5）** 与 **Vben Admin 5（antdv-next）** 的前后端分离中后台脚手架（Starter）。

- 后端为纯 API，默认适配 Vben，但不绑定任何前端；
- 提供 RBAC、数据权限、动态菜单、日志、通知、字典、参数、附件、定时任务等后台通用能力；
- 克隆即拥有全部代码，在此基础上直接开发业务。

## 当前状态

| 目录 | 说明 | 状态 |
|---|---|---|
| `server/` | Laravel 后端（纯 API） | 已就绪，见 [`server/README.md`](server/README.md) |
| `web/` | Vben 前端（pnpm + Turborepo monorepo） | 基础接入，见 [`web/README.md`](web/README.md) |
| `docs/` | 项目文档，索引见 [`docs/README.md`](docs/README.md) | 已有 |
| `deploy/` | Nginx / Caddy、Dockerfile、Supervisor 等部署文件 | 尚未创建 |

**后端（`server/`）** 已实现设计文档中的后台通用能力：

- 认证：Sanctum 会话 Cookie + Fortify（登录、两步验证、找回密码、个人资料与密码），停用账号与强制下线立即生效；
- 授权：菜单、按钮即权限码的节点树（Vben 的 `/menu/all`、`/auth/codes`），角色与用户管理（含防提权、最后一个超管的保护），可扩展的数据权限；
- 系统管理：字典、系统参数、附件（签名下载）、异步导出；
- 监控：操作日志、登录日志、在线会话与 token、定时任务、失败任务、健康检查、系统信息、备份；
- 消息：通知中心、公告；
- 业务对接示范模块 Content（文章，可整块删除），以及生成完整增删改查的 `core:make-*` 命令；
- 质量保证：Pint、Larastan（max）、Rector、Pest（含架构测试）；CI 在 SQLite、MySQL、PostgreSQL 上运行全部测试，并在 Octane（FrankenPHP）下做冒烟检查。

本地运行、测试与配置见 [`server/README.md`](server/README.md)。

**前端（`web/`）** 是裁剪后的 Vben Admin 5（antdv-next 版，只保留一个应用 `apps/web`），已对接后端的会话认证：登录、两步验证、找回与重置密码、个人中心，按 `mixed` 模式从后端加载权限码与菜单；系统管理、监控等业务页面尚未提供。说明见 [`web/README.md`](web/README.md)。

## 文档

- 后端设计：[`docs/backend-design.md`](docs/backend-design.md)（后端设计的唯一依据；与 Vben 的接口契约见其中 §6）
- AI 协作约定：[`CLAUDE.md`](CLAUDE.md)（各目录另有自己的 `CLAUDE.md`）

## 许可证

[MIT](LICENSE)
