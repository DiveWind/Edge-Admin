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
