<?php

namespace App\Core\System;

use App\Core\System\Actions\{AuthenticateUser, ResetUserPassword, UpdateUserPassword, UpdateUserProfileInformation};
use App\Core\System\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\{ServiceProvider, Str};
use Laravel\Fortify\Fortify;

/**
 * System（内核模块）：职责与对外提供的能力见同目录的 README.md。
 *
 * 注册本模块的契约绑定、多态别名、命令、事件监听、语言包与视图，并通过 Core 的扩展点接入（设计文档 §4.3、§5.5）。
 * 多态别名与契约绑定由 core:make-model、core:make-contract 自动登记。
 *
 * 认证（设计文档 §7）：Fortify 的登录校验与各 Action、登录 / 两步验证的限流器、找回密码邮件中的前端链接。
 * Fortify 的路由挂在后台入口下（config/fortify.php，由 AdminServiceProvider 决定是否注册）。
 */
class SystemServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFortify();
        $this->configureRateLimiting();
        $this->configurePasswordReset();
    }

    /**
     * Fortify：用户名 + 密码登录（停用账号拒绝登录），修改资料、修改密码、重置密码使用本模块的 Action。注册已关闭。
     */
    protected function configureFortify(): void
    {
        Fortify::authenticateUsing(fn (Request $request): ?User => resolve(AuthenticateUser::class)->handle(
            $request->string(Fortify::username())->toString(),
            $request->string('password')->toString(),
        ));

        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * 限流器（config/fortify.php 的 limiters），每分钟次数见 config('core.auth.login_rate_limit')：
     * - login：按“用户名 + IP”计数，超出时返回 429，提示还需等待的秒数（同时挂在 username 上）；
     * - two-factor：按待验证的用户（登录后会话中的 login.id）计数。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(config()->integer('core.auth.login_rate_limit'))
            ->by(Str::transliterate(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()))
            ->response(function (Request $request, array $headers): JsonResponse {
                $retryAfter = $headers['Retry-After'] ?? null;
                $message = __('auth.throttle', ['seconds' => is_int($retryAfter) ? $retryAfter : 60]);

                return new JsonResponse(['message' => $message, 'errors' => [Fortify::username() => [$message]]], 429, $headers);
            }));

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $pending = $request->hasSession() ? $request->session()->get('login.id') : null;

            return Limit::perMinute(config()->integer('core.auth.login_rate_limit'))
                ->by('two-factor|'.(is_scalar($pending) ? $pending : $request->ip()));
        });
    }

    /**
     * 找回密码邮件中的重置链接指向前端页面：`<frontend_url><reset_password_path>?token=…&email=…`（config/core.php 的 auth）。
     */
    protected function configurePasswordReset(): void
    {
        ResetPassword::createUrlUsing(fn (mixed $notifiable, string $token): string => rtrim(config()->string('core.auth.frontend_url'), '/')
            .config()->string('core.auth.reset_password_path')
            .'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : null,
            ]));
    }
}
