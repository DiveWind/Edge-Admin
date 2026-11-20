<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 授权树的一个节点（GET /system/roles/permission-tree，设计文档 §6.5）：`{ name, type, meta: { title, icon }, children }`。
 *
 * - name：权限码（角色的 permissions 提交它）；type：节点类型（catalog / menu / embedded / link / button）；
 * - meta.title：前端 i18n key（或纯文本），由前端翻译；meta.icon：可为 null；
 * - children：子节点（叶子为空数组）。节点的选取与排序由 EffectivePermissions::enabledTree() 完成。
 *
 * @mixin Permission
 */
class PermissionTreeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'meta' => [
                'title' => $this->title,
                'icon' => $this->icon,
            ],
            'children' => self::collection($this->relationLoaded('children') ? $this->children : []),
        ];
    }
}
