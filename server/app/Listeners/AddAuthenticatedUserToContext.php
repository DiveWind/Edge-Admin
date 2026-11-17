<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Bearer Token 认证成功后把用户 id 放入 Context：之后写的日志、派发的队列任务都会带上 user_id。
 */
class AddAuthenticatedUserToContext
{
    public function handle(TokenAuthenticated $event): void
    {
        Context::add('user_id', $event->token->tokenable_id);
    }
}
