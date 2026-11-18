<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `/menu/all` 中的一个节点，结构与 Vben 的路由一致（设计文档 §6.2）。节点的选取、剔除与排序由 PermissionRegistry::menuTreeFor() 完成：
 *
 * - name：权限码（Vben 的路由 name）；path：路由路径；
 * - component：目录不输出；菜单为相对 src/views 的路径；内嵌页与外链为 IFrameView（PermissionType::routeComponent()）；
 * - meta：title（i18n key，前端翻译）、icon（有时）、order，以及节点 meta 列中的白名单键（含 iframeSrc、link、hideInMenu 等）；
 * - children：有子节点时输出；不输出 redirect（Vben 自动重定向到第一个子节点）。
 *
 * @mixin Permission
 */
class MenuRouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $component = $this->type->routeComponent($this->component);
        $children = $this->relationLoaded('children') ? $this->children : null;

        return [
            'name' => $this->name,
            'path' => $this->path,
            ...($component === null ? [] : ['component' => $component]),
            'meta' => [
                ...array_intersect_key($this->meta ?? [], array_flip(Permission::META_KEYS)),
                'title' => $this->title,
                ...($this->icon === null ? [] : ['icon' => $this->icon]),
                'order' => $this->sort,
            ],
            ...($children === null || $children->isEmpty() ? [] : ['children' => self::collection($children)]),
        ];
    }
}
