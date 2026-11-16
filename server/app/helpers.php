<?php

use App\Services\Settings;

if (! function_exists('setting')) {
    /**
     * 读取系统参数（带缓存），不存在时返回 $default。
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}
