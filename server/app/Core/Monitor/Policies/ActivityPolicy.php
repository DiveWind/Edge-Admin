<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Models\Activity;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Activity（activitylog 的日志记录，含各模块的数据变更审计）的授权规则（设计文档 §6.6、§8.3）：标准方法齐全，一律拒绝。
 *
 * 操作日志、登录日志由 OperationLogPolicy、LoginLogPolicy 授权；模块审计日志的查看接口以后由各模块按需提供（届时在这里按日志名授权）。
 */
class ActivityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, Activity $activity): Response
    {
        return Response::deny();
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
    public function update(User $actor, Activity $activity): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $actor, Activity $activity): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, Activity $activity): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, Activity $activity): Response
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
