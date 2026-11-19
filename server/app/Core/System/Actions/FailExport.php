<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\ExportStatus;
use App\Core\System\Events\ExportFinished;
use App\Core\System\Models\Export;
use Illuminate\Support\Facades\DB;

/**
 * 标记导出失败（设计文档 §6.10）：RunExport 的 failed()（异常、超时、超过尝试次数），以及执行时发起人已停用或失去权限码。
 *
 * 锁行复核：记录已删除、已完成或已失败时不变（幂等）；否则标记失败与 finished_at，事务提交后派发 ExportFinished（Message 通知发起人）。
 * 不记录失败原因：异常细节只在失败任务中（§6.6），可能含不应给用户看的内容。
 */
class FailExport
{
    /**
     * Execute the action.
     */
    public function handle(int $exportId): void
    {
        $export = DB::transaction(function () use ($exportId): ?Export {
            $export = Export::query()->lockForUpdate()->find($exportId);

            if ($export === null || ! in_array($export->status, ExportStatus::active(), true)) {
                return null;
            }

            $export->status = ExportStatus::Failed;
            $export->finished_at = now();
            $export->save();

            return $export;
        });

        if ($export !== null) {
            event(new ExportFinished($export));
        }
    }
}
