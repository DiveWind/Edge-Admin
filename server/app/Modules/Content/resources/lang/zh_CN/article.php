<?php

/*
|--------------------------------------------------------------------------
| Content 模块的后端文案（中文，键与 en/article.php 相同）
|--------------------------------------------------------------------------
*/

return [
    'status' => [
        'draft' => '草稿',
        'published' => '已发布',
    ],

    'attributes' => [
        'title' => '标题',
        'summary' => '摘要',
        'content' => '正文',
        'category' => '分类',
        'cover_id' => '封面',
    ],

    'messages' => [
        'publish_draft_only' => '只有草稿能发布。',
        'unpublish_published_only' => '只有已发布的文章能取消发布。',
        'already_deleted' => '文章已被删除。',
        'restore_trashed_only' => '只有已删除的文章能恢复。',
        'force_delete_trashed_only' => '只有已删除的文章能彻底删除，请先删除。',
        'view_trashed' => '没有查看已删除文章的权限。',
    ],

    'export' => [
        'title' => '文章',
        'columns' => [
            'id' => 'ID',
            'title' => '标题',
            'category' => '分类',
            'status' => '状态',
            'published_at' => '发布时间',
            'creator' => '创建人',
            'created_at' => '创建时间',
            'deleted_at' => '删除时间',
        ],
    ],
];
