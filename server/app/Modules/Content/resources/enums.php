<?php

use App\Modules\Content\Enums\ArticleStatus;

/*
|--------------------------------------------------------------------------
| Content 模块下发给前端的枚举（EnumRegistry，/options/enums，设计文档 §5.5、§6.7）
|--------------------------------------------------------------------------
|
| 对外名称以模块名开头。
|
*/

return [
    'content.article_status' => ArticleStatus::class,
];
