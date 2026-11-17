<?php

namespace App\Observers;

use App\Services\Settings;

/**
 * 系统参数变更时清除参数缓存。
 */
class SettingObserver
{
    public function __construct(protected Settings $settings) {}

    public function saved(): void
    {
        $this->settings->flush();
    }

    public function deleted(): void
    {
        $this->settings->flush();
    }
}
