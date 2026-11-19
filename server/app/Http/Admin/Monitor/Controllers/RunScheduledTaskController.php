<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RunScheduledTask;
use App\Core\Monitor\Models\ScheduledTask;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 立即执行一个定时任务（POST /monitor/schedules/{schedule}/run，设计文档 §6.6）：派发到队列，立即返回 `data: null`。
 *
 * 只接受已同步的任务 id，不接受命令字符串；执行的是调度定义中的同名事件（ExecuteScheduledTask）。
 * 授权由 ScheduledTaskPolicy::run() 判定（monitor.schedule.run）；路由另经过 admin-ops 限流。
 */
class RunScheduledTaskController
{
    #[Authorize('run', 'schedule')]
    public function __invoke(ScheduledTask $schedule, RunScheduledTask $action): void
    {
        $action->handle($schedule);
    }
}
