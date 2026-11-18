# Laravel + Vben 中后台脚手架

基于 **Laravel 13（PHP 8.5）** 与 **Vben Admin 5（antdv-next）** 的前后端分离中后台脚手架（Starter）。

- 后端为纯 API，默认适配 Vben，但不绑定任何前端；
- 提供 RBAC、数据权限、动态菜单、日志、通知、字典、参数、附件、定时任务等后台通用能力；
- 克隆即拥有全部代码，在此基础上直接开发业务。

## 当前状态

> **重建中。** 仓库正在按新的设计从零重建，后端、前端、部署配置均**尚未就绪**，暂不能运行。

## 目录

| 目录 | 说明 | 状态 |
|---|---|---|
| `server/` | Laravel 后端（纯 API） | 待创建 |
| `web/` | Vben 前端 | 待创建 |
| `docs/` | 项目文档，索引见 [`docs/README.md`](docs/README.md) | 已有 |
| `deploy/` | Nginx / Caddy、Dockerfile、Supervisor 等部署文件 | 待创建 |

## 文档

- 后端设计：[`docs/backend-design.md`](docs/backend-design.md)（后端设计的唯一依据）
- AI 协作约定：[`CLAUDE.md`](CLAUDE.md)

## 许可证

[MIT](LICENSE)
