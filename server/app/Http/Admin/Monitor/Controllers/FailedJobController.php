<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\ForgetFailedJob;
use App\Core\Monitor\Models\FailedJob;
use App\Core\Monitor\Queries\FailedJobList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\FailedJobIndexRequest;
use App\Http\Admin\Monitor\Resources\FailedJobResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 失败任务（设计文档 §6.6）：列表、详情、删除。重试见 RetryFailedJobController / RetryFailedJobsController，清空见 FlushFailedJobsController。
 * 失败任务驱动不是 database-uuids 时列表为空、详情与删除 404。
 *
 * 授权由 FailedJobPolicy 判定（monitor.failed-job、monitor.failed-job.delete）；不按数据范围过滤（失败任务属于系统）。
 */
class FailedJobController
{
    /**
     * 列表（不含 payload、trace）：筛选 queue、connection（精确），exception（模糊），failed_at（区间）；排序 failed_at，默认 `-failed_at`。
     */
    #[Authorize('viewAny', FailedJob::class)]
    public function index(FailedJobIndexRequest $request, FailedJobList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return FailedJobResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 详情，另含 payload 摘要与 trace（已脱敏、截断）。
     */
    #[Authorize('view', 'failed_job')]
    public function show(FailedJob $failedJob): FailedJobResource
    {
        return new FailedJobResource($failedJob)->withDetails();
    }

    /**
     * 删除（FailedJobProviderInterface::forget()）。
     */
    #[Authorize('delete', 'failed_job')]
    public function destroy(FailedJob $failedJob, ForgetFailedJob $action): void
    {
        $action->handle($failedJob);
    }
}
