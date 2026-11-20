<?php

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Str;
use Tests\Core\Generator\ProjectCopy;

/*
|--------------------------------------------------------------------------
| 端到端：core:make-crud 的生成物不手改即通过全部检查（设计文档 §10.4）
|--------------------------------------------------------------------------
|
| 较慢（数分钟），不在 composer test 中执行（phpunit.xml 排除 generator-e2e 组）；用 composer test:generator-e2e 执行，CI 中单独一步。
|
| 在系统临时目录中建一份项目副本（ProjectCopy，vendor 用硬链接），在副本中：
| 1. 用全部选项生成新模块（Blog / Post），在同一模块中用默认选项再加一个模型（Tag，共享文件的追加），
|    再用最少的选项生成另一个模块（Shop / Product，不使用数据范围）；
| 2. 执行 Pint、PHPStan（max）、Rector（--dry-run）与全部 Pest 测试（含生成的授权测试与 tests/Arch）；
| 3. migrate:fresh --seed 与 core:sync；
| 最后删除副本，真实的工作区不留任何文件。
|
*/

/**
 * 命令失败时的说明：命令与输出的末尾。
 */
function generatorE2eFailure(string $step, ProcessResult $result): string
{
    return "{$step} 失败（退出码 {$result->exitCode()}）：\n".Str::of($result->output().$result->errorOutput())->trim()->substr(-6000);
}

it('generates CRUD modules that pass every check without manual edits', function (): void {
    $copy = ProjectCopy::create(base_path());

    try {
        $database = $copy->path('database/generator-e2e.sqlite');
        touch($database);

        // 副本中没有 .env：加密密钥显式给出（会话、Cookie 需要）；迁移与同步用副本中的 SQLite 文件库
        $key = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        $env = [
            ...$key,
            'APP_ENV' => 'local',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        $generate = [
            ['core:make-module', 'Blog', '--crud=Post', '--soft-deletes', '--export', '--audit'],
            ['core:make-crud', 'Tag', '--module=Blog'],
            ['core:make-module', 'Shop', '--crud=Product', '--no-data-scope'],
        ];

        foreach ($generate as $command) {
            $result = $copy->run([PHP_BINARY, 'artisan', ...$command, '--no-interaction'], $env);

            expect($result->successful())->toBeTrue(generatorE2eFailure(implode(' ', $command), $result));
        }

        $generated = [
            ...$copy->files('app/Modules/Blog'), ...$copy->files('app/Modules/Shop'),
            ...$copy->files('app/Http/Admin/Blog'), ...$copy->files('app/Http/Admin/Shop'),
            ...$copy->files('tests/Http/Admin/Blog'), ...$copy->files('tests/Http/Admin/Shop'), ...$copy->files('tests/Modules/Blog'),
            ...$copy->files('database/factories/Modules/Blog'), ...$copy->files('database/factories/Modules/Shop'),
        ];

        expect($generated)->toContain(
            'app/Modules/Blog/Actions/RestorePost.php', 'app/Modules/Blog/Exporters/PostExporter.php', 'app/Modules/Blog/resources/exporters.php',
            'app/Http/Admin/Blog/Controllers/TagController.php', 'app/Http/Admin/Shop/Controllers/ProductController.php',
            'tests/Http/Admin/Blog/Controllers/PostExportControllerTest.php', 'tests/Modules/Blog/Actions/PostActivityTest.php',
        );

        foreach ($generated as $file) {
            expect(file_get_contents($copy->path($file)))->not->toContain('{{', "{$file} 中有未替换的模板标记");
        }

        $checks = [
            'Pint' => [PHP_BINARY, 'vendor/bin/pint', '--test'],
            'PHPStan' => [PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress'],
            'Rector' => [PHP_BINARY, 'vendor/bin/rector', '--dry-run', '--no-progress-bar'],
            'Pest' => [PHP_BINARY, 'artisan', 'test', '--compact'],
            'migrate:fresh --seed' => [PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'],
            'core:sync' => [PHP_BINARY, 'artisan', 'core:sync', '--no-interaction'],
        ];

        foreach ($checks as $step => $command) {
            $result = $copy->run($command, in_array($step, ['migrate:fresh --seed', 'core:sync'], true) ? $env : $key);

            expect($result->successful())->toBeTrue(generatorE2eFailure($step, $result));
        }

        $routes = $copy->run([PHP_BINARY, 'artisan', 'route:list', '--path=blog', '--json'], $env);

        expect($routes->output())->toContain('admin.blog.posts.restore', 'admin.blog.posts.export', 'admin.blog.tags.index');
    } finally {
        $copy->delete();
    }

    expect(is_dir($copy->path))->toBeFalse();
})->group('generator-e2e');
