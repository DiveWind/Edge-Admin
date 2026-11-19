<?php

namespace App\Core\Message\Policies;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Notification（通知中心的通知）的授权规则（设计文档 §6.9、§8.3）：标准方法齐全，默认拒绝。
 *
 * 通知只属于接收人本人：不需要权限码、不按数据范围，管理员（含超管）也不能查看、修改他人的通知。
 *
 * - viewAny：任何已登录的启用用户（列表只查本人的，ownedBy($actor)）；
 * - view、update（标记已读）、delete：通知属于操作者本人，否则 403；
 * - create：一律拒绝（通知只由代码发送，InboxNotification）。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。通知没有软删除与批量删除接口，相关方法一律拒绝；“全部已读”只作用于本人的通知，不需要记录级授权。
 */
class NotificationPolicy
{
    /**
     * Determine whether the user can view any models. 列表只包含本人的通知。
     */
    public function viewAny(User $actor): Response
    {
        return Response::allow();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, Notification $notification): Response
    {
        return $this->own($actor, $notification);
    }

    /**
     * Determine whether the user can create models. 通知只由代码发送。
     */
    public function create(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can update the model（标记已读）。
     */
    public function update(User $actor, Notification $notification): Response
    {
        return $this->own($actor, $notification);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $actor, Notification $notification): Response
    {
        return $this->own($actor, $notification);
    }

    /**
     * Determine whether the user can restore the model. 通知没有软删除。
     */
    public function restore(User $actor, Notification $notification): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 通知没有软删除。
     */
    public function forceDelete(User $actor, Notification $notification): Response
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
     * 通知属于操作者本人。
     */
    private function own(User $actor, Notification $notification): Response
    {
        return $notification->isOwnedBy($actor) ? Response::allow() : Response::deny(__('You can only access your own notifications.'));
    }
}
