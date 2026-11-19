<?php

/*
|--------------------------------------------------------------------------
| 本进程加载哪些入口
|--------------------------------------------------------------------------
|
| CORE_APPS：逗号分隔的入口名，如 `CORE_APPS=admin,device`（设计文档 §5.7）。
| - 未设置：加载下方登记的全部入口；
| - 设为空（`CORE_APPS=`）：不加载任何入口，适合只跑队列、调度的进程。
| 未加载的入口不注册路由、限流器与异常输出格式，其路由在该进程中不存在。
|
| 路由缓存（route:cache）只包含生成缓存时已加载的入口：同一份代码以不同的 CORE_APPS
| 运行多个进程时，为每种组合设置不同的 APP_ROUTES_CACHE（缓存文件路径）。
|
*/

$only = env('CORE_APPS');

$only = is_string($only)
    ? array_values(array_filter(array_map(trim(...), explode(',', strtolower($only)))))
    : null;

$enabled = fn (string $app): bool => $only === null || in_array($app, $only, true);

return [

    /*
    |--------------------------------------------------------------------------
    | 入口清单
    |--------------------------------------------------------------------------
    |
    | 每个入口对应 app/Http/<Name> 目录与一个 ServiceProvider（在 bootstrap/providers.php 中注册）。
    | 这里只放部署级配置（是否加载、URL 前缀、限流阈值等），改动需重新部署；
    | 运行时可在后台修改的开关属于对应的业务模块，不放这里（设计文档 §5.8）。
    |
    | - enabled：本进程是否加载该入口（由 CORE_APPS 决定）；
    | - trusted：受信任的入口可直接读取模型；受限入口只能通过模块的 Contracts（设计文档 §5.2，tests/Arch 强制）；
    | - prefix：URL 前缀；
    | - rate_limit：每分钟请求上限（已登录按用户、未登录按 IP 计数）；
    | - ops_rate_limit：运维动作的每分钟上限（限流器 admin-ops，按用户计数）。
    |
    */

    'apps' => [

        // 后台 API，给 Vben 使用：响应统一为 {code, data, message}（设计文档 §6）
        'admin' => [
            'enabled' => $enabled('admin'),
            'trusted' => true,
            'prefix' => env('CORE_ADMIN_PREFIX', 'api/admin'),
            'rate_limit' => (int) env('CORE_ADMIN_RATE_LIMIT', 120),

            // 运维动作（定时任务的立即执行、失败任务的重试、立即健康检查）另外的每分钟上限，按用户计数（设计文档 §6.6）
            'ops_rate_limit' => (int) env('CORE_ADMIN_OPS_RATE_LIMIT', 10),

            // 列表分页（设计文档 §6.3）：page 从 1 开始；pageSize 缺省时取 page_size，超过 max_page_size 返回 422
            'pagination' => [
                'page_size' => (int) env('CORE_ADMIN_PAGE_SIZE', 20),
                'max_page_size' => (int) env('CORE_ADMIN_MAX_PAGE_SIZE', 100),
            ],

            // GET /user/info 的 homePath：登录后进入的前端页面（Vben 路由路径，设计文档 §6.2）
            'home_path' => env('CORE_ADMIN_HOME_PATH', '/analytics'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 提示语言
    |--------------------------------------------------------------------------
    |
    | 请求头 Accept-Language 可选的语言（lang/ 下的语言包，App\Http\Middleware\SetLocale）：
    | `zh-CN`、`zh`、`en-US` 等写法按语言匹配；未带请求头或不支持时使用 APP_LOCALE（设计文档 §6.1）。
    |
    */

    'locales' => ['zh_CN', 'en'],

    /*
    |--------------------------------------------------------------------------
    | 认证（设计文档 §7）
    |--------------------------------------------------------------------------
    |
    | 后台使用 Sanctum SPA 模式（会话 Cookie）+ Fortify 登录流程；Fortify 的路由挂在后台入口下（config/fortify.php），
    | 限流器、找回密码链接等在 App\Core\System\SystemServiceProvider 中配置。
    |
    | - login_rate_limit：系统参数 `auth.login_rate_limit` 的默认值（同一“用户名 + IP”每分钟最多登录几次，两步验证同样按此次数）。
    |   限流次数由后台的系统参数控制（设计文档 §7.2、§6.7）：管理员没有改过时使用这里的值，改过后以系统参数为准；
    | - frontend_url、reset_password_path：找回密码邮件中的重置链接指向前端页面
    |   `<frontend_url><reset_password_path>?token=…&email=…`（Vben 默认 hash 路由，故路径以 /#/ 开头）。
    |
    */

    'auth' => [
        'login_rate_limit' => (int) env('CORE_LOGIN_RATE_LIMIT', 5),
        'frontend_url' => env('CORE_FRONTEND_URL', 'http://localhost:5999'),
        'reset_password_path' => env('CORE_RESET_PASSWORD_PATH', '/#/auth/reset-password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控（设计文档 §6.6）
    |--------------------------------------------------------------------------
    |
    | masked_keys：需要脱敏的键名（不区分大小写，支持 * 通配，递归匹配嵌套的键），值替换为 `******`。
    | 操作日志的请求体与路由参数、失败任务的 payload 与异常、定时任务的输出共用（App\Core\Monitor\Services\DataMasker）；
    | 文本（异常、输出）中 `键=值`、`键: 值`、`"键":"值"` 形式的值同样替换。项目可追加（如身份证号、银行卡号的字段名），不要删减默认项。
    |
    | operation_log：后台入口的 RecordOperationLog 中间件记录已登录用户的写请求（POST、PUT、PATCH、DELETE），
    | 写入 activitylog 的 `operation` 日志；日志的保留天数见 config/activitylog.php 的 clean_after_days。
    | - except_routes：不记录操作日志的路由名（支持 * 通配）。认证请求只记登录日志：登录、登出、两步验证登录；
    | - max_value_length：请求体与路由参数中字符串值的最大长度（字符），超出部分截掉并加上 `…(truncated)`；
    | - max_payload_bytes：脱敏、截断后的请求体编码为 JSON 的最大字节数，超过时整体替换为 `{"_truncated": true, "_bytes": 原始字节数}`。
    |
    | failed_job：失败任务的输出（payload 摘要、异常）。
    | - max_value_length：payload 中字符串值、异常第一行与定时任务输出的最大长度（字符）；
    | - max_trace_lines：详情中异常调用栈最多输出的行数。
    |
    | health：健康检查的阈值（spatie/laravel-health，检查项在 MonitorServiceProvider 中注册）。
    | - disk_space：storage/ 所在磁盘的使用率（%），依赖 `df` 命令，Windows 下默认不注册；
    | - database_connections：数据库连接数，只在默认连接为 MySQL / MariaDB / PostgreSQL 时注册；
    | - queue：默认队列的心跳最长间隔（分钟）；schedule：调度心跳最长间隔（分钟）；environment：期望的运行环境。
    |
    */

    'monitor' => [
        'masked_keys' => ['*password*', '*token*', '*secret*', 'code', 'recovery_code'],

        'operation_log' => [
            'except_routes' => ['login.store', 'logout', 'two-factor.login.store'],
            'max_value_length' => (int) env('CORE_OPERATION_LOG_MAX_VALUE_LENGTH', 1000),
            'max_payload_bytes' => (int) env('CORE_OPERATION_LOG_MAX_PAYLOAD_BYTES', 65536),
        ],

        'failed_job' => [
            'max_value_length' => (int) env('CORE_FAILED_JOB_MAX_VALUE_LENGTH', 1000),
            'max_trace_lines' => (int) env('CORE_FAILED_JOB_MAX_TRACE_LINES', 30),
        ],

        'health' => [
            'disk_space' => [
                'enabled' => (bool) env('CORE_HEALTH_DISK_SPACE', PHP_OS_FAMILY !== 'Windows'),
                'warning_percentage' => (int) env('CORE_HEALTH_DISK_WARNING', 70),
                'failure_percentage' => (int) env('CORE_HEALTH_DISK_FAILURE', 90),
            ],
            'database_connections' => [
                'warning' => (int) env('CORE_HEALTH_DB_CONNECTIONS_WARNING', 50),
                'failure' => (int) env('CORE_HEALTH_DB_CONNECTIONS_FAILURE', 100),
            ],
            'queue_heartbeat_minutes' => (int) env('CORE_HEALTH_QUEUE_MINUTES', 5),
            'schedule_heartbeat_minutes' => (int) env('CORE_HEALTH_SCHEDULE_MINUTES', 2),
            'environment' => env('CORE_HEALTH_ENVIRONMENT', 'production'),
        ],
    ],

];
