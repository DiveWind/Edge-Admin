<?php

use App\Core\Generator\GeneratorServiceProvider;
use App\Core\Message\MessageServiceProvider;
use App\Core\Monitor\MonitorServiceProvider;
use App\Core\System\SystemServiceProvider;
use App\Http\Admin\AdminServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // 内核模块（app/Core）
    GeneratorServiceProvider::class,
    SystemServiceProvider::class,
    MonitorServiceProvider::class,
    MessageServiceProvider::class,

    // 业务模块（app/Modules）
    ContentServiceProvider::class,

    // 入口：是否加载由 config('core.apps') / CORE_APPS 决定
    AdminServiceProvider::class,
];
