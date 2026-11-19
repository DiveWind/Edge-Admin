<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户的输出格式（Admin 入口，设计文档 §6.5）。
 *
 * - roles：已分配的角色 `[{ name: 标识, title: 显示名 }]`（含停用的角色），按 sort、id 排序；列表、详情与写接口的响应都输出
 *   （加载了 roles 关联时）。前端列表直接显示角色名，不依赖角色下拉接口的权限；写接口提交的 roles 是标识 `string[]`；
 * - 不输出密码、两步验证密钥等敏感字段；时间为带时区偏移的 ISO 8601。
 *
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
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->map(fn (Model $role): array => $role->only(['name', 'title']))
                ->values()
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
