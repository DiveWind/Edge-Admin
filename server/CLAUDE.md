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

完整规范见 `../docs/backend-guide.md`；可机械检查的部分由 `tests/Arch/ArchTest.php`（Pest php / security / laravel 预设 + 分层规则）强制执行。

- 不使用 `declare(strict_types=1)`（与 Laravel / Pint `laravel` 预设保持一致）；完整的参数与返回类型、PHPDoc 泛型 / 数组形状。
- 文件一律用 `php artisan make:*` 生成（Action / Service 用 `make:class`，枚举用 `make:enum <Name>`，不要带 `Enums/` 前缀；列表请求 `make:index-request`、
  选项控制器 `make:option-controller`）；`stubs/*.stub` 是按本规范定制的官方模板，生成后执行 `composer format`（Pint 两遍：拆开再合并分组导入）；
  目录保持 Laravel 默认结构，控制器按模块分子命名空间 `App\Http\Controllers\Api\{Auth,System,Monitor,Message,Content,Options}`。
- 控制器只保留资源方法（index / show / store / update / destroy）或 `__invoke`，非资源动作拆为单动作控制器；
  只做"授权 → 调 Action 或单表 Eloquent → 返回 Resource"，不得依赖 `DB` / `Storage` / `Cache` / `Artisan` / `Gate` 等 facade 与 Jobs / Notifications。
- **授权声明在控制器动作上**（`routes/api.php` 只描述结构：分组导入、`Route::apiResources()`、`->only()`、`->parameters()`）：
  功能权限 `#[Authorize('<操作码>')]`，记录级规则 `#[Authorize('<policy 方法>', '<路由参数>')]`（官方 `Illuminate\Routing\Attributes\Controllers\Authorize`）。
  登录即可访问的路由须加入 `tests/Feature/RouteAuthorizationTest.php` 的 `LOGIN_ONLY_ROUTES`，否则测试失败。
- **多步写入或带副作用**（同步关联、吊销 token、派发 Job、读写文件）的用例放 `app/Actions/<模块>/`：单一 `handle()`、构造器注入、
  事务在 Action 内、入参为 `$request->validated()`（`array<string, mixed>`，PHPDoc 注明来源 Request）或明确的模型 / 标量；Actions 不依赖 HTTP 层。
- 模型：一律用 Eloquent Attribute 配置（`#[Fillable]` / `#[Hidden]` / `#[CollectedBy]` / `#[ObservedBy]` / `#[WithoutTimestamps]` …，架构测试禁止 `$fillable` 等属性）、
  `casts()` 中用 `app/Enums` 的枚举、`#[Scope]` 局部作用域（列表过滤统一叫 `filter(array $filters)`）；模型事件逻辑（缓存失效、派生字段）写成 Observer + `#[ObservedBy]`。
  开发环境开启 `Model::shouldBeStrict()`，Factory 需给出全部字段；日期为 `CarbonImmutable`。所有模型 `use StoresDatesInAppTimezone`
  （带偏移的日期时间输入换算到 `APP_TIMEZONE` 后写库，架构测试强制），时间范围过滤用 `$this->asAppDateTime($value)`。
- 树形数据（部门、权限）用 `#[CollectedBy(App\Collections\TreeCollection::class)]` + `->toTree()` 在 PHP 侧组装，不写递归 SQL。
- 列表请求继承 `App\Http\Requests\ListRequest`：声明 `filterRules()`、`$sortable`、`$defaultSort`；控制器 `->filter($request->filters())`、
  `$request->applySort($query)->paginate($request->perPage())->withQueryString()`。
- 功能权限 / 动态菜单：`permissions` 是目录 / 菜单 / 操作三级树。内置节点在 `config/permissions.php` 声明（菜单带 `path` / `icon`）→ `php artisan permission:sync`
  （只新增缺失项、修正 type / path / is_external，不覆盖界面修改、不删除节点）→ 控制器动作 `#[Authorize('<操作码>')]`。
  操作权限码必须含 `.`。权限判定一律走 `User::permissionCodes()` / `hasPermission()`（经 `App\Services\PermissionRegistry`：每请求一次查询，
  停用节点含后代不生效）；绕过模型事件批量改 `permissions` 表后调用 `PermissionRegistry::flush()`。
- 记录级规则写在 Policy（数据权限、受保护对象、"有子项不可删"等，返回带 message 的 `Response::deny()` → 403），在动作上 `#[Authorize('update', 'article')]`
  调用（中间件先于 Form Request 校验执行，保证先授权再校验）；只有 Policy 需要请求体中的值时才写在 Form Request 的 `authorize()`。字段级约束写在 Form Request（422）。
- 数据权限：模型 `use HasDataScope`，列表 `->visibleTo($user)`，单条在 Policy 中 `$model->isVisibleTo($user)`。
- 输出统一用 API Resource，时间字段 `?->toIso8601String()`。
- 数据字典用 `App\Services\Dictionary` / `new App\Rules\InDict('code')`；系统参数用 `setting('key', $default)`（`App\Services\Settings`），
  两者带缓存（`Cache::memo()`，每请求 / 任务只读一次缓存存储），由 `DictionaryObserver` / `SettingObserver` 自动失效。
- 用户可见文案放 `lang/zh_CN/*.php` 并同步 `lang/en`，接口语言由 `SetLocale` 按 `Accept-Language` 切换；操作日志摘要以路由名为键写在 `lang/*/operations.php`。
- 可配置项放 `config/edge.php` 并在 `.env.example` 中给出 `EDGE_*` 变量；可注入的类（Action / 中间件 / 命令）用 `#[Config('edge.xxx')]` 构造器注入，
  其余用 `config()->integer()` / `->array()` 等类型化读取。命令用 `#[Signature]` / `#[Description]`，Job 用 `#[Tries]` 等队列 Attribute（架构测试强制）。
- 导入：同一命名空间合并为 `use A\B\{C, D};`（Pint `group_import`，`composer format` 自动处理，`ConventionsTest` 检查）。完整写法约定见 backend-guide「现代 Laravel 写法约定」。
- 保持数据库无关：不写方言 SQL，模糊匹配用 `whereLike()`，按日期用 `whereDate()`，分组统计可在 PHP 侧完成。
- `routes/api.php`、`config/permissions.php`、`lang/*/operations.php` 中的 `// edge:*` 注释是代码生成器的锚点，请保留；`stubs/*.stub` 中的 `//` 占位行与
  `#[Fillable([])]`、`$table->id();` 等是生成器补全字段代码的位置，定制模板时请保留。

## 新增业务模块

优先用生成器：`php artisan edge:make-module <Name> --module=<模块> --fields="..." [--data-scope] [--soft-deletes] [--with-options]`（缺参数时交互询问，见 `../docs/generator.md`）。
它依次调用官方 `make:model` / `make:factory` / `make:migration` / `make:policy` / `make:request` / `make:resource` / `make:controller --type=module` / `make:test --pest`
与项目的 `make:index-request` / `make:option-controller`，再补全字段、登记权限 / 路由 / 操作日志并生成前端页面。之后按规范补充 Action / Policy 规则 / Seeder / 中文文案。
手工方式参照文章模块（`Article*`）：同样用上述 make 命令 → `config/permissions.php` 加权限点并 `permission:sync` → 控制器动作加 `#[Authorize]` →
`routes/api.php` 的 `Route::apiResources()` 登记 → `lang/*/operations.php` 摘要 → `composer format` → 更新 `../docs/architecture.md`。

## 验证

提交前在本目录执行：`composer lint`（Pint）、`composer analyse`（Larastan level 8）、`composer test`（Pest：架构测试 + Feature + Unit，sqlite 内存库）。
