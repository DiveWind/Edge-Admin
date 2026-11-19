<?php

use Illuminate\Database\{Connection, ConnectionInterface, DatabaseManager};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Schema};

require_once __DIR__.'/helpers.php';

/*
|--------------------------------------------------------------------------
| 分层与依赖边界（设计文档 §5.1、§5.2、§5.3、§5.6）
|--------------------------------------------------------------------------
|
| Http（入口） → Modules（业务） → Core（内核） → Support；入口之间互不依赖，业务模块之间默认互不依赖。
| “入口写数据必须调用 Action”由 PHPStan（spaze/phpstan-disallowed-calls，见 phpstan.neon）检查。
|
*/

arch('Core 不依赖业务模块与入口')
    ->expect('App\Core')
    ->not->toUse(['App\Modules', 'App\Http']);

arch('业务模块不依赖入口')
    ->expect('App\Modules')
    ->not->toUse('App\Http');

arch('Support 不依赖 Core、业务模块与入口')
    ->expect('App\Support')
    ->not->toUse(['App\Core', 'App\Modules', 'App\Http']);

// 注意：ignoring() 同时作用于被检查的目标，不能用“ignoring(自身命名空间)”表达“只允许依赖自己”，这里显式列出其他命名空间。
$entries = array_map(fn (string $entry): string => "App\\Http\\{$entry}", archEntries());

if ($entries !== []) {
    arch('多入口共用的中间件不依赖任何入口')
        ->expect('App\Http\Middleware')
        ->not->toUse($entries);
}

foreach ($entries as $entry) {
    if (($others = array_values(array_diff($entries, [$entry]))) !== []) {
        arch("入口 {$entry} 不依赖其他入口")
            ->expect($entry)
            ->not->toUse($others);
    }
}

$modules = archNamespaces('Modules/*');

foreach ($modules as $module) {
    if (($others = array_values(array_diff($modules, [$module]))) !== []) {
        arch("业务模块 {$module} 只通过事件或对方的 Contracts / Data 使用其他业务模块")
            ->expect($module)
            ->not->toUse($others)
            ->ignoring(array_merge(...array_map(fn (string $other): array => ["{$other}\\Contracts", "{$other}\\Data", "{$other}\\Events"], $others)));
    }
}

test('业务模块的测试只在它自己的测试目录中（删除业务模块后其余测试不受影响，设计文档 §4.2、§4.4）', function (): void {
    $violations = [];

    foreach (archNamespaces('Modules/*') as $module) {
        $name = substr($module, strlen('App\\Modules\\'));
        $own = ["Modules/{$name}/", ...array_map(fn (string $entry): string => "Http/{$entry}/{$name}/", archEntries())];

        foreach (archPhpFiles(['tests']) as $file) {
            $path = $file->getRelativePathname();

            if (array_any($own, fn (string $prefix): bool => str_starts_with($path, $prefix))) {
                continue;
            }

            // 只看代码中的类名（use 与限定名），不看字符串：生成器的测试以 Content 为示例名，在临时项目中生成代码
            $references = array_filter(
                PhpToken::tokenize($file->getContents()),
                fn (PhpToken $token): bool => $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && str_starts_with(ltrim($token->text, '\\').'\\', $module.'\\'),
            );

            if ($references !== []) {
                $violations[] = "tests/{$path} 引用了 {$module}";
            }
        }
    }

    expect($violations)->toBe([], '业务模块的测试放在 tests/Modules/<模块> 与 tests/Http/<入口>/<模块>；Core 与其他模块的测试不能依赖业务模块');
});

arch('入口不直接访问数据库')
    ->expect('App\Http')
    ->not->toUse([
        DB::class,
        Schema::class,
        DatabaseManager::class,
        ConnectionInterface::class,
        Connection::class,
    ]);

arch('入口内不定义模型')
    ->expect('App\Http')
    ->classes()
    ->not->toExtend(Model::class);

foreach (archEntryConfigs() as $entry => $config) {
    if ($config['trusted'] || archModelNamespaces() === []) {
        continue;
    }

    arch("受限入口 {$entry} 不使用任何模型，只通过模块的 Contracts 访问数据")
        ->expect("App\\Http\\{$entry}")
        ->not->toUse(archModelNamespaces());
}
