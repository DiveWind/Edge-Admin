<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{DictItem, User};
use Illuminate\Auth\Access\Response;

/**
 * DictItem（字典项）的授权规则（设计文档 §8.3）：标准方法齐全，默认拒绝。
 *
 * 与字典类型共用权限码：`system.dict` 是查看 / 列表权限，按钮 `system.dict.create`、`.update`、`.delete` 是写操作。
 * 不按数据范围过滤（§8.4）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：删除内置字典项 → 403（超管也一样）；内置字典项改 value、value 重复由 Action 返回 422。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。字典没有软删除与批量操作，相关方法一律拒绝。
 */
class DictItemPolicy
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
    public function view(User $actor, DictItem $dictItem): Response
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
     * Determine whether the user can update the model. 内置字典项可以修改（value 的保护由 UpdateDictItem 返回 422）。
     */
    public function update(User $actor, DictItem $dictItem): Response
    {
        return $actor->can('system.dict.update') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 内置字典项不能删除（§8.6），超管也一样。
     */
    public function delete(User $actor, DictItem $dictItem): Response
    {
        if (! $actor->can('system.dict.delete')) {
            return Response::deny();
        }

        return $dictItem->is_builtin ? Response::deny(__('Built-in dictionary items cannot be deleted.')) : Response::allow();
    }

    /**
     * Determine whether the user can restore the model. 字典没有软删除。
     */
    public function restore(User $actor, DictItem $dictItem): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 字典没有软删除。
     */
    public function forceDelete(User $actor, DictItem $dictItem): Response
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
