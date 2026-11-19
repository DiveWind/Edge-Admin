<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Role;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 删除角色（DELETE /system/roles/{role}，设计文档 §6.5、§8.6）。
 *
 * - 已分配给用户的角色不能删除 → 422（只有 message，没有对应的表单字段，§6.1）；
 * - super-admin 角色不能删除由 RolePolicy 判定（403）；
 * - 角色的授权（role_has_permissions）随之删除（外键级联），spatie 随删除清除权限缓存。
 */
class DeleteRole
{
    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            if ($role->users()->exists()) {
                throw new UnprocessableEntityHttpException(__('The role is assigned to users and cannot be deleted.'));
            }

            $role->delete();
        });
    }
}
