<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\MarkAllNotificationsAsRead;
use App\Core\System\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * 全部已读（PUT /notifications/read-all，设计文档 §6.9）：本人的未读通知一次标为已读，返回 `data: null`。
 * 只需登录（LOGIN_ONLY_ROUTES）：只作用于本人的通知，不需要记录级授权。
 */
class ReadAllNotificationsController
{
    public function __invoke(#[CurrentUser] User $actor, MarkAllNotificationsAsRead $action): void
    {
        $action->handle($actor);
    }
}
