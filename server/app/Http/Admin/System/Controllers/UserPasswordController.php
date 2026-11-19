<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\ResetUserPassword;
use App\Core\System\Models\User;
use App\Http\Admin\System\Requests\UserPasswordRequest;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 重置用户的密码（PUT /system/users/{user}/password，设计文档 §6.5、§7.4）：`{ password }`，成功后对方全部的会话与 token 失效。
 * 重置自己的密码、非超管重置超管的密码 403（UserPolicy）。响应 data 为 null。
 */
class UserPasswordController
{
    /**
     * Handle the incoming request.
     */
    #[Authorize('resetPassword', 'user')]
    public function __invoke(UserPasswordRequest $request, User $user, ResetUserPassword $action): void
    {
        $action->handle($user, $request->password());
    }
}
