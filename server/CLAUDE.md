<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

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
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

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

# Edge Admin 项目约定

以下为本项目约定，位于 Boost 自动生成区块之外，`boost:install` / `boost:update` 不会覆盖。

## 仓库布局

- 本目录 `server/` 是 Laravel 纯 API 应用；前端在 `../web/`（有自己的 `CLAUDE.md`，不要在后端任务中修改它）。
- 前后端接口契约：`../docs/architecture.md`。路径、字段名（snake_case）、权限码、响应包络、状态码以它为准；改接口必须同步改文档。

## 代码约定

- 不使用 `declare(strict_types=1)`（与 Laravel / Pint `laravel` 预设保持一致）；完整的参数与返回类型、PHPDoc 泛型 / 数组形状。
- 文件一律用 `php artisan make:*` 生成；目录保持 Laravel 默认结构，控制器按模块分子命名空间 `App\Http\Controllers\Api\{Auth,System,Monitor,Message,Content}`。
- 模型：`#[Fillable]` / `#[Hidden]` 属性、`casts()` 中用 `app/Enums` 的枚举、`#[Scope]` 局部作用域（列表过滤统一叫 `filter(array $filters)`）。开发环境开启 `Model::shouldBeStrict()`，Factory 需给出全部字段。
- 树形数据（部门、权限）用 `#[CollectedBy(TreeCollection::class)]` + `->toTree()` 在 PHP 侧组装，不写递归 SQL。
- 列表请求继承 `App\Http\Requests\ListRequest`：声明 `filterRules()`、`$sortable`、`$defaultSort`；控制器 `->filter($request->filters())`、`$request->applySort($query)->paginate($request->perPage())->withQueryString()`。
- 功能权限：`config/permissions.php` 声明 → `php artisan permission:sync` → 路由 `can:<code>`（`Route::apiResource()->middlewareFor()`）。权限码必须含 `.`。
- 记录级规则写在 Policy（数据权限、受保护对象、"有子项不可删"等，返回带 message 的 `Response::deny()` → 403）；编辑接口的 Policy 检查放在 Form Request 的 `authorize()`，保证先授权再校验。字段级约束写在 Form Request（422）。
- 数据权限：模型 `use HasDataScope`，列表 `->visibleTo($user)`，单条在 Policy 中 `$model->isVisibleTo($user)`。
- 输出统一用 API Resource，时间字段 `?->toIso8601String()`；控制器保持薄，复杂业务放 `app/Actions`（`handle()`）或 `app/Services`。
- 用户可见文案放 `lang/zh_CN/*.php`（同时补 `lang/en`），操作日志摘要以路由名为键写在 `lang/zh_CN/operations.php`。
- 可配置项放 `config/edge.php` 并在 `.env.example` 中给出 `EDGE_*` 变量；代码中用 `config()->integer()` 等类型化读取。
- 保持数据库无关：不写方言 SQL，模糊匹配用 `whereLike()`，按日期用 `whereDate()`，分组统计可在 PHP 侧完成。

## 新增业务模块

参照文章模块（`Article*`）：`make:model -mfs --policy` → 迁移 / 模型 / Factory / Seeder → `config/permissions.php` 加权限点并 `permission:sync` → `make:request`（`*IndexRequest` 继承 `ListRequest`）→ `make:resource` → Policy → `make:controller Api/<模块>/<Name>Controller --api` → `routes/api.php` 注册并挂 `can:` → `lang/zh_CN/operations.php` 摘要 → `make:test --pest` → 更新 `../docs/architecture.md`。

## 验证

提交前在本目录执行：`composer lint`（Pint）、`composer analyse`（Larastan level 8）、`composer test`（Pest，sqlite 内存库）。
