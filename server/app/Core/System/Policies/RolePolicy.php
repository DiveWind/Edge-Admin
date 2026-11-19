<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{Role, User};
use Illuminate\Auth\Access\Response;

/**
 * Role 的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 标准写法：先检查权限码 `$actor->can('<权限码>')`，再做业务保护；不通过时返回带提示文案的 Response::deny()（403）。
 * 权限码在 resources/menus.php 中声明：菜单节点 `system.role` 是查看 / 列表权限，按钮 `system.role.create`、`.update`、`.delete`、
 * `.status` 是写操作。角色不按数据范围过滤（角色表没有 created_by，数据范围约束的是业务数据）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里只判定“操作者 + 角色本身”即可决定的规则（删除 super-admin → 403；
 * 非超管修改、启停 super-admin → 403）；
 * 取决于提交内容或全局状态的（改 super-admin 的标识、停用 super-admin、授予超出自身的权限码、删除已分配的角色）由 Action 返回 422。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。角色没有软删除与批量操作，相关方法一律拒绝。
 */
class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.role') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, Role $role): Response
    {
        return $actor->can('system.role') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('system.role.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. super-admin 角色只有超管能修改（§8.6）。
     */
    public function update(User $actor, Role $role): Response
    {
        if (! $actor->can('system.role.update')) {
            return Response::deny();
        }

        return $this->protectSuperAdminRole($actor, $role);
    }

    /**
     * Determine whether the user can delete the model. super-admin 角色不能删除（§8.6）。
     */
    public function delete(User $actor, Role $role): Response
    {
        if (! $actor->can('system.role.delete')) {
            return Response::deny();
        }

        return $role->isSuperAdmin() ? Response::deny(__('The super admin role cannot be deleted.')) : Response::allow();
    }

    /**
     * 启用 / 停用（PUT /system/roles/{role}/status）。非超管对 super-admin 角色 → 403；
     * 超管停用 super-admin 返回 422（status），由 ChangeRoleStatus 判定。
     */
    public function changeStatus(User $actor, Role $role): Response
    {
        if (! $actor->can('system.role.status')) {
            return Response::deny();
        }

        return $this->protectSuperAdminRole($actor, $role);
    }

    /**
     * 角色表单的选项：授权用的节点树与数据范围（GET /system/roles/permission-tree、/system/roles/data-scopes），
     * 新增或修改角色时使用。
     */
    public function viewFormOptions(User $actor): Response
    {
        return $actor->can('system.role.create') || $actor->can('system.role.update') ? Response::allow() : Response::deny();
    }

    /**
     * 角色下拉（GET /system/roles/options）：用户表单中分配角色时使用，因此看的是用户的新增 / 修改权限码。
     */
    public function viewOptions(User $actor): Response
    {
        return $actor->can('system.user.create') || $actor->can('system.user.update') ? Response::allow() : Response::deny();
    }

    /**
     * super-admin 角色只有超管能修改、启停（§8.6）。
     */
    private function protectSuperAdminRole(User $actor, Role $role): Response
    {
        return $role->isSuperAdmin() && ! $actor->isSuperAdmin()
            ? Response::deny(__('Only super admins can manage the super admin role.'))
            : Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, Role $role): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, Role $role): Response
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
