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
    ],
];
