<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'data_scope' => $this->data_scope,
            'sort' => $this->sort,
            'status' => $this->status,
            'remark' => $this->remark,
            'is_super_admin' => $this->isSuperAdmin(),
            'users_count' => $this->whenCounted('users'),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->modelKeys()),
            'department_ids' => $this->whenLoaded('departments', fn () => $this->departments->modelKeys()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
