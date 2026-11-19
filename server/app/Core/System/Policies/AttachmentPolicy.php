<?php

namespace App\Core\System\Policies;

use App\Core\System\Models\{Attachment, User};
use Illuminate\Auth\Access\Response;

/**
 * Attachment（附件）的授权规则（设计文档 §6.8、§8.3）：标准方法齐全，默认拒绝。
 *
 * 权限码在 resources/menus.php 中声明：菜单节点 `system.attachment` 是附件管理的查看 / 列表权限，按钮 `system.attachment.delete` 是删除。
 *
 * - create：上传只需登录（任何已登录的启用用户）：各模块的表单都要上传，按权限码控制会让授权失控；防滥用靠限流、类型与大小白名单、孤儿清理；
 * - view（访问权）：
 *   1. 未绑定的附件：上传者本人；
 *   2. 已绑定的附件：委托给绑定对象——能 `view` 那条记录的人（那条记录的 Policy，含它的数据范围）；上传者本人也不例外；
 *   3. 以上都不满足时：附件管理员（`system.attachment`）且附件在数据范围内（按上传者 created_by，HasDataScope）；
 * - delete：`system.attachment.delete`；已绑定的附件不能删除（403，超管也一样：在绑定它的记录中替换、移除，或删除那条记录）；数据范围内。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。附件没有修改、软删除与批量操作，相关方法一律拒绝。
 */
class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models. 附件管理的列表，另按 visibleTo($actor) 过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('system.attachment') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model（访问权，见类注释）。
     */
    public function view(User $actor, Attachment $attachment): Response
    {
        if (! $attachment->isBound()) {
            if ($attachment->created_by === $actor->id) {
                return Response::allow();
            }
        } else {
            $attachable = $attachment->loadMissing('attachable')->attachable;

            if ($attachable !== null && $actor->can('view', $attachable)) {
                return Response::allow();
            }
        }

        return $actor->can('system.attachment') && $attachment->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models. 上传只需登录。
     */
    public function create(User $actor): Response
    {
        return Response::allow();
    }

    /**
     * Determine whether the user can update the model. 附件不能修改。
     */
    public function update(User $actor, Attachment $attachment): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 已绑定的附件不能删除（§8.6）。
     */
    public function delete(User $actor, Attachment $attachment): Response
    {
        if (! $actor->can('system.attachment.delete')) {
            return Response::deny();
        }

        if ($attachment->isBound()) {
            return Response::deny(__('Attachments in use cannot be deleted.'));
        }

        return $attachment->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model. 附件没有软删除。
     */
    public function restore(User $actor, Attachment $attachment): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 附件没有软删除。
     */
    public function forceDelete(User $actor, Attachment $attachment): Response
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
