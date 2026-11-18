<?php

namespace App\Http\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 后台入口的请求一律按 JSON 处理：把 Accept 设为 application/json。
 *
 * Fortify、Sanctum 与框架的确认密码中间件按 `wantsJson()` / `expectsJson()` 决定返回 JSON 还是重定向到页面；
 * 后台没有页面，客户端漏带 Accept 时也不应收到重定向（重定向不会被 FormatResponse 包装为 Vben 格式）。
 */
class ForceJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
