<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Role, User};
use App\Core\System\Services\Manageability;
use App\Support\Database\UniqueConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增角色（POST /system/roles，设计文档 §6.5、§8.5）：保存字段，再按 SyncRolePermissions 的规则保存权限码。
 *
 * - 可写入的字段为 Role 的 `#[Fillable]`（guard_name 除外，固定为 web），其余键忽略；
 * - 标识（name）唯一：表单已校验，并发冲突时同样返回 422（name，UniqueConstraint）；
 * - 数据范围防提权：非超管提交的 data_scope 不在自己可授予的范围内 → 422（data_scope，Manageability::ensureGrantableDataScope()，§8.5）；
 * - 未提交的字段取列默认值：status 为 enabled，data_scope 为 self，sort 为 0。
 */
class CreateRole
{
    public function __construct(
        private readonly SyncRolePermissions $syncPermissions,
        private readonly Manageability $manageability,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段
     * @param  list<string>  $permissions  权限码
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, array $permissions, User $actor): Role
    {
        return DB::transaction(function () use ($attributes, $permissions, $actor): Role {
            // 显式指定 guard：spatie 默认取当前请求的 guard（auth:sanctum 下为 sanctum）
            $role = new Role(['guard_name' => User::GUARD]);
            $role->fill(array_intersect_key($attributes, array_flip(array_diff($role->getFillable(), ['guard_name']))));

            $this->manageability->ensureGrantableDataScope($role, $actor);
            UniqueConstraint::save($role, ['name' => __('Identifier')]);

            $this->syncPermissions->handle($role, $permissions, $actor);

            return $role->refresh();
        });
    }
}
