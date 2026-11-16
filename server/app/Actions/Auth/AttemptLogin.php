<?php

namespace App\Actions\Auth;

use App\Enums\LoginStatus;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * 校验账号密码并签发 Sanctum Token，同时写登录日志。
 */
class AttemptLogin
{
    /**
     * @throws ValidationException
     */
    public function handle(string $username, string $password, ?string $ip, ?string $userAgent): NewAccessToken
    {
        $user = User::query()->where('username', $username)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->fail($username, $user, $ip, $userAgent, __('auth.failed'));
        }

        if (! $user->isActive()) {
            $this->fail($username, $user, $ip, $userAgent, __('auth.disabled'));
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

        $this->log($username, $user, $ip, $userAgent, LoginStatus::Success, __('auth.success'));

        return $user->createToken('api', ['*'], now()->addMinutes(config()->integer('edge.auth.token_ttl')));
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $username, ?User $user, ?string $ip, ?string $userAgent, string $message): never
    {
        $this->log($username, $user, $ip, $userAgent, LoginStatus::Failed, $message);

        throw ValidationException::withMessages(['username' => $message]);
    }

    protected function log(string $username, ?User $user, ?string $ip, ?string $userAgent, LoginStatus $status, string $message): void
    {
        LoginLog::query()->create([
            'user_id' => $user?->id,
            'username' => $username,
            'ip' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 500, ''),
            'status' => $status,
            'message' => $message,
        ]);
    }
}
