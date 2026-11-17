<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\{CreateRole, UpdateRole};
use App\Http\Controllers\Controller;
use App\Http\Requests\System\{RoleIndexRequest, RoleRequest};
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class RoleController extends Controller
{
    #[Authorize('system.role.view')]
    public function index(RoleIndexRequest $request): AnonymousResourceCollection
    {
        $query = Role::query()->withCount('users')->filter($request->filters());

        return RoleResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('system.role.create')]
    public function store(RoleRequest $request, CreateRole $createRole): RoleResource
    {
        $role = $createRole->handle($request->validated());

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    #[Authorize('system.role.view')]
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load(['permissions', 'departments'])->loadCount('users'));
    }

    #[Authorize('system.role.update')]
    public function update(RoleRequest $request, Role $role, UpdateRole $updateRole): RoleResource
    {
        $updateRole->handle($role, $request->validated());

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    #[Authorize('system.role.delete')]
    #[Authorize('delete', 'role')]
    public function destroy(Role $role): Response
    {
        $role->delete();

        return response()->noContent();
    }
}
