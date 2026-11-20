<?php

/*
|--------------------------------------------------------------------------
| Content 模块的节点（设计文档 §6.11、§8.2）
|--------------------------------------------------------------------------
|
| 在 ContentServiceProvider::boot() 中注册到 MenuRegistry，由 `php artisan core:sync-permissions` 写入 permissions 表。
| 格式与 Vben 的路由节点同构；buttons 展开为 `<父 name>.<键>` 的按钮节点（值是标题）。标题是前端的 i18n key，后端不翻译。
|
| 菜单节点自身的权限码就是“查看 / 列表”权限；按钮是写操作：新增、修改、删除（软删除）、发布（发布与取消发布共用）、
| 恢复、彻底删除（软删除整套，§10.1）、导出（异步导出，§6.10）。
| 删除本模块后执行 core:sync-permissions：这些内置节点转为自定义节点（保留授权），可在菜单管理中删除（§8.2）。
|
*/

return [
    [
        'name' => 'content',
        'type' => 'catalog',
        'path' => '/content',
        'meta' => ['title' => 'content.title', 'icon' => 'lucide:newspaper', 'order' => 100],
        'children' => [
            [
                'name' => 'content.article',
                'type' => 'menu',
                'path' => '/content/article',
                'component' => '/content/article/list',
                'meta' => ['title' => 'content.article.title', 'icon' => 'lucide:file-text', 'order' => 10],
                'buttons' => [
                    'create' => 'common.create',
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                    'publish' => 'content.article.publish',
                    'restore' => 'common.restore',
                    'force-delete' => 'common.forceDelete',
                    'export' => 'common.export',
                ],
            ],
        ],
    ],
];
