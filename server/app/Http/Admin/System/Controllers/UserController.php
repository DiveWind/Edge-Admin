<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{CreateUser, DeleteUser, UpdateUser};
use App\Core\System\Models\User;
use App\Http\Admin\System\Requests\{UserIndexRequest, UserRequest};
use App\Http\Admin\System\Resources\UserResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 用户管理（设计文档 §6.5、§8.4、§8.5、§8.6）：列表、详情、新增、修改、删除。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 UserPolicy 判定（默认拒绝，含数据范围），
 * 写入调用 System 模块的 Action（最后一个超管、防提权、唯一性等业务规则都在 Action 中）。
 * 启用 / 停用、重置密码是单独的单动作控制器（UserStatusController、UserPasswordController）。
 */
class UserController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)，预加载 roles）：筛选 username、name、email（模糊）、status（精确）、
     * created_at（区间 `起,止`）；排序 id、username、created_at，默认 `-id`。不在白名单内的筛选、排序参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', User::class)]
    public function index(UserIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::query()->visibleTo($actor)->with('roles'), $request)
            ->allowedFilters(
                AllowedFilter::partial('username'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('status'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'username', 'created_at')
            ->defaultSort('-id')
            ->paginate($request->pageSize());

        return UserResource::collection($users);
    }

    /**
     * 详情，含 roles。
     */
    #[Authorize('view', 'user')]
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    /**
     * 新增（201）：created_by 为操作者。
     */
    #[Authorize('create', User::class)]
    public function store(UserRequest $request, CreateUser $action, #[CurrentUser] User $actor): UserResource
    {
        $user = $action->handle($request->userAttributes(), $request->password(), $request->roles() ?? [], $actor);

        return new UserResource($user->load('roles'));
    }

    /**
     * 修改：未提交 roles 时角色不变；提交的 status 与当前不同时按启用 / 停用处理。
     */
    #[Authorize('update', 'user')]
    public function update(UserRequest $request, User $user, UpdateUser $action, #[CurrentUser] User $actor): UserResource
    {
        $user = $action->handle($user, $request->userAttributes(), $request->roles(), $actor);

        return new UserResource($user->load('roles'));
    }

    /**
     * 删除，并吊销对方的会话与 token：删除自己、非超管删除超管 403（Policy）；最后一个启用的超管 422（Action）。
     */
    #[Authorize('delete', 'user')]
    public function destroy(User $user, DeleteUser $action): void
    {
        $action->handle($user);
    }
}
