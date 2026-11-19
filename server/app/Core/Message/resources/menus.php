<?php

/*
|--------------------------------------------------------------------------
| Message 模块的菜单与权限节点（设计文档 §6.9、§8.2）
|--------------------------------------------------------------------------
|
| 在 MessageServiceProvider::boot() 中注册到 MenuRegistry，由 `php artisan core:sync-permissions` 写入 permissions 表。
| 格式与 Vben 的路由节点同构；buttons 展开为 `<父 name>.<键>` 的按钮节点（值是标题）。标题是前端的 i18n key，后端不翻译。
|
| 菜单节点自身的权限码就是“查看 / 列表”权限；公告的写操作：新增、修改（草稿）、删除、发布、撤回。
| 通知中心与“我收到的公告”是每个登录用户都有的个人功能，不是菜单节点：前端用静态路由（与个人中心相同，§6.2 的 mixed 模式）。
|
*/

return [
    [
        'name' => 'message',
        'type' => 'catalog',
        'path' => '/message',
        'meta' => ['title' => 'message.title', 'icon' => 'lucide:message-square', 'order' => 9200],
        'children' => [
            [
                'name' => 'message.announcement',
                'type' => 'menu',
                'path' => '/message/announcement',
                'component' => '/message/announcement/list',
                'meta' => ['title' => 'message.announcement.title', 'icon' => 'lucide:megaphone', 'order' => 10],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                    'publish' => 'message.announcement.publish',
                    'revoke' => 'message.announcement.revoke',
                ],
            ],
        ],
    ],
];
