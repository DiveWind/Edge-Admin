<?php

namespace App\Actions\System;

use App\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 编辑角色并同步权限与自定义数据权限部门。
 */
class UpdateRole
{
    /**
     * @param  array<string, mixed>  $data  已校验数据，字段见 RoleRequest（permission_ids / department_ids 为 id 数组）
     */
    public function handle(Role $role, array $data): Role
    {
        DB::transaction(function () use ($role, $data): void {
            $role->update(Arr::except($data, ['permission_ids', 'department_ids']));
            $role->syncAuthorizations(Arr::wrap($data['permission_ids'] ?? []), Arr::wrap($data['department_ids'] ?? []));
        });

        return $role;
    }
}
