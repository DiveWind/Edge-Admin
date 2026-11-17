<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 为每个 API 请求分配 request id 并放入 Context：同一请求内写的日志、派发的队列任务、操作日志都带上它，
 * 响应头 X-Request-Id 返回给客户端便于排查。客户端传入合法 UUID 时沿用（便于串联网关 / 前端日志）。
 */
class AddRequestContext
{
    public const string HEADER = 'X-Request-Id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER);

        Context::add('request_id', is_string($requestId) && Str::isUuid($requestId) ? $requestId : (string) Str::uuid());

        $response = $next($request);
        $response->headers->set(self::HEADER, Context::get('request_id'));

        return $response;
    }
}
