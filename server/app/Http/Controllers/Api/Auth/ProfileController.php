<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * 当前登录用户（/api/auth/me）与个人资料。
 */
class ProfileController extends Controller
{
    public function show(#[CurrentUser] User $user): CurrentUserResource
    {
        return new CurrentUserResource($user);
    }

    public function update(UpdateProfileRequest $request, #[CurrentUser] User $user): CurrentUserResource
    {
        $user->update($request->validated());

        return new CurrentUserResource($user);
    }
}
