<?php

namespace App\Core\Message\Listeners;

use App\Core\Message\Actions\DeleteUserNotifications;
use App\Core\System\Events\UserDeleting;

/**
 * 用户被删除时删除他的通知（设计文档 §6.9）：监听 System 的 UserDeleting（在 MessageServiceProvider 中登记；System 不依赖 Message）。
 * 同步执行，与删除用户在同一事务中。
 */
class DeleteNotificationsOfUser
{
    public function __construct(private readonly DeleteUserNotifications $deleteNotifications) {}

    /**
     * Handle the event.
     */
    public function handle(UserDeleting $event): void
    {
        $this->deleteNotifications->handle($event->user);
    }
}
