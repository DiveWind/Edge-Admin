<?php

namespace App\Core\System\Actions;

use App\Core\System\Events\{UserDeleted, UserDeleting};
use App\Core\System\Models\User;
use App\Core\System\Services\SuperAdminGuard;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * 删除用户（DELETE /system/users/{user}，设计文档 §6.5、§8.6）。
 *
 * - 最后一个启用的超管不能删除 → 422（只有 message，SuperAdminGuard，加锁防并发）：它是事务的第一条语句
 *   （先锁 super-admin 角色行，再删用户行及其关联，锁顺序见 §8.6）；
 * - 删除自己、非超管删除超管由 UserPolicy 判定（403）；
 * - 先吊销对方的会话与 token（RevokeUserSessions），再派发 UserDeleting（各模块在同一事务中清理属于他的数据：System 的导出、
 *   Message 的通知……），然后删除；角色分配随之删除（spatie），该用户创建的用户 created_by 置空（外键 nullOnDelete）；
 * - 事务提交后派发 UserDeleted（§5.5 的 Core 事件）。
 */
class DeleteUser
{
    public function __construct(
        private readonly RevokeUserSessions $revokeSessions,
        private readonly SuperAdminGuard $superAdminGuard,
    ) {}

    /**
     * Execute the action.
     *
     * @throws BusinessRuleViolation
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->superAdminGuard->ensureRemains($user, null);
            $this->revokeSessions->handle($user);

            event(new UserDeleting($user));

            $user->delete();

            event(new UserDeleted($user));
        });
    }
}
