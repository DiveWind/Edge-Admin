<?php

namespace App\Http\Admin\System\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 重置用户的密码（PUT /system/users/{user}/password，设计文档 §6.5、§7.4）：`{ password }`，规则同修改密码（Password::defaults()）。
 */
class UserPasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => __('Password')];
    }

    /**
     * 提交的新密码（已校验）。
     */
    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
