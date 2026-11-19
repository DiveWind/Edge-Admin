<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Data\{SettingGroupData, SettingValueData};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 系统参数分组的输出格式（Admin 入口，设计文档 §6.7）：`{ group, title, settings: [...] }`。
 * 参数的当前状态由控制器通过 Settings 服务取得后传入（Resource 不做查询）。
 *
 * @property-read SettingGroupData $resource
 */
class SettingGroupResource extends JsonResource
{
    /**
     * @param  list<SettingValueData>  $entries  本组参数的当前状态（按声明顺序）
     */
    public function __construct(SettingGroupData $group, private readonly array $entries)
    {
        parent::__construct($group);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'group' => $this->resource->key,
            'title' => $this->resource->title,
            'settings' => SettingResource::collection($this->entries),
        ];
    }
}
