<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RunBackup;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 立即备份（POST /monitor/backups/run，设计文档 §6.10）：派发调度中已定义的 `backup:run`（RunScheduledTask），立即返回 `data: null`。
 *
 * 不接受任何参数；执行结果在定时任务的执行记录中查看。权限码 monitor.backup.run（Gate::before）；路由另经过 admin-ops 限流。
 * 任务未同步 → 422（RunBackup）。
 */
class RunBackupController
{
    #[Authorize('monitor.backup.run')]
    public function __invoke(RunBackup $action): void
    {
        $action->handle();
    }
}
