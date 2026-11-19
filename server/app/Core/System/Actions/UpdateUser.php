<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 修改用户（PUT /system/users/{user}，设计文档 §6.5、§8.5、§8.6）：字段同新增，不含密码（重置密码是单独的接口）。
 *
 * - 用户名转为小写，用户名、邮箱唯一 → 422（username / email，见 CreateUser::save()）；
 * - 提交了 roles 时按 SyncUserRoles 的规则保存（防提权、保留操作者管不到的角色、最后一个超管不能去掉超管角色）；未提交时角色不变；
 * - 提交的 status 与当前不同时交给 ChangeUserStatus（不能停用自己、最后一个启用的超管不能停用、停用即吊销会话与 token）；
 * - 以上在同一个事务中，任何一步 422 都整体回滚。
 */
class UpdateUser
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
        private readonly ChangeUserStatus $changeStatus,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（CreateUser::ATTRIBUTES，未提交的保持不变）
     * @param  list<string>|null  $roles  角色标识；null 表示不修改
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $attributes, ?array $roles, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $roles, $actor): User {
            $attributes = CreateUser::only($attributes);
            $status = $attributes['status'] ?? null;
            unset($attributes['status']);

            $user->fill($attributes);
            CreateUser::save($user);

            if ($roles !== null) {
                $this->syncRoles->handle($user, $roles, $actor);
            }

            $status = is_string($status) ? Status::tryFrom($status) : $status;

            if ($status instanceof Status && $status !== $user->status) {
                $this->changeStatus->handle($user, $status, $actor);
            }

            return $user->refresh();
        });
    }
}
