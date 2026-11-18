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
    | - rate_limit：每分钟请求上限（已登录按用户、未登录按 IP 计数）。
    |
    */

    'apps' => [

        // 后台 API，给 Vben 使用：响应统一为 {code, data, message}（设计文档 §6）
        'admin' => [
            'enabled' => $enabled('admin'),
            'trusted' => true,
            'prefix' => env('CORE_ADMIN_PREFIX', 'api/admin'),
            'rate_limit' => (int) env('CORE_ADMIN_RATE_LIMIT', 120),

            // 列表分页（设计文档 §6.3）：page 从 1 开始；pageSize 缺省时取 page_size，超过 max_page_size 返回 422
            'pagination' => [
                'page_size' => (int) env('CORE_ADMIN_PAGE_SIZE', 20),
                'max_page_size' => (int) env('CORE_ADMIN_MAX_PAGE_SIZE', 100),
            ],
        ],

    ],

];
