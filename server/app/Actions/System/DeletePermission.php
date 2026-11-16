<?php

namespace App\Actions\System;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * 删除自定义菜单权限节点，并解除它与角色的授权关联。
 */
class DeletePermission
{
    public function handle(Permission $permission): void
    {
        DB::transaction(function () use ($permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
}
