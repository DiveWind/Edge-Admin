<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Attachment;
use Illuminate\Support\Facades\DB;

/**
 * 删除附件（DELETE /system/attachments/{attachment}，以及替换、绑定对象删除时，设计文档 §6.8）：记录与文件一起删除。
 *
 * - 文件在事务提交后才删除（Attachment 的 deleted 事件中 DB::afterCommit()）：事务回滚时文件保留；
 * - 已绑定的附件不能在附件管理中删除由 AttachmentPolicy 判定（403）；这里不拦，SyncAttachments 替换与 HasAttachments 级联删除也调用它。
 */
class DeleteAttachment
{
    /**
     * Execute the action.
     */
    public function handle(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $attachment->delete();
        });
    }
}
