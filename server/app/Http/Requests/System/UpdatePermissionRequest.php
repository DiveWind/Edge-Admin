<?php

namespace App\Http\Requests\System;

use App\Models\Permission;
use Closure;
use Illuminate\Validation\Rule;

/**
 * 编辑菜单权限节点。
 *
 * - 有子节点时，新类型必须仍能容纳现有子节点（如菜单下已有操作则不能改为目录）；
 * - 内置节点（is_system）不可改 code / type（422）；path / is_external 由配置决定（sync 时修正），提交的值被忽略。
 */
class UpdatePermissionRequest extends StorePermissionRequest
{
    protected function prepareForValidation(): void
    {
        $permission = $this->permission();

        if ($permission?->is_system) {
            $this->merge(['path' => $permission->path, 'is_external' => $permission->is_external]);
        }

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');
        $rules = parent::rules();
        $rules['type'][] = $this->childrenRule($permission);

        if (! $permission->is_system) {
            return $rules;
        }

        return [
            ...$rules,
            'code' => ['required', 'string', Rule::in([$permission->code])],
            'type' => ['required', Rule::in([$permission->type->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.in' => __('validation.custom.permission.system_code'),
            'type.in' => __('validation.custom.permission.system_type'),
        ];
    }

    protected function childrenRule(Permission $permission): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($permission): void {
            $type = $this->type();

            if ($type === null) {
                return;
            }

            $conflict = $permission->children()->get(['id', 'type'])
                ->contains(fn (Permission $child): bool => ! $type->accepts($child->type));

            if ($conflict) {
                $fail(__('validation.custom.permission.children_type'));
            }
        };
    }
}
