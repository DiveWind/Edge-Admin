<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Role, User};
use App\Core\System\Services\{Manageability, PermissionRegistry};
use Illuminate\Validation\ValidationException;

/**
 * 保存角色的权限码（设计文档 §8.5、§8.6），由 CreateRole、UpdateRole 在各自的事务中调用。
 *
 * 可授予的权限码取自 Manageability（“管得到”的统一判定，§8.5）；非超管只能修改管得到的角色（RolePolicy，403），
 * 因此对非超管而言第 2 条只在并发时（读取后角色被别人改动）才起作用，对超管则保留停用节点的授权。
 *
 * 1. 防提权：非超管提交的权限码中，不在操作者有效权限码里、且角色原本也没有的 → 422（permissions）；超管不受限；
 * 2. 操作者“管不到”的授权保持不变：角色原本就有、但不在操作者有效权限码中的（非超管自己没有的码；对超管是停用节点的授权，
 *    它们不出现在授权树中），无论是否提交都保留——操作者既不能加也不能删自己没有的码；
 * 3. 规范化：补齐每个节点的全部祖先节点，再 syncPermissions()（spatie 随之清除权限缓存，有效权限码立即按新授权计算）。
 */
class SyncRolePermissions
{
    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly Manageability $manageability,
    ) {}

    /**
     * Execute the action.
     *
     * @param  list<string>  $codes  提交的权限码（已校验为已登记的权限码）
     *
     * @throws ValidationException
     */
    public function handle(Role $role, array $codes, User $actor): void
    {
        $original = $role->exists
            ? array_values(array_filter($role->permissions()->pluck('name')->all(), is_string(...)))
            : [];
        $manageable = $this->manageability->grantableCodesFor($actor);

        if (! $actor->isSuperAdmin()) {
            $escalated = array_values(array_diff($codes, $manageable, $original));

            if ($escalated !== []) {
                throw ValidationException::withMessages([
                    'permissions' => [__('You cannot grant permissions you do not have: :codes.', ['codes' => implode(', ', $escalated)])],
                ]);
            }
        }

        $role->syncPermissions($this->permissions->withAncestors([...$codes, ...array_diff($original, $manageable)]));
    }
}
