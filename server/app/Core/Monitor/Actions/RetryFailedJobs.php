<?php

namespace App\Core\Monitor\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * 全部重试（设计文档 §6.6）：执行官方的 `queue:retry all`；指定 $queue 时为 `queue:retry --queue=<队列>`，只重试该队列的失败任务。
 *
 * 在调用方的进程中逐条推回队列，积压很多时建议在命令行执行。对任何失败任务驱动都有效。授权由 FailedJobPolicy::retryAny() 判定。
 */
class RetryFailedJobs
{
    /**
     * Execute the action.
     */
    public function handle(?string $queue = null): void
    {
        Artisan::call('queue:retry', $queue === null ? ['id' => ['all']] : ['--queue' => $queue]);
    }
}
