# Monitor

内核模块，目录 `app/Core/Monitor`。结构与规则见设计文档 §4.3、§5；接口与规则见 §6.6、§7.3。

## 职责

- 日志的存储：操作日志、登录日志与各模块的数据变更审计统一存在 spatie/laravel-activitylog 的 `activity_log` 表中，按日志名区分；
- 操作日志（`operation`）：写入用例 `LogOperation`（含脱敏）；记录写请求的中间件在后台入口（`App\Http\Admin\Middleware\RecordOperationLog`）；
- 登录日志（`login`）：监听认证事件（`Listeners/RecordLoginActivity`），写入用例 `LogLogin`；
- 在线会话与强制下线：database 会话驱动的会话表（`Models/Session`）与 Sanctum 的 token（`Queries/ActiveTokens`）；
- 日志与在线会话的授权与数据范围。

不负责：接口的路由与输出格式（`app/Http/Admin/Monitor`）；定时任务、系统监控在后续接入。

## 对外提供

| 类 | 用途 |
|---|---|
| `Models/Activity` | activitylog 的模型（`config('activitylog.activity_model')`，多态别名 `activity`）：写入时自动在 `properties.request_id` 中记下 Context 的请求 ID；`visibleTo($actor)` / `isVisibleTo($actor)`：数据范围委托给 causer 用户 |
| `Models/OperationLog`、`Models/LoginLog` | 继承 `Activity`，各带日志名（`LOG_NAME`）的全局作用域；只读 |
| `Models/Session` | 会话表的一行（表名、连接取自 `config('session.*')`）：`online()`（已登录、未过期；非 database 驱动时为空）、`visibleTo()`、`publicId()`（会话 ID 的前 16 位，路由绑定按它查找）、`isCurrent()` |
| `Queries/ActiveTokens` | 有效的后台用户 token（与 Sanctum 的过期校验一致，按所属用户的数据范围过滤）；`isCurrent()`：是否为本次请求使用的 token |
| `Actions/LogOperation` | 写入操作日志；`mask()`：按 `masked_keys` 递归脱敏，并按 `max_value_length` 截断过长的字符串值；`limitSize()`：请求体超过 `max_payload_bytes` 时整体替换为截断标记 |
| `Actions/LogLogin` | 写入登录日志（只接收用户名，不接收密码） |
| `Actions/LogoutSession`、`Actions/RevokeToken` | 强制下线：删除会话、吊销 token |
| `Enums/LoginEvent`、`Enums/LoginFailureReason` | 登录日志的事件（login、logout、failed、lockout、password_reset）与失败原因（invalid_credentials、disabled、two_factor） |
| `Policies/OperationLogPolicy`、`LoginLogPolicy` | 日志的查看（`monitor.operation-log`、`monitor.login-log`，数据范围委托给 causer），写入一律拒绝 |
| `Policies/SessionPolicy`、`PersonalAccessTokenPolicy` | 查看 `monitor.online`；强制下线 `monitor.online.logout`：不能是自己当前的会话 / 本次请求的 token，超管只有超管能下线，所属用户在数据范围内。后者在 `MonitorServiceProvider` 中用 `Gate::policy()` 绑定 |
| `Policies/ActivityPolicy` | 模块审计日志：暂无查看接口，一律拒绝 |

- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；接口放在各受信任入口的同名目录：`app/Http/Admin/Monitor`。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（`php artisan core:make-contract`）。

## 日志名

| 日志名 | 写入方 | description |
|---|---|---|
| `operation` | `LogOperation`（后台入口的 `RecordOperationLog` 中间件） | 路由名，前端翻译 |
| `login` | `LogLogin`（`RecordLoginActivity`） | 事件名，同 `event` |
| 模块名小写（如 `content`） | 各模块（`LogsActivity` 或 `activity('<模块>')`） | 稳定的标识，前端翻译 |

模块写审计日志时不要使用 `operation`、`login`。

## 登录日志的事件

| 事件 | `event` | `reason` | causer |
|---|---|---|---|
| `Illuminate\Auth\Events\Login` | `login` | — | 登录的用户 |
| `Illuminate\Auth\Events\Logout` | `logout` | — | 登出的用户 |
| `Illuminate\Auth\Events\Failed` | `failed` | `disabled` / `invalid_credentials` | 事件带的用户，没有时按提交的用户名查找 |
| `Laravel\Fortify\Events\TwoFactorAuthenticationFailed` | `failed` | `two_factor` | 待验证的用户 |
| `Illuminate\Auth\Events\Lockout` | `lockout` | — | 按提交的用户名查找 |
| `Illuminate\Auth\Events\PasswordReset` | `password_reset` | — | 被重置密码的用户（不记新密码与 token） |

`Lockout` 由 System 的登录限流器在拒绝时派发（Fortify 使用命名限流器时不派发）；停用账号用正确密码登录时，System 的 `AuthenticateUser` 派发 `Failed`。
`Failed` 的 `credentials` 只读取用户名，密码不会写入日志。`login` 另记 `via_remember`：按守卫的 `viaRemember()` 区分“记住我”的 Cookie 恢复登录。写入失败只 `report()`，不影响登录。

## 常驻内存

- activitylog 有状态的服务（`CauserResolver`、`ActivityLogStatus`、`ActivityBuffer`）都是 `scoped`，无需登记 Octane 的 flush；不使用其静态的 `beforeLogging()`；
- 操作日志在响应发送后写入（`defer()->always()`），数据只在闭包中捕获；`RecordLoginActivity` 每次派发时重新解析，取到的是当前请求。

## 依赖 Core 的能力

- System：菜单与权限节点扩展点 `MenuRegistry`（`resources/menus.php`）、用户模型与数据范围（`User::visibleTo()`、`DataScopeRegistry::isUnrestrictedFor()`）、超管判断 `isSuperAdmin()`。

## 配置

- `config/activitylog.php`：`activity_model` 指向 `Models/Activity`；`clean_after_days`（`ACTIVITYLOG_CLEAN_AFTER_DAYS`，默认 365），调度每天执行 `activitylog:clean --force`（`routes/console.php`）。
- `config/core.php` 的 `monitor.operation_log`：`except_routes`（不记操作日志的路由名）、`masked_keys`（脱敏的键名，支持 `*` 通配）、`max_value_length`（字符串值上限，默认 1000 字符）、`max_payload_bytes`（请求体上限，默认 64KB）。
- 会话：`config('session.driver')` 为 `database` 时才有在线会话列表。

## 生成命令

```bash
php artisan core:make-model <名称> --module=Monitor --core       # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action <名称> --module=Monitor --core      # 写操作
php artisan core:make-query <名称> --module=Monitor --core       # 复杂查询、统计
php artisan core:make-enum <名称> --module=Monitor --core        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=Monitor --core    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=Monitor --core --model=<模型>   # 后台接口
```
