<?php

namespace App\Actions\System;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * 管理员重置密码，并吊销该用户已有 token。
 */
class ResetUserPassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->update(['password' => $password]);
            $user->tokens()->delete();
        });
    }
}
