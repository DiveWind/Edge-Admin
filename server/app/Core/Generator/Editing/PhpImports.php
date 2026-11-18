<?php

namespace App\Core\Generator\Editing;

use Illuminate\Support\Str;
use PhpToken;

/**
 * PHP 文件顶部的类导入（use 语句）：解析、追加，并按本项目 Pint 的格式（pint.json）重新输出，生成物无需再跑 Pint：
 *
 * - 同一命名空间下的两个以上的类合并为分组导入 `use A\{B, C};`，组内按名称排序（区分大小写，与 group_import 一致）；
 * - 语句之间按不区分大小写的字母顺序排序，`\` 视为空格（与 ordered_imports 一致）；
 * - 与文件同一命名空间的类不导入（与 no_unused_imports 一致）。
 *
 * 只处理类导入。遇到函数 / 常量导入、导入之间夹着注释等无法安全重写的写法时抛出 UnexpectedFormatException。
 */
class PhpImports
{
    /**
     * @param  array<string, string>  $imports  代码中使用的名称（类名或别名）=> 完整类名
     */
    private function __construct(
        private readonly string $code,
        private readonly string $namespace,
        private readonly int $offset,
        private readonly int $length,
        private array $imports,
    ) {}

    /**
     * 解析代码中的命名空间与顶层的类导入。
     *
     * @throws UnexpectedFormatException
     */
    public static function parse(string $code): self
    {
        $tokens = array_values(PhpToken::tokenize($code));
        $namespace = '';
        $insertAt = 0;
        $start = null;
        $end = null;
        $imports = [];

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token->is([T_OPEN_TAG, T_WHITESPACE])) {
                $insertAt = $start === null && $token->is(T_OPEN_TAG) ? $token->pos + strlen($token->text) : $insertAt;

                continue;
            }

            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            if ($token->is(T_NAMESPACE) && $start === null) {
                [$namespace, $index] = self::statement($tokens, $index);
                $namespace = trim(Str::after($namespace, 'namespace'));
                $insertAt = $tokens[$index]->pos + 1;

                continue;
            }

            if (! $token->is(T_USE)) {
                break;
            }

            if ($end !== null && trim(substr($code, $end, $token->pos - $end)) !== '') {
                throw new UnexpectedFormatException('导入语句之间有注释或其他代码');
            }

            [$statement, $index] = self::statement($tokens, $index);

            foreach (self::classesIn($statement) as $alias => $class) {
                if (isset($imports[$alias]) && $imports[$alias] !== $class) {
                    throw new UnexpectedFormatException("名称 {$alias} 被导入了两次");
                }

                $imports[$alias] = $class;
            }

            $start ??= $token->pos;
            $end = $tokens[$index]->pos + 1;
        }

        return new self($code, $namespace, $start ?? $insertAt, $start === null || $end === null ? 0 : $end - $start, $imports);
    }

    /**
     * 按 Pint 的格式重新输出导入；解析失败（如用户自定义的模板中有函数导入）时原样返回。
     */
    public static function normalize(string $code): string
    {
        try {
            return self::parse($code)->render();
        } catch (UnexpectedFormatException) {
            return $code;
        }
    }

    /**
     * 文件的命名空间（没有时为空字符串）。
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * 代码中的类名（短名称、别名或带命名空间的名称）对应的完整类名。
     */
    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $first = Str::before($name, '\\');

        if (isset($this->imports[$first])) {
            return $this->imports[$first].Str::after($name, $first);
        }

        return ltrim($this->namespace.'\\'.$name, '\\');
    }

    /**
     * 导入 $class，返回代码中引用它的名称。短名称已被其他类占用时改用 $alias；未给 $alias 时抛出异常。
     *
     * @throws UnexpectedFormatException
     */
    public function add(string $class, ?string $alias = null): string
    {
        $class = ltrim($class, '\\');
        $existing = array_search($class, $this->imports, true);

        if (is_string($existing)) {
            return $existing;
        }

        $name = class_basename($class);

        if (! isset($this->imports[$name]) && $this->inNamespace($class)) {
            return $name;
        }

        if (! isset($this->imports[$name]) && ! $this->declaresClassNamed($name)) {
            $this->imports[$name] = $class;

            return $name;
        }

        if ($alias === null || isset($this->imports[$alias])) {
            throw new UnexpectedFormatException("名称 {$name} 已被其他类使用，无法导入 {$class}");
        }

        $this->imports[$alias] = $class;

        return $alias;
    }

    /**
     * 带有重新输出的导入块的完整代码。
     */
    public function render(): string
    {
        $block = $this->block();

        if ($this->length > 0) {
            return substr_replace($this->code, $block, $this->offset, $this->length);
        }

        if ($block === '') {
            return $this->code;
        }

        return rtrim(substr($this->code, 0, $this->offset))."\n\n".$block."\n\n".ltrim(substr($this->code, $this->offset));
    }

    /**
     * 导入块：每条语句一行，不含首尾空行。
     */
    private function block(): string
    {
        $groups = [];

        foreach ($this->imports as $alias => $class) {
            if (class_basename($class) === $alias && $this->inNamespace($class)) {
                continue;
            }

            $namespace = str_contains($class, '\\') ? Str::beforeLast($class, '\\') : '';

            $groups[$namespace][] = class_basename($class) === $alias ? $alias : class_basename($class).' as '.$alias;
        }

        $statements = [];

        foreach ($groups as $namespace => $names) {
            if ($namespace === '' || count($names) === 1) {
                array_push($statements, ...array_map(fn (string $name): string => 'use '.ltrim($namespace.'\\'.$name, '\\').';', $names));

                continue;
            }

            sort($names, SORT_STRING);

            $statements[] = 'use '.$namespace.'\\{'.implode(', ', $names).'};';
        }

        usort($statements, fn (string $a, string $b): int => strcasecmp(str_replace('\\', ' ', $a), str_replace('\\', ' ', $b)));

        return implode("\n", $statements);
    }

    /**
     * 类是否就在本文件的命名空间中（不需要导入）。
     */
    private function inNamespace(string $class): bool
    {
        return $this->namespace !== '' && str_contains($class, '\\') && Str::beforeLast($class, '\\') === $this->namespace;
    }

    /**
     * 文件中是否声明了同名的类（导入同名的类会冲突）。
     */
    private function declaresClassNamed(string $name): bool
    {
        return preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+'.preg_quote($name, '/').'\b/m', $this->code) === 1;
    }

    /**
     * 从 $index 开始到分号为止的语句文本与分号的位置。
     *
     * @param  list<PhpToken>  $tokens
     * @return array{string, int}
     *
     * @throws UnexpectedFormatException
     */
    private static function statement(array $tokens, int $index): array
    {
        $text = '';

        for ($count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index]->text === ';') {
                return [$text, $index];
            }

            if ($tokens[$index]->is([T_COMMENT, T_DOC_COMMENT])) {
                throw new UnexpectedFormatException('导入语句中有注释');
            }

            $text .= $tokens[$index]->is(T_WHITESPACE) ? ' ' : $tokens[$index]->text;
        }

        throw new UnexpectedFormatException('语句没有以分号结束');
    }

    /**
     * 一条 use 语句导入的类：别名 => 完整类名。
     *
     * @return array<string, string>
     *
     * @throws UnexpectedFormatException
     */
    private static function classesIn(string $statement): array
    {
        $name = '\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*';
        $item = '(?<name>'.$name.')(?:\s+as\s+(?<alias>[A-Za-z_][A-Za-z0-9_]*))?';
        $statement = trim($statement);

        if (preg_match('/^use\s+'.$item.'$/', $statement, $match) === 1) {
            return [($match['alias'] ?? '') !== '' ? $match['alias'] : class_basename($match['name']) => ltrim($match['name'], '\\')];
        }

        if (preg_match('/^use\s+(?<prefix>'.$name.')\\\\\s*\{(?<items>[^{}]*)\}$/', $statement, $group) !== 1) {
            throw new UnexpectedFormatException("无法解析的导入语句：{$statement};");
        }

        $classes = [];

        foreach (array_filter(array_map(trim(...), explode(',', $group['items']))) as $part) {
            if (preg_match('/^'.$item.'$/', $part, $match) !== 1) {
                throw new UnexpectedFormatException("无法解析的导入语句：{$statement};");
            }

            $classes[($match['alias'] ?? '') !== '' ? $match['alias'] : class_basename($match['name'])] = ltrim($group['prefix'], '\\').'\\'.$match['name'];
        }

        return $classes;
    }
}
