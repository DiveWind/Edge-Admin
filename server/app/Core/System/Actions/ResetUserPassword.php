<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use SensitiveParameter;

/**
 * 找回密码：通过邮件中的链接重置密码，该用户全部的会话与 token 失效（设计文档 §7.2、§7.4）。
 *
 * Fortify 的“重置密码”（POST /reset-password）校验重置令牌后调用 reset()。
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly UpdateUserPassword $updatePassword) {}

    /**
     * Fortify：校验新密码后重置。
     *
     * @param  array<array-key, mixed>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ])->validate();

        $this->handle($user, Arr::string($validated, 'password'));
    }

    /**
     * Execute the action.
     */
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        $this->updatePassword->handle($user, $password);
    }
}
