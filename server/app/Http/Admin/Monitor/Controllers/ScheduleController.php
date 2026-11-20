<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\ScheduledTask;
use App\Core\Monitor\Queries\ScheduledTaskList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\ScheduleIndexRequest;
use App\Http\Admin\Monitor\Resources\ScheduleResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 定时任务（设计文档 §6.6）：schedule-monitor 从调度定义同步的任务列表。任务由 schedule-monitor:sync 维护，没有新增、修改、删除接口；
 * 执行记录见 ScheduleLogController，立即执行见 RunScheduleController。
 *
 * 授权由 ScheduledTaskPolicy 判定（monitor.schedule）；不按数据范围过滤（定时任务属于系统）。
 */
class ScheduleController
{
    /**
     * 列表：筛选 name（模糊）、type（精确）；排序 id、name、last_started_at，默认 `name`。
     */
    #[Authorize('viewAny', ScheduledTask::class)]
    public function index(ScheduleIndexRequest $request, ScheduledTaskList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return ScheduleResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }
}
