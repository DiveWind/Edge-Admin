<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use App\Support\Database\UniqueConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * 新增用户（POST /system/users，设计文档 §6.5、§8.5、§8.6）：保存字段，再按 SyncUserRoles 的规则分配角色。
 *
 * - 可写入的字段为 User 的 `#[Fillable]`，其余键忽略；用户名统一存为小写（User::normalizeUsername()）；
 * - 用户名、邮箱唯一：表单已校验，并发冲突时同样返回 422（username / email，UniqueConstraint）；
 * - created_by 写入操作者（“仅本人”数据范围据此判断，§8.4）；
 * - 未提交 status 时取列默认值 enabled。
 */
class CreateUser
{
    public function __construct(private readonly SyncUserRoles $syncRoles) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段
     * @param  list<string>  $roles  角色标识
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, #[SensitiveParameter] string $password, array $roles, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $password, $roles, $actor): User {
            $user = new User;
            $user->fill(array_intersect_key($attributes, array_flip($user->getFillable())));
            $user->forceFill(['password' => $password, 'created_by' => $actor->getKey()]);

            if ($user->isDirty('username')) {
                $user->username = User::normalizeUsername($user->username);
            }

            UniqueConstraint::save($user, ['username' => __('Username'), 'email' => __('Email')]);

            $this->syncRoles->handle($user, $roles, $actor);

            return $user->refresh();
        });
    }
}
