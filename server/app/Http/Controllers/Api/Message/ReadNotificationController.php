<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class ReadNotificationController extends Controller
{
    /**
     * 标记自己的一条通知为已读（他人的通知 404）。
     */
    public function __invoke(string $id, #[CurrentUser] User $user): Response
    {
        $user->notifications()->findOrFail($id)->markAsRead();

        return response()->noContent();
    }
}
