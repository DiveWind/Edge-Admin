<?php

/*
|--------------------------------------------------------------------------
| Monitor 模块的菜单与权限节点（设计文档 §6.6、§8.2）
|--------------------------------------------------------------------------
|
| 在 MonitorServiceProvider::boot() 中注册到 MenuRegistry，由 `php artisan core:sync-permissions` 写入 permissions 表。
| 格式与 Vben 的路由节点同构；buttons 展开为 `<父 name>.<键>` 的按钮节点（值是标题）。标题是前端的 i18n key，后端不翻译。
|
| 菜单节点自身的权限码就是“查看 / 列表”权限。日志只读，没有按钮（不提供删除、清空，过期日志由调度清理）；
| 在线会话的强制下线（会话与 token）是按钮 monitor.online.logout。
|
*/

return [
    [
        'name' => 'monitor',
        'type' => 'catalog',
        'path' => '/monitor',
        'meta' => ['title' => 'monitor.title', 'icon' => 'lucide:monitor-cog', 'order' => 9100],
        'children' => [
            [
                'name' => 'monitor.online',
                'type' => 'menu',
                'path' => '/monitor/online',
                'component' => '/monitor/online/list',
                'meta' => ['title' => 'monitor.online.title', 'icon' => 'lucide:users-round', 'order' => 10],
                'buttons' => [
                    'logout' => 'monitor.online.logout',
                ],
            ],
            [
                'name' => 'monitor.operation-log',
                'type' => 'menu',
                'path' => '/monitor/operation-log',
                'component' => '/monitor/operation-log/list',
                'meta' => ['title' => 'monitor.operationLog.title', 'icon' => 'lucide:scroll-text', 'order' => 20],
            ],
            [
                'name' => 'monitor.login-log',
                'type' => 'menu',
                'path' => '/monitor/login-log',
                'component' => '/monitor/login-log/list',
                'meta' => ['title' => 'monitor.loginLog.title', 'icon' => 'lucide:log-in', 'order' => 30],
            ],
        ],
    ],
];
