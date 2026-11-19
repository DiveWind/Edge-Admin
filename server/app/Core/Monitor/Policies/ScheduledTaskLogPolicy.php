<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Models\ScheduledTaskLog;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * ScheduledTaskLog（定时任务的执行记录）的授权规则（设计文档 §6.6、§8.3）：标准方法齐全，默认拒绝。
 *
 * 查看沿用定时任务的菜单节点 `monitor.schedule`（执行记录接口检查所属任务的 ScheduledTaskPolicy::view()）；
 * 记录由 schedule-monitor 写入、model:prune 清理，接口只读。不按数据范围过滤（§6.6）。
 */
class ScheduledTaskLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('monitor.schedule') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, ScheduledTaskLog $log): Response
    {
        return $actor->can('monitor.schedule') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $actor, ScheduledTaskLog $log): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $actor, ScheduledTaskLog $log): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, ScheduledTaskLog $log): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, ScheduledTaskLog $log): Response
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
