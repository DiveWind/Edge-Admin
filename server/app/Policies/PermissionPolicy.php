<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    /**
     * 内置节点与有子节点的节点不可删除；被角色引用的节点删除时一并解除授权（见 DeletePermission）。
     */
    public function delete(User $actor, Permission $permission): Response
    {
        if ($permission->is_system) {
            return Response::deny(__('policy.permission.system'));
        }

        if ($permission->children()->exists()) {
            return Response::deny(__('policy.permission.has_children'));
        }

        return Response::allow();
    }
}
