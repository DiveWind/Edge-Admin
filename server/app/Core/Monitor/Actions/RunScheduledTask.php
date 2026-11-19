<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Jobs\ExecuteScheduledTask;
use App\Core\Monitor\Models\ScheduledTask;

/**
 * 立即执行一个定时任务（设计文档 §6.6）：派发 ExecuteScheduledTask 到队列，立即返回。
 *
 * 只传任务名（schedule-monitor 同步的 name），由队列任务在调度定义中查找对应的事件执行，任何调用方都无法借此执行任意命令。
 * 同一任务在排队或执行期间不重复入队（ExecuteScheduledTask 的 ShouldBeUnique）。授权由 ScheduledTaskPolicy::run() 判定。
 */
class RunScheduledTask
{
    /**
     * Execute the action.
     */
    public function handle(ScheduledTask $task): void
    {
        dispatch(new ExecuteScheduledTask($task->name));
    }
}
