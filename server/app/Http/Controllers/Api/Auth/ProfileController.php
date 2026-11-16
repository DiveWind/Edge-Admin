<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, #[CurrentUser] User $user): CurrentUserResource
    {
        $user->update($request->validated());

        return new CurrentUserResource($user);
    }

    /**
     * 修改密码，并吊销除当前 token 之外的其他 token。
     */
    public function password(UpdatePasswordRequest $request, #[CurrentUser] User $user): Response
    {
        $user->update(['password' => $request->validated('password')]);

        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }
}
