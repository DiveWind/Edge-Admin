<?php

namespace App\Policies;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * 在线会话（访问令牌）的记录级规则。
 */
class PersonalAccessTokenPolicy
{
    /**
     * 强制下线：不能踢掉当前会话（请使用退出登录）。
     */
    public function delete(User $actor, PersonalAccessToken $token): Response
    {
        $current = $actor->currentAccessToken();

        return $current instanceof PersonalAccessToken && $current->is($token)
            ? Response::deny(__('policy.online_session.current'))
            : Response::allow();
    }
}
