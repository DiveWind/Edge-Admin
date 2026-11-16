<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 当前用户的侧边菜单节点（GET /api/auth/menus）。
 *
 * @mixin Permission
 */
class MenuResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'path' => $this->path,
            'icon' => $this->icon,
            'is_external' => $this->is_external,
            'sort' => $this->sort,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
