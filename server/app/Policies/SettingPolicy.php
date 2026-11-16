<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SettingPolicy
{
    public function delete(User $actor, Setting $setting): Response
    {
        return $setting->is_system
            ? Response::deny(__('policy.setting.system'))
            : Response::allow();
    }
}
