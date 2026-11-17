<?php

namespace App\Console\Generator;

use RuntimeException;

/**
 * 模块模板（stubs/edge）的渲染：只负责文本替换，不理解 PHP / TS 语法（导入由 Pint / TypeScriptImports 整理）。
 *
 * - `{{ name }}`：替换为变量值（键可含 `.`，如 `{{ web.path }}`）；独占一行且值为空时整行删除，未知占位符原样保留。
 * - `{{ fields.<片段> }}`：对每个字段渲染 `fragments/<片段>.<字段类型>.stub`（不存在时用 `fragments/<片段>.stub`，
 *   都不存在则跳过该字段），以换行连接；`{{ fields.web.<片段> }}` 对应 `web/fragments/`。片段中可用全部模块变量与
 *   `{{ field.* }}`，引用的 `field.*` 值为 null 时跳过该字段。
 * - `{{#if name}} … {{else}} … {{/if}}` / `{{#unless name}} … {{/unless}}`：条件块，可嵌套，值为 null / 空串 / 不存在时为假；
 *   标签独占一行时整行删除。条件先于占位符执行，因此只出现在假分支里的 `field.*` 不会导致字段被跳过。
 * - 可选内容为空后留下的多余空行会被清理（见 tidy()）。
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

    public function __construct(protected string $directory) {}

    public function exists(string $name): bool
    {
        return is_file($this->path($name));
    }

    public function path(string $name): string
    {
        return rtrim($this->directory, '/')."/{$name}.stub";
    }

    /**
     * 渲染整个模板。
     *
     * @param  array<string, string|null>  $variables
     * @param  list<Field>  $fields
     */
    public function render(string $name, array $variables, array $fields = []): string
    {
        $template = self::conditionals($this->load($name), $variables);

        preg_match_all('/\{\{\s*fields\.([a-z_]+(?:\.[a-z_]+)?)\s*\}\}/', $template, $matches);

        foreach (array_unique($matches[1]) as $section) {
            $variables["fields.{$section}"] = implode("\n", $this->fragments($section, $variables, $fields));
        }

        return self::tidy($this->substitute($template, $variables));
    }

    /**
     * 为每个字段渲染一次片段，返回各字段的结果（跳过没有片段或不适用的字段）；
     * 生成器用它补全官方 make 命令生成的骨架（迁移列、casts、校验规则…）。
     *
     * @param  array<string, string|null>  $variables
     * @param  list<Field>  $fields
     * @return list<string>
     */
    public function fragments(string $section, array $variables, array $fields): array
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

        return $rendered;
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
     * 删除可选内容为空后留下的多余空行：紧跟在 `{` `[` `(` 之后、紧挨 `}` `]` `)` 之前的空行，以及连续空行。
     */
    public static function tidy(string $text): string
    {
        $text = (string) preg_replace('/([\{\[\(])\n(?:[ \t]*\n)+/', "$1\n", $text);
        $text = (string) preg_replace('/\n(?:[ \t]*\n)+([ \t]*[\}\]\)])/', "\n$1", $text);

        return (string) preg_replace('/\n(?:[ \t]*\n){2,}/', "\n\n", $text);
    }

    /**
     * @throws RuntimeException
     */
    protected function load(string $name): string
    {
        $this->exists($name) || throw new RuntimeException("模板 [{$this->path($name)}] 不存在。");

        return (string) file_get_contents($this->path($name));
    }

    /**
     * `rules` → fragments/rules[.<类型>].stub；`web.column` → web/fragments/column[.<类型>].stub。
     */
    protected function fragmentFor(string $section, Field $field): ?string
    {
        [$directory, $name] = str_contains($section, '.') ? explode('.', $section, 2) : ['', $section];
        $prefix = $directory === '' ? 'fragments' : "{$directory}/fragments";

        foreach (["{$prefix}/{$name}.{$field->type}", "{$prefix}/{$name}"] as $candidate) {
            if ($this->exists($candidate)) {
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
}
