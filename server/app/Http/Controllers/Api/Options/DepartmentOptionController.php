<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\Department;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 启用部门树（下拉选项，登录即可访问）。
 */
class DepartmentOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $departments = Department::query()->active()->ordered()->get(['id', 'name', 'parent_id']);

        return OptionResource::collection($departments->toTree());
    }
}
