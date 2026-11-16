<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    /**
     * 修改密码，并吊销除当前 token 之外的其他 token。
     */
    public function update(UpdatePasswordRequest $request, #[CurrentUser] User $user, ChangePassword $changePassword): Response
    {
        $changePassword->handle($user, $request->string('password')->toString(), $user->currentAccessToken()->getKey());

        return response()->noContent();
    }
}
