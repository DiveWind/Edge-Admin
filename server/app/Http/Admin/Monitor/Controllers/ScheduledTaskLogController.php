<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\ScheduledTask;
use App\Core\Monitor\Queries\ScheduledTaskLogList;
use App\Http\Admin\Monitor\Requests\ScheduledTaskLogIndexRequest;
use App\Http\Admin\Monitor\Resources\ScheduledTaskLogResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

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
    public function __invoke(ScheduledTaskLogIndexRequest $request, ScheduledTask $schedule, ScheduledTaskLogList $list): AnonymousResourceCollection
    {
        return ScheduledTaskLogResource::collection($list->builder($schedule, $request->listParameters())->paginate($request->pageSize()));
    }
}
