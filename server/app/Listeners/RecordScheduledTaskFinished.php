<?php

namespace App\Listeners;

use App\Enums\TaskStatus;
use App\Models\ScheduledTaskLog;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\File;

class RecordScheduledTaskFinished
{
    /**
     * Handle the event.
     */
    public function handle(ScheduledTaskFinished $event): void
    {
        $task = $event->task;

        if ($task->command === null) {
            return;
        }

        $log = ScheduledTaskLog::query()
            ->where('task', ScheduledTaskRegistry::commandFor($task))
            ->where('status', TaskStatus::Running)
            ->latest('id')
            ->first();

        $output = $task->output !== $task->getDefaultOutput() && File::exists($task->output)
            ? File::get($task->output)
            : null;

        $log?->finish(
            $task->exitCode === 0 ? TaskStatus::Success : TaskStatus::Failed,
            $output,
            (int) round($event->runtime * 1000),
        );
    }
}
