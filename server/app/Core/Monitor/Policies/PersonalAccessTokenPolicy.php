<?php

namespace App\Core\Monitor\Policies;

use App\Core\Monitor\Services\CurrentAccessToken;
use App\Core\System\Models\User;
use App\Core\System\Services\Manageability;
use Illuminate\Auth\Access\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum 的 PersonalAccessToken（设备 / App 的 token；不在 app/ 中，由 MonitorServiceProvider 用 Gate::policy() 绑定）的授权规则（设计文档 §6.6、§8.3、§8.6）：标准方法齐全，默认拒绝。
 *
 * 菜单节点 `monitor.online` 是查看 / 列表权限，按钮 `monitor.online.logout` 是强制下线；没有新增、修改。
 * 强制下线（delete）依次检查：权限码 → 不能是本次请求自己正在使用的token（应使用退出登录）→ 超管用户只有超管能下线
 * （沿用用户管理的超管保护）→ 所属用户在数据范围内 → 非超管只能下线“管得到”的用户的（§8.5，Manageability）。自己的其他会话与 token 可以下线。规则与调用方无关。
 */
class PersonalAccessTokenPolicy
{
    public function __construct(private readonly Manageability $manageability) {}

    /**
     * Determine whether the user can view any models. 列表另按所属用户的数据范围过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('monitor.online') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, PersonalAccessToken $token): Response
    {
        if (! $actor->can('monitor.online')) {
            return Response::deny();
        }

        return $this->withinDataScope($actor, $token);
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
    public function update(User $actor, PersonalAccessToken $token): Response
    {
        return Response::deny();
    }

    /**
     * 强制下线（DELETE）。
     */
    public function delete(User $actor, PersonalAccessToken $token): Response
    {
        if (! $actor->can('monitor.online.logout')) {
            return Response::deny();
        }

        if (resolve(CurrentAccessToken::class)->is($actor, $token)) {
            return Response::deny(__('You cannot revoke the token used by this request. Sign out instead.'));
        }

        $owner = $this->ownerOf($token);

        if ($owner instanceof User && $owner->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return Response::deny(__('Only super admins can manage super admin users.'));
        }

        if ($this->withinDataScope($actor, $token)->denied()) {
            return Response::deny();
        }

        // 自己的其他会话与 token 总能下线（§8.6）；他人的要求“管得到”
        return $owner instanceof User && ($owner->is($actor) || $this->manageability->canManageUser($actor, $owner))
            ? Response::allow()
            : Response::deny(__('You cannot manage users with permissions or data scopes beyond your own.'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $actor, PersonalAccessToken $token): Response
    {
        return Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $actor, PersonalAccessToken $token): Response
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

    /**
     * 所属用户（后台用户以外的为 null）。
     */
    private function ownerOf(PersonalAccessToken $token): ?User
    {
        $owner = $token->tokenable;

        return $owner instanceof User ? $owner : null;
    }

    /**
     * 数据范围（§8.4）：所属用户对 $actor 可见；没有所属用户（未登录的会话、其他类型的 token）→ 403。
     */
    private function withinDataScope(User $actor, PersonalAccessToken $token): Response
    {
        $owner = $this->ownerOf($token);

        return $owner instanceof User && $owner->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }
}
