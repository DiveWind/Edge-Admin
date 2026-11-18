<?php

namespace App\Http\Admin\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

/**
 * 重置密码失败（Fortify 的 POST /reset-password）的响应，替换 Fortify 的默认实现（设计文档 §6.2）：
 *
 * 邮箱不存在（`passwords.user`）与令牌无效返回同样的错误（`passwords.token`，在 `errors.email` 中），
 * 避免借重置接口探测哪些邮箱已注册。其他状态与 Fortify 相同。
 */
final readonly class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    /**
     * @param  string  $status  密码代理返回的状态（语言包键，如 `passwords.token`）
     */
    public function __construct(private string $status) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     *
     * @throws ValidationException
     */
    public function toResponse($request): never
    {
        $status = $this->status === Password::INVALID_USER ? Password::INVALID_TOKEN : $this->status;
        $message = __($status);

        throw ValidationException::withMessages(['email' => [is_string($message) ? $message : $status]]);
    }
}
