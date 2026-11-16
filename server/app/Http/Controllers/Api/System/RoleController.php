<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\CreateRole;
use App\Actions\System\UpdateRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\RoleIndexRequest;
use App\Http\Requests\System\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(RoleIndexRequest $request): AnonymousResourceCollection
    {
        $query = Role::query()->withCount('users')->filter($request->filters());

        return RoleResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(RoleRequest $request, CreateRole $createRole): RoleResource
    {
        $role = $createRole->handle($request->validated());

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load(['permissions', 'departments'])->loadCount('users'));
    }

    public function update(RoleRequest $request, Role $role, UpdateRole $updateRole): RoleResource
    {
        $updateRole->handle($role, $request->validated());

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);

        $role->delete();

        return response()->noContent();
    }
}
