<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 请求 ID（设计文档 §9）：每个响应带 `X-Request-Id`，并写入 Context（键 `request_id`）。
 * Context 中的数据会自动出现在日志中，并随队列任务传递，用于串联一次请求产生的全部日志。
 *
 * 上游（Nginx 的 `$request_id`、网关）已带合法的 `X-Request-Id` 时沿用，否则生成 UUIDv7。
 * 作为全局中间件放在最前面（bootstrap/app.php），异常响应、未匹配路由的 404 同样带上。
 */
class AssignRequestId
{
    /**
     * 响应头名称。
     */
    public const string HEADER = 'X-Request-Id';

    /**
     * Context 中的键。
     */
    public const string CONTEXT_KEY = 'request_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->incomingId($request) ?? Str::uuid7()->toString();

        Context::add(self::CONTEXT_KEY, $id);

        $response = $next($request);

        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    /**
     * 上游传入的请求 ID：只接受 8–128 位的字母、数字与 `.` `_` `:` `-`，避免日志注入。
     */
    protected function incomingId(Request $request): ?string
    {
        $id = $request->headers->get(self::HEADER);

        return is_string($id) && preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $id) === 1 ? $id : null;
    }
}
