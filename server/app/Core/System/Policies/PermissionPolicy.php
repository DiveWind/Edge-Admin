<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{Permission, User};
use Illuminate\Auth\Access\Response;

/**
 * Permission（节点）的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 标准写法：先检查权限码 `$actor->can('<权限码>')`，再做业务保护；不通过时返回带提示文案的 Response::deny()（403）。
 * 权限码在 resources/menus.php 中声明：菜单节点 `system.menu` 是查看 / 列表权限，按钮 `system.menu.create`、`.update`、`.delete`
 * 是写操作。节点不按数据范围过滤（数据范围约束的是业务数据）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里只判定“操作者 + 节点本身”即可决定的规则（删除内置节点 → 403，超管也一样）；
 * 取决于提交内容或全局状态的（内置节点改 name / type / path / component、类型层级、成环、停用生命线节点、有子节点不能删）
 * 由 Action 返回 422。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。节点没有软删除与批量操作，相关方法一律拒绝。
 */
class PermissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.menu') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, Permission $permission): Response
    {
        return $actor->can('system.menu') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('system.menu.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. 内置节点可以修改（结构性字段的保护由 UpdatePermission 返回 422）。
     */
    public function update(User $actor, Permission $permission): Response
    {
        return $actor->can('system.menu.update') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 内置节点不能删除（§8.6），超管也一样。
     */
    public function delete(User $actor, Permission $permission): Response
    {
        if (! $actor->can('system.menu.delete')) {
            return Response::deny();
        }

        return $permission->is_builtin ? Response::deny(__('Built-in menu nodes cannot be deleted.')) : Response::allow();
    }

    /**
     * Determine whether the user can restore the model. 节点没有软删除。
     */
    public function restore(User $actor, Permission $permission): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 节点没有软删除。
     */
    public function forceDelete(User $actor, Permission $permission): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete models in bulk. 没有批量删除接口。
     */
    public function deleteAny(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can restore models in bulk.
     */
    public function restoreAny(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete models in bulk.
     */
    public function forceDeleteAny(User $actor): Response
    {
        return Response::deny();
    }
}
