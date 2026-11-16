<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Http\Resources\PermissionResource;
use App\Models\ArticleCategory;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 表单下拉选项，仅返回启用项，登录即可访问。
 */
class OptionController extends Controller
{
    public function departments(): AnonymousResourceCollection
    {
        $departments = Department::query()->active()->ordered()->get(['id', 'name', 'parent_id']);

        return OptionResource::collection($departments->toTree());
    }

    /**
     * 权限树（与 GET /api/permissions 相同），供角色授权表单使用。
     */
    public function permissions(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('sort')->orderBy('id')->get()->toTree());
    }

    public function roles(): AnonymousResourceCollection
    {
        return OptionResource::collection(Role::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'code']));
    }

    public function positions(): AnonymousResourceCollection
    {
        return OptionResource::collection(Position::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'code']));
    }

    public function articleCategories(): AnonymousResourceCollection
    {
        return OptionResource::collection(ArticleCategory::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name']));
    }

    public function users(): AnonymousResourceCollection
    {
        return OptionResource::collection(User::query()->active()->orderBy('id')->get(['id', 'name']));
    }
}
