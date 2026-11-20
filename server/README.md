# server

Laravel 13（PHP 8.5）纯 API 后端，默认适配 Vben Admin 5。设计依据见 [`docs/backend-design.md`](../docs/backend-design.md)，开发规范见 [`CLAUDE.md`](CLAUDE.md)。

## 环境要求

- PHP 8.5（扩展：`pdo_sqlite` 或 `pdo_mysql` / `pdo_pgsql`、`mbstring`、`intl`、`zip`（备份）；`pcntl` 可选，`composer dev` 用它运行 `pail`）
- Composer 2
- 数据库：默认 SQLite；MySQL、PostgreSQL 上同样运行全部测试（本机实测 MySQL 8.0、PostgreSQL 16，CI 为 MySQL 8.4、PostgreSQL 17，见下文“测试”），配置示例见 `.env.example`

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
| `/api/admin/...` | 后台接口：认证见下文“认证”，系统管理见设计文档 §6.5 |
| `GET /docs/api` | OpenAPI 文档（只在 `APP_ENV=local` 开放，见下文“接口文档”） |

## 认证

后台（Vben）使用 Sanctum SPA 模式（会话 Cookie）+ Fortify 登录流程（设计文档 §7）。下表路径都在后台入口的前缀
（`CORE_ADMIN_PREFIX`，默认 `/api/admin`）下，响应均为 Vben 格式 `{ code, data, message }`，没有 204。

| 接口 | 提供方 | 说明 |
|---|---|---|
| `GET /sanctum/csrf-cookie` | Sanctum | 下发 `XSRF-TOKEN` Cookie；之后的写请求带 `X-XSRF-TOKEN` 请求头 |
| `POST /login`、`POST /logout` | Fortify | 字段 `username`、`password`、`remember`；开启两步验证时返回 `data: { two_factor: true }` |
| `POST /two-factor-challenge` | Fortify | `code`（动态验证码）或 `recovery_code`（恢复码） |
| `POST /forgot-password`、`POST /reset-password` | Fortify | 重置链接指向前端页面（`CORE_AUTH_FRONTEND_URL` + `CORE_AUTH_RESET_PASSWORD_PATH`） |
| `PUT /user/profile-information`、`PUT /user/password` | Fortify | 修改资料（`name`、`email`、`avatar_id`：头像附件的 id，`null` 去掉头像）；修改密码后其他会话与 token 失效 |
| `GET /user/confirmed-password-status`、`POST /user/confirm-password` | Fortify | 确认密码（两步验证的管理接口需要，否则 423） |
| `/user/two-factor-*` | Fortify | 开启、确认、关闭两步验证，二维码、密钥与恢复码 |
| `GET /user/info` | 自建 | 当前用户（`userId`、`username`、`realName`、`email`、`avatar`、`twoFactorEnabled`、`roles`、`desc`、`homePath`），未登录 401 |

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
| `composer test` | Pest 测试，含 `tests/Arch` 架构测试（默认 SQLite 内存库，其他数据库见下文“测试”） |
| `composer ci` | lint + analyse + rector --dry-run + test，与 CI 的 `quality` job 一致；不需要 `.env`（测试的环境变量与只用于测试的 `APP_KEY` 都在 `phpunit.xml` 中） |
| `composer test:generator-e2e` | 在系统临时目录的项目副本中运行生成器，再跑全部检查（约 2 分钟，修改生成器后执行） |
| `composer test:octane` | 用 Octane + FrankenPHP（2 个 worker）起服务，发一组检查请求间状态泄露的冒烟请求（需要 FrankenPHP 二进制，见下文“Octane”） |

## 测试

CI（`.github/workflows/server.yml`）在 PR 与推送到 main 时并行运行三个 job：`quality`（audit、`composer ci`、生成器端到端检查，SQLite）、
`database`（矩阵：MySQL 8.4、PostgreSQL 17 上的 `composer test`）与 `octane`（`composer test:octane`）。本地复现：

```bash
composer ci                          # 与 quality 相同（SQLite 内存库）

# MySQL / PostgreSQL：环境变量覆盖 phpunit.xml 的 sqlite（phpunit.xml 的 <env> 不覆盖已有的环境变量），库需事先建好、测试会清空它
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=core_test DB_USERNAME=core DB_PASSWORD=secret DB_URL= composer test
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=core_test DB_USERNAME=core DB_PASSWORD=secret DB_URL= composer test
```

- 测试用的库不要与开发库共用：`LazilyRefreshDatabase` 会执行 `migrate:fresh`，`tests/Core/System/Actions/ConcurrentActionsTest.php` 还会在两个子进程中提交数据、每个用例结束时清空全部表。
- `ConcurrentActionsTest`（加锁的 Action 两个进程真正并发：最后一个超管、删除与分配角色、删除节点与新增子节点、附件清理与绑定）只在 MySQL / PostgreSQL 上运行，SQLite 上跳过。
- 读回 JSON 列的断言用 `toBeIgnoringKeyOrder()`（MySQL、PostgreSQL 不保留 JSON 对象的键顺序），其他跨库注意事项见设计文档 §10.5。

## Octane

默认以 PHP-FPM 运行；代码按常驻内存安全来写（设计文档 §10.3），`laravel/octane` 在依赖中、`config/octane.php` 已提交，可直接切换到 FrankenPHP 的 worker 模式。

```bash
# FrankenPHP 二进制（自带 PHP 8.5 与常用扩展）：放在 server/frankenphp（已在 .gitignore 中）或 PATH 中
curl -fsSL -o frankenphp https://github.com/php/frankenphp/releases/download/v1.13.6/frankenphp-linux-x86_64 && chmod +x frankenphp

php artisan octane:start --server=frankenphp --workers=2 --max-requests=500   # 本地起服务（http://127.0.0.1:8000）
composer test:octane                 # 冒烟检查：临时 SQLite 库 + 2 个 worker，多个用户交替请求，结束后停止服务、删除库
```

- 冒烟请求（`tests/Http/Admin/Fixtures/octane-smoke.php`）检查：登录状态与守卫不串、数据范围、收回权限码后下一个请求（含另一个 worker）立即生效、
  `Accept-Language` 只作用于本请求、请求 ID（`Context`）、响应后写入的操作日志（`defer()`）带的是那个请求的 ID、停用账号立即失效。
- 不需要 FrankenPHP 的同一组请求在 `composer test` 中也会运行（`tests/Http/Admin/OctaneWorkerTest.php`：进程内用 Octane 的 `Worker` 处理，每个请求一个沙箱）。
- 新增有状态的单例或第三方包时：登记到 `config/octane.php` 的 `flush`（目前为空，理由见文件中的注释），并在冒烟请求中覆盖它。

## 接口文档

开发环境安装了 [Scramble](https://scramble.dedoc.co)（`dedoc/scramble`，`require-dev`），按路由、Form Request 与 API Resource 生成后台入口的 OpenAPI 3.1 文档，给前端对接用：

```bash
APP_ENV=local php artisan serve       # 浏览 http://127.0.0.1:8000/docs/api（JSON：/docs/api.json）
php artisan scramble:export --path=storage/api.json   # 导出文件（不提交）
```

- 只在 `APP_ENV=local` 开放（Scramble 的 `RestrictedDocsAccess`；其他环境 403，生产 `composer install --no-dev` 时没有这个包）。范围是后台入口的前缀（`config/scramble.php` 的 `api_path`，默认取 `CORE_ADMIN_PREFIX`），含挂在其下的 Fortify 路由。
- 响应已按 Vben 格式改写（`App\Http\Admin\Responses\VbenOpenApiDocument`）：成功为 `{ code: 0, data, message }`，分页的 `data` 为 `{ items, total }`，API Resource 不再套一层 `data`，没有 204；错误在 `message`（422 另有 `errors`）之外有 `code` 与 `data: null`。
  这个类引用了开发依赖 Scramble 的类型，但放在 `app/`（后台入口的 `Responses/`，与它描述的 `VbenResponse` 放在一起）：`AdminServiceProvider` 只在 `class_exists(Scramble::class)` 时登记它，生产环境没有 Scramble 时不会被加载；开发环境中它与其他代码一样经过 PHPStan 与架构测试。
- 已知的局限（只是文档不准，接口以设计文档 §6 为准，不为它改业务代码）：
  - 单动作控制器的标题来自 `__invoke()` 的注释，多数显示为“Handle the incoming request”；操作按控制器分组（tag），路由名在 `operationId` 中；
  - 列表的筛选、排序参数取自列表请求的校验规则（`filter[...]`、`sort`），可用的字段以 Query 类的白名单为准；`sort` 只给出格式，不列出可排序的字段；
  - 推断不出的返回值显示为空对象或任意类型：Fortify 的接口（登录返回 `{ two_factor }`、两步验证的二维码与恢复码等）、返回数组的 Query（`/menu/all`、`/monitor/system-info`、`/monitor/health`、`/settings/public`）；
  - 业务规则的 422（`BusinessRuleViolation`，只有 `message`）、Policy 的 403 文案、`admin-ops` 等限流器的 429 不一定出现在对应的接口上；错误响应的 `code` 只标注“与 HTTP 状态码相同”；
  - 认证方式（会话 Cookie + `X-XSRF-TOKEN`）没有写进文档的 `security`：在文档页中调试需要先在同一域名下登录。

## 目录

```
app/
├── Core/                             内核模块（各自的 README 说明职责与对外提供的能力）
│   ├── Generator/                    core:make-* 生成命令（只在命令行下注册），见下文“代码生成”
│   ├── System/                       用户、角色、菜单权限、数据权限、字典、参数、附件、异步导出、认证（Fortify 的 Action、限流器）
│   ├── Monitor/                      操作日志、登录日志、在线会话、定时任务、失败任务、健康检查、系统信息、备份、审计（LogAudit）
│   └── Message/                      通知中心、公告
├── Http/
│   ├── Admin/                        后台入口（给 Vben 用）
│   │   ├── AdminServiceProvider.php  路由、中间件组（含 Sanctum 会话）、限流、Vben 响应与异常格式
│   │   ├── Middleware/               FormatResponse（Vben 格式）、RecordOperationLog（操作日志）、ForceJson（一律按 JSON 处理）
│   │   ├── Requests/ListRequest.php  列表参数 page / pageSize / sort / filter[]
│   │   ├── Responses/                VbenResponse、替换 Fortify 的响应、VbenOpenApiDocument（接口文档的响应格式）
│   │   ├── System/  Monitor/  Message/  Content/     各模块的后台接口（Controllers、Requests、Resources）
│   │   └── routes.php
│   └── Middleware/                   多入口共用：AssignRequestId（X-Request-Id）、SetLocale（Accept-Language）、
│                                     EnsureUserIsEnabled（停用账号立即失效）、RedirectIfAuthenticated（guest：已登录时 JSON 请求返回 409）
├── Providers/AppServiceProvider.php  全局默认（强制多态别名、严格模式、密码规则等）
└── Support/                          与业务无关的共享件（Enums/HasLabel、HasColor，Exceptions/BusinessRuleViolation，Database/UniqueConstraint 等）
config/core.php                       入口清单与部署级配置（config/octane.php、config/scramble.php 见上文“Octane”“接口文档”）
tests/                                与 app/ 对应；tests/Arch 为边界与约定测试
```

业务模块放在 `app/Modules/<模块>`（用 `php artisan core:make-module` 创建）。`app/Modules/Content`（文章）是业务对接示范：只通过 Core 的扩展点接入、可整块删除，接口见设计文档 §6.11，删除步骤见它的 [`README.md`](app/Modules/Content/README.md)。分层与依赖规则见 [`CLAUDE.md`](CLAUDE.md) 与设计文档 §4、§5。

## 代码生成

新建文件使用 `core:make-*` 命令（`php artisan list core` 查看全部，`php artisan <命令> --help` 查看参数）。它们继承官方 `GeneratorCommand`：
缺少名称或模块时交互提问，`-f|--force` 覆盖已有文件，失败时退出码为 1；生成物直接通过 `composer ci`（Pint、Rector、PHPStan、架构测试）。

```bash
php artisan core:make-module Blog --crud=Post --soft-deletes --export --audit   # 新模块 + 一个模型的完整增删改查
php artisan core:make-crud Tag --module=Blog                           # 已有模块中再加一个模型的完整增删改查
php artisan core:make-module Content                                   # 只建业务模块
php artisan core:make-model Article --module=Content                   # 模型、迁移、工厂、Policy、语言包，登记多态别名
php artisan core:make-action PublishArticle --module=Content --model=Article   # 写操作（锁行、操作者）
php artisan core:make-controller Article --module=Content --model=Article      # 资源控制器的五个方法（连同请求、Resource、Query、Action）
php artisan core:make-controller PublishArticle --invokable --module=Content --model=Article   # 单动作控制器
php artisan core:make-app device --restricted                          # 受限的入口（对外开放，前缀带 /v1）
php artisan core:make-contract DeviceConfig --module=Content           # 受限入口通过它访问模块的数据
```

| 命令 | 生成 |
|---|---|
| `core:make-app {name} [--restricted]` | `app/Http/<Name>/<Name>ServiceProvider.php`、`routes.php`、`tests/Http/<Name>/<Name>ServiceProviderTest.php`；登记到 `config/core.php`、`bootstrap/providers.php`、`phpunit.xml` 与 `.env.example` 的 `CORE_APPS`。路由默认要求登录（`auth:sanctum`），受信任的入口与后台相同使用 Sanctum 会话。`--restricted`：`trusted=false`，前缀 `api/<name>/v1`，只用 Bearer token（无状态） |
| `core:make-module {name} [--core] [--crud=<模型> …]` | `app/Modules/<Name>/<Name>ServiceProvider.php`（登记到 `bootstrap/providers.php`；`boot()` 已接上语言包、`MenuRegistry`、`DictRegistry`）、空的 `resources/menus.php` 与 `resources/dicts.php`、`README.md`（含“删除本模块”）；`--core` 生成到 `app/Core/<Name>`。入口中的同名目录在生成控制器等时创建，不建空目录。`--crud=<模型>` 接着执行 `core:make-crud`（`--soft-deletes` 等选项原样传过去） |
| `core:make-crud {model} --module= [--app=admin] [--soft-deletes] [--export] [--audit] [--no-data-scope]` | 一个模型的完整增删改查（写法同 Content，设计文档 §10.4）：模型、迁移、工厂、Policy、Action（新增 / 修改 / 删除，软删除另有恢复 / 彻底删除，`--audit` 另有审计日志）、列表 Query、[导出类]、资源与单动作控制器、Request、Resource、语言包；菜单节点写入模块的 `resources/menus.php`，路由写入入口 `routes.php` 的模块分组，授权测试写入 `tests/Http/<入口>/<模块>`，模块 README 加入该模型一节（含“加字段时要改的地方”）。字段只有占位字段 `name` |
| `core:make-model {name} --module= [--soft-deletes] [--no-data-scope] [--audit]` | `Models/<Name>.php`（占位字段 `name`、`created_by` 与 `creator()`、`HasDataScope`、可选 `SoftDeletes`、`--audit` 时实现 `Auditable`）、建表迁移、工厂、`Policies/<Name>Policy.php`（§8.3 的标准写法：权限码 → 业务保护 → 数据范围，软删除的规则随模型生成）、语言包 `resources/lang/<语言>/<模型>.php`；在模块 Provider 中登记多态别名（业务模块 `<模块>.<模型>`，`--core` 时为 `<模型>`）与语言包。只提示还没在 `resources/menus.php` 中声明的权限码 |
| `core:make-migration` / `core:make-factory` / `core:make-policy {name} --module=` | 为模块中已有的模型单独生成迁移、工厂、Policy，按模型文件的写法（`core:make-policy --model=` 可指定模型，`--permission=` 指定权限码） |
| `core:make-action` / `core:make-query` / `core:make-exporter` / `core:make-enum` / `core:make-data {name} --module=` | `Actions/`（`--model=` 时按名称识别 `Create` / `Update` / `Delete` / `Restore` / `ForceDelete<模型>`，模型实现 `Auditable`（`core:make-model --audit`）时写审计日志）、`Queries/`（`<模型>List` 为 `builder(User $actor, array $parameters): QueryBuilder`）、`Exporters/<模型>Exporter`（登记到模块的 `resources/exporters.php`）、`Enums/`（实现 `HasLabel`，`--color` 另实现 `HasColor`）、`Data/<Name>Data`（只读数据对象） |
| `core:make-contract {name} --module=` | `Contracts/<Name>`（接口）、`Data/<Name>Data`、`Services/<Name>Service`（实现），并在模块 Provider 的 `$bindings` 中绑定 |
| `core:make-controller {name} --module= [--app=admin] [--model=] [--invokable]` | `app/Http/<App>/<Module>/Controllers/<Name>Controller.php`；`--model` 生成资源控制器的五个方法（`#[Authorize]`、列表走 `<模型>List`、写操作调用 Action 并传入 `#[CurrentUser]`），并补上缺少的请求、Resource、Query 与 Action；`--invokable` 生成单动作控制器（`Export<模型复数>`（导出）、`ForceDelete<模型>`、其他 `<动作><模型>`）。控制器还没登记时打印要添加的路由 |
| `core:make-request {name} --module= [--app=admin] [--list\|--export] [--model=]` | `…/Requests/<Name>Request.php`；`--list` 继承入口的 `ListRequest`（按模型校验筛选值），`--export` 继承同模块的列表请求（去掉分页、加 `format`），只有 `--model` 时为表单请求 |
| `core:make-resource {name} --module= [--app=admin] [--model=]` | `…/Resources/<Name>Resource.php`；`--model` 按模型逐个列出字段，`relations()` 返回需要预加载的关联 |

- 名称自动转为 StudlyCase 并补上后缀（`article` → `ArticleController`），不能带子目录；内核模块（`app/Core`）必须加 `--core`。
- 受限的入口不能用 `--model`（只能通过模块的 `Contracts` 访问数据）。
- 修改共享文件是幂等的，重复执行不会重复登记；文件格式不符合预期时命令失败，并打印需要手工粘贴的内容。
- 生成物不手改即通过 `composer ci`、`migrate:fresh --seed` 与 `core:sync`：由 `composer test:generator-e2e`（在系统临时目录的项目副本中生成并检查，CI 中单独一步）保证。
- 模板：`php artisan stub:publish`（连同官方模板）或 `php artisan vendor:publish --tag=core-stubs`（只发布本项目的）复制到 `stubs/core.<名称>.stub`，项目中的模板优先。

## 配置

| 环境变量 | 默认值 | 说明 |
|---|---|---|
| `CORE_APPS` | 未设置时加载全部入口 | 本进程加载的入口，逗号分隔，如 `admin`；设为空则不加载任何入口（只跑队列、调度的进程） |
| `CORE_ADMIN_PREFIX` | `api/admin` | 后台入口的 URL 前缀 |
| `CORE_ADMIN_RATE_LIMIT` | `120` | 后台入口每分钟请求上限（已登录按用户、未登录按 IP） |
| `CORE_ADMIN_PAGINATION_PAGE_SIZE` / `CORE_ADMIN_PAGINATION_MAX_PAGE_SIZE` | `20` / `100` | 列表默认每页条数与上限（超过上限返回 422） |
| `CORE_ADMIN_HOME_PATH` | `/analytics` | `GET /user/info` 的 `homePath`：登录后进入的前端页面 |
| `CORE_AUTH_LOGIN_RATE_LIMIT` | `5` | 每分钟登录次数（同一用户名 + IP；两步验证按待验证的用户） |
| `CORE_AUTH_FRONTEND_URL` / `CORE_AUTH_RESET_PASSWORD_PATH` | `http://localhost:5999` / `/#/auth/reset-password` | 找回密码邮件中的重置链接：`<地址><路径>?token=…&email=…` |
| `CORE_EMAIL_VERIFICATION` | `false` | 邮箱验证（Fortify） |
| `SANCTUM_STATEFUL_DOMAINS` | 本地开发地址 + `APP_URL` 的域名 | 使用会话 Cookie 的前端域名（带端口时写上端口） |
| `SESSION_SECURE_COOKIE` | 生产环境 `true` | 会话 Cookie 只经 HTTPS 发送 |
| `APP_TIMEZONE` | `UTC` | 应用时区：写库前统一换算到该时区，输出带偏移的 ISO 8601 |

使用路由缓存（`php artisan optimize`）时，缓存只包含生成时已加载的入口；同一份代码以不同的 `CORE_APPS` 运行多个进程时，为每种组合设置不同的 `APP_ROUTES_CACHE`。

## AI 辅助开发

已安装 [Laravel Boost](https://laravel.com/docs/boost)：`CLAUDE.md` 中的 Boost 区块与 `.claude/skills/` 由 `php artisan boost:update` 维护；MCP 服务在仓库根目录的 `.mcp.json`（`php server/artisan boost:mcp`）与本目录的 `.mcp.json` 中都已配置。
