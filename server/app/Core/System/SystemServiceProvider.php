<?php

namespace App\Core\System;

use App\Core\System\Actions\{AuthenticateUser, ResetUserPassword, UpdateUserPassword, UpdateUserProfileInformation};
use App\Core\System\Commands\{SyncCommand, SyncDictsCommand, SyncPermissionsCommand, SyncSettingsCommand};
use App\Core\System\Contracts\{DictRegistry, MenuRegistry, SettingRegistry};
use App\Core\System\Enums\{ExportFormat, ExportStatus, PermissionType, SettingType, Status};
use App\Core\System\Exporters\UserExporter;
use App\Core\System\Listeners\DeleteExportsOfDeletingUser;
use App\Core\System\Models\{Attachment, DictItem, DictType, Export, Permission, Role, Setting, User};
use App\Core\System\Services\{AllDataScope, DataScopeRegistry, DictRegistryService, EnumRegistry, ExporterRegistry, MenuRegistryService, PermissionRegistry, SelfDataScope, SettingRegistryService, Settings};
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\{Event, Gate, RateLimiter};
use Illuminate\Support\{ServiceProvider, Str};
use Laravel\Fortify\Fortify;
use Spatie\Permission\PermissionRegistrar;

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
 *
 * 字典、系统参数与枚举选项（设计文档 §6.7）：DictRegistry、SettingRegistry 扩展点（本模块的参数分组见 resources/settings.php）、
 * 同步命令 core:sync-dicts、core:sync-settings，以及 EnumRegistry 中本模块的枚举。
 *
 * 异步导出（设计文档 §6.10）：ExporterRegistry 扩展点（本模块登记用户列表的导出 system.user）；用户删除前删除他的导出（连同文件）。
 * 完成通知由 Message 监听 ExportFinished 发送（System 不依赖 Message）。
 */
class SystemServiceProvider extends ServiceProvider
{
    /**
     * 单例：只保存启动时注册的静态声明（菜单与权限节点、字典类型、系统参数分组），不保存请求数据。
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        MenuRegistry::class => MenuRegistryService::class,
        DictRegistry::class => DictRegistryService::class,
        SettingRegistry::class => SettingRegistryService::class,
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
    public function boot(MenuRegistry $menus, DataScopeRegistry $scopes, SettingRegistry $settings, EnumRegistry $enums, ExporterRegistry $exporters): void
    {
        Relation::morphMap([
            'permission' => Permission::class,
            'role' => Role::class,
            'dict_type' => DictType::class,
            'dict_item' => DictItem::class,
            'setting' => Setting::class,
            'attachment' => Attachment::class,
            'export' => Export::class,
        ]);

        $menus->register(require __DIR__.'/resources/menus.php');
        $scopes->register(new AllDataScope, new SelfDataScope);
        $settings->register(require __DIR__.'/resources/settings.php');
        $enums->register([
            'status' => Status::class,
            'permission_type' => PermissionType::class,
            'setting_type' => SettingType::class,
            'export_status' => ExportStatus::class,
            'export_format' => ExportFormat::class,
        ]);
        $exporters->register([
            'system.user' => UserExporter::class,
        ]);

        Event::listen('eloquent.deleting: '.User::class, DeleteExportsOfDeletingUser::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCommand::class, SyncPermissionsCommand::class, SyncDictsCommand::class, SyncSettingsCommand::class]);
        }

        $this->configureAuthorization();
        $this->configureQueueWorkers();
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
     * 常驻的队列 worker（设计文档 §10.3）：每个任务开始前清掉 spatie PermissionRegistrar（单例）在内存中的节点与授权集合
     * （不清共享的缓存存储），下次读取时从缓存重新载入，任务按最新的授权判定（如执行导出时发起人已失去权限码，§8.6）。
     * Web 端改动授权时 spatie 只能清掉本进程的内存副本与缓存存储，worker 中的副本不会随之更新；
     * #[Scoped] 的 PermissionRegistry、Cache::memo() 由框架在任务之间重置，不需要额外处理。Octane 由 spatie 的 reset 监听处理。
     */
    protected function configureQueueWorkers(): void
    {
        Event::listen(JobProcessing::class, function (): void {
            if ($this->app->resolved(PermissionRegistrar::class)) {
                $this->app->make(PermissionRegistrar::class)->clearPermissionsCollection();
            }
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
     * 限流器（config/fortify.php 的 limiters），每分钟次数为系统参数 `auth.login_rate_limit`（设计文档 §7.2；
     * 默认值来自 config('core.auth.login_rate_limit')，每次请求时读取，后台修改后立即生效）：
     * - login：按“用户名 + IP”计数，超出时返回 429，提示还需等待的秒数（同时挂在 username 上），并派发框架标准的 Lockout 事件
     *   （Fortify 使用命名限流器时不派发它；Monitor 的登录日志据此记录，设计文档 §7.3）；
     * - two-factor：按待验证的用户（登录后会话中的 login.id）计数。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(self::loginRateLimit())
            ->by(Str::transliterate(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()))
            ->response(function (Request $request, array $headers): JsonResponse {
                event(new Lockout($request));

                $retryAfter = $headers['Retry-After'] ?? null;
                $message = __('auth.throttle', ['seconds' => is_int($retryAfter) ? $retryAfter : 60]);

                return new JsonResponse(['message' => $message, 'errors' => [Fortify::username() => [$message]]], 429, $headers);
            }));

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $pending = $request->hasSession() ? $request->session()->get('login.id') : null;

            return Limit::perMinute(self::loginRateLimit())
                ->by('two-factor|'.(is_scalar($pending) ? $pending : $request->ip()));
        });
    }

    /**
     * 登录与两步验证每分钟的次数（系统参数 auth.login_rate_limit）。
     */
    protected static function loginRateLimit(): int
    {
        return resolve(Settings::class)->integer('auth.login_rate_limit');
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
