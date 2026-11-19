<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{CreatePermission, DeletePermission, UpdatePermission};
use App\Core\System\Models\Permission;
use App\Core\System\Queries\PermissionTree;
use App\Http\Admin\System\Requests\MenuRequest;
use App\Http\Admin\System\Resources\MenuResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 菜单管理（设计文档 §6.5、§8.2、§8.6）：菜单与权限节点（Permission）的整棵树、详情、新增、修改、删除。路由参数 `{menu}` 绑定 Permission。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 PermissionPolicy 判定（默认拒绝，删除内置节点 403），
 * 写入调用 System 模块的 Action（类型层级、成环、内置节点的结构性字段、生命线节点、有子节点不能删等业务规则都在 Action 中）。
 */
class MenuController
{
    /**
     * 整棵节点树（数组，不分页，是 §6.3 列表契约的例外）：含停用节点与按钮，同级按 sort、id 排序，一次查询组装。
     */
    #[Authorize('viewAny', Permission::class)]
    public function index(PermissionTree $tree): AnonymousResourceCollection
    {
        return MenuResource::collection($tree->get());
    }

    /**
     * 详情（不含 children）。
     */
    #[Authorize('view', 'menu')]
    public function show(Permission $menu): MenuResource
    {
        return new MenuResource($menu);
    }

    /**
     * 新增自定义节点（201）。
     */
    #[Authorize('create', Permission::class)]
    public function store(MenuRequest $request, CreatePermission $action): MenuResource
    {
        return new MenuResource($action->handle($request->menuAttributes()));
    }

    /**
     * 修改：parent_id、status 未提交时不变；内置节点的结构性字段不可修改（422）。
     */
    #[Authorize('update', 'menu')]
    public function update(MenuRequest $request, Permission $menu, UpdatePermission $action): MenuResource
    {
        return new MenuResource($action->handle($menu, $request->menuAttributes()));
    }

    /**
     * 删除：内置节点 403（Policy）；有子节点 422（Action）；角色的授权随之删除。
     */
    #[Authorize('delete', 'menu')]
    public function destroy(Permission $menu, DeletePermission $action): void
    {
        $action->handle($menu);
    }
}
