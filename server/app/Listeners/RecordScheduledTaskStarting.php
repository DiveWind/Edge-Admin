<?php

namespace App\Listeners;

use App\Models\ScheduledTaskLog;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Console\Events\ScheduledTaskStarting;

class RecordScheduledTaskStarting
{
    /**
     * Handle the event.
     */
    public function handle(ScheduledTaskStarting $event): void
    {
        if ($event->task->command === null) {
            return;
        }

        ScheduledTaskLog::start(ScheduledTaskRegistry::commandFor($event->task));
    }
}
