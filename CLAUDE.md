# Edge Admin

RBAC 中后台脚手架，前后端分离、同仓库。

## 仓库布局

- `server/` — Laravel 13 纯 API 后端。规范见 [`server/CLAUDE.md`](server/CLAUDE.md)（含 Laravel Boost 生成的 guidelines），在该目录下执行 composer / artisan 命令。
- `web/` — Ant Design Pro 前端。规范见 [`web/CLAUDE.md`](web/CLAUDE.md)，在该目录下执行 npm 命令。
- `docs/` — 项目文档；[`docs/architecture.md`](docs/architecture.md) 是前后端接口契约，**两端唯一依据**。
- `.github/workflows/ci.yml` — 后端（working-directory: server）pint + phpstan + pest；前端 lint + build。

两端互不引用对方代码，只通过契约通信。改动接口（路径、字段、权限码、状态码）必须同步修改 `docs/architecture.md`。

## Laravel Boost MCP

仓库根的 `.mcp.json` 以 `php server/artisan boost:mcp` 启动 Boost MCP，在根目录或 `server/` 打开均可使用。

## 新增业务模块（跨端步骤）

1. 在 `docs/architecture.md` 中写清接口、字段、权限码。
2. 后端（`server/`）：按 `server/CLAUDE.md` 的"新增业务模块"约定实现（模型 / 迁移 / 权限点 / Request / Resource / Policy / 控制器 / 路由 / Pest 测试），执行 `composer lint && composer analyse && composer test`。
3. 前端（`web/`）：按契约实现页面与 service，用权限码控制菜单与按钮，执行 `npm run lint && npm run build`。
