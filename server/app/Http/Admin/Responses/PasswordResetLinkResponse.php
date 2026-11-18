<?php

namespace App\Http\Admin\Responses;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\{FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse};

/**
 * 找回密码（Fortify 的 POST /forgot-password）的响应，替换 Fortify 的成功与失败两种响应（设计文档 §6.2）：
 *
 * 邮箱不存在（`passwords.user`）、发送过于频繁（`passwords.throttled`，只有已注册的邮箱才会出现）与发送成功
 * 返回完全相同的 `{ message }`（中性文案 `passwords.sent`），不暴露哪些邮箱已注册；频繁请求时不会重复发信。
 * 邮箱格式错误仍是 422（由 Fortify 的表单校验返回），请求频率由入口的限流器控制（429）。
 */
final readonly class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * 对外表现为“已发送”的状态。
     */
    private const array SENT = [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED];

    /**
     * @param  string  $status  密码代理返回的状态（语言包键，如 `passwords.sent`）
     */
    public function __construct(private string $status) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     *
     * @throws ValidationException
     */
    public function toResponse($request): JsonResponse
    {
        if (! in_array($this->status, self::SENT, true)) {
            throw ValidationException::withMessages(['email' => [$this->translate($this->status)]]);
        }

        return new JsonResponse(['message' => $this->translate(Password::RESET_LINK_SENT)]);
    }

    private function translate(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
