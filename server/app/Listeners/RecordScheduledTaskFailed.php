<?php

namespace App\Listeners;

use App\Enums\TaskStatus;
use App\Models\ScheduledTaskLog;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Console\Events\ScheduledTaskFailed;

class RecordScheduledTaskFailed
{
    /**
     * Handle the event.
     */
    public function handle(ScheduledTaskFailed $event): void
    {
        if ($event->task->command === null) {
            return;
        }

        $log = ScheduledTaskLog::query()
            ->where('task', ScheduledTaskRegistry::commandFor($event->task))
            ->latest('id')
            ->first();

        $log?->finish(
            TaskStatus::Failed,
            trim($log->output."\n".$event->exception->getMessage()),
            $event->runtime === null ? null : (int) round($event->runtime * 1000),
        );
    }
}
