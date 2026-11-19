<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Data\SettingValueData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 系统参数的输出格式（Admin 入口，设计文档 §6.7）：一个已声明参数的当前状态（SettingValueData）。
 *
 * - name、remark：前端 i18n key 或纯文本（后端不翻译）；type：SettingType 的值；
 * - value：当前生效的值（改过的值，否则为默认值）；default：声明的默认值；is_customized：库中是否有管理员改过的值；
 * - is_public：是否出现在免登录的公开参数接口中；
 * - dict：引用的字典类型（没有时为 null），前端据此调用 /options/dicts 渲染下拉。
 *
 * @property-read SettingValueData $resource
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
        $setting = $this->resource->setting;

        return [
            'key' => $setting->key,
            'name' => $setting->title,
            'remark' => $setting->remark,
            'type' => $setting->type,
            'value' => $this->resource->value,
            'default' => $this->resource->default,
            'is_customized' => $this->resource->customized,
            'is_public' => $setting->public,
            'dict' => $setting->dict,
        ];
    }
}
