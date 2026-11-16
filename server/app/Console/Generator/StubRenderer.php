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
 * - `{{ uses }}`：替换为本模板中各片段需要的 use 导入；PHP 文件最终会对顶层 use 去重并排序。
 * - 可选内容为空后留下的多余空行会被清理（见 tidy()）。
 * - 模板变体：`resolve('controller', ['data_scope'])` 依次查找 `controller.data_scope.stub`、`controller.stub`。
 *
 * 模板目录按 config('edge.generator.stub_paths') 顺序查找，前面的优先。
 */
class StubRenderer
{
    protected const string PLACEHOLDER = '/\{\{\s*([a-z_][a-z0-9_.\-]*)\s*\}\}/i';

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
        $template = $this->load($name);

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

        $output = self::tidy($this->substitute($template, $variables));

        return str_ends_with($name, '.php') || str_starts_with(ltrim($output), '<?php') ? self::sortImports($output) : $output;
    }

    /**
     * 渲染一个片段模板（不含字段循环），用于插入到已有文件的代码块；模板末尾的空行会原样保留。
     *
     * @param  array<string, string|null>  $variables
     */
    public function snippet(string $name, array $variables): string
    {
        return $this->substitute($this->load($name), $variables);
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

            $template = rtrim($this->load($fragment), "\n");

            preg_match_all('/\{\{\s*(field\.[a-z_]+)\s*\}\}/', $template, $used);

            foreach ($used[1] as $key) {
                if (($fieldVariables[$key] ?? null) === null) {
                    continue 2;
                }
            }

            $rendered[] = $this->substitute($template, [...$variables, ...$fieldVariables]);
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
