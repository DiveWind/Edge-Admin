<?php

use App\Core\Generator\GeneratorServiceProvider;
use App\Http\Admin\AdminServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // 内核模块（app/Core）
    GeneratorServiceProvider::class,

    // 入口：是否加载由 config('core.apps') / CORE_APPS 决定
    AdminServiceProvider::class,
];
