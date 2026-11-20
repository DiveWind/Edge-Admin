<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Queries\UnreadNotificationCount;
use App\Core\System\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * 我的未读数（GET /notifications/unread-count，设计文档 §6.9）：`{ count }`，顶栏的通知铃铛轮询它。
 * 只需登录（LOGIN_ONLY_ROUTES），只数本人的通知。
 */
class UnreadNotificationCountController
{
    /**
     * Handle the incoming request.
     *
     * @return array{count: int}
     */
    public function __invoke(UnreadNotificationCount $count, #[CurrentUser] User $actor): array
    {
        return ['count' => $count->get($actor)];
    }
}
