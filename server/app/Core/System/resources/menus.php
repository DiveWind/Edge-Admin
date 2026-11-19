<?php

/*
|--------------------------------------------------------------------------
| System 模块的菜单与权限节点（设计文档 §6.5、§8.2）
|--------------------------------------------------------------------------
|
| 在 SystemServiceProvider::boot() 中注册到 MenuRegistry，由 `php artisan core:sync-permissions` 写入 permissions 表。
| 格式与 Vben 的路由节点同构；buttons 展开为 `<父 name>.<键>` 的按钮节点（值是标题）。
| 标题是前端的 i18n key（内置节点的 key 写在前端 locales 中），后端不翻译。
|
| 菜单节点自身的权限码就是“查看 / 列表”权限；按钮只表示写操作。
| 已同步的节点只有 type、path、component、meta.link、meta.iframeSrc 以这里为准，其余以数据库为准（管理员可在菜单管理中调整）。
|
*/

return [
    [
        'name' => 'system',
        'type' => 'catalog',
        'path' => '/system',
        'meta' => ['title' => 'system.title', 'icon' => 'lucide:settings', 'order' => 9000],
        'children' => [
            [
                'name' => 'system.user',
                'type' => 'menu',
                'path' => '/system/user',
                'component' => '/system/user/list',
                'meta' => ['title' => 'system.user.title', 'icon' => 'lucide:user', 'order' => 10],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                    'status' => 'system.user.changeStatus',
                    'password' => 'system.user.resetPassword',
                    // 异步导出列表（§6.10）
                    'export' => 'common.export',
                ],
            ],
            [
                'name' => 'system.role',
                'type' => 'menu',
                'path' => '/system/role',
                'component' => '/system/role/list',
                'meta' => ['title' => 'system.role.title', 'icon' => 'lucide:shield-check', 'order' => 20],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                    'status' => 'system.role.changeStatus',
                ],
            ],
            [
                'name' => 'system.menu',
                'type' => 'menu',
                'path' => '/system/menu',
                'component' => '/system/menu/list',
                'meta' => ['title' => 'system.menu.title', 'icon' => 'lucide:menu', 'order' => 30],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                ],
            ],
            [
                // 字典管理：类型列表 + 选中类型的字典项；按钮由字典类型与字典项共用（§6.7）
                'name' => 'system.dict',
                'type' => 'menu',
                'path' => '/system/dict',
                'component' => '/system/dict/list',
                'meta' => ['title' => 'system.dict.title', 'icon' => 'lucide:book-open-text', 'order' => 40],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                ],
            ],
            [
                // 系统参数：按分组的表单；参数只能在模块中声明，接口只能修改值（§6.7）
                'name' => 'system.setting',
                'type' => 'menu',
                'path' => '/system/setting',
                'component' => '/system/setting/index',
                'meta' => ['title' => 'system.setting.title', 'icon' => 'lucide:sliders-horizontal', 'order' => 50],
                'buttons' => [
                    'update' => 'common.edit',
                ],
            ],
            [
                // 附件管理：列表与删除（已绑定的不能删）；上传是所有登录用户的能力，不归这里（§6.8）
                'name' => 'system.attachment',
                'type' => 'menu',
                'path' => '/system/attachment',
                'component' => '/system/attachment/list',
                'meta' => ['title' => 'system.attachment.title', 'icon' => 'lucide:paperclip', 'order' => 60],
                'buttons' => [
                    'delete' => 'common.delete',
                ],
            ],
        ],
    ],
];
