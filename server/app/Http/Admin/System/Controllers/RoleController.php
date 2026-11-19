<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{CreateRole, DeleteRole, UpdateRole};
use App\Core\System\Models\{Role, User};
use App\Http\Admin\System\Requests\{RoleIndexRequest, RoleRequest};
use App\Http\Admin\System\Resources\RoleResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 角色管理（设计文档 §6.5、§8.5、§8.6）：列表、详情、新增、修改、删除。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 RolePolicy 判定（默认拒绝），
 * 写入调用 System 模块的 Action（super-admin 的保护、防提权、已分配角色不能删等业务规则都在 Action 中）。
 * 启用 / 停用、授权树、数据范围选项、角色下拉是单独的单动作控制器（Role*Controller）。
 */
class RoleController
{
    /**
     * 列表（不含 permissions）：筛选 name、title（模糊）、status（精确）；排序 sort、id、created_at，默认 `sort,id`。
     * 不在白名单内的筛选、排序参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', Role::class)]
    public function index(RoleIndexRequest $request): AnonymousResourceCollection
    {
        $roles = QueryBuilder::for(Role::class, $request)
            ->allowedFilters(AllowedFilter::partial('name'), AllowedFilter::partial('title'), AllowedFilter::exact('status'))
            ->allowedSorts('sort', 'id', 'created_at')
            ->defaultSort('sort', 'id')
            ->paginate($request->pageSize());

        return RoleResource::collection($roles);
    }

    /**
     * 详情，含 permissions（权限码 string[]）。
     */
    #[Authorize('view', 'role')]
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    /**
     * 新增（201）。
     */
    #[Authorize('create', Role::class)]
    public function store(RoleRequest $request, CreateRole $action, #[CurrentUser] User $actor): RoleResource
    {
        $role = $action->handle($request->roleAttributes(), $request->permissions() ?? [], $actor);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * 修改：未提交 permissions 时权限不变（super-admin 角色不能提交 permissions）。
     */
    #[Authorize('update', 'role')]
    public function update(RoleRequest $request, Role $role, UpdateRole $action, #[CurrentUser] User $actor): RoleResource
    {
        $role = $action->handle($role, $request->roleAttributes(), $request->permissions(), $actor);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * 删除：super-admin 角色 403（Policy）；已分配给用户的角色 422（Action）。
     */
    #[Authorize('delete', 'role')]
    public function destroy(Role $role, DeleteRole $action): void
    {
        $action->handle($role);
    }
}
