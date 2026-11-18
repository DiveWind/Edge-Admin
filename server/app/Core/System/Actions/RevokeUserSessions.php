<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 让用户已登录的会话与 token 失效（停用账号、修改或重置密码时，设计文档 §7.3、§7.4）。
 *
 * - 会话：database 驱动按 sessions 表的 user_id 删除，可保留一个会话（修改密码的当前会话）；
 *   其他驱动（Redis、file 等）无法按用户找到会话，由中间件兜底：停用账号由 EnsureUserIsEnabled 在下一次请求时注销，
 *   修改密码由 Sanctum 的 AuthenticateSession（比对会话中保存的密码哈希）注销；
 * - token：删除该用户全部的 Sanctum token。
 */
class RevokeUserSessions
{
    /**
     * Execute the action.
     */
    public function handle(User $user, ?string $exceptSessionId = null): void
    {
        DB::transaction(function () use ($user, $exceptSessionId): void {
            if (config('session.driver') === 'database') {
                $connection = config('session.connection');

                DB::connection(is_string($connection) ? $connection : null)
                    ->table(config()->string('session.table'))
                    ->where('user_id', $user->getKey())
                    ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                    ->delete();
            }

            $user->tokens()->delete();
        });
    }
}
