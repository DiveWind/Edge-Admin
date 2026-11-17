# 后端开发规范

本文是 `server/` 的分层与编码规范。接口契约以 [architecture.md](architecture.md) 为准，权限见 [permissions.md](permissions.md)，
新模块可先用 [代码生成器](generator.md) 生成骨架。约定中可机械检查的部分已固化为 Pest 架构测试（`server/tests/Arch/ArchTest.php`），
违反时 `composer test` 会失败。

## 1. 基本原则

- 只用 Laravel 官方能力与官方包（Sanctum、原生 Gate / Policy、Form Request、API Resource、队列、调度、通知），**原则上不新增依赖**。
- 文件一律用 `php artisan make:*` 生成（`make:class` 用于 Action / Service；`stubs/*.stub` 是按本规范定制的官方模板），生成后 `composer format`；目录保持 Laravel 默认结构。
- 不使用 `declare(strict_types=1)`；完整的参数 / 返回类型与 PHPDoc 泛型、数组形状；Larastan level 8 零错误，不加 baseline / ignore。
- 数据库无关：不写方言 SQL，模糊匹配 `whereLike()`，按日期 `whereDate()`，分组统计可在 PHP 侧完成。
- 用户可见文案放 `lang/zh_CN/*.php` 并同步 `lang/en`；可配置项放 `config/edge.php` 并在 `.env.example` 中给出 `EDGE_*`。

## 2. 分层职责

| 层 | 位置 | 职责 | 不应该做 |
|---|---|---|---|
| 路由 | `routes/api.php` | 声明 URL 结构与路由名（= 操作日志摘要键）：`Route::apiResources()`、`->only()`、`->parameters()` | 写业务逻辑、声明权限（权限在控制器动作上） |
| Form Request | `app/Http/Requests/<模块>/` | 字段校验（422）；仅当 Policy 需要请求体中的值时在 `authorize()` 中 `Gate::inspect()` | 写入数据、调用外部服务、重复控制器上已声明的授权 |
| Controller | `app/Http/Controllers/Api/<模块>/` | 动作上 `#[Authorize('<权限码>')]` / `#[Authorize('<policy 方法>', '<路由参数>')]` 声明授权 → 调用 Action 或**单表** Eloquent → 返回 Resource | 事务、多步写入、派发 Job、发通知、操作文件 / 缓存（架构测试禁止依赖 `DB` `Storage` `Cache` `Artisan` `Bus` `Queue` `Notification` `Gate` facade 与 `App\Jobs` `App\Notifications`） |
| Action | `app/Actions/<模块>/` | 一个写操作用例，单一 `handle()`，构造器注入依赖，**事务在 Action 内** | 依赖 HTTP 层（Request / Resource / `App\Http`） |
| Service | `app/Services/` | 可复用的读取 / 计算 / 基础设施封装（字典、系统参数、统计、系统信息、数据权限解析、菜单权限树 `PermissionRegistry`） | 依赖 HTTP 层 |
| Model | `app/Models/` | 表结构映射：Eloquent Attribute（`#[Fillable]` `#[ObservedBy]` …）、`casts()`、关系、`#[Scope]`、与自身数据相关的小方法（如 `Role::syncAuthorizations()`） | 依赖 HTTP 层与 Action；在 `booted()` 里写事件闭包（用 Observer） |
| Observer | `app/Observers/` | 模型事件的副作用：缓存失效（`DictionaryObserver` `SettingObserver` `PermissionObserver`）、派生字段（`ArticleObserver` 填发布时间） | 多步业务写入（用 Action） |
| Policy | `app/Policies/` | 记录级规则：数据权限、受保护对象、"有子项不可删"等，返回带 message 的 `Response::deny()`（403） | 功能权限判断（交给权限码 `#[Authorize]`）、写数据 |
| Resource | `app/Http/Resources/` | 输出字段（snake_case），时间 `?->toIso8601String()`，关联用 `whenLoaded()` | 查询数据库（关联应在控制器预加载） |
| Enum | `app/Enums/` | 字符串枚举（状态、类型），在 `casts()` 与 `Rule::enum()` 中使用 | — |
| Rule | `app/Rules/` | 可复用的校验规则（如 `InDict`） | — |

### 何时需要 Action

满足任一条件就放到 `app/Actions/<模块>/<动词><名词>.php`：

1. **多步写入**：写多张表或写入后同步关联（`CreateUser` 同步角色 / 岗位，`UpdateRole` 同步权限 / 部门）；
2. **带副作用**：吊销 token（`DeleteUser`、`ResetUserPassword`、`UpdateUserStatus`、`ChangePassword`）、派发 Job（`PublishAnnouncement`、
   `QueueScheduledTask`）、读写文件（`StoreAttachment`、`DeleteAttachment`）、调用命令（`RetryFailedJob`）；
3. 需要在 HTTP 之外复用（命令行、队列、测试中直接调用）。

单表、无副作用的 CRUD（`Position::create($request->validated())`）**不必**包一层 Action。缓存失效这类"写后必然发生"的副作用由 Observer 处理
（`#[ObservedBy(DictionaryObserver::class)]` 等），因此它们的 CRUD 仍是单表操作。

Action 约定：

```php
class CreateUser
{
    public function __construct(protected SomeDependency $dependency) {}   // 需要时构造器注入

    /**
     * @param  array<string, mixed>  $data  已校验数据，字段见 StoreUserRequest
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User { /* ... */ });
    }
}
```

- 入参用 `$request->validated()` 数组（`array<string, mixed>`，PHPDoc 写明来源 Form Request）或明确的标量 / 模型参数，**不为了 DTO 而 DTO**；
- 返回值强类型（模型或 `void`）；控制器通过方法注入获得 Action：`public function store(StoreUserRequest $request, CreateUser $createUser)`。

### 控制器形状

架构测试（Pest `laravel` 预设）要求控制器只有资源方法 `index` `show` `store` `update` `destroy` 或 `__invoke`。
非资源动作拆为单动作控制器：`UserStatusController@update`（`PATCH users/{user}/status`）、`PublishAnnouncementController`（`__invoke`）、
`ClearLoginLogsController`、`Options/*OptionController` 等。URL 与路由名不受影响。

## 3. 列表查询规范

```php
// Request：继承 ListRequest
class PositionIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'sort', 'created_at'];   // 排序白名单，sort=-created_at 倒序
    protected string $defaultSort = 'sort';

    protected function filterRules(): array
    {
        return ['name' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::enum(Status::class)]];
    }
}

// Model：过滤作用域统一叫 filter()
#[Scope]
protected function filter(Builder $query, array $filters): void
{
    $query->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"));
}

// Controller
$query = Position::query()->withCount('users')->filter($request->filters());

return PositionResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
```

- 字符串过滤为模糊匹配，`status` / `*_id` 精确匹配，日期范围用 `created_from` / `created_to` + `whereDate()`（先 `asAppDateTime()` 换算，见下方"日期时间"）；
- `per_page` 默认 15、上限 100（`EDGE_PER_PAGE` / `EDGE_MAX_PER_PAGE`），超出或排序字段不在白名单返回 422；
- 排序总会追加 `id` 倒序作为稳定次级排序；需要的关联用 `with()` / `withCount()` 预加载（开发环境开启了 `Model::shouldBeStrict()`，懒加载会抛异常）；
- 数据权限模型在 `filter()` 之前加 `->visibleTo($actor)`。

### 日期时间

契约（[architecture.md](architecture.md) 第 2 节）：带偏移的输入按偏移换算，不带偏移的按 `APP_TIMEZONE` 解释，输出带偏移的 ISO 8601。实现统一放在模型层：

- 所有模型 `use App\Models\Concerns\StoresDatesInAppTimezone`（架构测试强制，生成器已自动添加）。它覆盖 Eloquent 的 `fromDateTime()`：
  写入 `date` / `datetime` cast 与时间戳列之前先 `setTimezone(config('app.timezone'))`。Laravel 默认按输入自带的时区直接格式化，
  `2026-11-16T10:30:00+00:00` 会被存成 `10:30` 并按 `+08:00` 读回（偏移丢失）。数据库里始终是应用时区的本地时间，与方言无关。
- 校验用 `date` 规则即可（接受带偏移的 ISO 8601），`casts()` 照常写 `'datetime'`，不需要在 Request 中手工转换。
- 时间范围过滤同样先换算：`$q->whereDate('created_at', '>=', $this->asAppDateTime($value))`（`asAppDateTime()` 由同一 trait 提供，
  返回应用时区的 Carbon，`whereDate()` 再取日期部分）。
- 输出：Resource 中 `?->toIso8601String()`（读出的 Carbon 已是应用时区）。

## 4. 错误规范

| 场景 | 状态码 | 做法 |
|---|---|---|
| 未登录 / token 失效 / 用户被禁用 | 401 | `auth:sanctum` + `active` 中间件 |
| 无功能权限 | 403 | 控制器动作 `#[Authorize('<权限码>')]` |
| 违反记录级业务规则 | 403 + `message` | Policy 返回 `Response::deny(__('policy.xxx'))`，控制器动作 `#[Authorize('delete', 'article')]`（需要请求体时在 Form Request `authorize()`） |
| 字段级约束 | 422 + `errors` | Form Request 规则 / `after()` 钩子，文案放 `lang/*/validation.php` 的 `custom` |
| 请求体含未声明字段 | 422 + `errors.<字段>` | 全局 `FormRequest::failOnUnknownFields()`：请求体只能包含 `rules()` 中的字段（含 `*_confirmation`、`ids.*` 通配）；值本身是任意 JSON 的字段在 `prepareForValidation()` 中序列化（见 `StoreSettingRequest`） |
| 记录不存在 / 参数格式不对 | 404 | 路由模型绑定 / `findOrFail()`；`routes/api.php` 顶部的 `Route::patterns()` 让非数字 id、非 UUID 等直接不匹配路由（不会把非法值带进 PostgreSQL 查询导致 500）；统一渲染为 `{message: "资源不存在。"}`，不暴露模型类名 |
| 限流 | 429 | `throttle:api`（全局）；登录在 `LoginRequest::authenticate()` 中用 `RateLimiter` 只累计失败、成功清零，均带 `Retry-After` |

异常的 JSON 渲染集中在 `bootstrap/app.php`，`message` 按当前语言翻译（中文 JSON 文案见 `lang/zh_CN.json`）。
不要在控制器里 `try/catch` 后手工拼错误响应。

## 5. 框架级默认值（`AppServiceProvider`）

- `Model::shouldBeStrict(! isProduction())`：禁止懒加载、静默丢弃非 fillable 属性、访问不存在的属性；
- `Date::use(CarbonImmutable::class)`：日期不可变，`$date->addDay()` 返回新实例；
- `DB::prohibitDestructiveCommands(isProduction())`：生产环境禁止 `migrate:fresh` / `db:wipe` 等；
- `Password::defaults()`：生产环境至少 8 位且含字母与数字，其他环境至少 8 位；
- `URL::forceHttps(config('edge.force_https'))`；`Sanctum::usePersonalAccessTokenModel(App\Models\PersonalAccessToken::class)`；
- 限流器 `api`（`EDGE_API_RATE_LIMIT`）；登录限流不走命名限流器，而是与官方 starter kit 一样在 `LoginRequest` 中
  `RateLimiter::tooManyAttempts()` / `hit()` / `clear()`（次数取系统参数 `security.login_max_attempts`，只累计失败，成功后清零，超出 429 + `Retry-After`）；
- JSON Resource 保持默认的 `data` 包裹（契约依赖它），不调用 `withoutWrapping()`；
- `FormRequest::failOnUnknownFields()`：全部写接口拒绝契约之外的字段（见第 4 节）；
- `Queue::route([...])`：Job 的队列集中登记（`configureQueues()`，队列名取 `config('edge.queues')` / `EDGE_QUEUE_*`，Job 类上不写队列，架构测试强制）；
- `AboutCommand::add('Edge Admin', ...)`：`php artisan about` 显示版本、语言、Token 有效期、上传配置（`Number::fileSize()`）、队列路由与权限节点数。

## 6. 多语言

- `SetLocale` 中间件（`api` 组最前）按 `Accept-Language` 在 `config('edge.locales')`（`zh_CN`、`en`）中选择语言，`zh-CN` / `en-US` 写法均可，
  不匹配时用 `APP_LOCALE`，响应带 `Content-Language`；
- 需要双语的后端文案：校验消息与属性名（`validation.php`）、业务提示（`policy.php`、`messages.php`、`auth.php`）、操作日志摘要（`operations.php`）、
  框架 JSON 文案（`lang/zh_CN.json`）；
- **不做多语言的数据**：权限点 / 菜单名称（前端按 `code` 做 i18n，后端 `name` 为中文兜底，管理员改过名时前端直接显示 `name`）、字典项 `label`、系统参数值。

## 7. 基础设施的使用

```php
// 数据字典（启用项，带缓存，字典变更自动失效）
app(Dictionary::class)->items('article_source');   // [['label' => '原创', 'value' => 'original', 'color' => 'green'], ...]
app(Dictionary::class)->values('article_source');  // ['original', 'reprint', 'translation']
'source' => ['required', new InDict('article_source')],

// 系统参数（带缓存，变更自动失效；参数不存在时返回默认值，可用 config 作为默认）
setting('site.name', config('app.name'));
app(Settings::class)->integer('security.login_max_attempts', config()->integer('edge.auth.login_max_attempts'));

// 附件：上传请调用 POST /api/uploads；代码中保存文件用 StoreAttachment Action
```

## 8. 测试规范

- Pest；`tests/Arch` 架构测试最先运行，`tests/Feature` 用 `RefreshDatabase` + sqlite 内存库，`tests/Unit` 只放不依赖框架的逻辑；
- 每个接口至少覆盖：成功路径、校验失败（422）、无功能权限（403）、记录级规则（403 + message）、数据权限（越权 403 / 列表不可见）；
- 用 Factory 造数据（Factory 给出全部字段，开启了严格模式），用 `tests/Pest.php` 中的 `userWithPermissions([...])`、`superAdmin()`、`actingAsUser()`；
  需要真实 token 时（如在线会话）用 `$user->createToken()` + `withToken()`；
- 测试客户端默认 `Accept-Language: zh-CN`（见 `tests/TestCase.php`），多语言行为在 `LocaleTest` 中单独覆盖；
- 外部副作用用 `Storage::fake()`、`Queue::fake()`、`Bus::fake()`（`Bus::assertBatched()`）、`Notification::fake()` 隔离；时间相关用 `$this->freezeTime()` / `$this->travel()`；
- `tests/Pest.php`：`LazilyRefreshDatabase`（不访问数据库的测试不迁移）、每个 Feature 测试 `Http::preventStrayRequests()`（禁止意外的出站请求）；
  非生产环境的 `Model::shouldBeStrict()` 在测试中同样生效（懒加载即失败）；
- 提交前：`composer lint && composer analyse && composer test`。

## 9. 新增业务模块（清单）

1. 先在 `docs/architecture.md` 写清接口、字段、权限码；
2. `php artisan edge:make-module <Name> --module=<模块> --fields="..."`（编排官方 make 命令，见 [generator.md](generator.md)）；
   手工时使用同一组命令：`make:model <Name> -mf --policy` → `make:request <模块>/Store<Name>Request` → `make:index-request <模块>/<Name>IndexRequest` →
   `make:resource` → `make:controller Api/<模块>/<Name>Controller --api --model=<Name>` → `make:test <Name>Test --pest`，再 `composer format`；
3. 控制器动作加 `#[Authorize('<权限码>')]`，`routes/api.php` 的 `Route::apiResources()` 登记；
4. 按本规范补充：多步写入 / 副作用 → Action；记录级规则 → Policy + `#[Authorize('<方法>', '<路由参数>')]`；Seeder；中文文案；
5. `php artisan migrate && php artisan permission:sync`；
6. `composer lint && composer analyse && composer test`。

## 10. 现代 Laravel 写法约定

同一类事情全项目只用一种写法。能机械检查的已由 Pint（`pint.json`）、Larastan 与 `tests/Arch/{ArchTest,ConventionsTest}.php`、
`tests/Feature/RouteAuthorizationTest.php` 强制；表中"强制"列给出对应的检查。

### 10.1 路由与授权

| 场景 | 标准写法 | 反例 | 强制 |
|---|---|---|---|
| 导入 | `use App\Http\Controllers\Api\System\{RoleController, UserController};`（同命名空间一条语句） | 一行一个 `use` | Pint `group_import` + `ConventionsTest` |
| 标准资源 | `Route::apiResources(['users' => UserController::class, ...])`；部分动作 `->only([...])`，参数名 `->parameters([...])` | 逐个 `Route::get/post/...` 拼 CRUD | — |
| 功能权限 | 控制器动作上 `#[Authorize('system.user.view')]` | 路由上 `->middlewareFor(..., 'can:...')` / `->can()` | `ConventionsTest`（路由文件不得出现 `can:` `middlewareFor`）、`RouteAuthorizationTest`（每条受保护路由恰好一个权限码且已在 `config/permissions.php` 声明） |
| 记录级授权 | 动作上 `#[Authorize('delete', 'article')]`（第二参数为路由参数名） | 控制器里 `Gate::authorize()`、Form Request 里 `Gate::inspect('update', ...)` | 架构测试禁止控制器依赖 `Gate` |
| 需要请求体的 Policy | Form Request `authorize(): Response { return Gate::inspect('updateStatus', [$user, $status]); }` | — | — |
| 登录即可访问 | 不加权限 Attribute，并把路由名加入 `RouteAuthorizationTest::LOGIN_ONLY_ROUTES` | 静默漏掉权限 | `RouteAuthorizationTest` |

`#[Authorize]` 是官方中间件（`Illuminate\Auth\Middleware\Authorize`），`route:list -v` 中与路由声明的中间件一样显示为 `Authorize:<ability>`，路由缓存下同样生效；
执行顺序为 `auth:sanctum` → `active` → `SubstituteBindings` → `Authorize` → 解析 Form Request（校验），因此"先授权再校验"不需要额外代码。

### 10.2 模型

| 场景 | 标准写法 | 反例 | 强制 |
|---|---|---|---|
| 可填充 / 隐藏 / 表名 / 时间戳 | `#[Fillable([...])]` `#[Hidden([...])]` `#[Table('...')]` `#[WithoutTimestamps]` | `protected $fillable` / `$hidden` / `$table` / `public $timestamps = false` | `ConventionsTest` |
| 类型转换 | `protected function casts(): array`（枚举用 `Status::class`） | `protected $casts` | `ConventionsTest` |
| 集合 / 作用域 | `#[CollectedBy(TreeCollection::class)]`、`#[Scope] protected function filter(Builder $query, array $filters): void` | `newCollection()` 覆盖、`scopeFilter()` | — |
| 模型事件 | Observer + `#[ObservedBy(DictionaryObserver::class)]`，Observer 构造器注入服务 | `booted()` 中 `static::saved(fn () => app(...)->flush())` | — |
| trait 初始化 | `#[Boot] public static function fillDataScopeOwnerOnCreating(): void`（名字说明用途） | 只靠 `bootHasDataScope()` 命名约定 | — |
| 关系 | 返回类型 + 泛型 `@return BelongsTo<Department, $this>` | 无返回类型 | Larastan |

### 10.3 请求、资源、控制器

| 场景 | 标准写法 | 反例 |
|---|---|---|
| 当前用户 | 参数 `#[CurrentUser] User $user` | `$request->user()` / `auth()->user()` + 断言类型 |
| 类型化取值 | `$request->enum('status', Status::class, Status::Active)`、`->string()`、`->integer()`、`->boolean()`、`->safe()->only()`；Policy 参数用枚举类型 | `$request->input('x')` 后手工转换 |
| 校验规则 | `Rule::enum()`（限定取值用 `->only()` / `->when()`）、`Rule::date()`（`->afterOrEqual('created_from')`）、`Rule::exists()`、`Password::defaults()`、`File::types()` | 手写 `in:` 枚举值、`Rule::enum()` + `Rule::in()` 叠用 |
| 请求体字段 | 只提交 `rules()` 中声明的字段（全局 `failOnUnknownFields()`，未知字段 422）；任意结构的 JSON 值在 `prepareForValidation()` 中序列化 | 依赖"多余字段会被忽略" |
| 路由参数 | 新资源的参数名加入 `routes/api.php` 的 `// edge:route-keys` 列表（只匹配数字） | 不加约束（`RouteAuthorizationTest` 会失败） |
| 输出 | 显式字段的 API Resource，`whenLoaded()` / `whenCounted()`，时间 `?->toIso8601String()` | `parent::toArray()`、在 Resource 中查库 |

### 10.4 命令、队列、容器、配置

| 场景 | 标准写法 | 反例 | 强制 |
|---|---|---|---|
| 命令签名 | `#[Signature('permission:sync')]` `#[Description('...')]` | `protected $signature` | `ConventionsTest` |
| 队列选项 | `#[Tries(1)]` `#[DeleteWhenMissingModels]` `#[Backoff]` `#[Timeout]` | `public int $tries = 1` | `ConventionsTest` |
| 事务后入队 | Job 实现 `ShouldQueueAfterCommit` | 每处 `dispatch()->afterCommit()` | — |
| 队列选择 | `AppServiceProvider::configureQueues()` 中 `Queue::route()`，队列名取 `config('edge.queues')` | Job 上 `#[Queue]` / `onQueue()` | `ConventionsTest`、`QueueRoutingTest` |
| 大批量处理 | 编排任务 + `Bus::batch()`，每块一个 `Batchable` 任务独立重试 | 一个任务内循环处理全部数据 | — |
| 防止重复执行 | `ShouldBeUnique` + `uniqueId()` + `#[UniqueFor]` | 自己用缓存加锁 | — |
| 读多写少的统计 | `Cache::flexible($key, [fresh, stale], fn)`（`config('edge.statistics_cache')`） | 每次请求现算 / 固定 TTL 到期后同步重算 | — |
| 类型化读取数组 | `Arr::string($node, 'code')`、`Arr::boolean($node, 'visible', true)` | `(string) $node['code']` | — |
| 配置注入 | 构造器 / handle 参数 `#[Config('edge.auth.token_ttl')] int $tokenTtl` | 类里散落 `config('...')` | — |
| 类型化读取（不可注入处） | `config()->integer()` / `->string()` / `->array()` / `->boolean()` | `(int) config('...')` | — |
| 请求内缓存 | 服务通过 `CacheManager::memo()` 读取（memo 为 scoped 绑定，每个请求 / 队列任务重置） | 自己写静态缓存属性 | — |
| 每请求单例 | `#[Scoped]`（`PermissionRegistry`） | provider 中手写 `scoped()` 绑定 | — |
| 日志上下文 | `Context`：`AddRequestContext` 写入 `request_id`（响应头 `X-Request-Id`），`AddAuthenticatedUserToContext` 写入 `user_id`；自动带到日志与队列任务 | 每条日志手动拼 request id | — |

### 10.5 PHP 8.3

- 类型化类常量（`public const string SUPER_ADMIN`）、构造器属性提升、`readonly` 值对象（`DataVisibility`、`ScheduledTask`）、`match`、一等可调用（`trim(...)`、`class_basename(...)`）、`json_validate()`、`never`；
- 密码参数加 `#[\SensitiveParameter]`（`AttemptLogin`、`ChangePassword`、`ResetUserPassword`），异常栈与日志中不会出现明文；
- `#[\Override]` 只用在**有意覆盖项目自身或框架具体方法**的地方（`UpdatePermissionRequest` 等继承 Store 请求的子类、`StoresDatesInAppTimezone::fromDateTime()`），
  父类方法改名时立即报错；实现抽象方法 / 接口（Resource `toArray()`、Factory `definition()`）不加，避免满屏噪音。

### 10.6 Laravel 11 → 13 新特性清单与结论

逐项对照官方文档（`laravel/docs` 13.x 源文件、11.x / 12.x / 13.x `releases.md`）与框架 CHANGELOG，并在 `vendor/` 中确认 13.42 已提供。
"采用"列给出落地位置；"不采用"给出具体理由（"现有写法也能用"不算理由）。

#### Laravel 13 发行说明点名的能力

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `Queue::route()` 队列集中路由 | **采用** | `AppServiceProvider::configureQueues()`，队列名来自 `config('edge.queues')`（`EDGE_QUEUE_NOTIFICATIONS` / `EDGE_QUEUE_TASKS`，留空为默认队列）。拆队列只改环境变量；`ConventionsTest` 禁止 Job 上写 `#[Queue]` / `#[Connection]`，`QueueRoutingTest` 要求每个 Job 都已登记。注意批处理不经过路由表，`SendAnnouncementNotifications` 用 `Queue::resolveQueueFromQueueRoute()` 取同一张表 |
| `Cache::touch()` | 不采用 | 项目没有"滑动过期"的缓存项：字典 / 参数为 `rememberForever` + Observer 失效，统计为 `flexible()`。出现会话类缓存时再用 |
| JSON:API Resources | 不采用（待负责人决定） | 会整体改变响应包络（`data.attributes` / `relationships` / `included`）与前端全部 service，见评估报告 |
| 扩展的 Attributes | **部分采用** | 新增 `#[UniqueFor]`、`#[WithoutRelations]`、`#[Backoff]`（Job）。`#[FailOnUnknownFields]` 改为全局开启（见下）。`#[RetryFor]` 文档已列出但 13.42 的 `Illuminate\Queue\Attributes` 中尚不存在；`#[DebounceFor]` 无"频繁重复派发"的 Job；`#[Seed]` / `#[Seeder]` 是 PHPUnit 类级 Attribute，Pest 测试不需要种子数据（Factory 构造） |
| Request Forgery Protection（`PreventRequestForgery`） | 不适用 | 它保护**基于 Cookie / Session** 的 `web` 组（`Sec-Fetch-Site` 同源校验 + CSRF token 回退）。本项目 `/api/*` 只认 `Authorization: Bearer`，浏览器不会自动携带该头，跨站请求无法冒用凭证，`api` 组也不含 Session；若将来改用 Sanctum SPA Cookie 认证，必须在 `api` 组启用 `statefulApi()`（会带上该中间件） |
| 语义 / 向量搜索（`whereVectorSimilarTo`） | 不采用 | 依赖 PostgreSQL + pgvector 与嵌入模型，项目默认 SQLite 且要求数据库无关；没有全文 / 语义检索需求 |
| Laravel AI SDK（`laravel/ai`） | 不采用（待负责人决定） | 需要新增依赖与模型服务商密钥，见评估报告（可选演示模块方案） |

#### 缓存

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `Cache::flexible()`（stale-while-revalidate） | **采用** | `DashboardStatistics`（8 项计数、14 天登录趋势、分类 / 部门分布）与 `SystemInfo::storage()`（磁盘、库大小、附件总量）。时长 `config('edge.statistics_cache')`（默认新鲜 60 秒、可陈旧至 300 秒，`fresh=0` 关闭）；过期后的请求先拿旧值，重算通过 `defer()` 在响应后进行且有锁。"最近操作"保持实时 |
| `Cache::memo()` | 已采用 | `Dictionary`、`Settings` 通过 `CacheManager::memo()` 读取，每请求 / 任务只访问一次缓存存储 |
| 缓存锁 / `withoutOverlapping` | **采用** | 调度任务组 `withoutOverlapping()->onOneServer()`（`routes/console.php`），`RunScheduledTask` 的 `ShouldBeUnique` 都基于缓存锁 |
| `Cache::remember` + 标签 | 不采用 | 标签不被 database / file 驱动支持（默认 `CACHE_STORE=database`）；按 key 失效已足够 |

#### 请求与校验

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `FormRequest::failOnUnknownFields()` | **采用（推翻上一轮）** | `AppServiceProvider` 全局开启：写接口请求体出现契约外字段返回 422（错误挂在该字段上）。拼错的字段名不再被静默忽略，前端 42 个 e2e 用例验证前端只发送契约字段。系统参数 json 值是任意结构：在 `StoreSettingRequest::prepareForValidation()` 中序列化后按 `json` 规则校验，无需退出检查。查询参数不受影响（框架只检查请求体） |
| `$request->enum()/string()/integer()/boolean()/safe()` | 已采用，补齐 | `UpdateUserStatusRequest::authorize()` 改为 `$this->enum('status', Status::class)`，`UserPolicy::updateStatus()` 参数变为 `?Status`；`UserStatusController` 用 `enum()` 的默认值参数 |
| `$request->date()` / `float()` / `collect()` / `fluent()` | 不采用 | 日期过滤需要按 `APP_TIMEZONE` 换算（`asAppDateTime()`，见第 3 节），`date()` 只解析不换算；没有 float 字段；`filters()` 已返回已校验数组，`fluent()` / `collect()` 只会多一层包装 |
| `Rule::enum()->only()/except()` + `when()` | **采用** | `RoleRequest`（超级管理员角色只能为启用）、`UpdateUserStatusRequest`（id=1 只能为启用）：一个规则替代 `Rule::enum()` + `Rule::in([...])` 两个 |
| `Rule::date()` 流式规则 | **采用** | 列表的 `created_from` / `created_to`（`Rule::date()->afterOrEqual('created_from')`），生成器的 date / datetime 字段与过滤规则 |
| `Rule::anyOf()` | 不采用 | 唯一的"多种形态"字段（系统参数 json 值：对象 / 数组 / JSON 字符串）在 `prepareForValidation()` 归一为字符串后用内置 `json` 规则，比 `anyOf` 更简单 |
| `Rule::numeric()` / `Rule::array()` / `File::image()` 流式 | 不采用 | 现有规则只有 `numeric` / `array` 一个约束，流式写法没有额外信息；上传已用 `File::types()->max()` |
| `required_if_accepted`、`prohibits` 等新规则 | 不采用 | 没有"勾选后必填 / 互斥字段"的表单；类型相关字段在 `prepareForValidation()` 中归一（`StorePermissionRequest`） |
| `#[StopOnFirstFailure]` | 不采用 | 前端按字段回填全部 422 错误 |

#### Eloquent / 查询

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| 路由参数约束（`Route::patterns()`） | **采用（推翻上一轮）** | `routes/api.php` 顶部：模型 id `[0-9]+`、通知 / 失败任务 UUID、定时任务 key slug。非法值直接 404，不会进入 PostgreSQL 的 bigint / uuid 比较（原来 500）。`RouteAuthorizationTest` 要求每个 `/api` 路由参数都有约束；生成器在 `// edge:route-keys` 锚点自动登记 |
| `whereAny` / `whereAll` / `whereNone` | 不采用（推荐写法） | 契约没有"关键字跨字段搜索"参数，每个过滤字段单独匹配。新增 `keyword` 过滤时用 `$q->whereAny(['username', 'name', 'phone'], 'like', "%{$value}%")`（注意 `whereLike()` 在 PostgreSQL 下大小写不敏感，`whereAny` + `like` 敏感） |
| `whereLike` / `whereRelation` / `whereHas` | 已采用 | 模糊匹配统一 `whereLike()`；`User::filter()` 用 `whereRelation('roles', ...)`；关联字段模糊匹配仍用 `whereHas(fn => whereLike())`（`whereRelation` 不支持大小写不敏感的 like） |
| `withWhereHas` / `whereBelongsTo` / `whereAttachedTo` | 不采用 | 没有"按关联过滤且同时加载该关联"的列表；过滤参数是 id 而不是模型实例 |
| `Model::automaticallyEagerLoadRelationships()` | 不采用 | 项目在非生产环境用 `shouldBeStrict()` 让懒加载直接失败，并在控制器显式 `with()`；自动预加载会让这道检查失效，N+1 不再在开发期暴露 |
| `chaperone()` / `latestOfMany()` | 不采用 | 树由 `TreeCollection` 组装且不回访父节点；"最近一条执行日志"的主体是调度事件而不是模型（`ScheduledTaskRegistry` 用一次分组子查询） |
| `#[UseResource]` + `toResource()` | 不采用 | 模型会依赖 `App\Http\Resources`（架构测试禁止模型依赖 HTTP 层）；`toResource()` 返回类型为 `JsonResource`，Larastan 3 没有对应扩展，控制器返回类型会退化 |
| `#[CollectedBy]` / `#[ObservedBy]` / `#[Scope]` | 已采用 | 见 10.2 |
| `Prunable` / `MassPrunable` | 已采用 | 登录 / 操作 / 任务日志由 `model:prune` 清理 |
| `preventLazyLoading` 等严格模式 | 已采用 | `Model::shouldBeStrict(! isProduction())`，测试中同样生效 |
| `incrementEach` / `upsert` / `createOrFirst` | 不采用 | 没有计数器字段；权限同步需要按层级回填 parent_id 并统计新增 / 修正数，不能用一次 `upsert` |
| `createQuietly` / `withoutEvents` | 不采用 | 所有写入都需要触发 Observer（缓存失效、发布时间） |
| 游标 / 简单分页 | 不采用 | ProTable 需要 `total` 与跳页，契约为 `LengthAwarePaginator` |
| `Attribute::make` 访问器 / JSON 列查询 / `when` 链式条件 | 已采用 / 不适用 | `Attachment::url()` 为 `Attribute::get()`，过滤作用域用 `->when()`；没有按 JSON 子字段查询的需求 |
| 类型化读取 `Arr::string()/boolean()/array()` | **采用** | `SyncBuiltinPermissions` 读取 `config/permissions.php`：配置写错类型时直接报错，不再 `(string)` / `(bool)` 静默转换后写库 |

#### 辅助函数

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `Number` | **采用** | `php artisan about` 的上传上限 `Number::fileSize()`。接口返回原始字节数 / 毫秒，由前端按语言格式化（契约），后端不格式化 |
| `rescue()` | **采用** | `SystemInfo` 的磁盘空间、表大小采集（替代 `try/catch`） |
| `Str` / `Stringable` | **采用** | `DictOptionRequest::codes()`（`string()->explode()` 链式）、`RecordOperationLog`（`Str::start()`） |
| `once()` | 不采用（理由更新） | `Once` 只在测试结束与 Octane 请求边界清空：在队列 worker 中静态调用的 `once()` 会跨任务一直保留旧值；对象内的 `once()` 无法按实例失效。权限码 / 菜单树需要在角色、节点变更后立即失效，已由 `#[Scoped] PermissionRegistry` + `flush()` 解决 |
| `defer()` | 间接采用 | `Cache::flexible()` 的后台重算基于 `defer()`；操作日志仍在 terminable 中间件 `terminate()` 中写入（同为响应后执行，是中间件的官方机制）；最后登录时间属于登录用例，需同步写入 |
| `Uri` / `fluent()` / `literal()` / `Arr::from()` | 不采用 | 后端不拼装 / 解析 URL（附件 URL 由 `Storage::url()` 生成）；没有需要临时对象的结构化数据 |
| `Benchmark` / `Sleep` / `Lottery` / `Pipeline` | 不采用 | 耗时统计跨越 `try/catch` 与中间件的 `handle` / `terminate` 两个阶段（`hrtime()`），`Benchmark::value()` 在异常时拿不到耗时；没有重试等待、概率采样或管道式处理 |

#### 基础设施

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `Context`（含队列透传） | 已采用 | `request_id` / `user_id` 自动带到日志与队列任务；`Context::scope()`、hidden context 暂无需要（没有只在局部生效或不应进日志的上下文） |
| `Concurrency::run()` / `defer()` | 不采用 | 默认 `process` 驱动为每个任务启动一个 PHP 进程并重新引导 Laravel（约数十毫秒），而工作台每项统计是毫秒级的单条查询，并行反而更慢；SQLite 内存库的测试也无法跨进程。统计改用 `Cache::flexible()` |
| `RateLimiter`（`perSecond`、`by`、`response`、`after()`） | 不采用 | 现有 `api` 限流按用户 / IP 每分钟计数已满足契约；429 的 JSON 渲染集中在 `bootstrap/app.php`，不需要每个限流器自定义 `response` |
| `Schedule::group()` / `onOneServer()` / `withoutOverlapping()` | **采用** | `routes/console.php` 用分组声明共同属性，多台服务器只有一台执行；新增 `queue:prune-batches`（清理公告群发的批处理记录） |
| `Process` / `Http` 客户端 | 不适用 | 没有外部进程与出站 HTTP；测试中 `Http::preventStrayRequests()` 防止意外引入 |
| 健康检查 `/up` + `DiagnosingHealth` | **采用** | `App\Listeners\CheckDatabaseConnection`（事件自动发现）：数据库不可用时 `/up` 返回 500，负载均衡可据此摘除实例 |
| 异常 `dontReportDuplicates()` | **采用** | `bootstrap/app.php`：同一异常实例只上报一次 |
| 异常 `throttle()` | 不采用 | 只写本地日志，没有按量计费的上报服务 |
| `Log::shareContext()` | 不采用 | `Context` 已覆盖且会透传到队列任务 |
| `Storage::serve` / 临时 URL / scoped disk | 不采用 | 附件在 public 磁盘直接访问；私有附件需求出现时再用 `temporaryUrl()` |
| `config()->integer()` 等类型化读取 / `#[Config]` 注入 | 已采用 | 见 10.4；`env()` 在 13.42 没有类型化版本，`config/edge.php` 中保留 `(int) env()` |
| `bootstrap/app.php` 流式配置（`withSchedule`、`withEvents`） | 不采用 | 调度保留在 `routes/console.php`（`ScheduledTaskRegistry` 与官方默认结构一致），监听器使用事件自动发现 |
| `AboutCommand::add()` | **采用** | `php artisan about` 中的 Edge Admin 区块 |

#### 队列 / 事件 / 通知

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `Bus::batch()` / `Batchable` | **采用** | 公告群发：`SendAnnouncementNotifications` 按 500 人一块派发批处理 `announcement:{id}`（`allowFailures()`），`SendAnnouncementNotificationChunk` 每块独立重试（`#[Tries(3)]`、`#[Backoff([10, 60])]`）。原来一个任务内循环发送，中途失败重试会给已发送的用户重复发通知；现在只重发失败的块，多 worker 可并行 |
| `ShouldBeUnique` + `#[UniqueFor]` | **采用** | `RunScheduledTask` 按命令加唯一锁：重复点击"立即执行"不会让同一命令并发 |
| `#[WithoutRelations]` | **采用** | 两个公告任务：序列化时不带已加载的关联，执行时也不重新加载 |
| `ShouldQueueAfterCommit` / `afterCommit` | 已采用 | 公告编排任务 |
| `WithoutOverlapping` / `RateLimited` / `Skip` job 中间件 | 不采用 | 并发控制已由唯一任务覆盖；没有调用外部限额服务的任务 |
| `ShouldBeEncrypted` | 不采用 | 队列载荷只有模型 id、用户 id 与命令名，不含敏感数据 |
| 事件 `ShouldDispatchAfterCommit` / 事件发现 | 已采用 / 不适用 | 监听器由事件发现注册；项目没有自定义领域事件 |
| 队列化通知 | 不采用 | 通知在分块任务内 `sendNow()`，本身已在队列中，再排队只会多一跳 |

#### 测试

| 特性 | 结论 | 落地位置 / 理由 |
|---|---|---|
| `LazilyRefreshDatabase` | **采用** | `tests/Pest.php`：不访问数据库的 Feature 测试（生成器、路由约束等）不再迁移 |
| `Http::preventStrayRequests()` | **采用** | `tests/Pest.php` 的 `beforeEach` |
| `freezeTime()` / `travel()` | **采用** | `CommonEndpointsTest` 验证统计缓存的新鲜期 / 陈旧期 |
| `Bus::fake()` + `assertBatched()` | **采用** | `AnnouncementTest` |
| `assertExactJson` / `assertOnlyJsonValidationErrors` | **采用** | 路由约束 404、未知字段 422 的断言 |
| `Exceptions::fake()` / `Context` 断言 / `withoutDefer()` | 不采用 | 健康检查用 Facade mock 模拟数据库故障即可；`Context` 已有 `request_id` 断言；需要验证的正是 deferred 重算，不能关闭 defer |
| `#[Seed]` / `#[Seeder]` | 不采用 | Pest 函数式测试，数据由 Factory 构造 |
| 并行测试（`--parallel`） | 不采用 | 全量约 22 秒，进程启动开销大于收益；CI 可按需加 |

### 10.7 重新审视后推翻的上一轮决定

| 上一轮"不采用" | 现在 | 原因 |
|---|---|---|
| 全局 `failOnUnknownFields()` | 采用 | "会改变契约"恰恰是目的：契约只定义了这些字段，多余字段被静默丢弃会掩盖前端拼写错误与过时字段；e2e 证明前端只发送契约字段 |
| `->whereNumber()` 约束资源 id | 采用（`Route::patterns()`） | PostgreSQL 下非数字 id 会 500，这是 bug 而不是风格问题；按参数名的全局约束只需一处声明，生成器自动登记，测试保证不遗漏 |
| `once()` | 仍不采用，理由更正 | 上一轮理由"无法按实例清除"不完整，真正的问题是 `Once` 在队列 worker 中不会在任务之间清空 |
| `#[UseResource]` + `toResource()` | 仍不采用，理由补充 | 除类型退化外，模型标注 Resource 会让模型层依赖 HTTP 层（违反分层架构测试） |

