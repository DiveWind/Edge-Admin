<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 下拉选项：{id, name}，带 code 的模型额外输出 code，树形选项带 parent_id / children。
 *
 * @property Model $resource
 */
class OptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->getAttribute('name'),
            'code' => $this->when(array_key_exists('code', $attributes), fn () => $this->resource->getAttribute('code')),
            'parent_id' => $this->when(array_key_exists('parent_id', $attributes), fn () => $this->resource->getAttribute('parent_id')),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
