<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\{CoreConfigFile, EnvExampleFile, PhpunitFile, ProvidersFile};
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-app：新入口 app/Http/<名称>（设计文档 §5.7）：
 *
 * - `<名称>ServiceProvider`（路由、URL 前缀、中间件组、限流器）与 `routes.php`；
 * - `tests/Http/<名称>/<名称>ServiceProviderTest.php`：按 CORE_APPS 加载、路由前缀、登录、限流的基础测试；
 * - 登记到 config/core.php（入口清单）、bootstrap/providers.php、phpunit.xml 与 .env.example 的 CORE_APPS（幂等）。
 *
 * 默认是受信任的入口（trusted=true，可直接读取模型）；`--restricted` 生成受限的入口（trusted=false，只能通过模块的
 * Contracts 访问数据，tests/Arch 强制），对外开放，URL 前缀从第一版起带 `/v1`。入口专用的中间件按需放在 Middleware/。
 */
#[Signature('core:make-app
    {name : 入口名，如 device（config/core.php 中的键为 snake_case，目录为 StudlyCase）}
    {--restricted : 受限的入口：trusted=false，只能通过模块的 Contracts 访问数据，URL 前缀带 /v1}
    {--f|force : 文件已存在时覆盖（登记幂等，不会重复）}')]
#[Description('生成入口：ServiceProvider、routes.php、基础测试，并登记到 config/core.php、bootstrap/providers.php、phpunit.xml')]
class AppMakeCommand extends CoreGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'App';

    /**
     * 新入口每分钟请求上限的默认值。
     */
    private const int RATE_LIMIT = 60;

    protected function stubName(): string
    {
        return 'app.provider';
    }

    /**
     * 输入错误时返回错误信息：`Middleware` 是多入口共用的中间件目录。
     */
    protected function inputError(): ?string
    {
        return parent::inputError() ?? ($this->getNameInput() === 'Middleware'
            ? 'app/Http/Middleware 是多个入口共用的中间件目录，不能用作入口名。'
            : null);
    }

    /**
     * 入口的 ServiceProvider：App\Http\<名称>\<名称>ServiceProvider。
     *
     * @param  string  $name
     */
    protected function qualifyClass($name): string
    {
        return $this->laravel->getNamespace().'Http\\'.$name.'\\'.$name.'ServiceProvider';
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, array{string, string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => ['入口名？', '如 device']];
    }

    /**
     * 生成路由文件与测试，并登记到共享文件。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $name = $this->getNameInput();
        $key = $this->key();
        $replacements = $this->replacements($class);
        $env = [$this->env().'_PREFIX' => $this->prefix(), $this->env().'_RATE_LIMIT' => (string) self::RATE_LIMIT];

        $routes = $this->writeFromStub('app.routes', dirname($path).'/routes.php', $replacements, 'Routes');
        $test = $this->writeFromStub($this->option('restricted') ? 'app.test.restricted' : 'app.test', base_path("tests/Http/{$name}/{$name}ServiceProviderTest.php"), $replacements, 'Test');

        $entry = $this->replace($this->files->get($this->resolveStub('app.config')), $replacements);

        $this->register(config_path('core.php'), "入口 {$key}", fn (string $code): ?string => CoreConfigFile::registerApp($code, $key, $entry));
        $this->register($this->laravel->bootstrapPath('providers.php'), class_basename($class), fn (string $code): ?string => ProvidersFile::register($code, $class));
        $this->register(base_path('phpunit.xml'), "CORE_APPS 中的 {$key}", fn (string $xml): ?string => PhpunitFile::registerApp($xml, $key, $env));

        if ($this->files->exists(base_path('.env.example'))) {
            $this->register(base_path('.env.example'), "CORE_APPS 中的 {$key}", fn (string $content): ?string => EnvExampleFile::registerApp($content, $key, $env));
        }

        $this->components->bulletList([
            "本地 .env 中的 CORE_APPS 若明确列出了入口，请加上 {$key}",
            "接口：php artisan core:make-controller <名称> --app={$key} --module=<模块>".($this->option('restricted') ? '（受限入口通过 core:make-contract 生成的契约访问数据）' : ''),
        ]);

        return $routes && $test ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $restricted = (bool) $this->option('restricted');

        return [
            'app' => $this->getNameInput(),
            'key' => $this->key(),
            'provider' => class_basename($class),
            'prefix' => $this->prefix(),
            'env' => $this->env(),
            'rateLimit' => (string) self::RATE_LIMIT,
            'trusted' => $restricted ? 'false' : 'true',
            'trustLevel' => $restricted ? '受限的入口' : '受信任的入口',
            'access' => $restricted
                ? '受限的入口：只能通过模块的 Contracts 读写数据，不使用任何模型（设计文档 §5.2，tests/Arch 强制）；对外开放，路由从第一版起带版本号。'
                : '受信任的入口：可直接读取 Core 与业务模块的模型，写入必须调用模块的 Action（设计文档 §5.2）。',
            // 认证（设计文档 §7.1）：受信任的入口与后台相同，前端请求使用 Sanctum 会话 Cookie；受限的入口只用 Bearer token
            'authentication' => $restricted
                ? '无状态：不启动会话、不校验 CSRF，只用 Sanctum 的 Bearer token 认证；'
                : 'EnsureFrontendRequestsAreStateful：来自 SANCTUM_STATEFUL_DOMAINS 的前端请求使用会话 Cookie 并校验 CSRF，其他请求只能用 Bearer token；',
            'authenticatedBy' => $restricted ? 'Bearer token' : '前端的会话 Cookie 或 Bearer token',
            'statefulImport' => $restricted ? '' : "use Laravel\\Sanctum\\Http\\Middleware\\EnsureFrontendRequestsAreStateful;\n",
            'statefulMiddleware' => $restricted ? '' : "EnsureFrontendRequestsAreStateful::class,\n            ",
        ];
    }

    /**
     * config/core.php 中的键：snake_case，如 device、user_center。
     */
    private function key(): string
    {
        return Str::snake($this->getNameInput());
    }

    /**
     * 默认的 URL 前缀：api/<kebab-case>，受限入口再加 /v1。
     */
    private function prefix(): string
    {
        return 'api/'.Str::kebab($this->getNameInput()).($this->option('restricted') ? '/v1' : '');
    }

    /**
     * 环境变量前缀，如 CORE_DEVICE。
     */
    private function env(): string
    {
        return 'CORE_'.Str::upper($this->key());
    }
}
