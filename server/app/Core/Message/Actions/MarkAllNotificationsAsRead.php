<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;

/**
 * 把 $user 的未读通知全部标为已读（PUT /notifications/read-all，设计文档 §6.9）：一条 UPDATE，只作用于本人的通知。
 *
 * 返回本次标记的条数。
 */
class MarkAllNotificationsAsRead
{
    /**
     * Execute the action.
     */
    public function handle(User $user): int
    {
        return Notification::query()->ownedBy($user)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
