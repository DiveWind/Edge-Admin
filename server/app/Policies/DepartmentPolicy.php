<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepartmentPolicy
{
    public function delete(User $actor, Department $department): Response
    {
        if ($department->children()->exists()) {
            return Response::deny(__('policy.department.has_children'));
        }

        if ($department->users()->exists()) {
            return Response::deny(__('policy.department.has_users'));
        }

        return Response::allow();
    }
}
