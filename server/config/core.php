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

// 逗号分隔的环境变量 → 小写的列表；未设置或为空时为 null
$list = fn (mixed $value): ?array => is_string($value) && trim($value) !== ''
    ? array_values(array_filter(array_map(trim(...), explode(',', strtolower($value)))))
    : null;

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
    | - ops_rate_limit：运维动作的每分钟上限（限流器 admin-ops，按用户计数）；
    | - upload_rate_limit：上传附件的每分钟上限（限流器 admin-uploads，按用户计数）；
    | - export_rate_limit：发起导出的每分钟上限（限流器 admin-exports，按用户计数）。
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

            // 上传附件（POST /attachments）另外的每分钟上限，按用户计数（设计文档 §6.8）
            'upload_rate_limit' => (int) env('CORE_ADMIN_UPLOAD_RATE_LIMIT', 30),

            // 发起导出（POST /<列表>/export）另外的每分钟上限，按用户计数（设计文档 §6.10）
            'export_rate_limit' => (int) env('CORE_ADMIN_EXPORT_RATE_LIMIT', 5),

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
    | backup：备份（spatie/laravel-backup，备份盘、内容、保留策略、健康标准与通知在 config/backup.php，设计文档 §6.10）。
    | - schedule：是否调度每日的 backup:clean、backup:run、backup:monitor（routes/console.php），同时决定是否注册健康检查 Backups。
    |   默认跟随环境：production 开启，其他环境关闭（开发环境常没有 mysqldump、sqlite3 等客户端）；显式设置 CORE_BACKUP_SCHEDULE 时以设置为准。
    |   与其他配置一样在读取配置时求值，config:cache 时按缓存时的 APP_ENV 与 CORE_BACKUP_SCHEDULE 固定下来。
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

        'backup' => [
            'schedule' => (bool) env('CORE_BACKUP_SCHEDULE', env('APP_ENV') === 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 附件（设计文档 §6.8）
    |--------------------------------------------------------------------------
    |
    | 部署级配置；上传的类型白名单与大小上限是后台可调的系统参数（attachment.allowed_extensions、attachment.max_size）。
    |
    | - disk：存储盘（config/filesystems.php 中的盘名）。默认 local（storage/app/private，不在 Web 根目录下）；
    |   生产可换成 S3 等私有盘，不要用 public 盘。下载、预览一律经过后台入口的签名路由；
    | - url_ttl：签名的下载、预览地址的时间窗口（分钟）：过期时间对齐到窗口，`expires = (floor(now / ttl) + 2) * ttl`，
    |   同一窗口内签出的地址相同（浏览器可以缓存），有效期在 ttl 与 2 × ttl 之间；
    | - avatar_url_ttl：头像地址（/user/info 的 avatar）的时间窗口（分钟），默认 24 小时；
    | - orphan_ttl：上传后一直没有绑定的附件，超过多少小时由 model:prune 连同文件删除。
    |
    | 上传大小同时受 PHP 的 upload_max_filesize、post_max_size 与 Web 服务器的请求体上限约束，它们须不小于 attachment.max_size。
    |
    */

    'attachments' => [
        'disk' => env('CORE_ATTACHMENT_DISK', 'local'),
        'url_ttl' => (int) env('CORE_ATTACHMENT_URL_TTL', 30),
        'avatar_url_ttl' => (int) env('CORE_ATTACHMENT_AVATAR_URL_TTL', 1440),
        'orphan_ttl' => (int) env('CORE_ATTACHMENT_ORPHAN_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | 消息：通知中心与公告（设计文档 §6.9）
    |--------------------------------------------------------------------------
    |
    | 部署级配置（改动需重新部署，不做成系统参数）。
    |
    | - notifications.read_ttl：已读的通知保留多少天（按创建时间），之后由 model:prune 删除；未读的不清理；
    | - announcements.batch_size：发布公告后每批给多少个用户发送站内通知（Bus::batch 中的一个任务）；
    | - announcements.link：公告通知的链接（前端的公告详情路由，`{id}` 替换为公告 id），与 auth.reset_password_path 一样依赖前端路由。
    |
    */

    'message' => [
        'notifications' => [
            'read_ttl' => (int) env('CORE_NOTIFICATION_READ_DAYS', 90),
        ],

        'announcements' => [
            'batch_size' => (int) env('CORE_ANNOUNCEMENT_BATCH_SIZE', 500),
            'link' => env('CORE_ANNOUNCEMENT_LINK', '/announcements/{id}'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 异步导出（设计文档 §6.10）
    |--------------------------------------------------------------------------
    |
    | 部署级配置。导出的文件存为附件（core.attachments.disk），只有发起人能下载。
    |
    | - ttl：导出任务与文件保留多少小时（按创建时间，不论状态），之后由 model:prune 连同文件删除（每小时执行）；
    | - max_active：同一用户同时排队与进行中的导出上限，超过时发起导出返回 422；
    | - chunk_size：生成文件时每次从数据库读取的行数（lazy() 分块，不一次读入全部数据）；
    | - timeout：导出队列任务的超时（秒），超时即失败。必须小于队列连接的 retry_after（默认 90）并留出余量，
    |   否则任务仍在执行时会被另一个 worker 再次取出；调大时同时调大 retry_after；
    | - link：完成 / 失败通知的链接（前端的“我的导出”路由，可含 `{id}`）。
    |
    */

    'exports' => [
        'ttl' => (int) env('CORE_EXPORT_TTL_HOURS', 24),
        'max_active' => (int) env('CORE_EXPORT_MAX_ACTIVE', 3),
        'chunk_size' => (int) env('CORE_EXPORT_CHUNK_SIZE', 500),
        'timeout' => (int) env('CORE_EXPORT_TIMEOUT', 60),
        'link' => env('CORE_EXPORT_LINK', '/exports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 富文本净化（设计文档 §9）
    |--------------------------------------------------------------------------
    |
    | 模型的富文本字段用类型转换 SanitizedHtml（`SanitizedHtml::class` 为默认档位，`SanitizedHtml::class.':basic'` 指定档位），
    | 写入属性时由 App\Support\Html\HtmlSanitizers 净化。所有档位共用：W3C 的安全元素与属性、链接协议白名单、a 加 rel="noopener noreferrer"。
    |
    | - default_profile：未指定档位时使用的档位；
    | - max_input_length：输入的最大字节数（symfony 的默认 20000 字节，超出部分会被静默截掉）；
    | - link_schemes：链接允许的协议；media_schemes：图片等媒体允许的协议（默认只有 https：避免混合内容；不允许 data:、javascript:）；
    | - profiles：档位。media 是否允许图片等媒体；media_hosts 只允许这些主机的媒体（null 不限）。
    |   正文中的图片不要引用附件的签名地址（会过期）。生产环境应设置 CORE_HTML_MEDIA_HOSTS，限定为自己的图床或 CDN（逗号分隔）：
    |   不限主机时，正文可以嵌入外部的跟踪图片，收到公告的人一打开就把 IP 暴露给外部主机。
    |
    */

    'html' => [
        'default_profile' => 'rich',
        'max_input_length' => 1_000_000,
        'link_schemes' => ['http', 'https', 'mailto'],
        'media_schemes' => ['https'],
        'profiles' => [
            // 基础：文字格式、标题、列表、表格、引用、链接；不允许图片等媒体
            'basic' => ['media' => false],

            // 含图片：另允许 https 的图片等媒体
            'rich' => [
                'media' => true,
                'media_hosts' => $list(env('CORE_HTML_MEDIA_HOSTS')),
            ],
        ],
    ],

];
