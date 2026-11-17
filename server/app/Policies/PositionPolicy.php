<?php

namespace App\Policies;

use App\Models\{Position, User};
use Illuminate\Auth\Access\Response;

class PositionPolicy
{
    public function delete(User $actor, Position $position): Response
    {
        return $position->users()->exists()
            ? Response::deny(__('policy.position.has_users'))
            : Response::allow();
    }
}
