<?php

/*
|--------------------------------------------------------------------------
| Permission Tree
|--------------------------------------------------------------------------
|
| 权限点在代码中声明，执行 `php artisan permission:sync` 同步到 permissions 表。
| 结构：模块(menu) → 菜单(menu) → 操作(action)。
| 节点的 `actions` 会展开为 "<节点 code>.<key>" 的操作权限，例如 system.user.view。
| `name` 为默认（中文）名称；前端按 code 做多语言展示（menu.* / permission.*）。
|
| `// edge:permissions` 与 `// edge:permissions:<模块>` 为 `php artisan edge:make-module`
| 的插入锚点，请保留。
|
*/

return [
    [
        'code' => 'dashboard',
        'name' => '工作台',
        'actions' => ['view' => '查看'],
    ],
    [
        'code' => 'system',
        'name' => '系统管理',
        'children' => [
            [
                'code' => 'system.user',
                'name' => '用户管理',
                'actions' => [
                    'view' => '查看',
                    'create' => '新增',
                    'update' => '编辑',
                    'delete' => '删除',
                    'reset-password' => '重置密码',
                ],
            ],
            [
                'code' => 'system.role',
                'name' => '角色管理',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.permission',
                'name' => '权限管理',
                'actions' => ['view' => '查看'],
            ],
            [
                'code' => 'system.department',
                'name' => '部门管理',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.position',
                'name' => '岗位管理',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.dict',
                'name' => '数据字典',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.setting',
                'name' => '系统参数',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.attachment',
                'name' => '附件管理',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            // edge:permissions:system
        ],
    ],
    [
        'code' => 'monitor',
        'name' => '系统监控',
        'children' => [
            [
                'code' => 'monitor.login-log',
                'name' => '登录日志',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.operation-log',
                'name' => '操作日志',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.scheduled-task',
                'name' => '定时任务',
                'actions' => ['view' => '查看', 'run' => '立即执行'],
            ],
            [
                'code' => 'monitor.failed-job',
                'name' => '失败队列',
                'actions' => ['view' => '查看', 'retry' => '重试', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.online-session',
                'name' => '在线会话',
                'actions' => ['view' => '查看', 'delete' => '强制下线'],
            ],
            [
                'code' => 'monitor.system-info',
                'name' => '系统信息',
                'actions' => ['view' => '查看'],
            ],
            // edge:permissions:monitor
        ],
    ],
    [
        'code' => 'message',
        'name' => '消息中心',
        'children' => [
            [
                'code' => 'message.announcement',
                'name' => '通知公告',
                'actions' => [
                    'view' => '查看',
                    'create' => '新增',
                    'update' => '编辑',
                    'delete' => '删除',
                    'publish' => '发布',
                ],
            ],
            // edge:permissions:message
        ],
    ],
    [
        'code' => 'content',
        'name' => '内容管理',
        'children' => [
            [
                'code' => 'content.category',
                'name' => '文章分类',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'content.article',
                'name' => '文章管理',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            // edge:permissions:content
        ],
    ],
    // edge:permissions
];
