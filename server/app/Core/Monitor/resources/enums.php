<?php

use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};

/*
|--------------------------------------------------------------------------
| Monitor 模块下发给前端的枚举（EnumRegistry，/options/enums，设计文档 §5.5、§6.7）
|--------------------------------------------------------------------------
*/

return [
    'login_event' => LoginEvent::class,
    'login_failure_reason' => LoginFailureReason::class,
];
