<?php

return [
    'custom' => [
        'role_ids' => [
            'super_admin' => 'Only super administrators can assign the super administrator role.',
            'root_user' => 'The initial super administrator must keep the super administrator role.',
        ],
        'status' => [
            'root_user' => 'The initial super administrator cannot be disabled.',
        ],
        'parent_id' => [
            'cycle' => 'The parent department cannot be itself or one of its sub-departments.',
        ],
        'permission' => [
            'parent' => 'parent node',
            'path' => 'path',
            'icon' => 'icon',
            'types' => ['directory' => 'a directory', 'menu' => 'a menu', 'action' => 'an action'],
            'cycle' => 'The parent node cannot be itself or one of its descendants.',
            'parent_type' => 'You cannot place :type under :parent.',
            'action_parent' => 'An action must be placed under a menu.',
            'children_type' => 'The existing child nodes are not compatible with this type (directories hold directories / menus, menus hold actions, actions have no children).',
            'code_format' => 'The code may only contain lowercase letters, digits, - and _, separated by dots.',
            'action_code' => 'The code of an action must contain a dot (e.g. system.user.view).',
            'external_path' => 'An external link must be an http(s) URL.',
            'internal_path' => 'The path must start with / and contain no spaces.',
            'system_code' => 'The code of a built-in menu cannot be changed.',
            'system_type' => 'The type of a built-in menu cannot be changed.',
        ],
        'setting' => [
            'system_key' => 'The key of a built-in setting cannot be changed.',
            'system_type' => 'The type of a built-in setting cannot be changed.',
        ],
    ],

    'attributes' => [
        'department_id' => 'department',
        'parent_id' => 'parent department',
        'leader_id' => 'leader',
        'role_ids' => 'roles',
        'role_ids.*' => 'role',
        'position_ids' => 'positions',
        'position_ids.*' => 'position',
        'permission_ids' => 'permissions',
        'permission_ids.*' => 'permission',
        'department_ids' => 'departments',
        'department_ids.*' => 'department',
        'data_scope' => 'data scope',
        'category_id' => 'category',
        'dict_type_id' => 'dictionary type',
        'is_public' => 'public',
        'codes' => 'dictionary codes',
        'uploaded_by' => 'uploader',
        'per_page' => 'page size',
        'created_from' => 'start date',
        'created_to' => 'end date',
    ],
];
