<?php

namespace App\Policies;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * 记录级授权（功能权限由路由上的 can:system.user.* 判定）。
 */
class UserPolicy
{
    public function view(User $actor, User $user): bool
    {
        return $user->isVisibleTo($actor);
    }

    public function update(User $actor, User $user): Response
    {
        if ($user->isRoot() && ! $actor->isRoot()) {
            return Response::deny(__('policy.user.root_protected'));
        }

        return $user->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    public function resetPassword(User $actor, User $user): Response
    {
        return $this->update($actor, $user);
    }

    /**
     * 启用 / 禁用：不能禁用自己；id=1 的保护由 Form Request 校验（422）。
     */
    public function updateStatus(User $actor, User $user, string $status): Response
    {
        if ($actor->is($user) && $status === Status::Disabled->value) {
            return Response::deny(__('policy.user.disable_self'));
        }

        return $user->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    public function delete(User $actor, User $user): Response
    {
        if ($actor->is($user)) {
            return Response::deny(__('policy.user.delete_self'));
        }

        if ($user->isRoot()) {
            return Response::deny(__('policy.user.root_protected'));
        }

        return $user->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }
}
