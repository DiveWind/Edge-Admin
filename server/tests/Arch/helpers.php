<?php

use Illuminate\Support\Str;
use Symfony\Component\Finder\{Finder, SplFileInfo};

/*
|--------------------------------------------------------------------------
| 架构测试的辅助函数
|--------------------------------------------------------------------------
|
| 架构规则在测试注册时（应用启动前）就要知道有哪些入口、模块与目录，这里直接读取文件系统与 config/core.php。
| Pest 的通配命名空间（App\Modules\*\Models）在目录不存在时会报错，因此只为已存在的目录注册规则。
|
*/

function archBasePath(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}

/**
 * 入口：app/Http 下除 Middleware（多入口共用的中间件）外的目录名，如 ['Admin']。
 *
 * @return list<string>
 */
function archEntries(): array
{
    return array_values(array_filter(
        array_map(basename(...), glob(archBasePath('app/Http/*'), GLOB_ONLYDIR) ?: []),
        fn (string $name): bool => $name !== 'Middleware',
    ));
}

/**
 * config/core.php 中登记的入口：入口目录名（StudlyCase）=> 配置。
 *
 * @return array<string, array{trusted: bool}>
 */
function archEntryConfigs(): array
{
    /** @var array{apps: array<string, array{trusted: bool}>} $config */
    $config = require archBasePath('config/core.php');

    return collect($config['apps'])->mapWithKeys(fn (array $app, string $name): array => [Str::studly($name) => $app])->all();
}

/**
 * Core 与业务模块的名称（App\Core\<Name>、App\Modules\<Name>）的命名空间。
 *
 * @return list<string>
 */
function archModules(): array
{
    return [...archNamespaces('Core/*'), ...archNamespaces('Modules/*')];
}

/**
 * 与 glob 模式匹配的、已存在的 app/ 子目录对应的命名空间，如 archNamespaces('Core/*\/Models') → ['App\Core\System\Models']。
 *
 * @return list<string>
 */
function archNamespaces(string ...$patterns): array
{
    $app = archBasePath('app/');

    return collect($patterns)
        ->flatMap(fn (string $pattern): array => glob($app.$pattern, GLOB_ONLYDIR) ?: [])
        ->map(fn (string $directory): string => 'App\\'.str_replace('/', '\\', substr($directory, strlen($app))))
        ->unique()
        ->values()
        ->all();
}

/**
 * 模型所在的命名空间（Core 与业务模块的 Models 目录）。
 *
 * @return list<string>
 */
function archModelNamespaces(): array
{
    return archNamespaces('Core/*/Models', 'Modules/*/Models');
}

/**
 * 给定目录（相对项目根目录）下的 PHP 文件。
 *
 * @param  list<string>  $paths  目录或文件
 * @return list<SplFileInfo>
 */
function archPhpFiles(array $paths): array
{
    $directories = array_values(array_filter(array_map(archBasePath(...), $paths), is_dir(...)));
    $files = array_values(array_filter(array_map(archBasePath(...), $paths), is_file(...)));

    $found = $directories === [] ? [] : iterator_to_array(Finder::create()->files()->name('*.php')->in($directories), false);

    return [...$found, ...array_map(fn (string $file): SplFileInfo => new SplFileInfo($file, '', basename($file)), $files)];
}

/**
 * app/ 下声明的类、trait、接口与枚举（按 PSR-4 由路径推出类名，不含 routes.php 这类非类文件）。
 *
 * @return list<class-string>
 */
function archClasses(): array
{
    return collect(archPhpFiles(['app']))
        ->map(fn (SplFileInfo $file): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class): bool => class_exists($class) || trait_exists($class) || interface_exists($class) || enum_exists($class))
        ->values()
        ->all();
}

/**
 * 文件中对给定函数的调用（不含方法调用、静态调用与函数声明），返回行号。
 *
 * @param  list<string>  $functions  小写函数名
 * @return list<array{function: string, line: int}>
 */
function archFunctionCalls(string $code, array $functions): array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($code),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));

    $calls = [];

    foreach ($tokens as $index => $token) {
        if ($token->is(T_EXIT)) {
            $name = strtolower($token->text);
        } elseif ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && ($tokens[$index + 1] ?? null)?->text === '(') {
            $name = strtolower(ltrim($token->text, '\\'));
        } else {
            continue;
        }

        $previous = $tokens[$index - 1] ?? null;

        if (in_array($name, $functions, true) && ! $previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST])) {
            $calls[] = ['function' => $name, 'line' => $token->line];
        }
    }

    return $calls;
}
