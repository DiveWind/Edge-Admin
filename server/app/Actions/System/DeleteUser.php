<?php

namespace App\Actions\System;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 软删除用户并吊销其全部 token。
 */
class DeleteUser
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
