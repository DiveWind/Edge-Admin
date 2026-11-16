<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn (): Password => Password::min(8));

        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureApiDocs();
        $this->configureDevCommands();
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

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::lower($request->string('username')->toString()).'|'.$request->ip();

            return Limit::perMinute(config()->integer('edge.auth.login_max_attempts'))->by($key);
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
