<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\User;
use App\Core\System\Services\Manageability;
use Illuminate\Auth\Access\Response;

/**
 * User 的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 标准写法：先检查权限码 `$actor->can('<权限码>')`，再做业务保护，最后检查数据范围 `$user->isVisibleTo($actor)`；
 * 不通过时返回带提示文案的 Response::deny()（403）。权限码在 resources/menus.php 中声明：菜单节点 `system.user` 是查看 / 列表权限，
 * 按钮 `system.user.create`、`.update`、`.delete`、`.status`（启用 / 停用）、`.password`（重置密码）是写操作。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里只判定“操作者 + 目标用户本身”即可决定的规则——缺权限码、超出数据范围、删除 / 停用自己、
 * 通过管理接口重置自己的密码（应在个人设置中验证当前密码后修改）、非超管操作超管用户（编辑、重置密码、启停、删除）、
 * 非超管操作管不到的用户（同上四种操作；“管得到”见 Manageability，§8.5）；
 * 取决于提交内容或全局状态的（最后一个启用的超管、授予或收回 super-admin、分配管不到的角色、唯一性）由 Action 返回 422。
 *
 * 数据范围：“仅本人”范围下本人与自己创建的用户可见（User::dataScopeOwnerColumns()）；可见不等于可操作，删除、停用自己仍在这里拒绝。
 * 规则与调用方无关：禁止判断是哪个入口在调用。用户没有软删除与批量操作，相关方法一律拒绝。
 */
class UserPolicy
{
    public function __construct(private readonly Manageability $manageability) {}

    /**
     * Determine whether the user can view any models. 列表另按 visibleTo($actor) 过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.user') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, User $user): Response
    {
        if (! $actor->can('system.user')) {
            return Response::deny();
        }

        return $this->withinDataScope($actor, $user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('system.user.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. 超管用户只有超管能编辑（§8.6）。
     */
    public function update(User $actor, User $user): Response
    {
        if (! $actor->can('system.user.update')) {
            return Response::deny();
        }

        return $this->protectSuperAdmin($actor, $user) ?? $this->protectUnmanageable($actor, $user);
    }

    /**
     * Determine whether the user can delete the model. 不能删除自己；超管用户只有超管能删除（§8.6）。
     */
    public function delete(User $actor, User $user): Response
    {
        if (! $actor->can('system.user.delete')) {
            return Response::deny();
        }

        if ($actor->is($user)) {
            return Response::deny(__('You cannot delete yourself.'));
        }

        return $this->protectSuperAdmin($actor, $user) ?? $this->protectUnmanageable($actor, $user);
    }

    /**
     * 启用 / 停用（PUT /system/users/{user}/status）。不能停用自己；超管用户只有超管能启停（§8.6）。
     */
    public function changeStatus(User $actor, User $user): Response
    {
        if (! $actor->can('system.user.status')) {
            return Response::deny();
        }

        if ($actor->is($user)) {
            return Response::deny(__('You cannot disable yourself.'));
        }

        return $this->protectSuperAdmin($actor, $user) ?? $this->protectUnmanageable($actor, $user);
    }

    /**
     * 重置密码（PUT /system/users/{user}/password）。超管用户只有超管能重置（§8.6）；
     * 不能重置自己的密码：自己的密码在个人设置中验证当前密码后修改（Fortify，§7.2）。
     */
    public function resetPassword(User $actor, User $user): Response
    {
        if (! $actor->can('system.user.password')) {
            return Response::deny();
        }

        if ($actor->is($user)) {
            return Response::deny(__('Change your own password in your profile settings.'));
        }

        return $this->protectSuperAdmin($actor, $user) ?? $this->protectUnmanageable($actor, $user);
    }

    /**
     * 用户表单的选项（角色下拉 GET /system/users/role-options）：新增或修改用户的权限。
     */
    public function viewFormOptions(User $actor): Response
    {
        return $actor->can('system.user.create') || $actor->can('system.user.update') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, User $user): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, User $user): Response
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

    /**
     * 超管用户只有超管能编辑、重置密码、启停、删除（§8.6）；不涉及时返回 null。
     */
    private function protectSuperAdmin(User $actor, User $user): ?Response
    {
        return $user->isSuperAdmin() && ! $actor->isSuperAdmin()
            ? Response::deny(__('Only super admins can manage super admin users.'))
            : null;
    }

    /**
     * 写操作：超出数据范围 → 403（不带文案）；非超管管不到该用户（他的某个角色，含停用的，权限码或数据范围超出操作者）→ 403（§8.5）。
     */
    private function protectUnmanageable(User $actor, User $user): Response
    {
        if (! $user->isVisibleTo($actor)) {
            return Response::deny();
        }

        return $this->manageability->canManageUser($actor, $user)
            ? Response::allow()
            : Response::deny(__('You cannot manage users with permissions or data scopes beyond your own.'));
    }

    /**
     * 数据范围（§8.4）：超出范围 → 403。
     */
    private function withinDataScope(User $actor, User $user): Response
    {
        return $user->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }
}
