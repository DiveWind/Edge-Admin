<?php

namespace App\Core\System\Listeners;

use App\Core\System\Actions\DeleteUserExports;
use App\Core\System\Events\UserDeleting;

/**
 * 用户删除前删除他发起的导出（连同文件，设计文档 §6.10）：监听 UserDeleting（在 SystemServiceProvider 中登记）。
 * 在删除之前执行：exports.user_id 的外键 cascadeOnDelete 会在删除用户时直接删掉记录，来不及删除文件。同步执行，与删除用户在同一事务中。
 */
class DeleteExportsOfUser
{
    public function __construct(private readonly DeleteUserExports $deleteExports) {}

    /**
     * Handle the event.
     */
    public function handle(UserDeleting $event): void
    {
        $this->deleteExports->handle($event->user);
    }
}
