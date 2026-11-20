<?php

namespace App\Core\Monitor\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * 立即健康检查（设计文档 §6.6）：同步执行官方的 `health:check --no-notification`（与调度每分钟执行的是同一套检查，
 * 同时更新保存的结果）。耗时取决于检查项，调用方需限流；检查后的结果由 Queries/LatestHealthResults 读取（命令与查询分开，§5.4）。
 */
class RunHealthChecks
{
    /**
     * Execute the action.
     */
    public function handle(): void
    {
        Artisan::call('health:check', ['--no-notification' => true]);
    }
}
