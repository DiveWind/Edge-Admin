<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\Department;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id'), $this->parentRule()],
            'name' => ['required', 'string', 'max:255'],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(Status::class)],
        ];
    }

    /**
     * 编辑时：上级部门不能是自己或自己的下级。
     */
    protected function parentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $department = $this->route('department');

            if ($department instanceof Department
                && in_array((int) $value, Department::descendantAndSelfIds($department->id), true)) {
                $fail(__('validation.custom.parent_id.cycle'));
            }
        };
    }
}
