<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserStatusRequest extends FormRequest
{
    /**
     * 记录级授权（Policy），先于字段校验执行。
     */
    public function authorize(): Response
    {
        return Gate::inspect('updateStatus', [$this->route('user'), $this->enum('status', Status::class)]);
    }

    /**
     * 初始超级管理员（id=1）不可禁用。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'status' => ['required', Rule::enum(Status::class)->when($user->isRoot(), fn (Enum $rule): Enum => $rule->only(Status::Active))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => __('validation.custom.status.root_user'),
        ];
    }
}
