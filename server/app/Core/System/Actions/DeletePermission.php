<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Permission;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 删除菜单与权限节点（DELETE /system/menus/{menu}，设计文档 §6.5、§8.6）。
 *
 * - 有子节点的节点不能删除 → 422（只有 message，没有对应的表单字段，§6.1）：先锁住节点行再检查，
 *   与新增子节点（CreatePermission 对上级加共享锁）互斥，不会在检查之后又挂上子节点；
 * - 内置节点不能删除由 PermissionPolicy 判定（403）；
 * - 节点的授权（role_has_permissions、model_has_permissions）随之删除：spatie 在 deleting 事件中解除关联，
 *   外键 cascadeOnDelete 兜底（绕过模型事件的删除也不会留下授权）；spatie 随删除清除权限缓存。
 */
class DeletePermission
{
    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Permission $permission): void
    {
        DB::transaction(function () use ($permission): void {
            Permission::query()->whereKey($permission->getKey())->lockForUpdate()->first();

            if ($permission->children()->exists()) {
                throw new UnprocessableEntityHttpException(__('The node has child nodes and cannot be deleted.'));
            }

            $permission->delete();
        });
    }
}
