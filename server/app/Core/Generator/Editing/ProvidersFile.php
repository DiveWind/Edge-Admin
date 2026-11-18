<?php

namespace App\Core\Generator\Editing;

use Illuminate\Support\Str;

/**
 * 在 bootstrap/providers.php 中登记 ServiceProvider（幂等）。
 *
 * 预期格式：顶层 `return [` … `];`，每项独占一行（`XxxServiceProvider::class,`），可有空行与 `//` 注释。
 * 按类别分组，组的顺序为 App\Providers → 内核模块 → 业务模块 → 入口：插在同类的最后一项之后；
 * 还没有同类时，带上组注释插在下一类之前（都没有时追加到末尾）。
 * 短名称冲突时（如 App\Modules\Device 与 App\Http\Device 的 DeviceServiceProvider，设计文档 §4.4）用别名导入，
 * 别名为类别加类名，如 HttpDeviceServiceProvider。
 */
class ProvidersFile
{
    /**
     * 各类别的命名空间前缀与组注释，按组的顺序排列。
     *
     * @var array<string, string>
     */
    private const array GROUPS = [
        'App\\Providers\\' => '',
        'App\\Core\\' => '// 内核模块（app/Core）',
        'App\\Modules\\' => '// 业务模块（app/Modules）',
        'App\\Http\\' => "// 入口：是否加载由 config('core.apps') / CORE_APPS 决定",
    ];

    /**
     * 返回登记后的内容；已登记时返回 null。
     *
     * @throws UnexpectedFormatException
     */
    public static function register(string $code, string $provider): ?string
    {
        $snippet = "use {$provider};\n\n    ".class_basename($provider).'::class,';

        try {
            $imports = PhpImports::parse($code);
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }

        if (preg_match('/^return \[\n(?<body>(?:.*\n)*?)\];\n?\z/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException('找不到独占一行的 `return [` 与文件末尾的 `];`', $snippet);
        }

        [$body, $offset] = $match['body'];
        $lines = $body === '' ? [] : explode("\n", rtrim($body, "\n"));
        $ranks = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '//')) {
                continue;
            }

            if (preg_match('/^\s*(?<name>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::class,\s*$/', $line, $entry) !== 1) {
                throw new UnexpectedFormatException('无法识别的一行：'.trim($line), $snippet);
            }

            $class = $imports->resolve($entry['name']);

            if ($class === $provider) {
                return null;
            }

            $ranks[$index] = self::rank($class);
        }

        $alias = Str::of($provider)->after('App\\')->before('\\')->append(class_basename($provider))->toString();
        $name = $imports->add($provider, $alias);

        array_splice($lines, ...self::insertion($lines, $ranks, $provider, "    {$name}::class,"));

        $code = substr_replace($code, implode("\n", $lines)."\n", $offset, strlen($body));

        $imports = PhpImports::parse($code);
        $imports->add($provider, $alias);

        return $imports->render();
    }

    /**
     * array_splice() 的参数：插入位置、0、要插入的行。
     * 插在同类最后一项之后；没有同类时带组注释插在下一类（连同它的组注释）之前，或追加到末尾。
     *
     * @param  list<string>  $lines
     * @param  array<int, int>  $ranks  行号 => 类别
     * @return array{int, int, list<string>}
     */
    private static function insertion(array $lines, array $ranks, string $provider, string $entry): array
    {
        $rank = self::rank($provider);
        $same = array_keys(array_filter($ranks, fn (int $value): bool => $value === $rank));

        if ($same !== []) {
            return [max($same) + 1, 0, [$entry]];
        }

        $comment = array_find(self::GROUPS, fn (string $comment, string $prefix): bool => str_starts_with($provider, $prefix)) ?? '';
        $group = array_values(array_filter(['    '.$comment, $entry], fn (string $line): bool => trim($line) !== ''));
        $later = array_keys(array_filter($ranks, fn (int $value): bool => $value > $rank));
        $index = $later === [] ? count($lines) : min($later);

        while ($later !== [] && $index > 0 && str_starts_with(trim($lines[$index - 1]), '//')) {
            $index--;
        }

        $before = $index > 0 && trim($lines[$index - 1]) !== '' ? [''] : [];
        $after = $index < count($lines) ? [''] : [];

        return [$index, 0, [...$before, ...$group, ...$after]];
    }

    /**
     * 类别：GROUPS 中的顺序；其他（如第三方包的 Provider）排在最后。
     */
    private static function rank(string $provider): int
    {
        return array_find_key(array_keys(self::GROUPS), fn (string $prefix): bool => str_starts_with($provider, $prefix)) ?? count(self::GROUPS);
    }
}
