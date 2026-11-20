<?php

namespace App\Core\System\Services;

use App\Core\System\Models\{Role, User};
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Validation\ValidationException;

/**
 * “管得到”的统一判定（设计文档 §8.5）：非超管只能管理权限码与数据范围都不超过自己的角色和用户。
 *
 * - 操作者管得到一个角色：该角色已授予的**全部**权限码（不论角色是否启用、节点是否启用）都在操作者的有效权限码内，
 *   且角色的 data_scope 在操作者可授予的范围内（DataScopeRegistry::grantableKeysFor()），且不是 super-admin。
 *   因此停用节点上的码、停用角色的码，非超管都管不到；
 * - 操作者管得到一个用户：该用户不是超管，该用户的**全部**角色（含停用的）操作者都管得到，且在操作者的数据范围内（isVisibleTo）；
 * - 超管管得到一切（超管专属的规则另由各 Policy、Action 判定）。
 *
 * 使用方：RolePolicy（update、delete、changeStatus）、UserPolicy（update、delete、changeStatus、resetPassword）、
 * SessionPolicy、PersonalAccessTokenPolicy（强制下线）返回 403；SyncRolePermissions（可授予的权限码）、
 * SyncUserRoles（分配、移除角色）、CreateRole / UpdateRole（可授予的数据范围，ensureGrantableDataScope()）返回 422。
 *
 * 纯判定，不写入任何数据（ensureGrantableDataScope() 只是把判定结果转为 422）；`#[Scoped]`：依赖每个请求（队列任务）一个实例的 EffectivePermissions，常驻内存下不跨请求保存数据（§10.3）。
 */
#[Scoped]
class Manageability
{
    public function __construct(
        private readonly EffectivePermissions $permissions,
        private readonly DataScopeRegistry $scopes,
    ) {}

    /**
     * 操作者可授予的权限码：即他的有效权限码（超管为全部生效节点，§8.1）。
     *
     * @return list<string>
     */
    public function grantableCodesFor(User $actor): array
    {
        return $this->permissions->codesFor($actor);
    }

    /**
     * 操作者能否授予数据范围 $key（DataScopeRegistry::grantableKeysFor() 中有它）。
     */
    public function canGrantDataScope(User $actor, string $key): bool
    {
        return $this->scopes->canGrant($actor, $key);
    }

    /**
     * 数据范围防提权（§8.5）：$role 的 data_scope 被提交（改动）时，它必须在操作者可授予的范围内，否则 → 422（data_scope）。
     * 未提交或保持原值时不检查，与权限码“管不到的保持不变”同一思路。由 CreateRole、UpdateRole 在保存前调用。
     *
     * @throws ValidationException
     */
    public function ensureGrantableDataScope(Role $role, User $actor): void
    {
        if (! $role->isDirty('data_scope') || $this->canGrantDataScope($actor, $role->data_scope)) {
            return;
        }

        throw ValidationException::withMessages([
            'data_scope' => [__('You cannot grant a data scope you do not have.')],
        ]);
    }

    /**
     * 操作者是否管得到角色 $role。
     */
    public function canManageRole(User $actor, Role $role): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $role->isSuperAdmin()
            && array_diff($this->codesOf($role), $this->grantableCodesFor($actor)) === []
            && in_array($role->data_scope, $this->scopes->grantableKeysFor($actor), true);
    }

    /**
     * 操作者是否管得到用户 $user。
     */
    public function canManageUser(User $actor, User $user): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        /** @var iterable<Role> $roles */
        $roles = $user->roles()->with('permissions')->get();

        foreach ($roles as $role) {
            if (! $this->canManageRole($actor, $role)) {
                return false;
            }
        }

        return $user->isVisibleTo($actor);
    }

    /**
     * 角色已授予的全部权限码（含停用节点上的）。
     *
     * @return list<string>
     */
    private function codesOf(Role $role): array
    {
        return array_values(array_filter($role->permissions->pluck('name')->all(), is_string(...)));
    }
}
