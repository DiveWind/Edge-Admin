<?php

use App\Http\Middleware\{AssignRequestId, RedirectIfAuthenticated};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 纯 API 后端
|--------------------------------------------------------------------------
|
| 各入口（app/Http/<Name>）的路由、中间件组、限流器与异常输出格式由各自的 ServiceProvider 注册
| （bootstrap/providers.php），这里只放全局配置。
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/', fn (): array => ['name' => config('app.name')])->name('home');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        // 没有登录页：未登录不重定向，由异常输出返回 401
        $middleware->redirectGuestsTo(null);

        // 已登录时访问 guest 路由（Fortify 的登录、找回密码等）：期望 JSON 的请求返回 409，不重定向
        $middleware->alias(['guest' => RedirectIfAuthenticated::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 异常一律输出 JSON；后台入口的 Vben 格式由 AdminServiceProvider 注册
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
    })->create();
