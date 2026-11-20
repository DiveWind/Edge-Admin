<?php

namespace App\Core\Message\Queries;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;

/**
 * 我的未读通知数（GET /notifications/unread-count，设计文档 §6.9）：只数 $actor 自己的通知（ownedBy()）中 read_at 为空的。
 */
class UnreadNotificationCount
{
    /**
     * Execute the query.
     */
    public function get(User $actor): int
    {
        return Notification::query()->ownedBy($actor)->whereNull('read_at')->count();
    }
}
