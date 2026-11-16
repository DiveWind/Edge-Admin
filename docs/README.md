# 文档索引

| 文档 | 说明 |
|---|---|
| [getting-started.md](getting-started.md) | 环境要求、本地运行（含可选的 Sail）、默认账号、常用命令 |
| [architecture.md](architecture.md) | **架构与前后端接口契约**（路径、字段、权限码、响应格式、初始数据），两端开发的唯一依据 |
| [backend-guide.md](backend-guide.md) | 后端分层规范：Controller / Request / Resource / Action / Service / Policy / Enum 职责，列表查询、错误、多语言、测试规范 |
| [permissions.md](permissions.md) | 功能权限与数据权限的使用和扩展 |
| [generator.md](generator.md) | 代码生成器 `edge:make-module`：字段 DSL、模板与占位符、锚点约定、前端模板与生成后收尾 |
| [deployment.md](deployment.md) | 部署：Nginx（API + SPA）、队列（Supervisor / systemd）、调度、优化命令、环境变量 |
| [frontend-guide.md](frontend-guide.md) | 前端开发规范：目录分层、请求与错误处理、权限、字典与系统参数、列表 / 表单规范、i18n、主题、新增页面、测试 |

- AI 助手 / 贡献者的后端约定：[`server/CLAUDE.md`](../server/CLAUDE.md)；前端：[`web/CLAUDE.md`](../web/CLAUDE.md)
- 本地运行后端时，OpenAPI 文档位于 <http://127.0.0.1:8000/docs/api>（仅 `local` 环境，由 Scramble 自动生成）

新增文档请放在本目录并在上表登记。
