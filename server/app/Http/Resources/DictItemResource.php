<?php

namespace App\Http\Resources;

use App\Models\DictItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'label' => $this->label,
            'value' => $this->value,
            'color' => $this->color,
            'sort' => $this->sort,
            'status' => $this->status,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
