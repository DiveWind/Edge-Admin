<?php

namespace App\Core\Monitor\Actions;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * 吊销一个 Sanctum token（设计文档 §6.6、§7.3）：删除后使用它的下一次请求即为 401。
 * 授权（当前 token、超管保护、数据范围）由 PersonalAccessTokenPolicy 判定。
 */
class RevokeToken
{
    /**
     * Execute the action.
     */
    public function handle(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
