<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Notification;

/**
 * 删除一条通知（DELETE /notifications/{notification}，设计文档 §6.9）。只能删除本人的通知由 NotificationPolicy 判定（403）。
 */
class DeleteNotification
{
    /**
     * Execute the action.
     */
    public function handle(Notification $notification): void
    {
        $notification->delete();
    }
}
