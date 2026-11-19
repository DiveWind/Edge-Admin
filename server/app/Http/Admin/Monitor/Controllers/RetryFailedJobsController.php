<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RetryFailedJobs;
use App\Core\Monitor\Models\FailedJob;
use App\Http\Admin\Monitor\Requests\RetryFailedJobsRequest;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 全部重试（POST /monitor/failed-jobs/retry，设计文档 §6.6）：`{ queue }` 可选，只重试该队列；官方的 queue:retry，成功返回 `data: null`。
 *
 * 授权由 FailedJobPolicy::retryAny() 判定（monitor.failed-job.retry）；路由另经过 admin-ops 限流。
 */
class RetryFailedJobsController
{
    #[Authorize('retryAny', FailedJob::class)]
    public function __invoke(RetryFailedJobsRequest $request, RetryFailedJobs $action): void
    {
        $queue = $request->validated('queue');

        $action->handle(is_string($queue) ? $queue : null);
    }
}
