<?php

namespace App\Http\Admin;

use App\Http\Admin\Middleware\{ForceJson, FormatResponse, RecordOperationLog};
use App\Http\Admin\Responses\{FailedPasswordResetResponse, PasswordResetLinkResponse, VbenResponse};
use App\Http\Middleware\EnsureUserIsEnabled;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\{SubstituteBindings, ThrottleRequests};
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\{RateLimiter, Route};
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\{FailedPasswordResetLinkRequestResponse, FailedPasswordResetResponse as FailedPasswordResetResponseContract, SuccessfulPasswordResetLinkRequestResponse};
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use LogicException;
use Throwable;

/**
 * 后台入口（Admin）：给 Vben 使用的后台 API（受信任的入口，设计文档 §5.2、§5.7、§6）。
 *
 * 负责本入口的全部配置：路由（routes.php）、URL 前缀、admin 中间件组、限流器、Vben 响应格式与异常输出。
 * 认证为 Sanctum SPA 模式（会话 Cookie）+ Fortify（设计文档 §7）：Fortify 的路由使用本入口的前缀与中间件组（config/fortify.php）。
 * config('core.apps.admin.enabled') 为 false（CORE_APPS 不含 admin）时什么都不注册，Fortify 的路由也不注册。
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * 入口名：config/core.php 中的键、中间件组名、限流器名、路由名前缀。
     */
    public const string NAME = 'admin';

    /**
     * 运维动作的限流器名（定时任务的立即执行、失败任务的重试、立即健康检查，设计文档 §6.6）：路由上用 `throttle:admin-ops`。
     */
    public const string OPS_LIMITER = self::NAME.'-ops';

    /**
     * Register any application services.
     *
     * Fortify 的路由挂在本入口下（admin 中间件组），本入口不加载时不注册；每次启动时按配置重新设置（静态开关，测试中会重建应用）。
     */
    public function register(): void
    {
        Fortify::$registersRoutes = config()->boolean('core.apps.'.self::NAME.'.enabled');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! config()->boolean('core.apps.'.self::NAME.'.enabled')) {
            return;
        }

        $this->configureMiddleware();
        $this->configureRateLimiting();
        $this->configureResponses();
        $this->configureRoutes();
    }

    /**
     * admin 中间件组（按顺序）：
     *
     * 1. FormatResponse：最外层，组内其他中间件的响应与异常（含 CSRF 的 419、限流的 429、未登录的 401）也被包装为 Vben 格式；
     * 2. RecordOperationLog：已登录用户的写请求记入操作日志（排除登录、登出等认证请求，响应发送后写入，设计文档 §6.6）；
     * 3. ForceJson：请求一律按 JSON 处理，Fortify 等不会返回重定向；
     * 4. EnsureFrontendRequestsAreStateful：来自 SANCTUM_STATEFUL_DOMAINS 的请求（按 Referer / Origin）启动会话、
     *    校验 CSRF（X-XSRF-TOKEN）、修改密码后让其他会话失效（Sanctum 的 AuthenticateSession）；其他请求只能用 Bearer token；
     * 5. EnsureUserIsEnabled：停用的账号立即失效（401）；
     * 6. 限流（已登录按用户、未登录按 IP）与路由模型绑定。
     *
     * 需要登录的路由加 `auth:sanctum`（会话与 token 都能识别）；它按框架的中间件优先级排在限流之前。
     */
    protected function configureMiddleware(): void
    {
        Route::middlewareGroup(self::NAME, [
            FormatResponse::class,
            RecordOperationLog::class,
            ForceJson::class,
            EnsureFrontendRequestsAreStateful::class,
            EnsureUserIsEnabled::class,
            ThrottleRequests::using(self::NAME),
            SubstituteBindings::class,
        ]);
    }

    /**
     * 每分钟请求上限：已登录按用户计数，未登录按 IP 计数。运维动作另有 OPS_LIMITER（ops_rate_limit），同样按用户计数。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for(self::NAME, fn (Request $request): Limit => Limit::perMinute(config()->integer('core.apps.'.self::NAME.'.rate_limit'))
            ->by(self::rateLimitKey($request)));

        RateLimiter::for(self::OPS_LIMITER, fn (Request $request): Limit => Limit::perMinute(config()->integer('core.apps.'.self::NAME.'.ops_rate_limit'))
            ->by(self::rateLimitKey($request)));
    }

    /**
     * 限流的计数键：已登录按用户，未登录按 IP。
     */
    protected static function rateLimitKey(Request $request): string
    {
        $user = $request->user()?->getAuthIdentifier();

        return is_scalar($user) ? 'user:'.$user : 'ip:'.$request->ip();
    }

    /**
     * Vben 响应格式：`response()->vben()` 宏，以及本入口路径下的异常输出（含未匹配路由的 404 / 405）；
     * 替换 Fortify 的找回、重置密码响应，不暴露哪些邮箱已注册（设计文档 §6.2）。
     */
    protected function configureResponses(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetResponseContract::class, FailedPasswordResetResponse::class);

        ResponseFactory::macro('vben', fn (mixed $data = null, string $message = VbenResponse::OK, int $status = 200): VbenResponse => VbenResponse::success($data, $message, $status));

        // 框架的 Handler 与命令行下 Collision 的包装类都提供 renderable()，契约 ExceptionHandler 中没有
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! method_exists($handler, 'renderable')) {
                throw new LogicException('The exception handler ['.$handler::class.'] does not support renderable().');
            }

            $handler->renderable(fn (Throwable $e, Request $request): ?VbenResponse => $this->handles($request) ? VbenResponse::fromException($e) : null);
        });
    }

    /**
     * 路由：前缀见 config('core.apps.admin.prefix')，路由名以 `admin.` 开头。
     */
    protected function configureRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(self::NAME)
            ->prefix(self::prefix())
            ->name(self::NAME.'.')
            ->group(__DIR__.'/routes.php');
    }

    /**
     * 请求是否属于本入口（按 URL 前缀判断，未匹配到路由的请求也适用）。
     */
    protected function handles(Request $request): bool
    {
        $prefix = self::prefix();

        return $prefix === '' || $request->is($prefix, $prefix.'/*');
    }

    protected static function prefix(): string
    {
        return trim(config()->string('core.apps.'.self::NAME.'.prefix'), '/');
    }
}
