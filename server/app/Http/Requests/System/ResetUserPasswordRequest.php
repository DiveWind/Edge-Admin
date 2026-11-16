<?php

namespace App\Http\Requests\System;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    /**
     * 记录级授权（Policy），先于字段校验执行。
     */
    public function authorize(): Response
    {
        $user = $this->route('user');

        return Gate::inspect('resetPassword', $user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
