<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 系统至少保留一个启用的超管（设计文档 §8.6）：$user 即将不再是启用的超管（删除、停用、去掉 super-admin 角色）时，
 * 如果它是最后一个启用的超管 → 422。由 DeleteUser、ChangeUserStatus、SyncUserRoles 在各自的事务中、写入之前调用。
 *
 * - 启用的超管：账号启用，且拥有启用的 super-admin 角色（与 User::isSuperAdmin() 同一口径，按数据库的当前状态判断）；
 * - 并发：先对 super-admin 角色那一行加排他锁（lockForUpdate），把“减少启用超管”的写操作串行化，锁持有到事务结束。
 *   两个请求同时停用仅剩的两个超管时，后一个等前一个提交后再读，看到只剩自己一个 → 422。
 *   因此必须在事务中调用（否则锁立即释放），不在事务中时抛出 LogicException。SQLite 不支持行锁，但同一时间只有一个写事务。
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
        if (DB::transactionLevel() === 0) {
            throw new LogicException(self::class.' must run inside a database transaction.');
        }

        if (Role::query()->where('name', Role::SUPER_ADMIN)->lockForUpdate()->first() === null) {
            return;
        }

        $superAdmins = User::query()
            ->where('status', Status::Enabled)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::SUPER_ADMIN)->where('status', Status::Enabled))
            ->limit(2)
            ->pluck('id')
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
}
