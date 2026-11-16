<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\DepartmentIndexRequest;
use App\Http\Requests\System\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    /**
     * 部门树（不分页）；有过滤条件时，匹配节点的父节点不在结果中则作为根节点。
     */
    public function index(DepartmentIndexRequest $request): AnonymousResourceCollection
    {
        $departments = Department::query()
            ->with('leader')
            ->filter($request->validated())
            ->ordered()
            ->get();

        return DepartmentResource::collection($departments->toTree());
    }

    public function store(DepartmentRequest $request): DepartmentResource
    {
        $department = Department::query()->create($request->validated());

        return new DepartmentResource($department->load('leader'));
    }

    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department->load('leader'));
    }

    public function update(DepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department->load('leader'));
    }

    public function destroy(Department $department): Response
    {
        Gate::authorize('delete', $department);

        $department->delete();

        return response()->noContent();
    }
}
