<?php

namespace App\Core\System;

use App\Core\System\Actions\{AuthenticateUser, ResetUserPassword, UpdateUserPassword, UpdateUserProfileInformation};
use App\Core\System\Commands\SyncPermissionsCommand;
use App\Core\System\Contracts\MenuRegistry;
use App\Core\System\Models\{Permission, Role, User};
use App\Core\System\Services\{AllDataScope, DataScopeRegistry, MenuRegistryService, PermissionRegistry, SelfDataScope};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Gate, RateLimiter};
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
 *
 * 菜单与权限（设计文档 §8.1、§8.2）：MenuRegistry 扩展点（本模块的节点见 resources/menus.php）、同步命令 core:sync-permissions、
 * 基于 PermissionRegistry 的 Gate::before。
 *
 * 数据权限（设计文档 §8.4）：DataScopeRegistry 扩展点，本模块注册默认的 `all`、`self` 两个范围。
 */
class SystemServiceProvider extends ServiceProvider
{
    /**
     * 单例：只保存启动时注册的静态声明（菜单与权限节点），不保存请求数据。
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        MenuRegistry::class => MenuRegistryService::class,
    ];

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
    public function boot(MenuRegistry $menus, DataScopeRegistry $scopes): void
    {
        Relation::morphMap([
            'permission' => Permission::class,
            'role' => Role::class,
        ]);

        $menus->register(require __DIR__.'/resources/menus.php');
        $scopes->register(new AllDataScope, new SelfDataScope);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }

        $this->configureAuthorization();
        $this->configureFortify();
        $this->configureRateLimiting();
        $this->configurePasswordReset();
    }

    /**
     * 权限判定（设计文档 §8.1）：Gate::before 只处理权限码。
     *
     * - 无参数的调用（`can('system.user.create')`、`#[Authorize('system.user')]`），且 ability 是已登记的权限码
     *   （permissions 表中有，不看格式）：在用户的有效权限码中为 true，否则为 false（节点或其任一祖先停用时，超管也为 false）；
     * - 带参数的调用（Policy：`can('update', $model)`、`can('create', User::class)`）一律返回 null，交给 Policy——
     *   即使有节点恰好叫 `update`，也不会绕过 Policy 的记录级规则；
     * - 其他 ability（未登记的名称）返回 null，交给 Policy：超管只对权限码直接放行，同样受 Policy 中记录级规则的约束。
     *
     * spatie 自带的 Gate 检查已关闭（config/permission.php 的 register_permission_check_method）。
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if ($arguments !== []) {
                return null;
            }

            $registry = resolve(PermissionRegistry::class);

            return $registry->isRegistered($ability) ? $registry->allows($user, $ability) : null;
        });
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
