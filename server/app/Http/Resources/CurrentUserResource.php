<?php

namespace App\Http\Resources;

use App\Models\{Position, Role, User};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 当前登录用户（/api/auth/me）。
 *
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['department', 'roles', 'positions']);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'is_super_admin' => $this->isSuperAdmin(),
            'department' => $this->department?->only('id', 'name'),
            'roles' => $this->roles->map(fn (Role $role): array => $role->only('id', 'name', 'code')),
            'positions' => $this->positions->map(fn (Position $position): array => $position->only('id', 'name', 'code')),
            'permissions' => $this->permissionCodes(),
            'data_scope' => $this->dataScope(),
            'unread_notifications_count' => $this->unreadNotifications()->count(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
