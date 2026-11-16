<?php

namespace App\Actions\System;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 编辑用户资料并同步角色、岗位。
 */
class UpdateUser
{
    /**
     * @param  array<string, mixed>  $data  已校验数据，字段见 UpdateUserRequest（role_ids / position_ids 为 id 数组）
     */
    public function handle(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data): void {
            $user->update(Arr::except($data, ['role_ids', 'position_ids']));
            $user->roles()->sync($data['role_ids']);
            $user->positions()->sync($data['position_ids']);
        });

        return $user;
    }
}
