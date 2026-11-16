<?php

namespace App\Actions\System;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 启用 / 禁用用户；禁用时吊销其全部 token。
 */
class UpdateUserStatus
{
    public function handle(User $user, Status $status): User
    {
        DB::transaction(function () use ($user, $status): void {
            $user->update(['status' => $status]);

            if (! $user->isActive()) {
                $user->tokens()->delete();
            }
        });

        return $user;
    }
}
