<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\FlushFailedJobs;
use App\Core\Monitor\Models\FailedJob;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 清空失败任务（DELETE /monitor/failed-jobs，设计文档 §6.6）：FailedJobProviderInterface::flush()，成功返回 `data: null`。
 *
 * 授权由 FailedJobPolicy::deleteAny() 判定（monitor.failed-job.delete）。
 */
class FlushFailedJobsController
{
    #[Authorize('deleteAny', FailedJob::class)]
    public function __invoke(FlushFailedJobs $action): void
    {
        $action->handle();
    }
}
