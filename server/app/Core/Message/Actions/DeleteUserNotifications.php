<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;

/**
 * 删除 $user 的全部通知（设计文档 §6.9）：用户被删除时由 DeleteNotificationsOfUser（监听 UserDeleting）调用（与删除用户在同一事务中）。
 */
class DeleteUserNotifications
{
    /**
     * Execute the action.
     */
    public function handle(User $user): void
    {
        Notification::query()->ownedBy($user)->delete();
    }
}
