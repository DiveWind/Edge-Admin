# 后端开发规范

本文是 `server/` 的分层与编码规范。接口契约以 [architecture.md](architecture.md) 为准，权限见 [permissions.md](permissions.md)，
新模块可先用 [代码生成器](generator.md) 生成骨架。约定中可机械检查的部分已固化为 Pest 架构测试（`server/tests/Arch/ArchTest.php`），
违反时 `composer test` 会失败。

## 1. 基本原则

- 只用 Laravel 官方能力与官方包（Sanctum、原生 Gate / Policy、Form Request、API Resource、队列、调度、通知），**原则上不新增依赖**。
- 文件一律用 `php artisan make:*` 生成（`make:class` 用于 Action / Service），目录保持 Laravel 默认结构。
- 不使用 `declare(strict_types=1)`；完整的参数 / 返回类型与 PHPDoc 泛型、数组形状；Larastan level 8 零错误，不加 baseline / ignore。
- 数据库无关：不写方言 SQL，模糊匹配 `whereLike()`，按日期 `whereDate()`，分组统计可在 PHP 侧完成。
- 用户可见文案放 `lang/zh_CN/*.php` 并同步 `lang/en`；可配置项放 `config/edge.php` 并在 `.env.example` 中给出 `EDGE_*`。

## 2. 分层职责

| 层 | 位置 | 职责 | 不应该做 |
|---|---|---|---|
| 路由 | `routes/api.php` | 声明 URL、路由名（= 操作日志摘要键）、功能权限 `can:<权限码>` | 写业务逻辑 |
| Form Request | `app/Http/Requests/<模块>/` | 字段校验（422）；编辑类接口在 `authorize()` 中 `Gate::inspect()` 做记录级授权，保证先授权后校验 | 写入数据、调用外部服务 |
| Controller | `app/Http/Controllers/Api/<模块>/` | 授权 → 调用 Action 或**单表** Eloquent → 返回 Resource | 事务、多步写入、派发 Job、发通知、操作文件 / 缓存（架构测试禁止依赖 `DB` `Storage` `Cache` `Artisan` `Bus` `Queue` `Notification` facade 与 `App\Jobs` `App\Notifications`） |
| Action | `app/Actions/<模块>/` | 一个写操作用例，单一 `handle()`，构造器注入依赖，**事务在 Action 内** | 依赖 HTTP 层（Request / Resource / `App\Http`） |
| Service | `app/Services/` | 可复用的读取 / 计算 / 基础设施封装（字典、系统参数、统计、系统信息、数据权限解析） | 依赖 HTTP 层 |
| Model | `app/Models/` | 表结构映射：`#[Fillable]`、`casts()`、关系、`#[Scope]`、与自身数据相关的小方法（如 `Role::syncAuthorizations()`） | 依赖 HTTP 层与 Action |
| Policy | `app/Policies/` | 记录级规则：数据权限、受保护对象、"有子项不可删"等，返回带 message 的 `Response::deny()`（403） | 功能权限判断（交给 `can:`）、写数据 |
| Resource | `app/Http/Resources/` | 输出字段（snake_case），时间 `?->toIso8601String()`，关联用 `whenLoaded()` | 查询数据库（关联应在控制器预加载） |
| Enum | `app/Enums/` | 字符串枚举（状态、类型），在 `casts()` 与 `Rule::enum()` 中使用 | — |
| Rule | `app/Rules/` | 可复用的校验规则（如 `InDict`） | — |

### 何时需要 Action

满足任一条件就放到 `app/Actions/<模块>/<动词><名词>.php`：

1. **多步写入**：写多张表或写入后同步关联（`CreateUser` 同步角色 / 岗位，`UpdateRole` 同步权限 / 部门）；
2. **带副作用**：吊销 token（`DeleteUser`、`ResetUserPassword`、`UpdateUserStatus`、`ChangePassword`）、派发 Job（`PublishAnnouncement`、
   `QueueScheduledTask`）、读写文件（`StoreAttachment`、`DeleteAttachment`）、调用命令（`RetryFailedJob`）；
3. 需要在 HTTP 之外复用（命令行、队列、测试中直接调用）。

单表、无副作用的 CRUD（`Position::create($request->validated())`）**不必**包一层 Action。缓存失效这类"写后必然发生"的副作用由模型事件处理
（字典、系统参数在 `booted()` 中清缓存），因此它们的 CRUD 仍是单表操作。

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
| 无功能权限 | 403 | 路由 `can:<权限码>` |
| 违反记录级业务规则 | 403 + `message` | Policy 返回 `Response::deny(__('policy.xxx'))`，控制器 `Gate::authorize()` 或 Form Request `authorize()` |
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
- **不做多语言的数据**：权限点 / 菜单名称（前端按 `code` 做 i18n，后端 `name` 为中文兜底）、字典项 `label`、系统参数值。

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
2. `php artisan edge:make-module <Name> --module=<模块> --fields="..."`（或手工：`make:model -mf --policy` → `make:request` → `make:resource` →
   `make:controller Api/<模块>/<Name>Controller --api` → `make:test --pest`）；
3. 按本规范补充：多步写入 / 副作用 → Action；记录级规则 → Policy；Seeder；中文文案；
4. `php artisan migrate && php artisan permission:sync`；
5. `composer lint && composer analyse && composer test`。
