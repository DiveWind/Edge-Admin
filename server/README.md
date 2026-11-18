# server

Laravel 13（PHP 8.5）纯 API 后端，默认适配 Vben Admin 5。设计依据见 [`docs/backend-design.md`](../docs/backend-design.md)，开发规范见 [`CLAUDE.md`](CLAUDE.md)。

## 环境要求

- PHP 8.5（扩展：`pdo_sqlite` 或 `pdo_mysql` / `pdo_pgsql`、`mbstring`、`intl`；`pcntl` 可选，`composer dev` 用它运行 `pail`）
- Composer 2
- 数据库：默认 SQLite；MySQL / PostgreSQL 的配置示例见 `.env.example`

## 本地运行

在 `server/` 目录下执行：

```bash
composer setup   # 安装依赖、生成 .env 与 APP_KEY、创建 SQLite 数据库并迁移
composer dev     # 同时启动 serve、queue:listen、pail（需 pcntl）、schedule:work
```

`composer dev` 即 `php artisan dev`（纯 PHP，不依赖 Node）。不使用它时可分别启动：

```bash
php artisan serve
php artisan queue:listen --tries=1
php artisan schedule:work
```

启动后：

| 地址 | 说明 |
|---|---|
| `GET /` | 应用名（JSON） |
| `GET /up` | 健康检查 |
| `GET /api/admin/ping` | 后台入口的示例路由，返回 `{"code":0,"data":{"pong":true},"message":"ok"}` |

## 命令

| 命令 | 作用 |
|---|---|
| `composer format` | Pint 格式化代码 |
| `composer lint` | Pint 检查代码风格（不修改） |
| `composer analyse` | Larastan 静态分析（最高级别，无 baseline） |
| `composer refactor` | Rector 重构；只检查不修改：`composer refactor -- --dry-run` |
| `composer test` | Pest 测试，含 `tests/Arch` 架构测试（SQLite 内存库） |
| `composer ci` | lint + analyse + rector --dry-run + test，与 CI 一致 |

## 目录

```
app/
├── Core/System/Models/User.php       内核模块（用户等通用后台能力）
├── Http/
│   ├── Admin/                        后台入口（给 Vben 用）
│   │   ├── AdminServiceProvider.php  路由、中间件组、限流、Vben 响应与异常格式
│   │   ├── Middleware/FormatResponse.php
│   │   ├── Requests/ListRequest.php  列表参数 page / pageSize / sort / filter[]
│   │   ├── Responses/VbenResponse.php
│   │   └── routes.php
│   └── Middleware/AssignRequestId.php 多入口共用：X-Request-Id
├── Providers/AppServiceProvider.php  全局默认（morph map、严格模式、密码规则等）
└── Support/Eloquent/Concerns/UsesAppTimezone.php
config/core.php                       入口清单与部署级配置
tests/                                与 app/ 对应；tests/Arch 为边界与约定测试
```

业务模块放在 `app/Modules/<模块>`（目前还没有）。分层与依赖规则见 [`CLAUDE.md`](CLAUDE.md) 与设计文档 §4、§5。

## 配置

| 环境变量 | 默认值 | 说明 |
|---|---|---|
| `CORE_APPS` | 未设置时加载全部入口 | 本进程加载的入口，逗号分隔，如 `admin`；设为空则不加载任何入口（只跑队列、调度的进程） |
| `CORE_ADMIN_PREFIX` | `api/admin` | 后台入口的 URL 前缀 |
| `CORE_ADMIN_RATE_LIMIT` | `120` | 后台入口每分钟请求上限（已登录按用户、未登录按 IP） |
| `CORE_ADMIN_PAGE_SIZE` / `CORE_ADMIN_MAX_PAGE_SIZE` | `20` / `100` | 列表默认每页条数与上限（超过上限返回 422） |
| `APP_TIMEZONE` | `UTC` | 应用时区：写库前统一换算到该时区，输出带偏移的 ISO 8601 |

使用路由缓存（`php artisan optimize`）时，缓存只包含生成时已加载的入口；同一份代码以不同的 `CORE_APPS` 运行多个进程时，为每种组合设置不同的 `APP_ROUTES_CACHE`。

## AI 辅助开发

已安装 [Laravel Boost](https://laravel.com/docs/boost)：`CLAUDE.md` 中的 Boost 区块与 `.claude/skills/` 由 `php artisan boost:update` 维护；MCP 服务在仓库根目录的 `.mcp.json`（`php server/artisan boost:mcp`）与本目录的 `.mcp.json` 中都已配置。
