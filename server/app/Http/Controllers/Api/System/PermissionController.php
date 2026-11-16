<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    /**
     * 权限树（只读，权限点由 config/permissions.php 声明）。
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $permissions = Permission::query()->orderBy('sort')->orderBy('id')->get();

        return PermissionResource::collection($permissions->toTree());
    }
}
