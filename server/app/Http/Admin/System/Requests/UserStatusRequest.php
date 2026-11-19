<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 启用 / 停用用户（PUT /system/users/{user}/status，设计文档 §6.5）：`{ status }`。
 * 最后一个启用的超管不能停用（422）由 System 模块的 ChangeUserStatus 返回。
 */
class UserStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(Status::class)],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('Status')];
    }

    /**
     * 提交的状态（已校验）。
     */
    public function status(): Status
    {
        return Status::from($this->string('status')->toString());
    }
}
