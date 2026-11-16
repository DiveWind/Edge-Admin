<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Edge Admin 的版本号，由 `GET /` 与 `GET /api/system-info` 返回。
    |
    */

    'version' => env('APP_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | 接口支持的语言（lang/ 下的目录名）。SetLocale 中间件按请求头
    | Accept-Language 在其中选择（zh-CN / en-US 等写法均可），
    | 未携带或不匹配时使用 APP_LOCALE。
    |
    */

    'locales' => ['zh_CN', 'en'],

    /*
    |--------------------------------------------------------------------------
    | HTTPS
    |--------------------------------------------------------------------------
    |
    | 为 true 时强制生成 https 链接（如上传文件 URL），适用于 TLS 在
    | 反向代理终止、PHP 侧看到的是 http 的部署。
    |
    */

    'force_https' => (bool) env('EDGE_FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | 列表接口的 `per_page` 默认值与上限。
    |
    */

    'pagination' => [
        'per_page' => (int) env('EDGE_PER_PAGE', 15),
        'max_per_page' => (int) env('EDGE_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | api_per_minute：全部 /api 接口每分钟的请求上限（已登录按用户、
    | 未登录按 IP 计数），超出返回 429。
    |
    */

    'rate_limit' => [
        'api_per_minute' => (int) env('EDGE_API_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | token_ttl：登录签发的 Sanctum Token 有效期（分钟）。
    | login_max_attempts：每分钟每个 "用户名 + IP" 允许的登录失败次数（成功登录不计数并清零）；
    | 系统参数 security.login_max_attempts 存在时以系统参数为准，此处为默认值。
    |
    */

    'auth' => [
        'token_ttl' => (int) env('EDGE_TOKEN_TTL', 60 * 24 * 7),
        'login_max_attempts' => (int) env('EDGE_LOGIN_MAX_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    |
    | retention_days：登录日志、操作日志、定时任务日志的保留天数，
    | 由定时任务 `model:prune` 清理；系统参数 log.retention_days 存在时以系统参数为准。
    | sensitive_fields：操作日志 payload 中需要脱敏的字段（支持 * 通配）。
    |
    */

    'logs' => [
        'retention_days' => (int) env('EDGE_LOG_RETENTION_DAYS', 90),
        'sensitive_fields' => ['password', 'password_*', '*_password', '*token*', '*secret*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | disk：附件存放的文件系统磁盘（public 磁盘需执行 `php artisan storage:link`）。
    | max_size：单个文件大小上限（KB）。
    | extensions：允许上传的扩展名（按文件真实 MIME 校验，逗号分隔）。
    | 出于 XSS 风险默认不允许 svg / html。
    |
    */

    'uploads' => [
        'disk' => env('EDGE_UPLOAD_DISK', 'public'),
        'max_size' => (int) env('EDGE_UPLOAD_MAX_SIZE', 2048),
        'extensions' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'EDGE_UPLOAD_EXTENSIONS',
            'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Generator
    |--------------------------------------------------------------------------
    |
    | `php artisan edge:make-module` 的配置。
    | stub_paths：按顺序查找模板的目录，前面的优先（可在前面追加自己的目录覆盖个别模板）。
    | base_path：后端代码的写入根目录，null 为项目根目录（测试中指向临时目录）。
    | web_path：前端工程目录，不存在时自动跳过前端代码生成。
    | web_locales：前端语言包目录（src/locales/<locale>）。
    |
    */

    'generator' => [
        'stub_paths' => [base_path('stubs/edge')],
        'base_path' => null,
        'web_path' => env('EDGE_GENERATOR_WEB_PATH', base_path('../web')),
        'web_locales' => ['zh-CN', 'en-US'],
    ],

];
