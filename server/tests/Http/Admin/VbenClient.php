<?php

namespace Tests\Http\Admin;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\{Route, Router};
use Illuminate\Session\SessionManager;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * 模拟浏览器中的 Vben 前端访问后台入口，用于认证相关的测试：
 *
 * - 带 Referer（SANCTUM_STATEFUL_DOMAINS 中的 localhost:5999），按 Sanctum SPA 模式使用会话 Cookie；
 * - 保存响应中的 Cookie 并在之后的请求中带上，写请求带 X-XSRF-TOKEN（取自 XSRF-TOKEN Cookie，与 axios 相同）；
 * - 每次请求前清空框架在内存中保留的认证与会话状态（守卫、会话、控制器实例），登录状态只能来自会话 Cookie，
 *   多个实例即多个浏览器（设备）；会话使用 database 驱动（与默认配置相同，停用账号、修改密码时按用户删除会话）；
 * - 开启 CSRF 校验（框架在单元测试中默认跳过）；
 * - 界面语言：withLanguage() 之后的请求带该 Accept-Language（与 Vben 切换语言相同），默认为测试请求自带的 `en-us`。
 */
final class VbenClient
{
    /**
     * 前端地址（Vite 开发服务器）。
     */
    public const string ORIGIN = 'http://localhost:5999';

    /**
     * Cookie 名 => 原始值（响应中加密后的值）。
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * 不带 X-XSRF-TOKEN 发送下一个写请求。
     */
    private bool $withoutCsrfToken = false;

    /**
     * 请求头 Accept-Language；null 时使用测试请求的默认值。
     */
    private ?string $language = null;

    public function __construct(private readonly TestCase $test)
    {
        config(['session.driver' => 'database']);

        self::enforceCsrf(app());
    }

    /**
     * 让 CSRF 校验在测试中生效（框架的 PreventRequestForgery 在单元测试中直接放行）。
     */
    public static function enforceCsrf(Application $app): void
    {
        $app->bind(PreventRequestForgery::class, fn (Application $app): PreventRequestForgery => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    /**
     * 登录流程：先取 CSRF Cookie，再提交用户名与密码。
     */
    public function login(string $username, string $password = 'password'): TestResponse
    {
        $this->get('/api/admin/sanctum/csrf-cookie')->assertOk();

        return $this->post('/api/admin/login', ['username' => $username, 'password' => $password]);
    }

    /**
     * 下一个写请求不带 X-XSRF-TOKEN。
     */
    public function withoutCsrfToken(): self
    {
        $this->withoutCsrfToken = true;

        return $this;
    }

    /**
     * 之后的请求带此 Accept-Language（如 `zh-CN`、`en-US`）。
     */
    public function withLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function get(string $uri): TestResponse
    {
        return $this->send('GET', $uri);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): TestResponse
    {
        return $this->send('POST', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): TestResponse
    {
        return $this->send('PUT', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $uri, array $data = []): TestResponse
    {
        return $this->send('DELETE', $uri, $data);
    }

    /**
     * 当前保存的 Cookie 值（原始值）。
     */
    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(string $method, string $uri, array $data = []): TestResponse
    {
        $this->forgetRequestState();

        $server = [
            'HTTP_ACCEPT' => 'application/json, text/plain, */*',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_REFERER' => self::ORIGIN.'/',
        ];

        if ($this->language !== null) {
            $server['HTTP_ACCEPT_LANGUAGE'] = $this->language;
        }

        if ($method !== 'GET' && ! $this->withoutCsrfToken && isset($this->cookies['XSRF-TOKEN'])) {
            $server['HTTP_X_XSRF_TOKEN'] = $this->cookies['XSRF-TOKEN'];
        }

        $this->withoutCsrfToken = false;

        $response = $this->test->call($method, $uri, [], $this->cookies, [], $server, $data === [] ? null : (string) json_encode($data));

        foreach ($response->headers->getCookies() as $cookie) {
            $this->remember($cookie);
        }

        return $response;
    }

    private function remember(Cookie $cookie): void
    {
        if ($cookie->isCleared() || $cookie->getValue() === null) {
            unset($this->cookies[$cookie->getName()]);

            return;
        }

        $this->cookies[$cookie->getName()] = $cookie->getValue();
    }

    /**
     * 清空上一个请求留在内存中的状态（PHP-FPM 下每个请求本来就是全新的）：认证守卫、会话、控制器实例与 scoped 实例。
     */
    private function forgetRequestState(): void
    {
        $app = app();

        $app->make(Factory::class)->forgetGuards();
        $app->forgetInstance('auth.driver');
        $app->make(SessionManager::class)->forgetDrivers();
        $app->forgetInstance('session.store');
        $app->forgetScopedInstances();

        foreach ($app->make(Router::class)->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $route->flushController();
        }
    }
}
