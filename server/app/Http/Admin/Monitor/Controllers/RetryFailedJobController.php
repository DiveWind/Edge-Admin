<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RetryFailedJob;
use App\Core\Monitor\Models\FailedJob;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 重试一条失败任务（POST /monitor/failed-jobs/{failed_job}/retry，设计文档 §6.6）：官方的 queue:retry，成功返回 `data: null`。
 *
 * 授权由 FailedJobPolicy::retry() 判定（monitor.failed-job.retry）；路由另经过 admin-ops 限流。
 */
class RetryFailedJobController
{
    #[Authorize('retry', 'failed_job')]
    public function __invoke(FailedJob $failedJob, RetryFailedJob $action): void
    {
        $action->handle($failedJob);
    }
}
