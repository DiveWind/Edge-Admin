<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\ScheduledTaskLog;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{Tries, UniqueFor};
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * 手动"立即执行"一个定时任务命令，并写入执行日志（只执行一次：失败即记为失败，由管理员决定是否重试）。
 *
 * 同一命令在排队 / 执行期间只保留一个任务（ShouldBeUnique，按命令加锁，最长 1 小时）：重复点击"立即执行"不会让
 * model:prune 等命令并发运行。
 */
#[Tries(1)]
#[UniqueFor(3600)]
class RunScheduledTask implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public string $command) {}

    public function uniqueId(): string
    {
        return $this->command;
    }

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
