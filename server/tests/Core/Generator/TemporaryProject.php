<?php

namespace Tests\Core\Generator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * 生成器测试用的隔离项目：系统临时目录下的最小项目骨架，并把应用的根目录切换过去，生成的文件不会写进真实项目。
 *
 * 骨架（FILES）与本项目的共享文件格式相同，但内容固定，测试结果不随项目中已有的入口、模块变化；
 * 需要验证本项目真实的共享文件时用 copyFromProject()。应用在每个测试后重建，根目录随之恢复；delete() 删除临时目录。
 */
final readonly class TemporaryProject
{
    /**
     * 骨架文件：相对路径 => 内容。
     *
     * @var array<string, string>
     */
    private const array FILES = [
        'composer.json' => '{"autoload": {"psr-4": {"App\\\\": "app/"}}}',
        'config/core.php' => <<<'PHP'
            <?php

            $only = env('CORE_APPS');

            $only = is_string($only)
                ? array_values(array_filter(array_map(trim(...), explode(',', strtolower($only)))))
                : null;

            $enabled = fn (string $app): bool => $only === null || in_array($app, $only, true);

            return [

                'apps' => [

                    // 后台 API
                    'admin' => [
                        'enabled' => $enabled('admin'),
                        'trusted' => true,
                        'prefix' => env('CORE_ADMIN_PREFIX', 'api/admin'),
                        'rate_limit' => (int) env('CORE_ADMIN_RATE_LIMIT', 120),
                    ],

                ],

            ];

            PHP,
        'bootstrap/providers.php' => <<<'PHP'
            <?php

            use App\Core\Generator\GeneratorServiceProvider;
            use App\Http\Admin\AdminServiceProvider;
            use App\Providers\AppServiceProvider;

            return [
                AppServiceProvider::class,

                // 内核模块（app/Core）
                GeneratorServiceProvider::class,

                // 入口：是否加载由 config('core.apps') / CORE_APPS 决定
                AdminServiceProvider::class,
            ];

            PHP,
        'phpunit.xml' => <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <php>
                    <env name="APP_ENV" value="testing"/>
                    <env name="CORE_APPS" value="admin"/>
                    <env name="CORE_ADMIN_PREFIX" value="api/admin"/>
                    <env name="CORE_ADMIN_RATE_LIMIT" value="120"/>
                </php>
            </phpunit>

            XML,
        '.env.example' => "APP_NAME=Laravel\n\nCORE_APPS=admin\n# CORE_ADMIN_PREFIX=api/admin\n\nAPP_MAINTENANCE_DRIVER=file\n",
        'app/Http/Admin/AdminServiceProvider.php' => "<?php\n\nnamespace App\\Http\\Admin;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AdminServiceProvider extends ServiceProvider {}\n",
        'app/Http/Admin/routes.php' => <<<'PHP'
            <?php

            use Illuminate\Support\Facades\Route;

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::get('user/info', fn (): array => [])->name('user.info');
            });

            PHP,
        'app/Http/Admin/Requests/ListRequest.php' => "<?php\n\nnamespace App\\Http\\Admin\\Requests;\n\nuse Illuminate\\Foundation\\Http\\FormRequest;\n\nclass ListRequest extends FormRequest {}\n",
    ];

    private function __construct(public string $path, public string $source) {}

    public static function create(Application $app): self
    {
        $project = new self(sys_get_temp_dir().'/core-generator-'.Str::lower(Str::random(16)), $app->basePath());

        foreach (self::FILES as $file => $contents) {
            $project->put($file, $contents);
        }

        new Filesystem()->ensureDirectoryExists($project->path('app/Core/System/Models'));
        new Filesystem()->ensureDirectoryExists($project->path('database/migrations'));

        $app->setBasePath($project->path);

        return $project;
    }

    /**
     * 用本项目中的真实文件替换骨架中的同名文件。
     */
    public function copyFromProject(string ...$files): void
    {
        foreach ($files as $file) {
            $this->put($file, (string) file_get_contents($this->source.'/'.$file));
        }
    }

    public function path(string $file = ''): string
    {
        return $this->path.($file === '' ? '' : '/'.$file);
    }

    public function exists(string $file): bool
    {
        return file_exists($this->path($file));
    }

    public function read(string $file): string
    {
        return (string) file_get_contents($this->path($file));
    }

    public function put(string $file, string $contents): void
    {
        new Filesystem()->ensureDirectoryExists(dirname($this->path($file)));

        file_put_contents($this->path($file), $contents);
    }

    /**
     * 项目中的文件（相对路径，排序后），可限定在某个目录下。
     *
     * @return list<string>
     */
    public function files(string $directory = ''): array
    {
        if (! is_dir($this->path($directory))) {
            return [];
        }

        $files = array_map(
            fn (SplFileInfo $file): string => Str::after($file->getPathname(), $this->path.'/'),
            iterator_to_array(Finder::create()->files()->ignoreDotFiles(false)->in($this->path($directory)), false),
        );

        sort($files);

        return $files;
    }

    /**
     * 用本项目的 Pint 配置检查临时项目中的 PHP 文件：通过时返回 null，否则返回 Pint 的输出。
     */
    public function pintFailures(): ?string
    {
        $result = Process::path($this->source)->run([PHP_BINARY, 'vendor/bin/pint', '--test', '--config='.$this->source.'/pint.json', $this->path]);

        return $result->successful() ? null : $result->output().$result->errorOutput();
    }

    public function delete(): void
    {
        new Filesystem()->deleteDirectory($this->path);
    }
}
