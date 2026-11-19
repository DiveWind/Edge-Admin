<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 角色的输出格式（Admin 入口，设计文档 §6.5）。
 *
 * - is_super_admin：是否为 super-admin 角色（前端据此隐藏授权树、禁用删除与停用）；
 * - permissions：已授予的权限码（string[]），只在详情与写接口的响应中输出（加载了 permissions 关联时）；
 * - 时间为带时区偏移的 ISO 8601。
 *
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
            'title' => $this->title,
            'status' => $this->status,
            'data_scope' => $this->data_scope,
            'remark' => $this->remark,
            'sort' => $this->sort,
            'is_super_admin' => $this->isSuperAdmin(),
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->permissions->sortBy('id')->pluck('name')->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
