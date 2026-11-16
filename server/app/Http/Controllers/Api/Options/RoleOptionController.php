<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 启用角色（下拉选项，登录即可访问）。
 */
class RoleOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return OptionResource::collection(Role::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'code']));
    }
}
