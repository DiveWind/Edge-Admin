<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;
use Illuminate\Validation\ValidationException;

/**
 * 启用 / 停用角色（PUT /system/roles/{role}/status，设计文档 §6.5、§8.6）。
 *
 * - super-admin 角色不能停用 → 422（status）；
 * - 停用的角色，其权限与数据范围立即不生效（PermissionRegistry、HasDataScope 只看启用角色；spatie 随保存清除权限缓存）。
 */
class ChangeRoleStatus
{
    /**
     * Execute the action.
     *
     * @throws ValidationException
     */
    public function handle(Role $role, Status $status): Role
    {
        if ($role->isSuperAdmin() && $status !== Status::Enabled) {
            throw ValidationException::withMessages([
                'status' => [__('The super admin role cannot be disabled.')],
            ]);
        }

        $role->status = $status;
        $role->save();

        return $role;
    }
}
