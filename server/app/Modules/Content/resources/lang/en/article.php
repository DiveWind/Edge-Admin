<?php

/*
|--------------------------------------------------------------------------
| Content 模块的后端文案（命名空间 content::，ContentServiceProvider 中 loadTranslationsFrom()）
|--------------------------------------------------------------------------
|
| 只放后端要输出的文案：枚举的显示名、校验的字段名、403 / 422 的提示、导出的显示名与表头（按发起人的语言翻译，§6.10）。
| 菜单标题、字典名称是前端的 i18n key，不在这里（§6.2、§6.7）。
|
*/

return [
    'status' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'attributes' => [
        'title' => 'title',
        'summary' => 'summary',
        'content' => 'content',
        'category' => 'category',
        'cover_id' => 'cover',
    ],

    'messages' => [
        'publish_draft_only' => 'Only draft articles can be published.',
        'unpublish_published_only' => 'Only published articles can be unpublished.',
        'restore_trashed_only' => 'Only deleted articles can be restored.',
        'force_delete_trashed_only' => 'Only deleted articles can be permanently deleted. Delete the article first.',
        'view_trashed' => 'You are not allowed to view deleted articles.',
    ],

    'export' => [
        'title' => 'Articles',
        'columns' => [
            'id' => 'ID',
            'title' => 'Title',
            'category' => 'Category',
            'status' => 'Status',
            'published_at' => 'Published at',
            'creator' => 'Created by',
            'created_at' => 'Created at',
            'deleted_at' => 'Deleted at',
        ],
    ],
];
