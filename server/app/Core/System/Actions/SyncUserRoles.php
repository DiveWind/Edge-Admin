<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Role, User};
use App\Core\System\Services\{Manageability, SuperAdminGuard};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 保存用户的角色（设计文档 §8.5、§8.6），由 CreateUser、UpdateUser 在各自的事务中调用；分配用 spatie 的 syncRoles()。
 *
 * 防提权（非超管）：
 * 1. 授予或收回 super-admin → 422（roles）；
 * 2. 只能分配、移除“管得到”的角色（Manageability::canManageRole()：角色已授予的全部权限码都在操作者的有效权限码之内，
 *    且角色的数据范围在操作者可授予的范围内）。提交中新增的角色管不到 → 422（roles）；
 * 3. 用户原本就有、但操作者管不到的角色（含 super-admin），无论是否提交都保留不动（与 SyncRolePermissions 的保留规则同一思路）。
 * 超管不受以上限制。
 *
 * 最后一个启用的超管不能去掉 super-admin 角色 → 422（roles，SuperAdminGuard，加锁防并发）。
 * 修改用户时，调用方（UpdateUser）已在事务开头锁住 super-admin 角色行（锁顺序见 SuperAdminGuard）。
 */
class SyncUserRoles
{
    public function __construct(
        private readonly Manageability $manageability,
        private readonly SuperAdminGuard $superAdminGuard,
    ) {}

    /**
     * Execute the action.
     *
     * @param  list<string>  $names  提交的角色标识（已校验为已存在的角色）
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $names, User $actor): void
    {
        $original = $user->exists ? array_values(array_filter($user->roles()->pluck('name')->all(), is_string(...))) : [];
        $names = array_values(array_unique($names));

        // 共享锁：与 DeleteRole 的行锁互斥（删除角色前的“已分配”检查不会被并发的分配绕过）
        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', User::GUARD)
            ->whereIn('name', [...$names, ...$original])
            ->sharedLock()
            ->get()
            ->keyBy('name');

        if (! $actor->isSuperAdmin()) {
            $names = $this->restrictToManageable($names, $original, $roles, $actor);
        }

        if (in_array(Role::SUPER_ADMIN, $original, true) && ! in_array(Role::SUPER_ADMIN, $names, true)) {
            $this->superAdminGuard->ensureRemains($user, 'roles');
        }

        $user->syncRoles($roles->filter(fn (Role $role): bool => in_array($role->name, $names, true))->values());
    }

    /**
     * 非超管：校验新增的角色，并保留原有但管不到的角色，返回最终的角色标识。
     *
     * @param  list<string>  $names
     * @param  list<string>  $original
     * @param  Collection<array-key, Role>  $roles  涉及的角色（以标识为键）
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function restrictToManageable(array $names, array $original, Collection $roles, User $actor): array
    {
        $added = array_diff($names, $original);
        $removed = array_diff($original, $names);

        if (in_array(Role::SUPER_ADMIN, [...$added, ...$removed], true)) {
            throw ValidationException::withMessages([
                'roles' => [__('Only super admins can grant or revoke the super admin role.')],
            ]);
        }

        $unmanageable = $roles
            ->reject(fn (Role $role): bool => $this->manageability->canManageRole($actor, $role))
            ->keys()
            ->all();

        $escalated = array_values(array_intersect($added, $unmanageable));

        if ($escalated !== []) {
            throw ValidationException::withMessages([
                'roles' => [__('You cannot assign roles with permissions or data scopes you do not have: :roles.', ['roles' => implode(', ', $escalated)])],
            ]);
        }

        return array_values(array_unique([...$names, ...array_intersect($original, $unmanageable)]));
    }
}
