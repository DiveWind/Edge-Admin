<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Core\System\Models\DictType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增、修改字典类型（POST /system/dict-types、PUT /system/dict-types/{dict_type}，设计文档 §6.7）。
 *
 * 只做输入格式校验：code（小写点分的标识，最长 64，唯一）与 name 必填；status、remark 可选（修改时未提交表示不变）。
 * 内置类型的 code 不能修改由 System 模块的 UpdateDictType 返回 422。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class DictTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('dict_type');

        return [
            'code' => ['required', 'string', 'max:'.DictType::CODE_MAX_LENGTH, 'regex:'.DictType::CODE_PATTERN, Rule::unique(DictType::class, 'code')->ignore($type instanceof DictType ? $type : null)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
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
            'code' => __('Code'),
            'name' => __('Dictionary name'),
            'status' => __('Status'),
            'remark' => __('Remark'),
        ];
    }
}
