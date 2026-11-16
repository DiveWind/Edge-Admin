<?php

/*
|--------------------------------------------------------------------------
| Builtin Menu Tree
|--------------------------------------------------------------------------
|
| 内置菜单权限在代码中声明，执行 `php artisan permission:sync` 同步到 permissions 表并标记为内置（is_system）。
| 管理员还可以在「菜单管理」界面新增自定义节点，或调整内置节点的名称 / 图标 / 排序 / 显示 / 状态 / 上级。
|
| 节点字段：
| - type：directory（目录）/ menu（菜单），省略时有 children 为 directory，否则为 menu；
| - path：前端路由路径（menu 必填，须在 web/config/routes.ts 中有对应的静态路由）；外链菜单写 URL 并设 'external' => true；
| - icon：@ant-design/icons 图标名（如 SettingOutlined），前端只在一级菜单显示图标；
| - visible：false 时不在菜单中显示，权限仍然有效（默认 true）；
| - actions：展开为 "<节点 code>.<key>" 的操作权限（type=action），例如 system.user.view。
|
| sync 规则（见 docs/permissions.md）：缺失的节点按配置新增；已存在的内置节点只修正 type / path / external 等结构性字段，
| 界面上改过的 name / icon / sort / visible / status / parent 不会被覆盖。
| `name` 为默认（中文）名称；前端按 code 做多语言展示（permission.*）。
|
| `// edge:permissions` 与 `// edge:permissions:<模块>` 为 `php artisan edge:make-module`
| 的插入锚点，请保留。
|
*/

return [
    [
        'code' => 'dashboard',
        'name' => '工作台',
        'type' => 'menu',
        'path' => '/dashboard',
        'icon' => 'DashboardOutlined',
        'actions' => ['view' => '查看'],
    ],
    [
        'code' => 'system',
        'name' => '系统管理',
        'type' => 'directory',
        'path' => '/system',
        'icon' => 'SettingOutlined',
        'children' => [
            [
                'code' => 'system.user',
                'name' => '用户管理',
                'path' => '/system/users',
                'icon' => 'UserOutlined',
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
                'path' => '/system/roles',
                'icon' => 'TeamOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.permission',
                'name' => '菜单管理',
                'path' => '/system/permissions',
                'icon' => 'MenuOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.department',
                'name' => '部门管理',
                'path' => '/system/departments',
                'icon' => 'ApartmentOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.position',
                'name' => '岗位管理',
                'path' => '/system/positions',
                'icon' => 'IdcardOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.dict',
                'name' => '数据字典',
                'path' => '/system/dicts',
                'icon' => 'BookOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.setting',
                'name' => '系统参数',
                'path' => '/system/settings',
                'icon' => 'ControlOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'system.attachment',
                'name' => '附件管理',
                'path' => '/system/attachments',
                'icon' => 'PaperClipOutlined',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            // edge:permissions:system
        ],
    ],
    [
        'code' => 'monitor',
        'name' => '系统监控',
        'type' => 'directory',
        'path' => '/monitor',
        'icon' => 'MonitorOutlined',
        'children' => [
            [
                'code' => 'monitor.login-log',
                'name' => '登录日志',
                'path' => '/monitor/login-logs',
                'icon' => 'LoginOutlined',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.operation-log',
                'name' => '操作日志',
                'path' => '/monitor/operation-logs',
                'icon' => 'ProfileOutlined',
                'actions' => ['view' => '查看', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.scheduled-task',
                'name' => '定时任务',
                'path' => '/monitor/tasks',
                'icon' => 'ScheduleOutlined',
                'actions' => ['view' => '查看', 'run' => '立即执行'],
            ],
            [
                'code' => 'monitor.failed-job',
                'name' => '失败队列',
                'path' => '/monitor/failed-jobs',
                'icon' => 'WarningOutlined',
                'actions' => ['view' => '查看', 'retry' => '重试', 'delete' => '删除'],
            ],
            [
                'code' => 'monitor.online-session',
                'name' => '在线会话',
                'path' => '/monitor/online-sessions',
                'icon' => 'WifiOutlined',
                'actions' => ['view' => '查看', 'delete' => '强制下线'],
            ],
            [
                'code' => 'monitor.system-info',
                'name' => '系统信息',
                'path' => '/monitor/system-info',
                'icon' => 'InfoCircleOutlined',
                'actions' => ['view' => '查看'],
            ],
            // edge:permissions:monitor
        ],
    ],
    [
        'code' => 'message',
        'name' => '消息中心',
        'type' => 'directory',
        'path' => '/message',
        'icon' => 'MessageOutlined',
        'children' => [
            [
                'code' => 'message.announcement',
                'name' => '通知公告',
                'path' => '/message/announcements',
                'icon' => 'NotificationOutlined',
                'actions' => [
                    'view' => '查看',
                    'create' => '新增',
                    'update' => '编辑',
                    'delete' => '删除',
                    'publish' => '发布',
                ],
            ],
            [
                'code' => 'message.notification',
                'name' => '我的通知',
                'path' => '/message/notifications',
                'icon' => 'BellOutlined',
            ],
            // edge:permissions:message
        ],
    ],
    [
        'code' => 'content',
        'name' => '内容管理',
        'type' => 'directory',
        'path' => '/content',
        'icon' => 'ReadOutlined',
        'children' => [
            [
                'code' => 'content.category',
                'name' => '文章分类',
                'path' => '/content/categories',
                'icon' => 'TagsOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            [
                'code' => 'content.article',
                'name' => '文章管理',
                'path' => '/content/articles',
                'icon' => 'FileTextOutlined',
                'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
            ],
            // edge:permissions:content
        ],
    ],
    // edge:permissions
];
