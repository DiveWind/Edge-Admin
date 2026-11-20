<?php

use App\Modules\Content\Exporters\ArticleExporter;

/*
|--------------------------------------------------------------------------
| Content 模块的异步导出（ExporterRegistry，设计文档 §5.5、§6.10）
|--------------------------------------------------------------------------
|
| 对外名称（存入 exports.exporter，以模块名开头）=> 导出类（core:make-exporter 自动登记）。
|
*/

return [
    'content.article' => ArticleExporter::class,
];
