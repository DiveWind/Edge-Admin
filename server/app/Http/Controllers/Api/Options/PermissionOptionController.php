<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Services\PermissionRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 角色授权树：全部生效节点（停用节点及其后代不返回，隐藏节点照常返回），登录即可访问。
 */
class PermissionOptionController extends Controller
{
    public function __invoke(PermissionRegistry $permissions): AnonymousResourceCollection
    {
        return PermissionResource::collection($permissions->enabledTree());
    }
}
