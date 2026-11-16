<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 当前用户可见的菜单树：启用、可见且已授权的目录 / 菜单（没有可见子菜单的目录被剔除）。
 */
class MenuController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, PermissionRegistry $permissions): AnonymousResourceCollection
    {
        return MenuResource::collection($permissions->menuTreeFor($user));
    }
}
