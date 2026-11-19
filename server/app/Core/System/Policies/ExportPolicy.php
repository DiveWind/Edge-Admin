<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{Export, User};
use Illuminate\Auth\Access\Response;

/**
 * Export（异步导出）的授权规则（设计文档 §6.10、§8.3）：标准方法齐全，默认拒绝。
 *
 * 导出只属于发起人：不需要权限码、不按数据范围（数据范围在生成文件时已作用于其中的数据），管理员（含超管）也不能查看、下载他人的导出。
 *
 * - viewAny：任何已登录的启用用户（“我的导出”只查本人的，ownedBy($actor)）；
 * - view、delete：导出属于操作者本人，否则 403。文件的访问权委托给 view（AttachmentPolicy 第 2 条；Export 实现 PrivateAttachable，
 *   附件管理员的第 3 条不适用），因此只有发起人能拿到下载地址；
 * - create：一律拒绝——发起导出由各列表的导出入口按导出权限码（`<菜单>.export`）授权，不经过本 Policy；update 等一律拒绝。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。
 */
class ExportPolicy
{
    /**
     * Determine whether the user can view any models. 列表只包含本人的导出。
     */
    public function viewAny(User $actor): Response
    {
        return Response::allow();
    }

    /**
     * Determine whether the user can view the model（含下载文件）。
     */
    public function view(User $actor, Export $export): Response
    {
        return $this->own($actor, $export);
    }

    /**
     * Determine whether the user can create models. 发起导出由各列表的导出入口按权限码授权。
     */
    public function create(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can update the model. 导出不能修改。
     */
    public function update(User $actor, Export $export): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete the model（排队、进行中的即取消）。
     */
    public function delete(User $actor, Export $export): Response
    {
        return $this->own($actor, $export);
    }

    /**
     * Determine whether the user can restore the model. 导出没有软删除。
     */
    public function restore(User $actor, Export $export): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 导出没有软删除。
     */
    public function forceDelete(User $actor, Export $export): Response
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
     * 导出属于操作者本人。
     */
    private function own(User $actor, Export $export): Response
    {
        return $export->isOwnedBy($actor) ? Response::allow() : Response::deny(__('You can only access your own exports.'));
    }
}
