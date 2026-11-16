<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class UnreadNotificationCountController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ['count' => $user->unreadNotifications()->count()]]);
    }
}
