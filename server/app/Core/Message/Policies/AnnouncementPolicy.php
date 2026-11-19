<?php

namespace App\Core\Message\Policies;

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Announcement（公告）的授权规则（设计文档 §6.9、§8.3）：标准方法齐全，默认拒绝。
 *
 * 权限码在 resources/menus.php 中声明：菜单节点 `message.announcement` 是公告管理的查看 / 列表权限，按钮 `message.announcement.create`、
 * `.update`、`.delete`、`.publish`、`.revoke` 是写操作。公告是管理员创建的业务记录，记录级方法都检查数据范围（按 created_by，§8.4）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里判定只看“操作者 + 公告本身”的规则——只有草稿能修改、发布，只有已发布的能撤回，已发布的不能删除；
 * 目标角色与 target 不符、引用不能使用的附件、并发时状态已变由 Action / Form Request 返回 422。
 *
 * read（我收到的公告）：不需要权限码、不按数据范围，只看是否发给了操作者（已发布且目标包含操作者，Announcement::isReceivedBy()）。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。公告没有软删除与批量操作，相关方法一律拒绝。
 */
class AnnouncementPolicy
{
    /**
     * Determine whether the user can view any models. 列表另按 visibleTo($actor) 过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('message.announcement') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model（公告管理的详情；也是附件访问权的委托对象，§6.8）。
     */
    public function view(User $actor, Announcement $announcement): Response
    {
        if (! $actor->can('message.announcement')) {
            return Response::deny();
        }

        return $announcement->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('message.announcement.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. 只有草稿能修改（已发布的先撤回，撤回是终态）。
     */
    public function update(User $actor, Announcement $announcement): Response
    {
        if (! $actor->can('message.announcement.update')) {
            return Response::deny();
        }

        if (! $announcement->isDraft()) {
            return Response::deny(__('Only draft announcements can be edited.'));
        }

        return $announcement->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model. 已发布的不能删除（先撤回）；草稿与已撤回的可以。
     */
    public function delete(User $actor, Announcement $announcement): Response
    {
        if (! $actor->can('message.announcement.delete')) {
            return Response::deny();
        }

        if ($announcement->isPublished()) {
            return Response::deny(__('Published announcements must be revoked before they can be deleted.'));
        }

        return $announcement->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 发布：只有草稿能发布。
     */
    public function publish(User $actor, Announcement $announcement): Response
    {
        if (! $actor->can('message.announcement.publish')) {
            return Response::deny();
        }

        if (! $announcement->isDraft()) {
            return Response::deny(__('Only draft announcements can be published.'));
        }

        return $announcement->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 撤回：只有已发布的能撤回。
     */
    public function revoke(User $actor, Announcement $announcement): Response
    {
        if (! $actor->can('message.announcement.revoke')) {
            return Response::deny();
        }

        if (! $announcement->isPublished()) {
            return Response::deny(__('Only published announcements can be revoked.'));
        }

        return $announcement->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 表单的选项（目标角色下拉）：新增或修改公告的权限。
     */
    public function viewFormOptions(User $actor): Response
    {
        return $actor->can('message.announcement.create') || $actor->can('message.announcement.update') ? Response::allow() : Response::deny();
    }

    /**
     * 我收到的公告：已发布，且目标包含操作者。
     */
    public function read(User $actor, Announcement $announcement): Response
    {
        return $announcement->isReceivedBy($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model. 公告没有软删除。
     */
    public function restore(User $actor, Announcement $announcement): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 公告没有软删除。
     */
    public function forceDelete(User $actor, Announcement $announcement): Response
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
