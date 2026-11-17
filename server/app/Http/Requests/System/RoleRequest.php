<?php

namespace App\Http\Requests\System;

use App\Enums\{DataScope, Status};
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * 超级管理员角色不可改码、不可禁用。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $protected = $role?->isSuperAdmin() ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'alpha_dash', 'max:64', Rule::unique('roles')->ignore($role),
                ...($protected ? [Rule::in([Role::SUPER_ADMIN])] : []),
            ],
            'data_scope' => ['required', Rule::enum(DataScope::class)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(Status::class), ...($protected ? [Rule::in([Status::Active->value])] : [])],
            'remark' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')],
        ];
    }
}
