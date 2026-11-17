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
| 记录不存在 | 404 | 路由模型绑定 / `findOrFail()`；统一渲染为 `{message: "资源不存在。"}`，不暴露模型类名 |
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
- JSON Resource 保持默认的 `data` 包裹（契约依赖它），不调用 `withoutWrapping()`。

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
- 外部副作用用 `Storage::fake()`、`Queue::fake()`、`Notification::fake()` 隔离；
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
| 类型化取值 | `$request->enum('status', Status::class)`、`->string()`、`->integer()`、`->boolean()`、`->safe()->only()` | `$request->input('x')` 后手工转换 |
| 校验规则 | `Rule::enum()`、`Rule::exists()`、`Password::defaults()`、`File::types()` | 手写 `in:` 枚举值 |
| 输出 | 显式字段的 API Resource，`whenLoaded()` / `whenCounted()`，时间 `?->toIso8601String()` | `parent::toArray()`、在 Resource 中查库 |

### 10.4 命令、队列、容器、配置

| 场景 | 标准写法 | 反例 | 强制 |
|---|---|---|---|
| 命令签名 | `#[Signature('permission:sync')]` `#[Description('...')]` | `protected $signature` | `ConventionsTest` |
| 队列选项 | `#[Tries(1)]` `#[DeleteWhenMissingModels]` `#[Backoff]` `#[Timeout]` | `public int $tries = 1` | `ConventionsTest` |
| 事务后入队 | Job 实现 `ShouldQueueAfterCommit` | 每处 `dispatch()->afterCommit()` | — |
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

### 10.6 评估后不采用的写法

| 特性 | 原因 |
|---|---|
| `#[UsePolicy]` `#[UseFactory]` `#[UseModel]` `#[Table]`（约定命名的模型） | 全部模型、Policy、Factory 都遵循命名约定，框架自动发现；只在不符合约定时才写 |
| `#[UseResource]` + `$model->toResource()` | 返回类型只能推断为 `JsonResource`，`new XxxResource($model)` 对 Larastan / Scramble（OpenAPI）更精确 |
| 全局 `FormRequest::failOnUnknownFields()` / `#[FailOnUnknownFields]` | 会改变契约（目前未知字段被忽略，前端 ProTable / 表单可能携带额外字段），需前后端一起评估后再启用 |
| `#[StopOnFirstFailure]` | 前端按字段回填全部 422 错误，只返回第一个错误会降低体验 |
| `defer()` 写操作日志 / 最后登录时间 | 操作日志已在 terminable 中间件 `terminate()` 中于响应发送后写入（官方机制，语义相同）；最后登录时间属于登录事务的一部分，需同步写入 |
| `once()` 缓存权限码 | 需要在角色变更后按实例失效（`flushPermissionCache()`），`once()` 无法按实例清除 |
| 枚举 `label()` / `options()` | 枚举文案由前端按值做 i18n（契约约定），后端没有使用方，不为了特性而加 |
| `->whereNumber()` 约束资源 id | 隐式模型绑定已对不存在的 id 返回 404；参数名随资源变化，逐个声明收益小（PostgreSQL 下非数字 id 的 500 记为遗留项） |
| 全量 `#[\Override]`（PHPStan `checkMissingOverrideMethodAttribute`） | 见 10.5，只在有意覆盖具体方法处使用 |
