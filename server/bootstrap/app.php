<?php

use App\Http\Middleware\{AddRequestContext, EnsureUserIsActive, RecordOperationLog, SetLocale};
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\{HttpException, MethodNotAllowedHttpException, NotFoundHttpException};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->api(prepend: [
            AddRequestContext::class,
            SetLocale::class,
        ], append: [
            RecordOperationLog::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 同一个异常实例只上报一次（如被 rescue() 上报后又被重新抛出）。
        $exceptions->dontReportDuplicates();

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 统一为 {message} JSON，message 按当前语言翻译（中文见 lang/zh_CN.json）。
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __($e->getMessage())], 401);
            }
        });

        // 路由不存在与 ModelNotFoundException（框架已转换为 NotFoundHttpException）均不暴露模型类名。
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('Not Found')], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('Method Not Allowed')], 405, $e->getHeaders());
            }
        });

        // 403（AccessDenied）、429（ThrottleRequests，带 Retry-After 头）等。
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getMessage() !== '') {
                return response()->json(['message' => __($e->getMessage())], $e->getStatusCode(), $e->getHeaders());
            }
        });
    })->create();
