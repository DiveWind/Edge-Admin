<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Models\LoginLog;
use App\Core\System\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * LoginLog（登录日志）的授权规则（设计文档 §6.6、§8.3）：标准方法齐全，默认拒绝。
 *
 * 菜单节点 `monitor.login-log` 是查看 / 列表权限；日志只读，新增、修改、删除（含批量）一律拒绝，过期日志由 activitylog:clean 清理。
 * 数据范围委托给 causer（Activity::isVisibleTo()，§8.4）：超出范围 → 403。规则与调用方无关。
 */
class LoginLogPolicy
{
    /**
     * Determine whether the user can view any models. 列表另按 visibleTo($actor) 过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('monitor.login-log') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model. 数据范围委托给 causer（§8.4）。
     */
    public function view(User $actor, LoginLog $loginLog): Response
    {
        if (! $actor->can('monitor.login-log')) {
            return Response::deny();
        }

        return $loginLog->isVisibleTo($actor) ? Response::allow() : Response::deny();
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
    public function update(User $actor, LoginLog $loginLog): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $actor, LoginLog $loginLog): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, LoginLog $loginLog): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, LoginLog $loginLog): Response
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
