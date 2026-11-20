<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\Manageability;
use App\Support\Database\UniqueConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 修改角色（PUT /system/roles/{role}，设计文档 §6.5、§8.5、§8.6）：
 *
 * - super-admin 角色：标识不能改 → 422（name）；不能停用 → 422（status）；权限隐含全部，不能提交 permissions → 422（permissions）；
 * - 可写入的字段同新增（Role 的 `#[Fillable]`，guard_name 除外）；标识唯一 → 422（name，UniqueConstraint，含并发冲突）；
 * - 数据范围防提权：非超管把 data_scope 改为自己不可授予的范围 → 422（data_scope）；未改动时不检查（Manageability::ensureGrantableDataScope()）；
 *   非超管只能修改“管得到”的角色（RolePolicy，403，§8.5），因此 status 随 fill 写入不会被用来启用管不到的角色；
 * - 提交了 permissions 时按 SyncRolePermissions 的规则保存（防提权、保留操作者管不到的授权、补齐祖先）；未提交时权限不变。
 */
class UpdateRole
{
    public function __construct(
        private readonly SyncRolePermissions $syncPermissions,
        private readonly Manageability $manageability,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（未提交的保持不变）
     * @param  list<string>|null  $permissions  权限码；null 表示不修改
     *
     * @throws ValidationException
     */
    public function handle(Role $role, array $attributes, ?array $permissions, User $actor): Role
    {
        return DB::transaction(function () use ($role, $attributes, $permissions, $actor): Role {
            $isSuperAdmin = $role->isSuperAdmin();

            $role->fill(array_intersect_key($attributes, array_flip(array_diff($role->getFillable(), ['guard_name']))));

            if ($isSuperAdmin) {
                $this->protectSuperAdmin($role, $permissions);
            }

            $this->manageability->ensureGrantableDataScope($role, $actor);
            UniqueConstraint::save($role, ['name' => __('Identifier')]);

            if ($permissions !== null) {
                $this->syncPermissions->handle($role, $permissions, $actor);
            }

            return $role->refresh();
        });
    }

    /**
     * super-admin 角色的保护（§8.6）。
     *
     * @param  list<string>|null  $permissions
     *
     * @throws ValidationException
     */
    private function protectSuperAdmin(Role $role, ?array $permissions): void
    {
        $errors = [];

        if ($role->isDirty('name')) {
            $errors['name'] = [__('The identifier of the super admin role cannot be changed.')];
        }

        if ($role->status !== Status::Enabled) {
            $errors['status'] = [__('The super admin role cannot be disabled.')];
        }

        if ($permissions !== null) {
            $errors['permissions'] = [__('The super admin role has all permissions; they cannot be assigned.')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
