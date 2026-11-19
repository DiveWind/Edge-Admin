<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Queries\LatestHealthResults;
use Illuminate\Support\Facades\Artisan;

/**
 * 立即健康检查（设计文档 §6.6）：同步执行官方的 `health:check --no-notification`（与调度每分钟执行的是同一套检查，
 * 同时更新保存的结果），返回最新结果。耗时取决于检查项，调用方需限流。
 */
class RunHealthChecks
{
    public function __construct(private readonly LatestHealthResults $results) {}

    /**
     * Execute the action.
     *
     * @return array{finished_at: string|null, checks: list<array{name: string, label: string, status: string, short_summary: string, notification_message: string, meta: array<array-key, mixed>}>}
     */
    public function handle(): array
    {
        Artisan::call('health:check', ['--no-notification' => true]);

        return $this->results->get();
    }
}
