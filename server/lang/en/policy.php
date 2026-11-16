<?php

return [
    'user' => [
        'delete_self' => 'You cannot delete yourself.',
        'root_protected' => 'The initial super administrator is protected.',
    ],
    'role' => [
        'super_admin' => 'The super administrator role cannot be deleted.',
        'has_users' => 'This role is assigned to users and cannot be deleted.',
    ],
    'department' => [
        'has_children' => 'This department has sub-departments and cannot be deleted.',
        'has_users' => 'This department has users and cannot be deleted.',
    ],
    'position' => [
        'has_users' => 'This position has users and cannot be deleted.',
    ],
    'article_category' => [
        'has_articles' => 'This category has articles and cannot be deleted.',
    ],
    'announcement' => [
        'published' => 'Published announcements cannot be edited.',
    ],
];
