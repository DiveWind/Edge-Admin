<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 按 Accept-Language 切换接口语言：仅限 config('edge.locales')（zh-CN / zh_CN / en-US / en 等写法均可），
 * 未携带或不匹配时使用 APP_LOCALE。
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default = config()->string('app.locale');

        /** @var list<string> $supported */
        $supported = config('edge.locales', []);

        // 默认语言排在首位：请求头缺失或不匹配时 getPreferredLanguage() 返回首项。
        $locale = $request->getPreferredLanguage(array_values(array_unique([$default, ...$supported])));

        app()->setLocale($locale ?? $default);

        $response = $next($request);
        $response->headers->set('Content-Language', str_replace('_', '-', app()->getLocale()));
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
