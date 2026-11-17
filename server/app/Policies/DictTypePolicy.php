<?php

namespace App\Policies;

use App\Models\{DictType, User};
use Illuminate\Auth\Access\Response;

class DictTypePolicy
{
    public function delete(User $actor, DictType $dictType): Response
    {
        return $dictType->items()->exists()
            ? Response::deny(__('policy.dict_type.has_items'))
            : Response::allow();
    }
}
