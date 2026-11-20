<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Models\ScheduledTask;
use App\Support\Exceptions\BusinessRuleViolation;

/**
 * 立即备份（POST /monitor/backups/run，设计文档 §6.10）：按任务名 `backup:run` 找到已同步的定时任务，交给 RunScheduledTask
 * （§6.6 的立即执行，同一套白名单：只执行调度中已定义的事件，不接受任何命令或参数），立即返回。
 *
 * - 任务未同步（备份调度已关闭，或尚未执行 schedule-monitor:sync）→ 422（message）；
 * - 同一任务排队或执行期间不重复入队（ExecuteScheduledTask 的 ShouldBeUnique）；
 * - 在队列 worker 中执行，受 worker 的 --timeout 限制（§11.3）。
 */
class RunBackup
{
    /**
     * schedule-monitor 中备份任务的名称（routes/console.php 中的 BackupCommand）。
     */
    private const string TASK = 'backup:run';

    public function __construct(private readonly RunScheduledTask $run) {}

    /**
     * Execute the action.
     *
     * @throws BusinessRuleViolation
     */
    public function handle(): void
    {
        $task = ScheduledTask::query()->where('name', self::TASK)->first()
            ?? throw new BusinessRuleViolation(__('The backup task is not in the schedule. Enable the backup schedule and sync the scheduled tasks first.'));

        $this->run->handle($task);
    }
}
