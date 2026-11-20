<?php

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget, NotificationLevel};

/*
|--------------------------------------------------------------------------
| Message 模块下发给前端的枚举（EnumRegistry，/options/enums，设计文档 §5.5、§6.7）
|--------------------------------------------------------------------------
*/

return [
    'notification_level' => NotificationLevel::class,
    'announcement_status' => AnnouncementStatus::class,
    'announcement_target' => AnnouncementTarget::class,
];
