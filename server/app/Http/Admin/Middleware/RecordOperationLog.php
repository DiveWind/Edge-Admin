<?php

namespace App\Http\Admin\Middleware;

use App\Core\Monitor\Actions\LogOperation;
use App\Core\System\Models\User;
use App\Http\Middleware\AssignRequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

/**
 * 操作日志（设计文档 §6.6）：记录已登录用户的写请求，调用 Monitor 的 LogOperation 写入 activitylog 的 `operation` 日志。
 * 放在 admin 中间件组的第二个（紧随 FormatResponse），耗时覆盖本入口的全部处理。
 *
 * - 只记 POST、PUT、PATCH、DELETE；只记请求结束时已登录的后台用户（未登录的写请求不记）；
 * - 认证请求只记登录日志：路由名在 config('core.monitor.operation_log.except_routes') 中的不记（登录、登出、两步验证登录）；
 * - 无论成功失败都记，status 为最终的 HTTP 状态码；
 * - payload 在进入后续中间件之前读取（提交的原始请求体，不含查询字符串与上传的文件），脱敏由 LogOperation 完成；
 * - 在响应发送之后写入（defer()->always()：4xx / 5xx 也写），写入失败只 report()，不影响响应。
 *
 * 常驻内存：本类不保存状态，数据只在 defer 的闭包中捕获；Octane 下由框架的 InvokeDeferredCallbacks 在发送响应后执行。
 */
class RecordOperationLog
{
    /**
     * 记录的请求方法。
     */
    public const array METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly LogOperation $logOperation) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $payload = $request->post();

        $response = $next($request);

        $durationMs = intdiv(hrtime(true) - $startedAt, 1_000_000);
        $user = $request->user();
        $route = $request->route();
        $routeName = $route?->getName();

        $excepted = array_filter(config()->array('core.monitor.operation_log.except_routes'), is_string(...));

        if (! $user instanceof User || ($routeName !== null && Str::is($excepted, $routeName))) {
            return $response;
        }

        $description = $routeName ?? $request->method().' /'.ltrim($route?->uri() ?? $request->path(), '/');
        $requestId = Context::get(AssignRequestId::CONTEXT_KEY);
        $properties = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_parameters' => $route?->originalParameters() ?? [],
            'payload' => $payload,
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => is_string($requestId) ? $requestId : null,
        ];

        defer(function () use ($user, $description, $properties): void {
            try {
                $this->logOperation->handle($user, $description, $properties);
            } catch (Throwable $e) {
                report($e);
            }
        })->always();

        return $response;
    }
}
