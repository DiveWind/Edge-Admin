<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 停用用户：不能再登录，已登录的会话与 token 立即失效（设计文档 §7.3）。供后台的用户管理调用。
 *
 * 会话为 database 驱动时直接删除该用户的全部会话；其他驱动由 EnsureUserIsEnabled 中间件在下一次请求时注销。
 */
class DisableUser
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /**
     * Execute the action.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => Status::Disabled])->save();

            $this->revokeSessions->handle($user);
        });
    }
}
