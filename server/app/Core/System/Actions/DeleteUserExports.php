<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Export, User};
use Illuminate\Support\Facades\DB;

/**
 * 删除用户发起的全部导出（连同文件，设计文档 §6.10）：用户删除前调用（SystemServiceProvider 监听 User 的 deleting 事件）。
 * 逐条删除（DeleteExport），以便 HasAttachments 删除文件；exports.user_id 的外键 cascadeOnDelete 兜底。
 */
class DeleteUserExports
{
    public function __construct(private readonly DeleteExport $delete) {}

    /**
     * Execute the action.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            Export::query()->ownedBy($user)->lazyById()->each(fn (Export $export) => $this->delete->handle($export));
        });
    }
}
