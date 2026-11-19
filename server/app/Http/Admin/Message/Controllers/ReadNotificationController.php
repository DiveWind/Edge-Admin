<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\MarkNotificationAsRead;
use App\Core\Message\Models\Notification;
use App\Http\Admin\Message\Resources\NotificationResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 标记一条通知为已读（PUT /notifications/{notification}/read，设计文档 §6.9）：幂等（已读的保持原 read_at），返回该通知。
 * 只能操作本人的通知（NotificationPolicy::update，403）。
 */
class ReadNotificationController
{
    #[Authorize('update', 'notification')]
    public function __invoke(Notification $notification, MarkNotificationAsRead $action): NotificationResource
    {
        return new NotificationResource($action->handle($notification));
    }
}
