<?php

namespace App\Console\Generator;

use RuntimeException;

/**
 * 模板渲染：
 *
 * - `{{ name }}`：替换为变量值（键可含 `.`，如 `{{ web.path }}`）；独占一行的占位符值为空时整行删除。
 * - `{{ fields.<片段> }}`：对每个字段渲染片段模板 `fragments/<片段>.<字段类型>.stub`
 *   （不存在时用 `fragments/<片段>.stub`，都不存在则跳过该字段），结果以换行连接；
 *   `{{ fields.web.<片段> }}` 对应 `web/fragments/<片段>[.<字段类型>].stub`；
 *   片段中可用全部模块变量与 `{{ field.* }}`，引用的 `field.*` 值为 null 时跳过该字段。
 * - `{{#if name}} … {{else}} … {{/if}}` / `{{#unless name}} … {{/unless}}`：条件块，可嵌套；`name` 为模块变量或片段中的
 *   `field.*`，值为 null / 空串 / 不存在时为假。条件标签独占一行时整行删除。条件先于占位符替换执行，
 *   因此片段中只出现在假分支里的 `field.*` 不会导致该字段被跳过。
 * - `{{ uses }}`：替换为本模板中各片段需要的 use 导入；PHP 文件最终会对顶层 use 去重并排序。
 * - `{{@import … from '…'}}`（独占一行，可写在模板与片段中）：声明 TS 导入；渲染后收集所有实际输出的声明，
 *   按来源合并、去重、排序后输出到 `{{ imports }}` 所在行（没有该占位符时放在文件开头），因此生成的 TSX 不含未使用的导入。
 * - 可选内容为空后留下的多余空行会被清理（见 tidy()）。
 * - 模板变体：`resolve('controller', ['data_scope'])` 依次查找 `controller.data_scope.stub`、`controller.stub`。
 *
 * 模板目录按 config('edge.generator.stub_paths') 顺序查找，前面的优先。
 */
class StubRenderer
{
    protected const string PLACEHOLDER = '/\{\{\s*([a-z_][a-z0-9_.\-]*)\s*\}\}/i';

    /**
     * 最内层的条件块（内容中不含其他开始标签）：1 = if / unless，2 = 变量名，3 = 内容。
     */
    protected const string CONDITION = '/\{\{#(if|unless)\s+([a-z_][a-z0-9_.\-]*)\s*\}\}((?:(?!\{\{#(?:if|unless)\s).)*?)\{\{\/\1\}\}/is';

    /**
     * 独占一行的条件标签（连同行首缩进与换行一起删除）。
     */
    protected const string CONDITION_LINE = '/^[ \t]*(\{\{(?:#(?:if|unless)\s+[a-z0-9_.\-]+\s*|else|\/(?:if|unless))\}\})[ \t]*\n/im';

    /**
     * TS 导入声明 `{{@import … from '…'}}`（独占一行）。
     */
    protected const string IMPORT_DIRECTIVE = '/^[ \t]*\{\{@import\s+(.+?)\}\}[ \t]*(?:\n|$)/m';

    /**
     * @param  list<string>  $paths
     */
    public function __construct(protected array $paths) {}

    /**
     * 按变体顺序查找模板，返回相对名（不含 .stub）。
     *
     * @param  list<string>  $variants
     */
    public function resolve(string $name, array $variants = []): ?string
    {
        foreach ([...array_map(fn (string $variant): string => "{$name}.{$variant}", $variants), $name] as $candidate) {
            if ($this->path($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    public function path(string $name): ?string
    {
        foreach ($this->paths as $directory) {
            $file = rtrim($directory, '/')."/{$name}.stub";

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    public function load(string $name): string
    {
        $path = $this->path($name) ?? throw new RuntimeException("模板 [{$name}.stub] 不存在（查找目录：".implode(', ', $this->paths).'）。');

        return (string) file_get_contents($path);
    }

    /**
     * @param  array<string, string|null>  $variables
     * @param  list<Field>  $fields
     * @param  list<string>  $imports  额外的 use 导入
     */
    public function render(string $name, array $variables, array $fields = [], array $imports = []): string
    {
        $template = self::conditionals($this->load($name), $variables);

        preg_match_all('/\{\{\s*fields\.([a-z_]+(?:\.[a-z_]+)?)\s*\}\}/', $template, $matches);

        foreach (array_unique($matches[1]) as $section) {
            $variables["fields.{$section}"] = $this->section($section, $variables, $fields);

            foreach ($fields as $field) {
                if ($this->fragmentFor($section, $field) !== null) {
                    $imports = [...$imports, ...$field->imports($section)];
                }
            }
        }

        $imports = array_values(array_unique($imports));
        sort($imports);
        $variables['uses'] = implode("\n", array_map(fn (string $class): string => "use {$class};", $imports));

        $output = self::tidy(self::organizeTsImports($this->substitute($template, $variables)));

        return str_ends_with($name, '.php') || str_starts_with(ltrim($output), '<?php') ? self::sortImports($output) : $output;
    }

    /**
     * 渲染一个片段模板（不含字段循环），用于插入到已有文件的代码块；模板末尾的空行会原样保留。
     *
     * @param  array<string, string|null>  $variables
     */
    public function snippet(string $name, array $variables): string
    {
        return $this->substitute(self::conditionals($this->load($name), $variables), $variables);
    }

    /**
     * @param  array<string, string|null>  $variables
     * @param  list<Field>  $fields
     */
    protected function section(string $section, array $variables, array $fields): string
    {
        $rendered = [];

        foreach ($fields as $field) {
            $fragment = $this->fragmentFor($section, $field);

            if ($fragment === null) {
                continue;
            }

            $fieldVariables = [];

            foreach ($field->variables() as $key => $value) {
                $fieldVariables["field.{$key}"] = $value;
            }

            $scope = [...$variables, ...$fieldVariables];
            $template = rtrim(self::conditionals($this->load($fragment), $scope), "\n");

            preg_match_all('/\{\{\s*(field\.[a-z_]+)\s*\}\}/', $template, $used);

            foreach ($used[1] as $key) {
                if (($fieldVariables[$key] ?? null) === null) {
                    continue 2;
                }
            }

            $rendered[] = $this->substitute($template, $scope);
        }

        return implode("\n", $rendered);
    }

    /**
     * `rules` → fragments/rules[.<类型>].stub；`web.column` → web/fragments/column[.<类型>].stub。
     */
    protected function fragmentFor(string $section, Field $field): ?string
    {
        [$directory, $name] = str_contains($section, '.') ? explode('.', $section, 2) : ['', $section];
        $prefix = $directory === '' ? 'fragments' : "{$directory}/fragments";

        foreach (["{$prefix}/{$name}.{$field->type}", "{$prefix}/{$name}"] as $candidate) {
            if ($this->path($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function substitute(string $template, array $variables): string
    {
        $lines = [];

        foreach (explode("\n", $template) as $line) {
            if (preg_match('/^\s*\{\{\s*([a-z_][a-z0-9_.\-]*)\s*\}\}\s*$/i', $line, $match) === 1
                && array_key_exists($match[1], $variables)
                && (string) $variables[$match[1]] === '') {
                continue;
            }

            $lines[] = preg_replace_callback(
                self::PLACEHOLDER,
                fn (array $placeholder): string => array_key_exists($placeholder[1], $variables) ? (string) $variables[$placeholder[1]] : $placeholder[0],
                $line,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * 展开条件块：由内向外逐层求值，`{{else}}` 之前为真分支、之后为假分支。
     *
     * @param  array<string, string|null>  $variables
     */
    public static function conditionals(string $template, array $variables): string
    {
        if (! str_contains($template, '{{#')) {
            return $template;
        }

        $template = (string) preg_replace(self::CONDITION_LINE, '$1', $template);

        do {
            $template = (string) preg_replace_callback(self::CONDITION, function (array $block) use ($variables): string {
                $truthy = ($variables[$block[2]] ?? '') !== '';
                [$then, $else] = array_pad(explode('{{else}}', $block[3], 2), 2, '');

                return ($block[1] === 'if') === $truthy ? $then : $else;
            }, $template, -1, $count);
        } while ($count > 0);

        return $template;
    }

    /**
     * 收集 `{{@import …}}` 声明，按来源合并去重后输出到 `{{ imports }}`。
     * 顺序与 Biome 的 organizeImports 一致：npm 包 → `@/` 别名 → 相对路径，同组按来源排序，同一来源 `import type` 在前。
     */
    public static function organizeTsImports(string $output): string
    {
        $placeholder = '/^\{\{\s*imports\s*\}\}$/m';

        if (preg_match_all(self::IMPORT_DIRECTIVE, $output, $matches) === 0) {
            return (string) preg_replace('/^\{\{\s*imports\s*\}\}\n/m', '', $output);
        }

        /** @var array<string, array{source: string, type: bool, default: string|null, named: list<string>}> $imports */
        $imports = [];

        foreach ($matches[1] as $declaration) {
            $declaration = 'import '.rtrim(trim($declaration), ';');

            if (preg_match('/^import\s+(type\s+)?([A-Za-z_$][\w$]*)?\s*,?\s*(?:\{([^}]*)\})?\s*from\s+\'([^\']+)\'$/', $declaration, $parts) !== 1) {
                throw new RuntimeException("无法解析导入声明 [{$declaration}]，格式：{{@import [type] [默认导入][, ]{ 命名导入 } from '来源'}}。");
            }

            $key = ($parts[1] !== '' ? 'type:' : 'value:').$parts[4];
            $imports[$key] ??= ['source' => $parts[4], 'type' => $parts[1] !== '', 'default' => null, 'named' => []];
            $imports[$key]['default'] = $parts[2] !== '' ? $parts[2] : $imports[$key]['default'];
            $named = array_filter(array_map(trim(...), explode(',', $parts[3])), fn (string $name): bool => $name !== '');
            $imports[$key]['named'] = array_values(array_unique([...$imports[$key]['named'], ...$named]));
        }

        $group = fn (string $source): int => match (true) {
            str_starts_with($source, '.') => 2,
            str_starts_with($source, '@/') => 1,
            default => 0,
        };

        usort($imports, fn (array $a, array $b): int => [$group($a['source']), $a['source'], ! $a['type']] <=> [$group($b['source']), $b['source'], ! $b['type']]);

        $lines = array_map(function (array $import): string {
            $named = $import['named'];
            usort($named, strnatcmp(...));
            $prefix = 'import '.($import['type'] ? 'type ' : '');
            $default = $import['default'] ?? '';
            $suffix = " from '{$import['source']}';";

            if ($named === []) {
                return $prefix.$default.$suffix;
            }

            $head = $prefix.($default !== '' ? "{$default}, " : '');
            $line = $head.'{ '.implode(', ', $named).' }'.$suffix;

            return strlen($line) <= 80 ? $line : $head."{\n".implode('', array_map(fn (string $name): string => "  {$name},\n", $named)).'}'.$suffix;
        }, $imports);

        $output = (string) preg_replace(self::IMPORT_DIRECTIVE, '', $output);
        $block = implode("\n", $lines);

        return preg_match($placeholder, $output) === 1
            ? (string) preg_replace_callback($placeholder, fn (): string => $block, $output, 1)
            : $block."\n".$output;
    }

    /**
     * 删除可选内容为空后留下的多余空行：紧跟在 `{` `[` `(` 之后、紧挨 `}` `]` `)` 之前的空行，以及连续空行。
     */
    public static function tidy(string $text): string
    {
        $text = (string) preg_replace('/([\{\[\(])\n(?:[ \t]*\n)+/', "$1\n", $text);
        $text = (string) preg_replace('/\n(?:[ \t]*\n)+([ \t]*[\}\]\)])/', "\n$1", $text);

        return (string) preg_replace('/\n(?:[ \t]*\n){2,}/', "\n\n", $text);
    }

    /**
     * 对 PHP 文件顶层的 `use X;` 去重、去掉同命名空间的导入，并按字母排序（与 Pint 的 ordered_imports 一致）。
     */
    public static function sortImports(string $php): string
    {
        $kept = [];
        $imports = [];
        $first = null;

        foreach (explode("\n", $php) as $line) {
            if (preg_match('/^use [A-Za-z0-9_\\\\]+;$/', $line) === 1) {
                $first ??= count($kept);
                $imports[] = $line;

                continue;
            }

            $kept[] = $line;
        }

        if ($first === null) {
            return $php;
        }

        // 与文件同命名空间的类无需导入（如 App\Models 中引用 App\Models\User）。
        $namespace = preg_match('/^namespace ([A-Za-z0-9_\\\\]+);$/m', $php, $match) === 1 ? $match[1] : null;
        $imports = array_values(array_filter(
            array_unique($imports),
            fn (string $import): bool => $namespace === null || preg_match('/^use '.preg_quote($namespace, '/').'\\\\[A-Za-z0-9_]+;$/', $import) !== 1,
        ));
        usort($imports, fn (string $a, string $b): int => strcasecmp(rtrim($a, ';'), rtrim($b, ';')));
        array_splice($kept, $first, 0, $imports);

        return implode("\n", $kept);
    }
}
