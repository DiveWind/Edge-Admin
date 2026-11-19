<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Export;
use Illuminate\Support\Facades\DB;

/**
 * 删除导出（DELETE /exports/{export}、用户删除时、到期清理之外的删除，设计文档 §6.10）：任何状态都可以删除。
 *
 * - 文件随记录删除（HasAttachments：附件在事务提交后删除文件）；
 * - 排队、进行中的导出被删除即取消：GenerateExport 在开始时、写完文件后都锁行复核，发现记录不在就结束并删除文件。
 *
 * 只能删除本人的导出由 ExportPolicy 判定（403）。
 */
class DeleteExport
{
    /**
     * Execute the action.
     */
    public function handle(Export $export): void
    {
        DB::transaction(function () use ($export): void {
            $export->delete();
        });
    }
}
