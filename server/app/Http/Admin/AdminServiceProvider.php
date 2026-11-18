<?php

namespace App\Http\Admin;

use App\Http\Admin\Middleware\FormatResponse;
use App\Http\Admin\Responses\VbenResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\{SubstituteBindings, ThrottleRequests};
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\{RateLimiter, Route};
use Illuminate\Support\ServiceProvider;
use LogicException;
use Throwable;

/**
 * 后台入口（Admin）：给 Vben 使用的后台 API（受信任的入口，设计文档 §5.2、§5.7、§6）。
 *
 * 负责本入口的全部配置：路由（routes.php）、URL 前缀、admin 中间件组、限流器、Vben 响应格式与异常输出。
 * config('core.apps.admin.enabled') 为 false（CORE_APPS 不含 admin）时什么都不注册。
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * 入口名：config/core.php 中的键、中间件组名、限流器名、路由名前缀。
     */
    public const string NAME = 'admin';

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
     * admin 中间件组。FormatResponse 在最外层，保证组内其他中间件的响应也被包装。
     * 认证（Sanctum 有状态会话）在第 4 步加入。
     */
    protected function configureMiddleware(): void
    {
        Route::middlewareGroup(self::NAME, [
            FormatResponse::class,
            ThrottleRequests::using(self::NAME),
            SubstituteBindings::class,
        ]);
    }

    /**
     * 每分钟请求上限：已登录按用户计数，未登录按 IP 计数。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for(self::NAME, function (Request $request): Limit {
            $user = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(config()->integer('core.apps.'.self::NAME.'.rate_limit'))
                ->by(is_scalar($user) ? 'user:'.$user : 'ip:'.$request->ip());
        });
    }

    /**
     * Vben 响应格式：`response()->vben()` 宏，以及本入口路径下的异常输出（含未匹配路由的 404 / 405）。
     */
    protected function configureResponses(): void
    {
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
