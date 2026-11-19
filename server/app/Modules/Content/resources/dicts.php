<?php

/*
|--------------------------------------------------------------------------
| Content 模块的字典类型与初始字典项（设计文档 §6.7、§6.11）
|--------------------------------------------------------------------------
|
| 在 ContentServiceProvider::boot() 中注册到 DictRegistry，由 `php artisan core:sync-dicts` 写入 dict_types、dict_items（内置）。
| 类型编码以模块名开头；名称与 label 是前端的 i18n key（或纯文本），后端不翻译。
| 已同步的类型与字典项只有标识（code、value）以这里为准，其余（名称、label、color、sort、status）以数据库为准：管理员可在字典管理中
| 调整、停用，或新增自定义分类；文章的 category 用 InDict('content.article_category') 校验，只接受启用项。
|
*/

return [
    [
        'code' => 'content.article_category',
        'name' => 'content.dict.articleCategory',
        'items' => [
            ['value' => 'news', 'label' => 'content.dict.articleCategory.news', 'color' => 'blue'],
            ['value' => 'tutorial', 'label' => 'content.dict.articleCategory.tutorial', 'color' => 'green'],
            ['value' => 'case', 'label' => 'content.dict.articleCategory.case', 'color' => 'purple'],
        ],
    ],
];
