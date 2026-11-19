<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;
use App\Core\System\Services\{DataScopeRegistry, PermissionRegistry};
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增、修改角色（POST /system/roles、PUT /system/roles/{role}，设计文档 §6.5、§8.5）。
 *
 * 只做输入格式校验：name（标识，alpha_dash、唯一）与 title 必填；status、data_scope（已登记的数据范围）、remark、sort 可选；
 * permissions 为已登记的权限码数组，可选（修改时未提交表示权限不变）。
 * super-admin 的保护、防提权等业务规则在 System 模块的 Action 中（CreateRole、UpdateRole）。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class RoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(DataScopeRegistry $scopes, PermissionRegistry $permissions): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:64', 'alpha_dash:ascii', Rule::unique(Role::class, 'name')->ignore($role instanceof Role ? $role : null)],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'data_scope' => ['sometimes', 'string', Rule::in($scopes->keys())],
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'permissions' => ['sometimes', 'array', 'list'],
            'permissions.*' => ['string', 'distinct', Rule::in($permissions->registeredCodes())],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Identifier'),
            'title' => __('Display name'),
            'status' => __('Status'),
            'data_scope' => __('Data scope'),
            'remark' => __('Remark'),
            'sort' => __('Sort order'),
            'permissions' => __('Permissions'),
            'permissions.*' => __('Permissions'),
        ];
    }

    /**
     * 角色的字段（不含 permissions），交给 CreateRole / UpdateRole。
     *
     * @return array<string, mixed>
     */
    public function roleAttributes(): array
    {
        return array_diff_key($this->validated(), ['permissions' => true]);
    }

    /**
     * 提交的权限码；未提交时为 null。
     *
     * @return list<string>|null
     */
    public function permissions(): ?array
    {
        if (! $this->has('permissions')) {
            return null;
        }

        return array_values(array_filter($this->array('permissions'), is_string(...)));
    }
}
