<?php

namespace App\Http\Admin\Responses;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * 后台入口（Admin）的 Vben 响应格式（设计文档 §6.1）。HTTP 状态码照常使用，响应体统一为：
 *
 * - 成功：`{ "code": 0, "data": ..., "message": "ok" }`
 * - 分页：`{ "code": 0, "data": { "items": [...], "total": 123 }, "message": "ok" }`
 * - 失败：`{ "code": <HTTP 状态码>, "data": null, "message": "...", "errors"?: {...} }`
 *
 * 例外：后台入口不返回 204。没有内容的成功响应统一为 HTTP 200 + `{ "code": 0, "data": null, "message": "ok" }`
 * （Vben 的默认响应拦截器遇到空响应体会报错）。
 *
 * 由 FormatResponse 中间件（路由内的响应）与 AdminServiceProvider 注册的异常渲染（含未匹配路由的 404 / 405）生成；
 * 控制器照常返回 API Resource、数组或分页器，需要自定义 message 时用 `response()->vben($data, $message)`。
 * 已是本类实例的响应不会被重复包装。
 */
final class VbenResponse extends JsonResponse
{
    /**
     * 成功响应的 code。
     */
    public const int SUCCESS = 0;

    /**
     * 成功响应的 message。
     */
    public const string OK = 'ok';

    /**
     * 成功响应。204 一律改为 200（见类注释）。
     *
     * @param  array<mixed>  $headers
     */
    public static function success(mixed $data = null, string $message = self::OK, int $status = 200, array $headers = []): self
    {
        return new self(
            ['code' => self::SUCCESS, 'data' => $data, 'message' => $message],
            $status === Response::HTTP_NO_CONTENT ? Response::HTTP_OK : $status,
            $headers,
        );
    }

    /**
     * 失败响应：code 与 HTTP 状态码一致；$extra 追加到顶层（如 422 的 `errors`）。
     *
     * @param  array<string, mixed>  $extra
     * @param  array<mixed>  $headers
     */
    public static function error(int $status, ?string $message = null, array $extra = [], array $headers = []): self
    {
        return new self(
            ['code' => $status, 'data' => null, 'message' => $message ?? self::defaultMessage($status)] + $extra,
            $status,
            $headers,
        );
    }

    /**
     * 把路由返回的响应转换为 Vben 格式，保留状态码（204 除外）、响应头与 Cookie；无需转换时返回 null。
     *
     * - JSON 响应：成功时按 dataOf() 取 data，失败时取 message 与其余顶层字段（如 errors）；
     * - 空响应体（`response()->noContent()`、Fortify 的 `new JsonResponse('', 204)` 等）：成功为 data null，失败为通用文案；
     * - 其他响应（文件下载、流、重定向、有内容的非 JSON 响应）：不转换。
     */
    public static function fromResponse(Response $response): ?self
    {
        if ($response instanceof self) {
            return null;
        }

        $status = $response->getStatusCode();

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);

            $formatted = $status >= 400
                ? self::error($status, self::messageOf($payload), self::extraOf($payload))
                : self::success($status === Response::HTTP_NO_CONTENT ? null : self::dataOf($payload, $response->original), status: $status);

            $formatted->setEncodingOptions($response->getEncodingOptions());

            return $formatted->withHeadersFrom($response);
        }

        if ($response->isRedirection() || ! self::hasEmptyBody($response)) {
            return null;
        }

        return ($status >= 400 ? self::error($status) : self::success(status: $status))->withHeadersFrom($response);
    }

    /**
     * 把异常转换为 Vben 格式。返回 null 时交给框架的默认渲染（HttpResponseException 自带响应，路由内的由 FormatResponse 包装）。
     *
     * 404 / 405 统一使用通用文案，不暴露路由路径与模型类名；调试模式下 500 附带 `debug` 信息。
     */
    public static function fromException(Throwable $e): ?self
    {
        return match (true) {
            $e instanceof HttpResponseException => null,
            $e instanceof ValidationException => self::error($e->status, self::translate('The given data was invalid.'), ['errors' => $e->errors()]),
            $e instanceof AuthenticationException => self::error(Response::HTTP_UNAUTHORIZED, self::translate($e->getMessage())),
            $e instanceof HttpExceptionInterface => self::error(
                $e->getStatusCode(),
                in_array($e->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED], true) || $e->getMessage() === ''
                    ? null
                    : self::translate($e->getMessage()),
                headers: $e->getHeaders(),
            ),
            default => self::serverError($e),
        };
    }

    /**
     * 复制原响应的响应头与 Cookie（Content-Type、Content-Length 以本响应为准）。
     */
    private function withHeadersFrom(Response $response): self
    {
        $this->headers->add(Arr::except(
            $response->headers->allPreserveCaseWithoutCookies(),
            ['Content-Type', 'content-type', 'Content-Length', 'content-length'],
        ));

        foreach ($response->headers->getCookies() as $cookie) {
            $this->headers->setCookie($cookie);
        }

        return $this;
    }

    /**
     * 500：生产环境只返回通用文案；调试模式附带异常信息，与框架默认的 JSON 输出一致。
     */
    private static function serverError(Throwable $e): self
    {
        if (! config()->boolean('app.debug')) {
            return self::error(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::error(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage(), ['debug' => [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_map(fn (array $frame): array => Arr::except($frame, ['args']), $e->getTrace()),
        ]]);
    }

    /**
     * 成功响应的 data：
     * - 分页器（`paginate()` 的结果）→ `{ items, total }`；
     * - API Resource 的默认包装（只含 `data`，分页时还有 `links` / `meta`）→ 去掉包装；分页集合 → `{ items, total }`；
     * - 空字符串（Fortify 等返回的 `new JsonResponse('')`）→ null；
     * - 其他 → 原样。
     */
    private static function dataOf(mixed $payload, mixed $original): mixed
    {
        if ($payload === '') {
            return null;
        }

        if (! is_array($payload)) {
            return $payload;
        }

        if ($original instanceof LengthAwarePaginator) {
            return ['items' => $payload['data'] ?? [], 'total' => $original->total()];
        }

        if (! array_key_exists('data', $payload) || array_diff(array_keys($payload), ['data', 'links', 'meta']) !== []) {
            return $payload;
        }

        $total = data_get($payload, 'meta.total');

        return is_int($total) ? ['items' => $payload['data'], 'total' => $total] : $payload['data'];
    }

    private static function messageOf(mixed $payload): ?string
    {
        $message = is_array($payload) ? ($payload['message'] ?? null) : null;

        return is_string($message) && $message !== '' ? self::translate($message) : null;
    }

    /**
     * 失败响应中除 code / data / message 外的顶层字段（如 errors）。
     *
     * @return array<string, mixed>
     */
    private static function extraOf(mixed $payload): array
    {
        $extra = [];

        foreach (is_array($payload) ? $payload : [] as $key => $value) {
            if (is_string($key) && ! in_array($key, ['code', 'data', 'message'], true)) {
                $extra[$key] = $value;
            }
        }

        return $extra;
    }

    /**
     * 是否为空响应体（流式响应、文件响应的 getContent() 为 false，不算空）。
     */
    private static function hasEmptyBody(Response $response): bool
    {
        return $response->getStatusCode() === Response::HTTP_NO_CONTENT || $response->getContent() === '';
    }

    private static function defaultMessage(int $status): string
    {
        return self::translate(Response::$statusTexts[$status] ?? 'Unknown Error');
    }

    private static function translate(string $message): string
    {
        $translated = __($message);

        return is_string($translated) ? $translated : $message;
    }
}
