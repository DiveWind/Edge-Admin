<?php

use App\Core\System\Exporters\UserExporter;

/*
|--------------------------------------------------------------------------
| 异步导出（ExporterRegistry，设计文档 §5.5、§6.10）
|--------------------------------------------------------------------------
|
| 对外名称（存入 exports.exporter）=> 导出类（实现 Exporter）。
|
*/

return [
    'user' => UserExporter::class,
];
