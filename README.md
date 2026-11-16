# Edge Admin

Edge Admin 是一个前后端分离的 **RBAC 中后台脚手架**：后端是纯 API 的 Laravel 应用（`server/`），前端是 Ant Design Pro（`web/`），
两端只通过 [`docs/architecture.md`](docs/architecture.md) 中的 REST 契约通信，可以独立开发、独立部署。

内置：账号认证、用户 / 角色 / 权限 / 部门 / 岗位管理、五种数据权限、登录与操作日志、定时任务监控、失败队列、
通知公告与站内信、文件上传、工作台统计，以及一个接入了数据权限的"文章"业务示例模块，可作为后续业务开发的范本。

## 技术栈

| | 后端 `server/` | 前端 `web/` |
|---|---|---|
| 语言 / 框架 | PHP 8.3 · Laravel 13 | TypeScript · React · Umi Max · Ant Design Pro |
| 认证 | Laravel Sanctum（API Token） | Bearer Token |
| 权限 | 原生 Gate / Policy / `can:` 中间件（无第三方权限包） | `access.ts` |
| 队列 / 定时任务 / 通知 | database 队列 · Laravel Schedule · database notifications | — |
| API 文档 | dedoc/scramble（`/docs/api`，仅 local） | — |
| 质量 | Pest · Pint · Larastan（level 8） · Laravel Boost | Biome · tsc · `npx antd lint` |

数据库默认 SQLite，代码不含方言 SQL，可切换 MySQL / PostgreSQL。

## 目录结构

```
.
├── server/                    # Laravel 应用（纯 API）
│   ├── app/
│   │   ├── Actions/           # 少量业务动作（如登录 AttemptLogin）
│   │   ├── Console/Commands/  # permission:sync
│   │   ├── Enums/             # Status、DataScope、PermissionType、PublishStatus…
│   │   ├── Http/
│   │   │   ├── Controllers/Api/{Auth,System,Monitor,Message,Content}/
│   │   │   ├── Middleware/    # EnsureUserIsActive、RecordOperationLog
│   │   │   ├── Requests/      # Form Request（ListRequest 为分页列表基类）
│   │   │   └── Resources/     # API Resource
│   │   ├── Jobs/ Listeners/ Notifications/
│   │   ├── Models/            # Eloquent 模型；Concerns/HasDataScope、Collections/TreeCollection
│   │   ├── Policies/          # 记录级授权
│   │   └── Services/          # DataScopeResolver、ScheduledTaskRegistry、DashboardStatistics
│   ├── config/edge.php        # 项目配置（分页、Token 有效期、日志保留、上传）
│   ├── config/permissions.php # 权限点声明（树）
│   ├── database/              # 迁移、Factory、Seeder
│   ├── lang/zh_CN/            # 中文提示、操作日志摘要
│   ├── routes/api.php         # 全部接口（含 can: 权限声明）
│   ├── routes/console.php     # 定时任务
│   └── tests/                 # Pest
├── web/                       # Ant Design Pro 前端
├── docs/                      # 项目文档（接口契约 architecture.md）
└── .github/workflows/ci.yml   # 后端 pint + phpstan + pest；前端 lint + build
```

## 快速开始

环境要求：PHP ≥ 8.3（pdo_sqlite、mbstring、fileinfo）、Composer 2、Node ≥ 22。

### 后端

```bash
cd server
composer setup        # 安装依赖、生成 .env 与 APP_KEY、创建 SQLite 库、migrate --seed、storage:link
composer dev          # 同时启动 serve(8000) + queue:listen + schedule:work + pail
```

`composer dev` 调用官方 `php artisan dev`，借助 `npx @laravel/multiplex` 并行运行多个进程（需要 Node）。
也可以分别在三个终端启动：

```bash
php artisan serve          # http://127.0.0.1:8000
php artisan queue:listen   # 公告通知、"立即执行"定时任务都走队列
php artisan schedule:work  # 本地运行调度器
```

- 健康检查：`GET /up`；`GET /` 返回 `{name, version}`
- API 文档：<http://127.0.0.1:8000/docs/api>（仅 `APP_ENV=local`）
- 上传文件存放在 `public` 磁盘，需要 `php artisan storage:link`（`composer setup` 已执行），URL 前缀取自 `APP_URL`

切换 MySQL / PostgreSQL：修改 `server/.env` 中的 `DB_*`（`.env.example` 内有示例），再执行 `php artisan migrate:fresh --seed`。

### 前端

```bash
cd web
npm ci
npm run dev           # http://localhost:8001，/api 代理到 127.0.0.1:8000
```

前端的更多说明见 [`web/CLAUDE.md`](web/CLAUDE.md)。

## 默认账号

密码均为 `password`。

| 账号 | 角色 | 数据权限 |
|---|---|---|
| `admin` | 超级管理员（id=1，受保护） | 全部 |
| `editor` | 内容编辑（研发中心） | 本部门及下级部门 |
| `viewer` | 只读访客（前端组） | 仅本人 |
| `marketing` | 市场主管（市场部） | 自定义部门（市场部及华东、华南区） |
| `disabled` | — | 已禁用，无法登录 |

## 权限使用说明

### 功能权限

1. 权限点在 `server/config/permissions.php` 中以树形声明（模块 → 菜单 → 操作），权限码形如 `模块.资源.操作`，**必须包含 `.`**。
2. 执行 `php artisan permission:sync` 同步到 `permissions` 表（幂等：新增、更新名称 / 层级、删除配置中已不存在的项）；Seeder 会自动调用。
3. 路由上用官方中间件声明：`->middleware('can:content.article.view')`，资源路由用
   `Route::apiResource(...)->middlewareFor('store', 'can:content.article.create')`。
4. `AppServiceProvider` 中的 `Gate::before`：ability 含 `.` 视为权限码，用户拥有即放行（超级管理员拥有全部）；
   不含 `.` 的 ability（`view`、`update`、`delete`…）交给 Policy。
5. 角色被禁用后其权限不再生效；用户被禁用后无法登录，已签发的 token 由 `active` 中间件拒绝（401）。

### 数据权限

角色的 `data_scope`：`all`（全部）、`custom`（自定义部门）、`department_and_children`（本部门及下级）、`department`（仅本部门）、
`self`（仅本人创建）。一个用户有多个角色时取**并集**，超级管理员恒为全部。

接入方式（以文章为例）：

```php
class Article extends Model
{
    use HasDataScope;   // 约定字段 department_id + created_by，可覆盖 dataScopeColumns()
}

// 列表：只查询可见数据
Article::query()->visibleTo($request->user())->paginate();

// 单条：Policy 中使用同一套规则
public function view(User $actor, Article $article): bool
{
    return $article->isVisibleTo($actor);
}
```

创建记录时 `created_by`、`department_id` 会自动填充为当前用户及其部门。

## 如何新增一个业务模块（以"文章"为例）

所有文件尽量用 artisan 生成，然后参照文章模块的现有实现修改：

1. **数据表与模型**：`php artisan make:model Article -mfs --policy`
   - 迁移：业务字段 + `department_id`、`created_by`（需要数据权限时）；不写方言 SQL
   - 模型：`#[Fillable]`、`casts()`（状态用 `app/Enums` 中的枚举）、关系、`#[Scope] filter()` 过滤作用域；需要数据权限时 `use HasDataScope`
   - Factory / Seeder：给出像样的演示数据，并在 `DatabaseSeeder` 中登记
2. **权限点**：在 `config/permissions.php` 对应模块下增加节点
   `['code' => 'content.article', 'name' => '文章管理', 'actions' => ['view' => '查看', 'create' => '新增', ...]]`，
   执行 `php artisan permission:sync`
3. **请求校验**：`php artisan make:request Content/ArticleIndexRequest`（继承 `ListRequest`，声明 `filterRules()` 与 `$sortable`），
   `php artisan make:request Content/ArticleRequest`（新增与编辑规则相同时共用一个；不同时拆成 `Store*` / `Update*`）。
   编辑时的记录级授权放在 Form Request 的 `authorize()` 中（`Gate::inspect('update', $article)`），保证先授权后校验
4. **输出**：`php artisan make:resource ArticleResource`，时间字段统一 `toIso8601String()`，关联用 `whenLoaded()`
5. **授权**：Policy 只写记录级规则（数据权限、业务约束），功能权限交给路由上的 `can:`
6. **控制器**：`php artisan make:controller Api/Content/ArticleController --api`，保持薄：
   查询 → `visibleTo()` → `filter()` → `$request->applySort()` → `paginate($request->perPage())` → Resource
7. **路由**：在 `routes/api.php` 的 `auth:sanctum` 分组中注册 `Route::apiResource(...)->middlewareFor(...)`
8. **操作日志摘要**（可选）：在 `lang/zh_CN/operations.php` 中以路由名为键添加文案，如 `'articles.store' => '新增文章'`
9. **测试**：`php artisan make:test ArticleTest --pest`，覆盖 CRUD、权限 403、数据权限
10. **契约**：在 `docs/architecture.md` 中补充接口与权限码，前端按契约在 `web/` 中实现页面并在 `access.ts` 中使用权限码
11. 提交前执行 `composer lint && composer analyse && composer test`

## 常用命令

在 `server/` 目录下：

| 命令 | 说明 |
|---|---|
| `composer setup` | 首次安装 |
| `composer dev` | 本地开发（serve + queue + schedule + logs） |
| `composer lint` / `composer format` | Pint 检查 / 自动格式化 |
| `composer analyse` | Larastan 静态分析 |
| `composer test` | Pest 测试 |
| `php artisan migrate:fresh --seed` | 重建数据库并填充演示数据 |
| `php artisan permission:sync` | 同步权限点 |
| `php artisan schedule:list` | 查看定时任务 |
| `php artisan queue:retry all` | 重试全部失败任务 |

在 `web/` 目录下：`npm run dev`、`npm run lint`、`npm run build`。

## 部署要点

后端：

- `composer install --no-dev --optimize-autoloader`；`.env` 设置 `APP_ENV=production`、`APP_DEBUG=false`、`APP_URL`、数据库与
  `CORS_ALLOWED_ORIGINS`（前端域名）；`php artisan key:generate`（首次）
- `php artisan migrate --force`，首次部署可 `php artisan db:seed --force`；之后每次部署执行 `php artisan permission:sync`
- `php artisan storage:link`；`php artisan optimize` 缓存配置、路由、事件
- Web 服务器文档根目录指向 `server/public`
- 队列常驻：`php artisan queue:work --tries=3`（Supervisor / systemd 托管）
- 调度：cron `* * * * * cd /path/to/server && php artisan schedule:run >> /dev/null 2>&1`
- 内置定时任务：`model:prune`（清理超过 `EDGE_LOG_RETENTION_DAYS` 天的登录 / 操作 / 任务日志）、
  `sanctum:prune-expired --hours=24`、`queue:prune-failed --hours=168`
- API 文档 `/docs/api` 在非 local 环境默认不可访问

前端：`npm ci && npm run build`，将 `web/dist` 部署到静态服务器，并把 `/api` 反向代理到后端（或同域部署）。
