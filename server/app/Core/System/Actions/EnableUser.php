<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;

/**
 * 启用用户（设计文档 §6.5）：恢复登录。与 DisableUser 对应，由 ChangeUserStatus 调用。
 */
class EnableUser
{
    /**
     * Execute the action.
     */
    public function handle(User $user): void
    {
        $user->forceFill(['status' => Status::Enabled])->save();
    }
}
