<?php

namespace App\Policies;

use App\Models\{Role, User};
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function delete(User $actor, Role $role): Response
    {
        if ($role->isSuperAdmin()) {
            return Response::deny(__('policy.role.super_admin'));
        }

        if ($role->users()->exists()) {
            return Response::deny(__('policy.role.has_users'));
        }

        return Response::allow();
    }
}
