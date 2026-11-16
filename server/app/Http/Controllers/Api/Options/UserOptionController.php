<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 启用用户（下拉选项，登录即可访问）。
 */
class UserOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return OptionResource::collection(User::query()->active()->orderBy('id')->get(['id', 'name']));
    }
}
