<?php

namespace App\Core\Monitor\Services;

use App\Core\System\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 本次请求使用的 token（设计文档 §6.6）：PersonalAccessTokenPolicy（不能强制下线自己正在用的 token）与 token 列表的 is_current 共用。
 *
 * currentAccessToken() 的 PHPDoc 声明为 PersonalAccessToken，但会话 Cookie 认证时实际是没有 id 的 TransientToken，这里按 id 比较，
 * 两种情况都安全。无状态。
 */
class CurrentAccessToken
{
    /**
     * $token 是否为 $actor 本次请求使用的 token。
     */
    public function is(User $actor, PersonalAccessToken $token): bool
    {
        $currentId = data_get($actor->currentAccessToken(), 'id');

        return $currentId !== null && $currentId === $token->getKey();
    }
}
