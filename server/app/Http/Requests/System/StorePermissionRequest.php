<?php

namespace App\Http\Requests\System;

use App\Enums\PermissionType;
use App\Enums\Status;
use App\Models\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 新增菜单权限节点。
 *
 * 层级：目录下可放目录 / 菜单，菜单下只能放操作，操作没有子节点；操作必须挂在菜单下。
 * 与类型无关的字段在校验前归一：非菜单节点 is_external = false，操作节点 path / icon = null。
 */
class StorePermissionRequest extends FormRequest
{
    /**
     * 权限码：小写字母、数字、`-`、`_`，以 `.` 分段；操作（Gate ability）必须含 `.`。
     */
    public const string CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]*(\.[a-z0-9][a-z0-9_-]*)*$/';

    protected function prepareForValidation(): void
    {
        $type = PermissionType::tryFrom($this->string('type')->toString());

        if ($type !== null && $type !== PermissionType::Menu) {
            $this->merge(['is_external' => false]);
        }

        if ($type === PermissionType::Action) {
            $this->merge(['path' => null, 'icon' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => $this->typeRules(),
            'parent_id' => ['nullable', 'integer', Rule::exists('permissions', 'id'), $this->parentRule()],
            'name' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:128', 'regex:'.self::CODE_PATTERN, Rule::unique('permissions')->ignore($this->permission()), $this->actionCodeRule()],
            'path' => $this->pathRules(),
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z][A-Za-z0-9]*$/'],
            'is_external' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'status' => $this->statusRules(),
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => __('validation.custom.permission.parent'),
            'path' => __('validation.custom.permission.path'),
            'icon' => __('validation.custom.permission.icon'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('validation.custom.permission.code_format'),
            'path.url' => __('validation.custom.permission.external_path'),
            'path.regex' => __('validation.custom.permission.internal_path'),
        ];
    }

    /**
     * 编辑时为当前节点，新增时为 null。
     */
    protected function permission(): ?Permission
    {
        $permission = $this->route('permission');

        return $permission instanceof Permission ? $permission : null;
    }

    protected function type(): ?PermissionType
    {
        return PermissionType::tryFrom($this->string('type')->toString());
    }

    /**
     * @return list<mixed>
     */
    protected function typeRules(): array
    {
        return ['required', Rule::enum(PermissionType::class)];
    }

    /**
     * @return list<mixed>
     */
    protected function statusRules(): array
    {
        return ['required', Rule::enum(Status::class)];
    }

    /**
     * 菜单必填 path：外链为 http(s) URL，否则为以 `/` 开头的前端路由；目录可选；操作无 path。
     *
     * @return list<mixed>
     */
    protected function pathRules(): array
    {
        $required = $this->type() === PermissionType::Menu ? 'required' : 'nullable';

        return $this->boolean('is_external')
            ? [$required, 'string', 'max:255', 'url:http,https']
            : [$required, 'string', 'max:255', 'regex:/^\/\S*$/'];
    }

    protected function actionCodeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->type() === PermissionType::Action && is_string($value) && ! str_contains($value, '.')) {
                $fail(__('validation.custom.permission.action_code'));
            }
        };
    }

    /**
     * 上级节点类型须能容纳本节点类型；编辑时上级不能是自己或自己的下级。
     */
    protected function parentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = $this->type();
            $parent = Permission::query()->find($value);

            if ($type === null || ! $parent instanceof Permission) {
                return;
            }

            $current = $this->permission();

            if ($current !== null && in_array($parent->id, Permission::descendantAndSelfIds($current->id), true)) {
                $fail(__('validation.custom.permission.cycle'));

                return;
            }

            if (! $parent->type->accepts($type)) {
                $fail(__('validation.custom.permission.parent_type', [
                    'parent' => __("validation.custom.permission.types.{$parent->type->value}"),
                    'type' => __("validation.custom.permission.types.{$type->value}"),
                ]));
            }
        };
    }

    /**
     * 操作必须挂在菜单下（parent_id 为空时上面的规则不会执行）。
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->type() === PermissionType::Action && $this->input('parent_id') === null && ! $validator->errors()->has('parent_id')) {
                    $validator->errors()->add('parent_id', __('validation.custom.permission.action_parent'));
                }
            },
        ];
    }
}
