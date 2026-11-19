<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 启用 / 停用角色（PUT /system/roles/{role}/status，设计文档 §6.5）：`{ status }`。
 * 停用 super-admin 的 422 由 System 模块的 ChangeRoleStatus 返回。
 */
class RoleStatusRequest extends FormRequest
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
