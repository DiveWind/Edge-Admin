<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 修改角色（PUT /system/roles/{role}，设计文档 §6.5、§8.5、§8.6）：
 *
 * - super-admin 角色：标识不能改 → 422（name）；不能停用 → 422（status）；权限隐含全部，不能提交 permissions → 422（permissions）；
 * - 标识唯一 → 422（name）；
 * - 提交了 permissions 时按 SyncRolePermissions 的规则保存（防提权、保留操作者管不到的授权、补齐祖先）；未提交时权限不变。
 */
class UpdateRole
{
    public function __construct(private readonly SyncRolePermissions $syncPermissions) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（CreateRole::ATTRIBUTES，未提交的保持不变）
     * @param  list<string>|null  $permissions  权限码；null 表示不修改
     *
     * @throws ValidationException
     */
    public function handle(Role $role, array $attributes, ?array $permissions, User $actor): Role
    {
        return DB::transaction(function () use ($role, $attributes, $permissions, $actor): Role {
            $isSuperAdmin = $role->isSuperAdmin();

            $role->fill(CreateRole::only($attributes));

            if ($isSuperAdmin) {
                $this->protectSuperAdmin($role, $permissions);
            }

            CreateRole::save($role);

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
