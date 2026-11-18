<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 以给定的环境变量重新创建应用（如测试 CORE_APPS、CORE_ADMIN_PREFIX），创建后立即还原。
     *
     * 只接受字符串：重建应用时 phpdotenv 会接管当时未定义的变量（之后每次重建都用 .env 中的值覆盖），
     * 需要“未设置”的场景请用 withEnv() 直接读取配置文件。
     *
     * @param  array<string, string>  $env
     */
    protected function refreshApplicationWithEnv(array $env): void
    {
        $this->withEnv($env, fn () => $this->refreshApplication());
    }

    /**
     * 临时设置（null 为删除）环境变量并执行回调，结束后还原。
     *
     * @template TReturn
     *
     * @param  array<string, string|null>  $env
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function withEnv(array $env, Closure $callback): mixed
    {
        $originals = [];

        foreach ($env as $name => $value) {
            $originals[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];

            $this->putEnv($name, $value, $value, $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($originals as $name => [$env, $server, $putenv]) {
                $this->putEnv($name, $env, $server, $putenv === false ? null : $putenv);
            }
        }
    }

    private function putEnv(string $name, mixed $env, mixed $server, ?string $putenv): void
    {
        unset($_ENV[$name], $_SERVER[$name]);

        if ($env !== null) {
            $_ENV[$name] = $env;
        }

        if ($server !== null) {
            $_SERVER[$name] = $server;
        }

        putenv($putenv === null ? $name : "{$name}={$putenv}");
    }
}
