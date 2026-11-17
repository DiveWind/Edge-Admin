<?php

namespace App\Http\Resources;

use App\Models\{Department, Position, Role, User};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (Department $department): array => $department->only('id', 'name')),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn (Role $role): array => $role->only('id', 'name', 'code'))),
            'positions' => $this->whenLoaded('positions', fn () => $this->positions->map(fn (Position $position): array => $position->only('id', 'name', 'code'))),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
