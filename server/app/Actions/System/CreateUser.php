<?php

namespace App\Actions\System;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 新增用户并同步角色、岗位。
 */
class CreateUser
{
    /**
     * @param  array<string, mixed>  $data  已校验数据，字段见 StoreUserRequest（role_ids / position_ids 为 id 数组）
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create(Arr::except($data, ['role_ids', 'position_ids']));
            $user->roles()->sync($data['role_ids']);
            $user->positions()->sync($data['position_ids']);

            return $user;
        });
    }
}
