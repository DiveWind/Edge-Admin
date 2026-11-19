<?php

namespace App\Core\Message\Listeners;

use App\Core\Message\Actions\DeleteUserNotifications;
use App\Core\System\Models\User;

/**
 * 用户被删除后删除他的通知（设计文档 §6.9）：监听 User 的 deleted 模型事件（在 MessageServiceProvider 中登记；System 不依赖 Message）。
 * 同步执行，与删除用户在同一事务中。
 */
class DeleteNotificationsOfDeletedUser
{
    public function __construct(private readonly DeleteUserNotifications $deleteNotifications) {}

    /**
     * Handle the event.
     */
    public function handle(User $user): void
    {
        $this->deleteNotifications->handle($user);
    }
}
