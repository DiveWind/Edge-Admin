<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\DeletePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\{PermissionIndexRequest, StorePermissionRequest, UpdatePermissionRequest};
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 菜单管理：目录 / 菜单 / 操作组成的权限树。内置节点（is_system）由 config/permissions.php 声明，
 * 可调整名称、图标、排序、显示、状态与上级，不可删除、不可改 code / type。
 */
class PermissionController extends Controller
{
    /**
     * 菜单树（不分页）；有过滤条件时，匹配节点的父节点不在结果中则作为根节点。
     */
    #[Authorize('system.permission.view')]
    public function index(PermissionIndexRequest $request): AnonymousResourceCollection
    {
        $permissions = Permission::query()->filter($request->validated())->ordered()->get();

        return PermissionResource::collection($permissions->toTree());
    }

    #[Authorize('system.permission.create')]
    public function store(StorePermissionRequest $request): PermissionResource
    {
        return new PermissionResource(Permission::query()->create($request->validated()));
    }

    #[Authorize('system.permission.view')]
    public function show(Permission $permission): PermissionResource
    {
        return new PermissionResource($permission);
    }

    #[Authorize('system.permission.update')]
    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        $permission->update($request->validated());

        return new PermissionResource($permission);
    }

    /**
     * 有子节点或内置节点不可删除（403）；被角色引用时同时解除授权。
     */
    #[Authorize('system.permission.delete')]
    #[Authorize('delete', 'permission')]
    public function destroy(Permission $permission, DeletePermission $delete): Response
    {
        $delete->handle($permission);

        return response()->noContent();
    }
}
