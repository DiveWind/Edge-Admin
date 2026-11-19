<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\DictType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 字典类型的输出格式（Admin 入口，设计文档 §6.7）。
 *
 * - is_builtin：模块声明的类型（不能删除，code 不能修改）；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin DictType
 */
class DictTypeResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'remark' => $this->remark,
            'is_builtin' => $this->is_builtin,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
