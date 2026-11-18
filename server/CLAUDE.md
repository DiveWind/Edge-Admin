# server/ 开发规范

> 本文件由两部分组成：下方 Laravel Boost 的区块由 `php artisan boost:install` / `boost:update` 自动生成，**不要手改**；
> 区块之后的“项目约定”是本项目的规则，**两者冲突时以“项目约定”为准**。

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>


## 项目约定

后端设计的唯一依据是 [`docs/backend-design.md`](../docs/backend-design.md)；仓库级约定见根目录 [`CLAUDE.md`](../CLAUDE.md)。本节是摘要，细节以设计文档为准。

### 目录与分层（设计文档 §4、§5）

| 目录 | 是什么 | 可以依赖 |
|---|---|---|
| `app/Core/<模块>` | 内核：System、Monitor、Message 等通用后台能力，尽量不改 | Support |
| `app/Modules/<模块>` | 业务模块 | Core、Support；其他业务模块只能通过事件或对方的 `Contracts` / `Data` |
| `app/Http/<入口>` | 入口（Admin 等）：路由、控制器、Form Request、API Resource、入口专用中间件 | Core、Modules、Support；**入口之间互不依赖** |
| `app/Http/Middleware` | 多个入口共用的中间件 | 不依赖任何入口 |
| `app/Support` | 与业务无关的共享基础件 | 只依赖框架 |

- 模型、业务规则、授权规则都在 Core / Modules（领域层）；入口只开放接口，不定义模型、不使用 `DB` 门面。
- 受信任的入口（Admin）可直接读取模型；受限的入口只能通过模块的 `Contracts` 拿 `Data`。
- Policy 属于模块，禁止出现“判断是哪个入口在调用”的分支；模块通知入口只能用事件。
- 每个入口一个 `<入口>ServiceProvider` + `routes.php`，登记在 `config/core.php` 与 `bootstrap/providers.php`；`CORE_APPS` 决定本进程加载哪些入口。
- 以上规则由 `tests/Arch` 检查，放错位置会测试失败；冲突时改代码，不要放宽规则。

### 写操作必须走 Action

- 所有写入（`save()`、`update()`、`delete()`、`create()`、关联的 `attach()` / `sync()` 等）都在模块的 Action 中完成（一个类一个用例，`handle()` 方法），Action 负责事务、业务约束与触发事件。
- `app/Http` 中调用 Eloquent / 查询构造器的写方法会被 PHPStan 拦截（`phpstan.neon` 的 `disallowedMethodCalls` / `disallowedStaticCalls`），不要绕过。

### 后台入口（Admin）的接口约定（设计文档 §6）

- 控制器照常返回 API Resource、数组或分页器，响应由 `FormatResponse` 中间件统一包装为 `{ code, data, message }`；**不要在控制器里手动包装**，需要自定义 message 时用 `response()->vben($data, $message)`。
- 后台入口不返回 204：无内容的成功响应自动变为 200 + `data: null`。
- 列表接口：请求继承 `App\Http\Admin\Requests\ListRequest`，用 `->paginate($request->pageSize())` 分页，响应自动变为 `{ items, total }`；参数名 `page`、`pageSize`、`sort`、`filter[字段]`。
- API Resource 逐个列出字段，禁止整模型 `toArray()` 输出；日期字段输出 `toIso8601String()`。
- 后台入口的路由不带版本号；对外开放的入口（设备、开放平台）从第一版起带 `/v1`。

### 命名与生成

- 本脚手架不带品牌名：配置 `config/core.php`、命令前缀 `core:`、环境变量前缀 `CORE_`。
- 新建文件**优先使用 `php artisan core:make-*` 生成命令**（第 2b 步实现，`php artisan list core` 查看全部）。在此之前使用官方 `make:*` 时，名称写以 `App/` 开头的完整路径，如 `make:model App/Modules/Content/Models/Article`。
- 测试放在 `tests/` 下与 `app/` 对应的目录（`tests/Http/Admin/...`、`tests/Core/System/...`），不使用 `tests/Feature`、`tests/Unit`。
- 模型：用 `#[Fillable]`、`#[Hidden]` 等 Attribute 与 `casts()` 方法，不用属性写法；使用 `App\Support\Eloquent\Concerns\UsesAppTimezone`；新的多态类型先在 `AppServiceProvider` 的 morph map 中登记别名；工厂放在 `database/factories/` 下与模型相同的路径。

### 常驻内存兼容（设计文档 §10.3）

默认以 PHP-FPM 运行，但代码一律按常驻内存（Octane + FrankenPHP）安全来写：

- 禁止用静态属性、静态变量或单例保存请求相关数据；请求级状态用 `#[Scoped]`、`Cache::memo()` 或 `Context`。
- 禁止在请求中修改 `config()`。
- 禁止 `exit`、`die`、`dd`、`dump`、`ray`。
- `env()` 只能出现在 `config/` 中，代码里读取 `config()`。

### Laravel Boost 通用指南中不适用于本项目的部分

- “用 `php artisan make:` 创建文件”“`make:test` 创建测试”：按上文“命名与生成”。
- “API 默认带版本号”：后台入口不带版本号（见上文）。
- Laravel Cloud 部署、npm / Vite 构建：本项目是纯 API 后端，部署方式见设计文档 §11。

### 命令

| 命令 | 作用 |
|---|---|
| `composer format` | Pint 格式化 |
| `composer lint` | Pint 检查（不修改） |
| `composer analyse` | Larastan（最高级别，无 baseline） |
| `composer refactor` | Rector（CI 中 `--dry-run`） |
| `composer test` | Pest（含 `tests/Arch` 架构测试） |
| `composer ci` | 以上检查全部执行；提交前必须通过 |
