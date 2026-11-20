<?php

use App\Http\Middleware\{AssignRequestId, RedirectIfAuthenticated, SetLocale};
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\JsonResponse;
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

        // 按 Accept-Language 选择提示语言（config('core.locales')），各入口共用
        $middleware->append(SetLocale::class);

        // 没有登录页：未登录不重定向，由异常输出返回 401
        $middleware->redirectGuestsTo(null);

        // 已登录时访问 guest 路由（Fortify 的登录、找回密码等）：期望 JSON 的请求返回 409，不重定向
        $middleware->alias(['guest' => RedirectIfAuthenticated::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 异常一律输出 JSON；后台入口的 Vben 格式由 AdminServiceProvider 注册
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
    })
    ->booted(function (Application $app): void {
        // 业务规则异常的全局兜底（设计文档 §5.4）：没有被入口自己的异常输出处理的（各入口在 Provider 的 boot() 中登记，
        // 先于本回调，如 Admin 的 Vben 格式），按 Laravel 校验失败的标准 JSON 返回 422：`{ message[, errors: { 字段: [message] }] }`，
        // 而不是 500。启动完成后才登记，保证它排在全部入口的渲染回调之后。
        // 框架的 Handler 与命令行下 Collision 的包装类都提供 renderable()（同 AdminServiceProvider）
        $app->make(ExceptionHandler::class)->renderable(fn (BusinessRuleViolation $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
            ...($e->field === null ? [] : ['errors' => [$e->field => [$e->getMessage()]]]),
        ], 422));
    })->create();
