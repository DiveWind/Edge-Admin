<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Notification;

/**
 * 标记一条通知为已读（PUT /notifications/{notification}/read，设计文档 §6.9）：已读的保持原 read_at（幂等）。
 * 只能操作本人的通知由 NotificationPolicy 判定（403）。
 */
class MarkNotificationAsRead
{
    /**
     * Execute the action.
     */
    public function handle(Notification $notification): Notification
    {
        $notification->markAsRead();

        return $notification;
    }
}
