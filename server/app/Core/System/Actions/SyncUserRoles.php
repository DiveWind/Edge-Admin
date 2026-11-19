<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Role, User};
use App\Core\System\Services\{DataScopeRegistry, PermissionRegistry};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 保存用户的角色（设计文档 §8.5、§8.6），由 CreateUser、UpdateUser 在各自的事务中调用；分配用 spatie 的 syncRoles()。
 *
 * 防提权（非超管）：
 * 1. 授予或收回 super-admin → 422（roles）；
 * 2. 只能分配、移除“管得到”的角色：角色的权限码全部在操作者的有效权限码之内，且角色的数据范围在操作者可授予的范围内
 *    （DataScopeRegistry::grantableKeysFor()）。提交中新增的角色管不到 → 422（roles）；
 * 3. 用户原本就有、但操作者管不到的角色（含 super-admin），无论是否提交都保留不动（与 SyncRolePermissions 的保留规则同一思路）。
 * 超管不受以上限制。
 *
 * 最后一个启用的超管不能去掉 super-admin 角色 → 422（roles，EnsureSuperAdminRemains，加锁防并发）。
 */
class SyncUserRoles
{
    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly DataScopeRegistry $scopes,
        private readonly EnsureSuperAdminRemains $ensureSuperAdminRemains,
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

        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', User::GUARD)
            ->whereIn('name', [...$names, ...$original])
            ->get()
            ->keyBy('name');

        if (! $actor->isSuperAdmin()) {
            $names = $this->restrictToManageable($names, $original, $roles, $actor);
        }

        if (in_array(Role::SUPER_ADMIN, $original, true) && ! in_array(Role::SUPER_ADMIN, $names, true)) {
            $this->ensureSuperAdminRemains->handle($user, 'roles');
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

        $codes = $this->permissions->codesFor($actor);
        $scopes = $this->scopes->grantableKeysFor($actor);
        $unmanageable = $roles
            ->filter(fn (Role $role): bool => $role->isSuperAdmin()
                || array_diff($this->codesOf($role), $codes) !== []
                || ! in_array($role->data_scope, $scopes, true))
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

    /**
     * 角色已授予的权限码。
     *
     * @return list<string>
     */
    private function codesOf(Role $role): array
    {
        return array_values(array_filter($role->permissions->pluck('name')->all(), is_string(...)));
    }
}
