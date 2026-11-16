<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 用户修改自己的密码，并吊销除当前 token 之外的其他 token（其他设备下线）。
 */
class ChangePassword
{
    public function handle(User $user, string $password, int|string|null $currentTokenId): void
    {
        DB::transaction(function () use ($user, $password, $currentTokenId): void {
            $user->update(['password' => $password]);

            $user->tokens()
                ->when($currentTokenId !== null, fn ($query) => $query->whereKeyNot($currentTokenId))
                ->delete();
        });
    }
}
