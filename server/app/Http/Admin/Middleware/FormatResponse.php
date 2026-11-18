<?php

namespace App\Http\Admin\Middleware;

use App\Http\Admin\Responses\VbenResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 把后台入口的响应包装为 Vben 格式（{code, data, message}，规则见 VbenResponse::fromResponse()）。
 *
 * 位于 admin 中间件组的最外层，控制器、Form Request、Fortify 都无需感知响应格式；
 * 路由内抛出的异常由 AdminServiceProvider 注册的异常渲染直接生成 VbenResponse，这里不再重复包装。
 */
class FormatResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return VbenResponse::fromResponse($response) ?? $response;
    }
}
