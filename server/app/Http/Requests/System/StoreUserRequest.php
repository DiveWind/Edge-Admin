<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\{Role, User};
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('users')],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            ...$this->profileRules(),
        ];
    }

    /**
     * 新增与编辑共用的字段规则。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function profileRules(?User $user = null): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status' => ['required', Rule::enum(Status::class)],
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id'), $this->superAdminRoleRule()],
            'position_ids' => ['present', 'array'],
            'position_ids.*' => ['integer', 'distinct', Rule::exists('positions', 'id')],
        ];
    }

    /**
     * 只有超级管理员可以分配超级管理员角色。
     */
    protected function superAdminRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var User $actor */
            $actor = $this->user();

            if (! $actor->isSuperAdmin() && Role::query()->whereKey($value)->where('code', Role::SUPER_ADMIN)->exists()) {
                $fail(__('validation.custom.role_ids.super_admin'));
            }
        };
    }
}
