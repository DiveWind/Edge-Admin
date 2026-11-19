<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Models\FailedJob;
use Illuminate\Support\Facades\Artisan;

/**
 * 重试一条失败任务（设计文档 §6.6）：执行官方的 `queue:retry <uuid>`。
 *
 * 由它刷新 retryUntil、推回原连接与队列、派发 JobRetryRequested 并删除失败记录。授权由 FailedJobPolicy::retry() 判定。
 */
class RetryFailedJob
{
    /**
     * Execute the action.
     */
    public function handle(FailedJob $job): void
    {
        Artisan::call('queue:retry', ['id' => [$job->uuid]]);
    }
}
