<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\UpdateUserStatus;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class UserStatusController extends Controller
{
    /**
     * 启用 / 禁用用户；禁用时吊销其全部 token。
     */
    #[Authorize('system.user.update')]
    public function update(UpdateUserStatusRequest $request, User $user, UpdateUserStatus $updateUserStatus): UserResource
    {
        $updateUserStatus->handle($user, $request->enum('status', Status::class) ?? Status::Active);

        return new UserResource($user->load(['department', 'roles', 'positions']));
    }
}
