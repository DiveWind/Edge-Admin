<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * `guest` 中间件（bootstrap/app.php 中替换框架的同名别名）：已登录时，期望 JSON 的请求返回 409，而不是重定向到首页。
 *
 * Fortify 的登录、两步验证、找回与重置密码等路由要求未登录；SPA 已登录时再次提交这些请求，
 * 重定向会被 XHR 自动跟随到一个不是 JSON 接口的地址。其他请求保持框架的行为。
 */
class RedirectIfAuthenticated extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    #[Override]
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (! $request->expectsJson()) {
            return parent::handle($request, $next, ...$guards);
        }

        foreach ($guards === [] ? [null] : $guards as $guard) {
            if (Auth::guard($guard)->check()) {
                throw new ConflictHttpException(__('Already authenticated.'));
            }
        }

        return $next($request);
    }
}
