<?php

return [
    'user' => [
        'delete_self' => '不能删除自己。',
        'root_protected' => '初始超级管理员账号受保护。',
    ],
    'role' => [
        'super_admin' => '超级管理员角色不可删除。',
        'has_users' => '该角色已分配给用户，不能删除。',
    ],
    'department' => [
        'has_children' => '该部门存在下级部门，不能删除。',
        'has_users' => '该部门下存在用户，不能删除。',
    ],
    'position' => [
        'has_users' => '该岗位下存在用户，不能删除。',
    ],
    'article_category' => [
        'has_articles' => '该分类下存在文章，不能删除。',
    ],
    'announcement' => [
        'published' => '已发布的公告不可编辑。',
    ],
];
