<?php

namespace App\Services;

use App\Enums\DataScope;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;

/**
 * 解析用户的可见数据范围：多角色取并集，超级管理员恒为全部。
 */
class DataScopeResolver
{
    public function resolve(User $user): DataVisibility
    {
        if ($user->isSuperAdmin()) {
            return DataVisibility::everything();
        }

        $user->loadMissing('roles.departments');

        $departmentIds = [];
        $self = false;

        foreach ($user->activeRoles() as $role) {
            /** @var Role $role */
            switch ($role->data_scope) {
                case DataScope::All:
                    return DataVisibility::everything();
                case DataScope::Custom:
                    array_push($departmentIds, ...$role->departments->map(fn (Department $department): int => $department->id));
                    break;
                case DataScope::DepartmentAndChildren:
                    if ($user->department_id !== null) {
                        array_push($departmentIds, ...Department::descendantAndSelfIds($user->department_id));
                    }
                    break;
                case DataScope::Department:
                    if ($user->department_id !== null) {
                        $departmentIds[] = $user->department_id;
                    }
                    break;
                case DataScope::Self:
                    $self = true;
                    break;
            }
        }

        return new DataVisibility(
            all: false,
            departmentIds: array_values(array_unique($departmentIds)),
            self: $self,
        );
    }
}
