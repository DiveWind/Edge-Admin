<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class ReadAllNotificationsController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
