<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{Setting, User};
use Illuminate\Auth\Access\Response;

/**
 * Setting（系统参数）的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 权限码在 resources/menus.php 中声明：菜单节点 `system.setting` 是查看权限，按钮 `system.setting.update` 是修改参数的值。
 * 参数只能在模块中声明（SettingRegistry），接口不能新增、删除：create、delete 等一律拒绝。不按数据范围过滤（§8.4）。
 *
 * 修改接口按“类”授权（`#[Authorize('update', Setting::class)]`）：参数由声明决定，库中可能还没有对应的行（尚未同步）。
 * 值不符合类型或声明的规则由入口的 Form Request 返回 422。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。
 */
class SettingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.setting') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, Setting $setting): Response
    {
        return $actor->can('system.setting') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models. 参数只能在模块中声明，接口不能新增（§6.7）。
     */
    public function create(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can update the model. 修改参数的值（单个或按分组）。
     */
    public function update(User $actor, ?Setting $setting = null): Response
    {
        return $actor->can('system.setting.update') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 参数只能在模块中声明，接口不能删除（§6.7）。
     */
    public function delete(User $actor, Setting $setting): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, Setting $setting): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, Setting $setting): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete models in bulk.
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
