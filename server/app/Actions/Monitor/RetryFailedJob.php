<?php

namespace App\Actions\Monitor;

use Illuminate\Contracts\Console\Kernel;

/**
 * 将失败任务重新入队（复用官方 queue:retry 命令）。
 */
class RetryFailedJob
{
    public function __construct(protected Kernel $artisan) {}

    public function handle(string $uuid): void
    {
        $this->artisan->call('queue:retry', ['id' => [$uuid]]);
    }
}
