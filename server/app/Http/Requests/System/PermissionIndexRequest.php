<?php

namespace App\Http\Requests\System;

use App\Enums\{PermissionType, Status};
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 菜单权限为树形列表，不分页。
 */
class PermissionIndexRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(PermissionType::class)],
            'status' => ['nullable', Rule::enum(Status::class)],
        ];
    }
}
