<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\ScheduledTaskLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * 手动"立即执行"一个定时任务命令，并写入执行日志。
 */
class RunScheduledTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $command) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = ScheduledTaskLog::start($this->command);
        $startedAt = hrtime(true);

        try {
            $exitCode = Artisan::call($this->command);

            $log->finish($exitCode === 0 ? TaskStatus::Success : TaskStatus::Failed, Artisan::output(), $this->elapsedMs($startedAt));
        } catch (Throwable $e) {
            $log->finish(TaskStatus::Failed, $e->getMessage(), $this->elapsedMs($startedAt));

            throw $e;
        }
    }

    protected function elapsedMs(int $startedAt): int
    {
        return intdiv(hrtime(true) - $startedAt, 1_000_000);
    }
}
