<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 启用 / 停用用户（PUT /system/users/{user}/status，以及修改用户时提交了 status，设计文档 §6.5、§8.6）。
 *
 * - 停用自己 → 422（status）：状态接口由 UserPolicy 先返回 403，这里拦的是修改接口中提交的 status；
 * - 最后一个启用的超管不能停用 → 422（status，EnsureSuperAdminRemains，加锁防并发）；
 * - 停用走 DisableUser（已登录的会话与 token 立即失效），启用走 EnableUser。
 */
class ChangeUserStatus
{
    public function __construct(
        private readonly DisableUser $disableUser,
        private readonly EnableUser $enableUser,
        private readonly EnsureSuperAdminRemains $ensureSuperAdminRemains,
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
                $this->enableUser->handle($user);

                return;
            }

            if ($actor->is($user)) {
                throw ValidationException::withMessages(['status' => [__('You cannot disable yourself.')]]);
            }

            $this->ensureSuperAdminRemains->handle($user, 'status');
            $this->disableUser->handle($user);
        });

        return $user;
    }
}
