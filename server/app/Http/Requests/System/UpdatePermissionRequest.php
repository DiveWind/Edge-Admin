<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\Permission;
use Closure;
use Illuminate\Validation\Rule;
use Override;

/**
 * 编辑菜单权限节点。
 *
 * - 有子节点时，新类型必须仍能容纳现有子节点（如菜单下已有操作则不能改为目录）；
 * - 不能停用包含菜单管理查看 / 编辑权限的节点（会把所有人锁在菜单管理之外，422）；
 * - 内置节点（is_system）不可改 code / type（422）；path / is_external 由配置决定（sync 时修正），提交的值被忽略。
 */
class UpdatePermissionRequest extends StorePermissionRequest
{
    /**
     * 停用后会导致无法再管理菜单的权限码。
     *
     * @var list<string>
     */
    public const array LIFELINE_CODES = ['system.permission.view', 'system.permission.update'];

    #[Override]
    protected function prepareForValidation(): void
    {
        $permission = $this->permission();

        if ($permission?->is_system) {
            $this->merge(['path' => $permission->path, 'is_external' => $permission->is_external]);
        }

        parent::prepareForValidation();
    }

    #[Override]
    public function rules(): array
    {
        $permission = $this->current();
        $rules = parent::rules();

        if (! $permission->is_system) {
            return $rules;
        }

        return [
            ...$rules,
            'code' => ['required', 'string', Rule::in([$permission->code])],
            'type' => ['required', Rule::in([$permission->type->value])],
        ];
    }

    #[Override]
    protected function typeRules(): array
    {
        return [...parent::typeRules(), $this->childrenRule($this->current())];
    }

    #[Override]
    protected function statusRules(): array
    {
        return [...parent::statusRules(), $this->lockoutRule($this->current())];
    }

    protected function current(): Permission
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return $permission;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.in' => __('validation.custom.permission.system_code'),
            'type.in' => __('validation.custom.permission.system_type'),
        ];
    }

    /**
     * 不能停用包含菜单管理查看 / 编辑权限的节点（自身或子孙），否则包括超级管理员在内都无法再启用它。
     */
    protected function lockoutRule(Permission $permission): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($permission): void {
            if ($value !== Status::Disabled->value) {
                return;
            }

            $lifeline = Permission::query()->whereIn('code', self::LIFELINE_CODES)->pluck('id')->all();

            if (array_intersect($lifeline, Permission::descendantAndSelfIds($permission->id)) !== []) {
                $fail(__('validation.custom.permission.lockout'));
            }
        };
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
