<?php

namespace App\Actions\Monitor;

use App\Jobs\RunScheduledTask;
use App\Services\ScheduledTask;

/**
 * 将定时任务立即执行一次（入队，由队列 worker 执行并写执行日志）。
 */
class QueueScheduledTask
{
    public function handle(ScheduledTask $task): void
    {
        RunScheduledTask::dispatch($task->command);
    }
}
