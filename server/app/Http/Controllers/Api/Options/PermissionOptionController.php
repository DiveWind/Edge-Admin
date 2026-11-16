<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 权限树（与 GET /api/permissions 相同），供角色授权表单使用，登录即可访问。
 */
class PermissionOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('sort')->orderBy('id')->get()->toTree());
    }
}
