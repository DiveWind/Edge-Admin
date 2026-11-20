<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 系统至少保留一个启用的超管（设计文档 §8.6）：$user 即将不再是启用的超管（删除、停用、去掉 super-admin 角色）时，
 * 如果它是最后一个启用的超管 → 422。由 DeleteUser、ChangeUserStatus、SyncUserRoles 在各自的事务中、写入之前调用。
 *
 * - 启用的超管：账号启用，且拥有启用的 super-admin 角色（与 User::isSuperAdmin() 同一口径，按数据库的当前状态判断）；
 * - 并发与锁顺序（§8.6）：凡是可能减少启用超管的事务（DeleteUser、ChangeUserStatus 停用、UpdateUser 提交了 roles 或停用），
 *   **第一条语句**都是 lock()——对 super-admin 角色那一行加排他锁（lockForUpdate），锁持有到事务结束；
 *   之后才读写用户、角色分配等其他行。锁顺序一致（先 super-admin 角色行，再用户行），避免互相等待的死锁，
 *   也使 MySQL / MariaDB（REPEATABLE READ）的一致性快照建立在拿到锁之后；
 * - 计数本身也是加锁读（sharedLock，只锁 super-admin 的持有者及其分配行）：加锁读总是读取最新提交的版本，
 *   即使事务中此前已有普通读取建立了旧快照，也不会把刚被别人停用的超管算进来。
 *   两个请求同时停用仅剩的两个超管时，后一个等前一个提交后再读，看到只剩自己一个 → 422。
 * - 必须在事务中调用（否则锁立即释放），不在事务中时抛出 LogicException。SQLite 不支持行锁，但同一时间只有一个写事务。
 */
class EnsureSuperAdminRemains
{
    /**
     * Execute the action.
     *
     * @param  string|null  $field  错误挂的字段（status、roles）；null 时只有 message（删除，§6.1）
     *
     * @throws ValidationException
     * @throws UnprocessableEntityHttpException
     * @throws LogicException
     */
    public function handle(User $user, ?string $field): void
    {
        $role = $this->lock();

        if (! $role instanceof Role || ! $role->isEnabled()) {
            return;
        }

        $superAdmins = $role->users()
            ->where('users.status', Status::Enabled)
            ->limit(2)
            ->sharedLock()
            ->pluck('users.id')
            ->all();

        if ($superAdmins !== [$user->getKey()]) {
            return;
        }

        $message = match ($field) {
            null => __('The last enabled super admin cannot be deleted.'),
            'status' => __('The last enabled super admin cannot be disabled.'),
            default => __('The super admin role cannot be removed from the last enabled super admin.'),
        };

        if ($field === null) {
            throw new UnprocessableEntityHttpException($message);
        }

        throw ValidationException::withMessages([$field => [$message]]);
    }

    /**
     * 对 super-admin 角色那一行加排他锁并返回它（不存在时为 null）：可能减少启用超管的事务以它作为第一条语句（锁顺序见类注释）。
     * 同一事务中重复调用无副作用。
     *
     * @throws LogicException
     */
    public function lock(): ?Role
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(self::class.' must run inside a database transaction.');
        }

        return Role::query()->where('name', Role::SUPER_ADMIN)->where('guard_name', User::GUARD)->lockForUpdate()->first();
    }
}
