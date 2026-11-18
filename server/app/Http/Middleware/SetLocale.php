<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * 按请求头 `Accept-Language` 选择本次请求的提示语言（设计文档 §6.1）。
 *
 * - 可选语言见 config('core.locales')（如 `zh_CN`、`en`）；`zh-CN`、`zh_cn`、`zh`、`en-US` 等写法按语言与地区匹配，
 *   按 q 值从高到低取第一个支持的语言；地区不同时按主语言匹配（`en-GB` → `en`、`zh` → `zh_CN`）；
 * - 未带请求头或都不支持时使用应用默认语言（APP_LOCALE）；
 * - 响应带 `Content-Language`（如 `zh-CN`）与 `Vary: Accept-Language`。
 *
 * 作为全局中间件（bootstrap/app.php），各入口与未匹配路由的 404 都适用。请求结束后恢复默认语言，
 * 常驻内存（Octane）下不影响下一个请求。
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
        $default = App::getLocale();
        $locale = $this->preferredLocale($request) ?? $default;

        App::setLocale($locale);

        try {
            $response = $next($request);
        } finally {
            App::setLocale($default);
        }

        $response->headers->set('Content-Language', str_replace('_', '-', $locale));
        $response->setVary('Accept-Language', false);

        return $response;
    }

    /**
     * 请求的语言中第一个支持的语言；没有时返回 null。
     */
    protected function preferredLocale(Request $request): ?string
    {
        $supported = $this->supportedLocales();

        // getLanguages() 已按 q 值排序，并把 `zh-cn` 规范为 `zh_CN`
        foreach ($request->getLanguages() as $language) {
            $locale = $supported[strtolower($language)]
                ?? array_find($supported, fn (string $locale): bool => $this->primary($locale) === $this->primary($language));

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * 小写的语言代码 => 语言包目录名，如 `zh_cn` => `zh_CN`。
     *
     * @return array<string, string>
     */
    protected function supportedLocales(): array
    {
        $locales = [];

        foreach (config()->array('core.locales') as $locale) {
            if (is_string($locale) && $locale !== '') {
                $locales[strtolower($locale)] = $locale;
            }
        }

        return $locales;
    }

    /**
     * 主语言（`zh_CN` → `zh`）。
     */
    private function primary(string $locale): string
    {
        return strtolower(explode('_', str_replace('-', '_', $locale), 2)[0]);
    }
}
