<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 菜单管理中的一个节点（/system/menus，设计文档 §6.5）。与 `/menu/all`（MenuRouteResource）不同，这里输出节点的原始数据，供管理页编辑：
 *
 * - 顶层：id、parent_id（根为 null）、type、name（权限码）、path（按钮为 null）、component（只有 menu 有值，其余为 null；
 *   不换成 IFrameView）、status、is_builtin；
 * - meta：title、icon（可为 null）、order（即 sort 列），以及 meta 列中的白名单键（Permission::META_KEYS，没有的键不出现）；
 * - children：列表（整棵树）中每个节点都有，叶子为空数组，同级按 sort、id 排序（PermissionTree）；
 *   详情与写接口的响应不含 children。
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
            'type' => $this->type,
            'name' => $this->name,
            'path' => $this->path,
            'component' => $this->component,
            'status' => $this->status,
            'is_builtin' => $this->is_builtin,
            'meta' => [
                'title' => $this->title,
                'icon' => $this->icon,
                'order' => $this->sort,
                ...array_intersect_key($this->meta ?? [], array_flip(Permission::META_KEYS)),
            ],
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children)),
        ];
    }
}
