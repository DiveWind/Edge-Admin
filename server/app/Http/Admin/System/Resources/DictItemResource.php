<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\DictItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 字典项的输出格式（Admin 入口，设计文档 §6.7）。
 *
 * - is_builtin：模块声明的字典项（不能删除，value 不能修改）；color 没有设置时为 null；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin DictItem
 */
class DictItemResource extends JsonResource
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
            'dict_type_id' => $this->dict_type_id,
            'value' => $this->value,
            'label' => $this->label,
            'color' => $this->color,
            'sort' => $this->sort,
            'status' => $this->status,
            'is_builtin' => $this->is_builtin,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
