<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\{ScheduledTask, ScheduledTaskLog};
use App\Http\Admin\Monitor\Requests\ScheduledTaskLogIndexRequest;
use App\Http\Admin\Monitor\Resources\ScheduledTaskLogResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 定时任务的执行记录（GET /monitor/schedules/{schedule}/logs，设计文档 §6.6）：由 schedule-monitor 写入，只读。
 *
 * 授权检查所属任务的 ScheduledTaskPolicy::view()（monitor.schedule）。
 */
class ScheduledTaskLogController
{
    /**
     * 列表：筛选 type（精确，可用逗号传多个）、created_at（区间）；排序 id，默认 `-id`。
     */
    #[Authorize('view', 'schedule')]
    public function __invoke(ScheduledTaskLogIndexRequest $request, ScheduledTask $schedule): AnonymousResourceCollection
    {
        $logs = QueryBuilder::for(ScheduledTaskLog::query()->where('monitored_scheduled_task_id', $schedule->id), $request)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id')
            ->defaultSort('-id')
            ->paginate($request->pageSize());

        return ScheduledTaskLogResource::collection($logs);
    }
}
