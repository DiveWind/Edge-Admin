<?php

use App\Core\System\Enums\{ExportFormat, ExportStatus, PermissionType, SettingType, Status};

/*
|--------------------------------------------------------------------------
| 下发给前端的枚举（EnumRegistry，/options/enums，设计文档 §5.5、§6.7）
|--------------------------------------------------------------------------
|
| 对外名称 => 枚举类（实现 HasLabel，需要语义色时再实现 HasColor）。显式白名单：没有登记的枚举不下发。
|
*/

return [
    'status' => Status::class,
    'permission_type' => PermissionType::class,
    'setting_type' => SettingType::class,
    'export_status' => ExportStatus::class,
    'export_format' => ExportFormat::class,
];
