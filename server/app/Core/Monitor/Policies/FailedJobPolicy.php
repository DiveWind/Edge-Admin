<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Models\FailedJob;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * FailedJob（失败任务）的授权规则（设计文档 §6.6、§8.3）：标准方法齐全，默认拒绝。
 *
 * 菜单节点 `monitor.failed-job` 是查看 / 列表权限；按钮 `monitor.failed-job.retry` 是重试（retry 单条、retryAny 全部），
 * `monitor.failed-job.delete` 是删除（delete 单条、deleteAny 清空）。失败任务由队列写入，接口不能新增、修改。
 * 不按数据范围过滤：失败任务属于系统，不属于任何用户；payload 与异常可能含任意用户的数据，只授予可信的运维角色（§6.6）。
 */
class FailedJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('monitor.failed-job') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, FailedJob $job): Response
    {
        return $actor->can('monitor.failed-job') ? Response::allow() : Response::deny();
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
    public function update(User $actor, FailedJob $job): Response
    {
        return Response::deny();
    }

    /**
     * 删除一条失败任务（FailedJobProviderInterface::forget()）。
     */
    public function delete(User $actor, FailedJob $job): Response
    {
        return $actor->can('monitor.failed-job.delete') ? Response::allow() : Response::deny();
    }

    /**
     * 重试一条失败任务（queue:retry）。
     */
    public function retry(User $actor, FailedJob $job): Response
    {
        return $actor->can('monitor.failed-job.retry') ? Response::allow() : Response::deny();
    }

    /**
     * 全部重试（queue:retry all，或指定队列）。
     */
    public function retryAny(User $actor): Response
    {
        return $actor->can('monitor.failed-job.retry') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, FailedJob $job): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, FailedJob $job): Response
    {
        return Response::deny();
    }

    /**
     * 清空全部失败任务（FailedJobProviderInterface::flush()）。
     */
    public function deleteAny(User $actor): Response
    {
        return $actor->can('monitor.failed-job.delete') ? Response::allow() : Response::deny();
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
