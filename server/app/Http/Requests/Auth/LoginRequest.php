<?php

namespace App\Http\Requests\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Services\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * 登录：与 Laravel 官方 starter kit 的 LoginRequest 相同，按"用户名 + IP"只累计失败次数，登录成功后清零；
 * 每分钟允许的失败次数取系统参数 security.login_max_attempts（默认 config('edge.auth.login_max_attempts')），
 * 超出返回 429 + Retry-After。
 */
class LoginRequest extends FormRequest
{
    /**
     * 失败计数的有效期（秒）。
     */
    protected const int DECAY_SECONDS = 60;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * 校验账号密码并签发 token：失败（含账号被禁用）累计一次，成功清除该"用户名 + IP"的失败计数。
     *
     * @throws ThrottleRequestsException
     * @throws ValidationException
     */
    public function authenticate(AttemptLogin $attemptLogin): NewAccessToken
    {
        $this->ensureIsNotRateLimited();

        try {
            $token = $attemptLogin->handle(
                $this->string('username')->toString(),
                $this->string('password')->toString(),
                $this->ip(),
                $this->userAgent(),
            );
        } catch (ValidationException $e) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw $e;
        }

        RateLimiter::clear($this->throttleKey());

        return $token;
    }

    /**
     * @throws ThrottleRequestsException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => $seconds]), headers: ['Retry-After' => $seconds]);
    }

    public function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('username')->toString()).'|'.$this->ip());
    }

    protected function maxAttempts(): int
    {
        return max(1, app(Settings::class)->integer('security.login_max_attempts', config()->integer('edge.auth.login_max_attempts')));
    }
}
