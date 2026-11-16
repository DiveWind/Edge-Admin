<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * value 按 type 输出：string → 字符串，number → 数字，boolean → 布尔，json → 对象 / 数组。
 *
 * @mixin Setting
 */
class SettingResource extends JsonResource
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
            'group' => $this->group,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'value' => $this->value,
            'is_public' => $this->is_public,
            'is_system' => $this->is_system,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
