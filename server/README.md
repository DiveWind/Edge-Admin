# server

Laravel 13（PHP 8.5）纯 API 后端，默认适配 Vben Admin 5。设计依据见 [`docs/backend-design.md`](../docs/backend-design.md)，开发规范见 [`CLAUDE.md`](CLAUDE.md)。

## 环境要求

- PHP 8.5（扩展：`pdo_sqlite` 或 `pdo_mysql` / `pdo_pgsql`、`mbstring`、`intl`；`pcntl` 可选，`composer dev` 用它运行 `pail`）
- Composer 2
- 数据库：默认 SQLite；MySQL / PostgreSQL 的配置示例见 `.env.example`

## 本地运行

在 `server/` 目录下执行：

```bash
composer setup                      # 安装依赖、生成 .env 与 APP_KEY、创建 SQLite 数据库并迁移
php artisan migrate:fresh --seed    # 演示账号（仅本地与测试环境）：admin / password；停用的账号 disabled / password
composer dev                        # 同时启动 serve、queue:listen、pail（需 pcntl）、schedule:work
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
| `/api/admin/...` | 后台的认证接口，见下文“认证” |

## 认证

后台（Vben）使用 Sanctum SPA 模式（会话 Cookie）+ Fortify 登录流程（设计文档 §7）。下表路径都在后台入口的前缀
（`CORE_ADMIN_PREFIX`，默认 `/api/admin`）下，响应均为 Vben 格式 `{ code, data, message }`，没有 204。

| 接口 | 提供方 | 说明 |
|---|---|---|
| `GET /sanctum/csrf-cookie` | Sanctum | 下发 `XSRF-TOKEN` Cookie；之后的写请求带 `X-XSRF-TOKEN` 请求头 |
| `POST /login`、`POST /logout` | Fortify | 字段 `username`、`password`、`remember`；开启两步验证时返回 `data: { two_factor: true }` |
| `POST /two-factor-challenge` | Fortify | `code`（动态验证码）或 `recovery_code`（恢复码） |
| `POST /forgot-password`、`POST /reset-password` | Fortify | 重置链接指向前端页面（`CORE_FRONTEND_URL` + `CORE_RESET_PASSWORD_PATH`） |
| `PUT /user/profile-information`、`PUT /user/password` | Fortify | 修改资料（`name`、`email`、`avatar`）；修改密码后其他会话与 token 失效 |
| `GET /user/confirmed-password-status`、`POST /user/confirm-password` | Fortify | 确认密码（两步验证的管理接口需要，否则 423） |
| `/user/two-factor-*` | Fortify | 开启、确认、关闭两步验证，二维码、密钥与恢复码 |
| `GET /user/info` | 自建 | 当前用户（`userId`、`username`、`realName`、`avatar`、`roles`、`desc`、`homePath`），未登录 401 |

- 只有来自 `SANCTUM_STATEFUL_DOMAINS`（按 `Referer` / `Origin`）的请求使用会话 Cookie 并校验 CSRF；开发时前端在
  `localhost:5999` 经 Vite 代理同源访问后端，默认已包含。其他请求只能用 Bearer token（`auth:sanctum` 两者都识别）。
- 会话 Cookie：`HttpOnly`、`SameSite=lax`，生产环境默认 `Secure`；会话为 `database` 驱动。
- 停用的账号不能登录（422，`username`），已登录的会话与 token 立即失效（401）。
- 注册、Passkeys 关闭；邮箱验证默认关闭（`CORE_EMAIL_VERIFICATION=true` 开启）。
- Fortify 的 Action 与限流器在 `app/Core/System`（见其 README），路由的前缀与中间件组见 `config/fortify.php`。

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
├── Core/                             内核模块
│   ├── Generator/                    core:make-* 生成命令（只在命令行下注册），见下文“代码生成”
│   └── System/                       用户与账号状态、认证（Fortify 的 Action、限流器）；Contracts/HasLabel（枚举的显示名称）
├── Http/
│   ├── Admin/                        后台入口（给 Vben 用）
│   │   ├── AdminServiceProvider.php  路由、中间件组（含 Sanctum 会话）、限流、Vben 响应与异常格式
│   │   ├── Middleware/               FormatResponse（Vben 格式）、ForceJson（一律按 JSON 处理）
│   │   ├── Requests/ListRequest.php  列表参数 page / pageSize / sort / filter[]
│   │   ├── Responses/VbenResponse.php
│   │   ├── System/                   System 模块的后台接口（GET /user/info）
│   │   └── routes.php
│   └── Middleware/                   多入口共用：AssignRequestId（X-Request-Id）、EnsureUserIsEnabled（停用账号立即失效）、
│                                     RedirectIfAuthenticated（guest：已登录时 JSON 请求返回 409）
├── Providers/AppServiceProvider.php  全局默认（morph map、严格模式、密码规则等）
└── Support/Eloquent/Concerns/UsesAppTimezone.php
config/core.php                       入口清单与部署级配置
tests/                                与 app/ 对应；tests/Arch 为边界与约定测试
```

业务模块放在 `app/Modules/<模块>`（目前还没有，用 `php artisan core:make-module` 创建）。分层与依赖规则见 [`CLAUDE.md`](CLAUDE.md) 与设计文档 §4、§5。

## 代码生成

新建文件使用 `core:make-*` 命令（`php artisan list core` 查看全部，`php artisan <命令> --help` 查看参数）。它们继承官方 `GeneratorCommand`：
缺少名称或模块时交互提问，`-f|--force` 覆盖已有文件，失败时退出码为 1；生成物直接通过 `composer ci`（Pint、Rector、PHPStan、架构测试）。

```bash
php artisan core:make-module Content                                   # 业务模块
php artisan core:make-model Article --module=Content                   # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action PublishArticle --module=Content           # 写操作
php artisan core:make-controller Article --module=Content --model=Article   # 后台接口 index / show（连同列表请求与 Resource）
php artisan core:make-app device --restricted                          # 受限的入口（对外开放，前缀带 /v1）
php artisan core:make-contract DeviceConfig --module=Content           # 受限入口通过它访问模块的数据
```

| 命令 | 生成 |
|---|---|
| `core:make-app {name} [--restricted]` | `app/Http/<Name>/<Name>ServiceProvider.php`、`routes.php`、`tests/Http/<Name>/<Name>ServiceProviderTest.php`；登记到 `config/core.php`、`bootstrap/providers.php`、`phpunit.xml` 与 `.env.example` 的 `CORE_APPS`。路由默认要求登录（`auth:sanctum`），受信任的入口与后台相同使用 Sanctum 会话。`--restricted`：`trusted=false`，前缀 `api/<name>/v1`，只用 Bearer token（无状态） |
| `core:make-module {name} [--core]` | `app/Modules/<Name>/<Name>ServiceProvider.php`（登记到 `bootstrap/providers.php`）与 `README.md`；`--core` 生成到 `app/Core/<Name>`。入口中的同名目录在生成控制器等时创建，不建空目录。`--crud` 尚未提供 |
| `core:make-model {name} --module=` | `Models/<Name>.php`、`database/migrations/*_create_<表>_table.php`、`database/factories/<与模型相同的路径>Factory.php`、`Policies/<Name>Policy.php`（标准方法齐全，默认拒绝）；在模块 Provider 的 `Relation::morphMap()` 中登记别名 `<模块>.<模型>` |
| `core:make-migration` / `core:make-factory` / `core:make-policy {name} --module=` | 为模块中已有的模型单独生成迁移、工厂、Policy（`core:make-policy --model=` 可指定模型） |
| `core:make-action` / `core:make-query` / `core:make-enum` / `core:make-data {name} --module=` | `Actions/`（`handle()`）、`Queries/`（只读）、`Enums/`（实现 `HasLabel`）、`Data/<Name>Data`（只读数据对象） |
| `core:make-contract {name} --module=` | `Contracts/<Name>`（接口）、`Data/<Name>Data`、`Services/<Name>Service`（实现），并在模块 Provider 的 `$bindings` 中绑定 |
| `core:make-controller {name} --module= [--app=admin] [--model=]` | `app/Http/<App>/<Module>/Controllers/<Name>Controller.php`；`--model` 生成 `#[Authorize]` 的 `index`（`paginate($request->pageSize())`）与 `show`，并补上缺少的列表请求与 Resource。路由不自动登记，命令会打印要添加的路由 |
| `core:make-request {name} --module= [--app=admin] [--list]` | `…/Requests/<Name>Request.php`；`--list` 继承入口的 `ListRequest` |
| `core:make-resource {name} --module= [--app=admin] [--model=]` | `…/Resources/<Name>Resource.php`；`--model` 逐个列出 `id` 与时间字段 |

- 名称自动转为 StudlyCase 并补上后缀（`article` → `ArticleController`），不能带子目录；内核模块（`app/Core`）必须加 `--core`。
- 受限的入口不能用 `--model`（只能通过模块的 `Contracts` 访问数据）。
- 修改共享文件是幂等的，重复执行不会重复登记；文件格式不符合预期时命令失败，并打印需要手工粘贴的内容。
- 模板：`php artisan stub:publish`（连同官方模板）或 `php artisan vendor:publish --tag=core-stubs`（只发布本项目的）复制到 `stubs/core.<名称>.stub`，项目中的模板优先。

## 配置

| 环境变量 | 默认值 | 说明 |
|---|---|---|
| `CORE_APPS` | 未设置时加载全部入口 | 本进程加载的入口，逗号分隔，如 `admin`；设为空则不加载任何入口（只跑队列、调度的进程） |
| `CORE_ADMIN_PREFIX` | `api/admin` | 后台入口的 URL 前缀 |
| `CORE_ADMIN_RATE_LIMIT` | `120` | 后台入口每分钟请求上限（已登录按用户、未登录按 IP） |
| `CORE_ADMIN_PAGE_SIZE` / `CORE_ADMIN_MAX_PAGE_SIZE` | `20` / `100` | 列表默认每页条数与上限（超过上限返回 422） |
| `CORE_ADMIN_HOME_PATH` | `/analytics` | `GET /user/info` 的 `homePath`：登录后进入的前端页面 |
| `CORE_LOGIN_RATE_LIMIT` | `5` | 每分钟登录次数（同一用户名 + IP；两步验证按待验证的用户） |
| `CORE_FRONTEND_URL` / `CORE_RESET_PASSWORD_PATH` | `http://localhost:5999` / `/#/auth/reset-password` | 找回密码邮件中的重置链接：`<地址><路径>?token=…&email=…` |
| `CORE_EMAIL_VERIFICATION` | `false` | 邮箱验证（Fortify） |
| `SANCTUM_STATEFUL_DOMAINS` | 本地开发地址 + `APP_URL` 的域名 | 使用会话 Cookie 的前端域名（带端口时写上端口） |
| `SESSION_SECURE_COOKIE` | 生产环境 `true` | 会话 Cookie 只经 HTTPS 发送 |
| `APP_TIMEZONE` | `UTC` | 应用时区：写库前统一换算到该时区，输出带偏移的 ISO 8601 |

使用路由缓存（`php artisan optimize`）时，缓存只包含生成时已加载的入口；同一份代码以不同的 `CORE_APPS` 运行多个进程时，为每种组合设置不同的 `APP_ROUTES_CACHE`。

## AI 辅助开发

已安装 [Laravel Boost](https://laravel.com/docs/boost)：`CLAUDE.md` 中的 Boost 区块与 `.claude/skills/` 由 `php artisan boost:update` 维护；MCP 服务在仓库根目录的 `.mcp.json`（`php server/artisan boost:mcp`）与本目录的 `.mcp.json` 中都已配置。
