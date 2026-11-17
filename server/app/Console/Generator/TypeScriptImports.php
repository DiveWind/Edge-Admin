<?php

namespace App\Console\Generator;

use RuntimeException;

/**
 * 整理生成的 TS / TSX 中的 import：模板与字段片段直接写普通的 `import … from '…';`（顶格、单行），
 * 渲染后这里把全文中的 import 行收集起来，按来源合并去重，排在文件开头。
 *
 * 只有实际输出的片段才会贡献导入（跳过的字段、条件块假分支中的 import 不会出现），因此生成的文件没有未使用的导入；
 * 顺序与 Biome 的 organizeImports 一致：npm 包 → `@/` 别名 → 相对路径，同组按来源排序，同一来源 `import type` 在前。
 */
class TypeScriptImports
{
    protected const string IMPORT_LINE = '/^import\s+(type\s+)?([A-Za-z_$][\w$]*)?\s*,?\s*(?:\{([^}]*)\})?\s*from\s+\'([^\']+)\';[ \t]*(?:\n|$)/m';

    public static function organize(string $source): string
    {
        if (preg_match_all(self::IMPORT_LINE, $source, $matches, PREG_SET_ORDER) === 0) {
            return $source;
        }

        /** @var array<string, array{source: string, type: bool, default: string|null, named: list<string>}> $imports */
        $imports = [];

        foreach ($matches as [, $type, $default, $named, $from]) {
            if ($default === '' && trim($named) === '') {
                throw new RuntimeException("无法解析导入声明 [from '{$from}']：需要默认导入或命名导入。");
            }

            $key = ($type !== '' ? 'type:' : 'value:').$from;
            $imports[$key] ??= ['source' => $from, 'type' => $type !== '', 'default' => null, 'named' => []];
            $imports[$key]['default'] = $default !== '' ? $default : $imports[$key]['default'];
            $names = array_filter(array_map(trim(...), explode(',', $named)), fn (string $name): bool => $name !== '');
            $imports[$key]['named'] = array_values(array_unique([...$imports[$key]['named'], ...$names]));
        }

        $group = fn (string $from): int => match (true) {
            str_starts_with($from, '.') => 2,
            str_starts_with($from, '@/') => 1,
            default => 0,
        };

        usort($imports, fn (array $a, array $b): int => [$group($a['source']), $a['source'], ! $a['type']] <=> [$group($b['source']), $b['source'], ! $b['type']]);

        $body = ltrim((string) preg_replace(self::IMPORT_LINE, '', $source), "\n");

        return implode("\n", array_map(self::statement(...), $imports))."\n\n".$body;
    }

    /**
     * @param  array{source: string, type: bool, default: string|null, named: list<string>}  $import
     */
    protected static function statement(array $import): string
    {
        $named = $import['named'];
        usort($named, strnatcmp(...));
        $head = 'import '.($import['type'] ? 'type ' : '').($import['default'] ?? '');
        $from = " from '{$import['source']}';";

        if ($named === []) {
            return $head.$from;
        }

        $head .= $import['default'] !== null ? ', ' : '';
        $line = $head.'{ '.implode(', ', $named).' }'.$from;

        return strlen($line) <= 80 ? $line : $head."{\n".implode('', array_map(fn (string $name): string => "  {$name},\n", $named)).'}'.$from;
    }
}
