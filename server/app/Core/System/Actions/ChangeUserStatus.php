<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use App\Core\System\Services\SuperAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 启用 / 停用用户（PUT /system/users/{user}/status，以及修改用户时提交了 status，设计文档 §6.5、§8.6）。
 *
 * - 停用自己 → 422（status）：状态接口由 UserPolicy 先返回 403，这里拦的是修改接口中提交的 status；
 * - 最后一个启用的超管不能停用 → 422（status，SuperAdminGuard，加锁防并发）；
 * - 停用走 DisableUser（已登录的会话与 token 立即失效，它也被其他用例复用）；启用只是恢复状态，直接写入；
 * - 锁顺序（§8.6）：停用时事务的第一条语句是锁住 super-admin 角色行（SuperAdminGuard），之后才写用户行。
 */
class ChangeUserStatus
{
    public function __construct(
        private readonly DisableUser $disableUser,
        private readonly SuperAdminGuard $superAdminGuard,
    ) {}

    /**
     * Execute the action.
     *
     * @throws ValidationException
     */
    public function handle(User $user, Status $status, User $actor): User
    {
        DB::transaction(function () use ($user, $status, $actor): void {
            if ($status === Status::Enabled) {
                $user->forceFill(['status' => Status::Enabled])->save();

                return;
            }

            if ($actor->is($user)) {
                throw ValidationException::withMessages(['status' => [__('You cannot disable yourself.')]]);
            }

            // 先锁 super-admin 角色行（锁顺序），再计数、写用户行
            $this->superAdminGuard->ensureRemains($user, 'status');
            $this->disableUser->handle($user);
        });

        return $user;
    }
}
