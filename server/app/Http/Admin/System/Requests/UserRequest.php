<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 新增、修改用户（POST /system/users、PUT /system/users/{user}，设计文档 §6.5、§8.5）。
 *
 * 只做输入格式校验：username（alpha_dash，最长 64，转为小写后唯一）与 name 必填；email 可空、唯一；
 * password 只在新增时提交且必填（规则同修改密码：Password::defaults()），修改时提交 → 422（未声明的字段）；
 * roles 为已存在角色的标识数组（新增时可省略，修改时未提交表示角色不变）；status 可选（新增默认 enabled）。
 * 最后一个超管、防提权、不能停用自己等业务规则在 System 模块的 Action 中（CreateUser、UpdateUser）。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignore = $user instanceof User ? $user : null;

        return [
            'username' => ['required', 'string', 'max:64', 'alpha_dash:ascii', Rule::unique(User::class, 'username')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($ignore)],
            ...($ignore instanceof User ? [] : ['password' => ['required', 'string', Password::defaults()]]),
            'status' => ['sometimes', Rule::enum(Status::class)],
            'roles' => ['sometimes', 'array', 'list'],
            'roles.*' => ['string', 'distinct', Rule::exists(Role::class, 'name')->where('guard_name', User::GUARD)],
        ];
    }

    /**
     * 用户名转为小写后再校验唯一（与登录时 Fortify 的 lowercase_usernames 一致）。
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower($this->string('username')->toString())]);
        }
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => __('Username'),
            'name' => __('Name'),
            'email' => __('Email'),
            'password' => __('Password'),
            'status' => __('Status'),
            'roles' => __('Roles'),
            'roles.*' => __('Roles'),
        ];
    }

    /**
     * 用户的字段（不含 password、roles），交给 CreateUser / UpdateUser。
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return array_diff_key($this->validated(), ['password' => true, 'roles' => true]);
    }

    /**
     * 新增时提交的密码（已校验）。
     */
    public function password(): string
    {
        return $this->string('password')->toString();
    }

    /**
     * 提交的角色标识；未提交时为 null。
     *
     * @return list<string>|null
     */
    public function roles(): ?array
    {
        if (! $this->has('roles')) {
            return null;
        }

        return array_values(array_filter($this->array('roles'), is_string(...)));
    }
}
