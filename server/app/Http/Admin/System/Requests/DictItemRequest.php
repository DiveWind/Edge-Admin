<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{DictItem, DictType};
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增、修改字典项（POST /system/dict-types/{dict_type}/items、PUT /system/dict-items/{dict_item}，设计文档 §6.7）。
 *
 * 只做输入格式校验：value（字符串，最长 64，同一类型内唯一）与 label 必填；color（antd 预设色名或 #RGB / #RRGGBB，可为 null）、
 * sort、status 可选（修改时未提交表示不变）。所属类型由路径决定，不能修改。
 * 内置字典项的 value 不能修改由 System 模块的 UpdateDictItem 返回 422。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class DictItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('dict_item');
        $type = $this->route('dict_type');
        $typeId = $item instanceof DictItem ? $item->dict_type_id : ($type instanceof DictType ? $type->id : null);

        return [
            'value' => ['required', 'string', 'max:'.DictItem::VALUE_MAX_LENGTH, Rule::unique(DictItem::class, 'value')->where('dict_type_id', $typeId)->ignore($item instanceof DictItem ? $item : null)],
            'label' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:'.DictItem::COLOR_PATTERN],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['sometimes', Rule::enum(Status::class)],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'value' => __('Value'),
            'label' => __('Label'),
            'color' => __('Color'),
            'sort' => __('Sort order'),
            'status' => __('Status'),
        ];
    }
}
