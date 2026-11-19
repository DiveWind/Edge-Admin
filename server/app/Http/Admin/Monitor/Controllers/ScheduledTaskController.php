<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\ScheduledTask;
use App\Http\Admin\Monitor\Requests\ScheduledTaskIndexRequest;
use App\Http\Admin\Monitor\Resources\ScheduledTaskResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 定时任务（设计文档 §6.6）：schedule-monitor 从调度定义同步的任务列表。任务由 schedule-monitor:sync 维护，没有新增、修改、删除接口；
 * 执行记录见 ScheduledTaskLogController，立即执行见 RunScheduledTaskController。
 *
 * 授权由 ScheduledTaskPolicy 判定（monitor.schedule）；不按数据范围过滤（定时任务属于系统）。
 */
class ScheduledTaskController
{
    /**
     * 列表：筛选 name（模糊）、type（精确）；排序 id、name、last_started_at，默认 `name`。
     */
    #[Authorize('viewAny', ScheduledTask::class)]
    public function index(ScheduledTaskIndexRequest $request): AnonymousResourceCollection
    {
        $tasks = QueryBuilder::for(ScheduledTask::query(), $request)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts('id', 'name', 'last_started_at')
            ->defaultSort('name')
            ->paginate($request->pageSize());

        return ScheduledTaskResource::collection($tasks);
    }
}
