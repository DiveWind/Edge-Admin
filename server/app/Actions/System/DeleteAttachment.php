<?php

namespace App\Actions\System;

use App\Models\Attachment;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * 删除附件记录及其物理文件（记录删除提交后再删文件，避免事务回滚后文件已丢失）。
 */
class DeleteAttachment
{
    public function handle(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $attachment->delete();

            DB::afterCommit(fn () => Storage::disk($attachment->disk)->delete($attachment->path));
        });
    }
}
