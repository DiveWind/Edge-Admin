<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Models\ScheduledTask;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * ScheduledTask（定时任务）的授权规则（设计文档 §6.6、§8.3）：标准方法齐全，默认拒绝。
 *
 * 菜单节点 `monitor.schedule` 是查看 / 列表权限（含执行记录），按钮 `monitor.schedule.run` 是立即执行（run）。
 * 任务由 schedule-monitor:sync 从调度定义同步，接口不能新增、修改、删除。不按数据范围过滤：定时任务属于系统，不属于任何用户（§6.6）。
 */
class ScheduledTaskPolicy
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
    public function view(User $actor, ScheduledTask $task): Response
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
    public function update(User $actor, ScheduledTask $task): Response
    {
        return Response::deny();
    }

    /**
     * 删除：任务由 schedule-monitor:sync 维护，一律拒绝。
     */
    public function delete(User $actor, ScheduledTask $task): Response
    {
        return Response::deny();
    }

    /**
     * 立即执行（派发到队列执行一次调度中定义的该任务）。
     */
    public function run(User $actor, ScheduledTask $task): Response
    {
        return $actor->can('monitor.schedule.run') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, ScheduledTask $task): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, ScheduledTask $task): Response
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
