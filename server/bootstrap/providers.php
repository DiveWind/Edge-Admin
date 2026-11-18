<?php

use App\Http\Admin\AdminServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // 入口：是否加载由 config('core.apps') / CORE_APPS 决定
    AdminServiceProvider::class,
];
