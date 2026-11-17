<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\ResetUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class UserPasswordController extends Controller
{
    /**
     * 管理员重置密码，并吊销该用户已有 token。
     */
    #[Authorize('system.user.reset-password')]
    #[Authorize('resetPassword', 'user')]
    public function update(ResetUserPasswordRequest $request, User $user, ResetUserPassword $resetUserPassword): Response
    {
        $resetUserPassword->handle($user, $request->string('password')->toString());

        return response()->noContent();
    }
}
