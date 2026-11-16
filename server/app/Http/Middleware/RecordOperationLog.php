<?php

namespace App\Http\Middleware;

use App\Models\OperationLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 记录已登录用户的非 GET 请求（响应发送后写入）。
 */
class RecordOperationLog
{
    protected const string MASK = '******';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('operation_log_started_at', hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user instanceof User || $request->isMethodSafe()) {
            return;
        }

        $startedAt = $request->attributes->get('operation_log_started_at');
        $routeName = $request->route()?->getName();

        OperationLog::query()->create([
            'user_id' => $user->getKey(),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $routeName,
            'summary' => $this->summary($routeName),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => is_int($startedAt) ? intdiv(hrtime(true) - $startedAt, 1_000_000) : 0,
            'payload' => $this->mask($request->input()),
        ]);
    }

    /**
     * 操作摘要取自 lang/{locale}/operations.php 中以路由名为键的文案。
     */
    protected function summary(?string $routeName): ?string
    {
        $key = "operations.{$routeName}";

        return $routeName !== null && Lang::has($key) ? (string) __($key) : null;
    }

    /**
     * 递归脱敏 config('edge.logs.sensitive_fields') 匹配的字段。
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected function mask(array $payload): array
    {
        /** @var list<string> $patterns */
        $patterns = config('edge.logs.sensitive_fields', []);

        foreach ($payload as $key => $value) {
            if (is_string($key) && Str::is($patterns, Str::lower($key))) {
                $payload[$key] = self::MASK;
            } elseif (is_array($value)) {
                $payload[$key] = $this->mask($value);
            }
        }

        return Arr::except($payload, ['_token']);
    }
}
