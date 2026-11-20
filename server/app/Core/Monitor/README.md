# Monitor

内核模块，目录 `app/Core/Monitor`。结构与规则见设计文档 §4.3、§5；接口与规则见 §6.6、§7.3。

## 职责

- 日志的存储：操作日志、登录日志与各模块的数据变更审计统一存在 spatie/laravel-activitylog 的 `activity_log` 表中，按日志名区分；
- 操作日志（`operation`）：写入用例 `LogOperation`（含脱敏）；记录写请求的中间件在后台入口（`App\Http\Admin\Middleware\RecordOperationLog`）；
- 登录日志（`login`）：监听认证事件（`Listeners/RecordLoginActivity`），写入用例 `LogLogin`；
- 在线会话与强制下线：database 会话驱动的会话表（`Models/Session`）与 Sanctum 的 token（`Queries/OnlineTokenList`）；
- 日志与在线会话的授权与数据范围；
- 定时任务：spatie/laravel-schedule-monitor 同步、记录的任务与执行记录（`Models/ScheduledTask`、`ScheduledTaskLog`），立即执行（`Actions/RunScheduledTask` → `Jobs/ExecuteScheduledTask`）；
- 失败任务：框架的 `failed_jobs`（`Models/FailedJob`，只读），重试、删除、清空走官方能力；
- 健康检查（spatie/laravel-health，检查项在本模块 Provider 中注册）与系统信息（`Queries/SystemInfo`）；
- 脱敏与截断的共用规则（`Services/DataMasker`）：操作日志、失败任务、定时任务的输出。

不负责：接口的路由与输出格式（`app/Http/Admin/Monitor`）；Pulse 暂未接入（设计文档 §14）。

## 对外提供

备份（设计文档 §6.10）：`Queries/BackupFiles`（各备份盘上的备份，内存分页）、`Queries/BackupDestinations`（各盘的健康状态，与 `backup:monitor` 同一套规则）、`Actions/RunBackup`（立即备份：按任务名 `backup:run` 交给 `RunScheduledTask`，只执行调度中已定义的事件）、`Checks/BackupsCheck`（健康检查 `Backups`，备份调度开启时注册）。菜单 `monitor.backup`（按钮 `monitor.backup.run`）。**不提供下载备份**。

| 类 | 用途 |
|---|---|
| `Models/Activity` | activitylog 的模型（`config('activitylog.activity_model')`，多态别名 `activity`）：写入时自动在 `properties.request_id` 中记下 Context 的请求 ID；`visibleTo($actor)` / `isVisibleTo($actor)`：数据范围委托给 causer 用户 |
| `Models/OperationLog`、`Models/LoginLog` | 继承 `Activity`，各带日志名（`LOG_NAME`）的全局作用域；只读 |
| `Queries/LoginLogList`、`Queries/OperationLogList` | 登录日志、操作日志的列表查询（`visibleTo`，筛选与排序白名单） |
| `Models/Session` | 会话表的一行（表名、连接取自 `config('session.*')`）：`online()`（已登录、未过期；非 database 驱动时为空）、`visibleTo()`、`publicId()`（会话 ID 的前 16 位，路由绑定按它查找）、`isCurrent()` |
| `Queries/OnlineSessionList` | 在线会话的列表查询（`online()`、`visibleTo`，筛选与排序白名单） |
| `Queries/OnlineTokenList` | 有效的后台用户 token 列表（与 Sanctum 的过期校验一致，按所属用户的数据范围过滤，筛选与排序白名单） |
| `Services/CurrentAccessToken` | 是否为本次请求使用的 token（Policy 与 token 列表的 `is_current` 共用） |
| `Actions/LogOperation` | 写入操作日志：按 `masked_keys` 递归脱敏，并按 `max_value_length` 截断过长的字符串值（`DataMasker`）；请求体超过 `max_payload_bytes` 时整体替换为截断标记；`user_agent` 最长 `Activity::USER_AGENT_LENGTH` |
| `Contracts/Auditable`、`Actions/LogAudit` | 扩展点“日志”（§5.5）：模型实现 `Auditable`（`auditSnapshot()`、`auditDigestedFields()`），模块的 Action 在同一事务中调用 `LogAudit` 写数据变更审计（日志名为多态别名的模块段；新增记 attributes、修改类事件只记变化、彻底删除记 old、删除与恢复只记事件；大字段记 `{ sha256, length }`） |
| `Services/DataMasker` | 脱敏与截断：`maskArray()`（键名规则，递归）、`maskText()`（文本中 `键=值`、`键: 值`、`"键":"值"` 形式）、`truncate()`；键名取自 `core.monitor.masked_keys` |
| `Models/ScheduledTask`、`Models/ScheduledTaskLog` | 继承 schedule-monitor 的模型（`config/schedule-monitor.php` 的 `models`），包写入、接口只读；`nextRunAt()`、`isRunning()`、`scheduleTimezone()`；`metaValue()` |
| `Queries/ScheduledTaskList`、`Queries/ScheduledTaskLogList` | 定时任务、某个任务的执行记录的列表查询（筛选与排序白名单） |
| `Actions/RunScheduledTask`、`Jobs/ExecuteScheduledTask` | 立即执行：只派发任务名；队列任务在 `Schedule::events()` 中按同步规则查找同名事件，与 `schedule:run` 相同地派发 `ScheduledTask*` 事件后执行；找不到即失败。`ShouldBeUnique`（按任务名）、`Tries(1)` |
| `Models/FailedJob` | 失败任务表的一行（表名、连接取自 `config('queue.failed.*')`，路由键 `uuid`）：`stored()`（驱动不是 `database-uuids` 时为空）、`payloadSummary()`（去掉 `data.command`，脱敏、截断）、`exceptionMessage()`、`exceptionTrace()`（脱敏、去掉项目根目录、限制行数） |
| `Queries/FailedJobList` | 失败任务的列表查询（`stored()`，筛选与排序白名单） |
| `Actions/RetryFailedJob`、`RetryFailedJobs` | 重试：`queue:retry <uuid>`；全部重试：`queue:retry all` 或 `--queue=` |
| `Actions/ForgetFailedJob`、`FlushFailedJobs` | 删除、清空：`FailedJobProviderInterface::forget()` / `flush()` |
| `Actions/RunHealthChecks`、`Queries/LatestHealthResults` | 立即检查（`health:check --no-notification`，不返回结果）；最近一次结果（包的结果存储，本项目为缓存；入口先调前者再调后者） |
| `Queries/SystemInfo` | 系统信息：版本、运行方式、驱动、磁盘；不输出密钥、密码、主机、路径与环境变量 |
| `Actions/LogLogin` | 写入登录日志（只接收用户名，不接收密码） |
| `Actions/LogoutSession`、`Actions/RevokeToken` | 强制下线：删除会话、吊销 token |
| `Enums/LoginEvent`、`Enums/LoginFailureReason` | 登录日志的事件（login、logout、failed、lockout、password_reset）与失败原因（invalid_credentials、disabled、two_factor） |
| `Policies/OperationLogPolicy`、`LoginLogPolicy` | 日志的查看（`monitor.operation-log`、`monitor.login-log`，数据范围委托给 causer），写入一律拒绝 |
| `Policies/SessionPolicy`、`PersonalAccessTokenPolicy` | 查看 `monitor.online`；强制下线 `monitor.online.logout`：不能是自己当前的会话 / 本次请求的 token，超管只有超管能下线，所属用户在数据范围内，非超管只能下线“管得到”的用户的（System 的 `Services/Manageability`，自己的其他会话与 token 除外）。后者在 `MonitorServiceProvider` 中用 `Gate::policy()` 绑定 |
| `Policies/ActivityPolicy` | 模块审计日志：暂无查看接口，一律拒绝 |
| `Policies/ScheduledTaskPolicy`、`ScheduledTaskLogPolicy` | 查看 `monitor.schedule`；立即执行 `run`：`monitor.schedule.run`；其余拒绝 |
| `Policies/FailedJobPolicy` | 查看 `monitor.failed-job`；`retry` / `retryAny`：`monitor.failed-job.retry`；`delete` / `deleteAny`（清空）：`monitor.failed-job.delete`；其余拒绝 |

定时任务、失败任务、健康状态与系统信息属于系统本身，**不按数据范围过滤**，只由权限码决定；健康检查与系统信息没有模型，接口直接声明权限码 `monitor.health`、`monitor.health.check`。

- 内部实现（不对外）：`Listeners/RecordLoginActivity`。
- 会话表与 token 表归 System 所有（System 的 `RevokeUserSessions` 按用户整体删除），本模块只做单条强制下线（`LogoutSession`、`RevokeToken`）。
- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；列表的查询调用 `Queries/<模型>List`；接口放在各受信任入口的同名目录：`app/Http/Admin/Monitor`。
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

## 立即执行的执行记录

`ExecuteScheduledTask` 基于 Laravel 13 的 `ScheduleRunCommand::runEvent()` 派发调度事件，schedule-monitor 据此写执行记录。一次手动执行只留下 `starting` 加一条结束记录：

| 结果 | 派发的事件 | 执行记录 |
|---|---|---|
| 正常结束，退出码 0 | `ScheduledTaskFinished` | `finished`（`exit_code`、`runtime`、`memory`） |
| 退出码非 0（命令未抛异常） | `ScheduledTaskFinished`；异常 `report()` | `failed`（`exit_code`、`runtime`；包的 `markAsFinished()` 见到非 0 退出码转为失败） |
| 抛出异常（回调任务等） | `ScheduledTaskFailed`；异常 `report()` | `failed`（`failure_message`） |

与 `runEvent()` 的不同：框架在退出码非 0 时先派发 `ScheduledTaskFinished`，再抛出异常派发 `ScheduledTaskFailed`，schedule-monitor 两条都记为 `failed`。
因此由 `schedule:run` 定时执行的这种失败会有两条 `failed`（框架与包的行为，不改）；手动执行不再派发 `ScheduledTaskFailed`，监听它的代码收不到手动执行的非 0 退出码。

## 脱敏的已知限制

`DataMasker` 按键名脱敏，文本中只认 `键=值`、`键: 值`、`键 => 值`、`"键":"值"`（含单引号）四种写法：

- SQL 绑定值、位置参数、序列化字符串等没有键名的数据识别不了，异常消息中仍可能含业务数据：失败任务页只授予可信的运维角色；
- 精确键名也会命中普通文本，如默认的 `code` 会把 `code: 500` 替换为 `code: ******`；
- 先脱敏再截断：截断点落在 `******` 中间时星号会变少，但不会露出原值；
- payload 中序列化的任务对象（`data.command`）不反序列化、不输出，因此看不到任务的属性；
- 只有通配符的键名规则（`*`）只用于数组，不用于文本。

## 常驻内存

- activitylog 有状态的服务（`CauserResolver`、`ActivityLogStatus`、`ActivityBuffer`）都是 `scoped`，无需登记 Octane 的 flush；不使用其静态的 `beforeLogging()`；
- 操作日志在响应发送后写入（`defer()->always()`），数据只在闭包中捕获；`RecordLoginActivity` 每次派发时重新解析，取到的是当前请求；
- schedule-monitor 的单例 `MonitoredScheduledTasks` 只在定义调度时记录 `monitorName` 等设置；laravel-health 的 `Health` 单例只在启动时登记检查项；
  `DataMasker` 无状态。`ExecuteScheduledTask` 执行时临时关闭事件的 `runInBackground`，执行后恢复。
- laravel-backup 的 `Config` 是 `scoped`；备份的 Query 每次读取 `config()` 与备份盘，`BackupsCheck` 在 `run()` 时按当时的时间判断（不像包自带的 `BackupsCheck` 在启动时算好阈值）。

## 依赖 Core 的能力

- System：菜单与权限节点扩展点 `MenuRegistry`（`resources/menus.php`）、用户模型与数据范围（`User::visibleTo()`、`DataScopeRegistry::isUnrestrictedFor()`）、超管判断 `isSuperAdmin()`、“管得到”的判定 `Services/Manageability`。
- System：枚举选项的白名单 `EnumRegistry`：登记 `login_event`（`Enums/LoginEvent`，实现 `HasColor`）、`login_failure_reason`（`Enums/LoginFailureReason`），由 `/options/enums` 下发（设计文档 §6.7）。
- 包：spatie/laravel-activitylog、spatie/laravel-schedule-monitor、spatie/laravel-health、spatie/laravel-backup；框架的 `failed_jobs`、`queue:retry`。

## 备份

- 配置都是部署级的（`config/backup.php` 与环境变量 `BACKUP_*`）：备份数据库与 `storage/app/private`，备份盘默认 `backups`（`storage/app/backups`），包自带的通知默认关闭（`BACKUP_NOTIFICATION_MAIL` 开启失败类的邮件）。
- 调度（`routes/console.php`，`CORE_BACKUP_SCHEDULE`，未设置时 production 开启、其他环境关闭）：01:00 `backup:clean`、01:30 `backup:run`、07:00 `backup:monitor`；被 schedule-monitor 监控，可在定时任务页立即执行。
- 定时执行在调度进程中，不受队列超时限制；立即执行 / 立即备份在队列 worker 中，受 `--timeout` 限制（设计文档 §11.3）。
- 运行环境须有 `mysqldump` / `pg_dump` / `sqlite3` 与 `zip` 扩展。

## 配置

- `config/activitylog.php`：`activity_model` 指向 `Models/Activity`；`clean_after_days`（`ACTIVITYLOG_CLEAN_AFTER_DAYS`，默认 365），调度每天执行 `activitylog:clean --force`（`routes/console.php`）。
- `config/core.php` 的 `monitor.operation_log`：`except_routes`（不记操作日志的路由名）、`max_value_length`（字符串值上限，默认 1000 字符）、`max_payload_bytes`（请求体上限，默认 64KB）。
- `config/core.php` 的 `monitor.masked_keys`（脱敏的键名，各处共用）、`monitor.failed_job`（`max_value_length`、`max_trace_lines`）、`monitor.health`（检查阈值）；`apps.admin.ops_rate_limit`（立即执行、重试、立即检查的限流）。
- `config/schedule-monitor.php`：`models` 指向本模块的模型；`delete_log_items_older_than_days`（`SCHEDULE_MONITOR_LOG_DAYS`，默认 30）。调度定义改动后执行 `php artisan schedule-monitor:sync`（部署流程与 `DatabaseSeeder` 已包含）。
- `config/health.php`：结果存缓存（`HEALTH_CACHE_STORE`），通知关闭；不注册包的 HTTP 端点，存活探针用 `/up`。
- 调度（`routes/console.php`）：每天 `model:prune --model=ScheduledTaskLog`；每分钟 `health:check --no-notification`、`health:schedule-check-heartbeat`、`health:queue-check-heartbeat`（`doNotMonitor()`）。
- 会话：`config('session.driver')` 为 `database` 时才有在线会话列表；失败任务：`queue.failed.driver` 为 `database-uuids` 时才有失败任务列表。

## 生成命令

```bash
php artisan core:make-model <名称> --module=Monitor --core       # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action <名称> --module=Monitor --core      # 写操作
php artisan core:make-query <名称> --module=Monitor --core       # 复杂查询、统计
php artisan core:make-enum <名称> --module=Monitor --core        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=Monitor --core    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=Monitor --core --model=<模型>   # 后台接口
```
