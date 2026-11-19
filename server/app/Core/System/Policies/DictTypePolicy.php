<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{DictType, User};
use Illuminate\Auth\Access\Response;

/**
 * DictType（字典类型）的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 权限码在 resources/menus.php 中声明：菜单节点 `system.dict` 是查看 / 列表权限，按钮 `system.dict.create`、`.update`、`.delete`
 * 是写操作（字典类型与字典项共用）。字典属于系统本身，不按数据范围过滤（§8.4）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里只判定“操作者 + 类型本身”即可决定的规则（删除内置类型 → 403，超管也一样）；
 * 内置类型改 code、code 重复由 Action 返回 422。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。字典没有软删除与批量操作，相关方法一律拒绝。
 */
class DictTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.dict') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, DictType $dictType): Response
    {
        return $actor->can('system.dict') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('system.dict.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. 内置类型可以修改（code 的保护由 UpdateDictType 返回 422）。
     */
    public function update(User $actor, DictType $dictType): Response
    {
        return $actor->can('system.dict.update') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 内置类型不能删除（§8.6），超管也一样。
     */
    public function delete(User $actor, DictType $dictType): Response
    {
        if (! $actor->can('system.dict.delete')) {
            return Response::deny();
        }

        return $dictType->is_builtin ? Response::deny(__('Built-in dictionary types cannot be deleted.')) : Response::allow();
    }

    /**
     * Determine whether the user can restore the model. 字典没有软删除。
     */
    public function restore(User $actor, DictType $dictType): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 字典没有软删除。
     */
    public function forceDelete(User $actor, DictType $dictType): Response
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
