<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use SensitiveParameter;

/**
 * 登录时校验用户名与密码（Fortify::authenticateUsing()，见 SystemServiceProvider；设计文档 §7.2）。
 *
 * - 用户名或密码错误：返回 null，由 Fortify 返回 422（`auth.failed`，挂在 username 上）并触发 Failed 事件；
 * - 账号已停用：密码正确时才提示，抛出 422（挂在 username 上），不会登录；
 * - 与框架的 SessionGuard 一致：用 Timebox 抹平“用户不存在”与“密码错误”的耗时差异；登录成功时按需重新哈希密码。
 *
 * 用户名由 Fortify 先转为小写（config('fortify.lowercase_usernames')），保存时也应为小写。
 */
class AuthenticateUser
{
    /**
     * 校验失败时的最短耗时（微秒），与 SessionGuard 相同。
     */
    private const int MINIMUM_DURATION = 200_000;

    /**
     * Execute the action.
     *
     * @throws ValidationException
     */
    public function handle(string $username, #[SensitiveParameter] string $password): ?User
    {
        return new Timebox()->call(function (Timebox $timebox) use ($username, $password): ?User {
            $user = User::query()->where('username', $username)->first();

            if ($user === null || ! Hash::check($password, $user->password)) {
                return null;
            }

            $timebox->returnEarly();

            if (! $user->isEnabled()) {
                throw ValidationException::withMessages([
                    Fortify::username() => [__('This account has been disabled.')],
                ]);
            }

            if (config()->boolean('hashing.rehash_on_login', true) && Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => $password])->save();
            }

            return $user;
        }, self::MINIMUM_DURATION);
    }
}
