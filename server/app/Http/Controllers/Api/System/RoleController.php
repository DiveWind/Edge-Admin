<?php

namespace App\Http\Controllers\Api\System;

use App\Enums\DataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\RoleIndexRequest;
use App\Http\Requests\System\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(RoleIndexRequest $request): AnonymousResourceCollection
    {
        $query = Role::query()->withCount('users')->filter($request->filters());

        return RoleResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(RoleRequest $request): RoleResource
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::query()->create(Arr::except($request->validated(), ['permission_ids', 'department_ids']));
            $this->syncRelations($role, $request->validated());

            return $role;
        });

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load(['permissions', 'departments'])->loadCount('users'));
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        DB::transaction(function () use ($request, $role): void {
            $role->update(Arr::except($request->validated(), ['permission_ids', 'department_ids']));
            $this->syncRelations($role, $request->validated());
        });

        return new RoleResource($role->load(['permissions', 'departments']));
    }

    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);

        $role->delete();

        return response()->noContent();
    }

    /**
     * 同步权限与自定义数据权限部门（仅 data_scope = custom 时保留部门）。
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncRelations(Role $role, array $data): void
    {
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $role->departments()->sync($role->data_scope === DataScope::Custom ? ($data['department_ids'] ?? []) : []);
    }
}
