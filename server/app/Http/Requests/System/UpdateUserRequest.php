<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * 记录级授权（Policy），先于字段校验执行。
     */
    public function authorize(): Response
    {
        $user = $this->route('user');

        return Gate::inspect('update', $user);
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('users')->ignore($user)],
            'name' => ['required', 'string', 'max:255'],
            ...$this->profileRules($user),
        ];
    }

    /**
     * 初始超级管理员（id=1）不可禁用，且必须保留超级管理员角色。
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->route('user');

                if (! $user->isRoot()) {
                    return;
                }

                if ($this->input('status') !== Status::Active->value) {
                    $validator->errors()->add('status', __('validation.custom.status.root_user'));
                }

                $superAdminRoleId = Role::query()->where('code', Role::SUPER_ADMIN)->value('id');

                if (! in_array($superAdminRoleId, array_map('intval', (array) $this->input('role_ids', [])), true)) {
                    $validator->errors()->add('role_ids', __('validation.custom.role_ids.root_user'));
                }
            },
        ];
    }
}
