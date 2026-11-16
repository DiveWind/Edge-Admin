<?php

namespace App\Actions\System;

use App\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 新增角色并同步权限与自定义数据权限部门。
 */
class CreateRole
{
    /**
     * @param  array<string, mixed>  $data  已校验数据，字段见 RoleRequest（permission_ids / department_ids 为 id 数组）
     */
    public function handle(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create(Arr::except($data, ['permission_ids', 'department_ids']));
            $role->syncAuthorizations(Arr::wrap($data['permission_ids'] ?? []), Arr::wrap($data['department_ids'] ?? []));

            return $role;
        });
    }
}
