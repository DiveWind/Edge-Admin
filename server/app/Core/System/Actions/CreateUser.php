<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * 新增用户（POST /system/users，设计文档 §6.5、§8.5、§8.6）：保存字段，再按 SyncUserRoles 的规则分配角色。
 *
 * - 用户名统一存为小写（与 Fortify 登录时的 lowercase_usernames 一致）；
 * - 用户名、邮箱唯一：表单已校验，并发冲突时同样返回 422（username / email）；
 * - created_by 写入操作者（“仅本人”数据范围据此判断，§8.4）；
 * - 未提交 status 时取列默认值 enabled。
 */
class CreateUser
{
    /**
     * 新增、修改时可写入的字段（其余键忽略；密码只在新增与重置密码时写入）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['username', 'name', 'email', 'status'];

    public function __construct(private readonly SyncUserRoles $syncRoles) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（ATTRIBUTES）
     * @param  list<string>  $roles  角色标识
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, #[SensitiveParameter] string $password, array $roles, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $password, $roles, $actor): User {
            $user = new User;
            $user->fill(self::only($attributes));
            $user->forceFill(['password' => $password, 'created_by' => $actor->getKey()]);

            self::save($user);

            $this->syncRoles->handle($user, $roles, $actor);

            return $user->refresh();
        });
    }

    /**
     * 只保留可写入的字段；用户名转为小写。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function only(array $attributes): array
    {
        $attributes = array_intersect_key($attributes, array_flip(self::ATTRIBUTES));

        if (isset($attributes['username']) && is_string($attributes['username'])) {
            $attributes['username'] = Str::lower($attributes['username']);
        }

        return $attributes;
    }

    /**
     * 保存用户；用户名或邮箱重复（并发写入时数据库的唯一索引）返回 422（username / email）。
     *
     * 保存包在嵌套事务（保存点）中：PostgreSQL 在语句失败后会中止整个事务，回滚到保存点后才能再查询是哪个字段重复。
     *
     * @throws ValidationException
     */
    public static function save(User $user): void
    {
        try {
            DB::transaction(fn () => $user->save());
        } catch (UniqueConstraintViolationException) {
            $usernameTaken = User::query()
                ->where('username', $user->username)
                ->when($user->exists, fn (Builder $query) => $query->whereKeyNot($user->getKey()))
                ->exists();

            throw ValidationException::withMessages($usernameTaken
                ? ['username' => [__('validation.unique', ['attribute' => __('Username')])]]
                : ['email' => [__('validation.unique', ['attribute' => __('Email')])]]);
        }
    }
}
