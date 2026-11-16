<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureApiDocs();
        $this->configureDevCommands();
    }

    /**
     * 框架级默认值：
     * - 非生产环境开启 Eloquent 严格模式（禁止懒加载、静默丢弃未 fillable 的属性、访问不存在的属性）；
     * - 日期统一为不可变的 CarbonImmutable，避免链式修改误改原值；
     * - 生产环境禁止 migrate:fresh / db:wipe 等破坏性命令；
     * - 密码规则：生产环境至少 8 位且包含字母与数字，其他环境至少 8 位；
     * - 按配置强制 https 链接（TLS 在反向代理终止时）；
     * - Sanctum 使用自定义 PersonalAccessToken（记录 ip / user_agent，用于在线会话）。
     */
    protected function configureDefaults(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        URL::forceHttps(config()->boolean('edge.force_https'));

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * 功能权限：权限码（形如 `模块.资源.操作`，必含 "."）即 Gate ability，拥有则放行；
     * Policy 方法名（view / update / delete…）不含 "."，返回 null 交给 Policy 判定，
     * 因此超级管理员同样受记录级规则约束（如不能删除自己）。
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }

    /**
     * api：全部 /api 接口（bootstrap/app.php 中 throttleApi()），已登录按用户、未登录按 IP 计数；
     * login：登录接口按 "用户名 + IP"，次数取系统参数 security.login_max_attempts（默认取 config）。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(config()->integer('edge.rate_limit.api_per_minute'))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::lower($request->string('username')->toString()).'|'.$request->ip();
            $maxAttempts = $this->app->make(Settings::class)
                ->integer('security.login_max_attempts', config()->integer('edge.auth.login_max_attempts'));

            return Limit::perMinute(max(1, $maxAttempts))->by($key);
        });
    }

    /**
     * Scramble 生成的 OpenAPI 文档（/docs/api），默认仅 local 环境可访问。
     */
    protected function configureApiDocs(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    /**
     * `php artisan dev`（composer dev）额外启动调度器，便于本地观察定时任务。
     */
    protected function configureDevCommands(): void
    {
        DevCommands::artisan('schedule:work', 'schedule');
    }
}
