<?php

namespace App\Console\Generator;

/**
 * 补全官方 make 命令生成的骨架：在模板约定的位置（`//` 占位行、`#[Fillable([])]`、`$table->id();` 等）插入字段相关代码。
 *
 * 每个操作都是"找到约定位置 → 替换 / 插入"；找不到（模板被改得不再包含该位置）时不猜测，
 * 记录到 misses()，由生成器提示用户手动补充。插入的代码可以写完整类名，导入与缩进由随后的 Pint 整理。
 */
class SourceFile
{
    /**
     * @var list<array{0: string, 1: string}> [说明, 需要手动补充的代码]
     */
    protected array $misses = [];

    public function __construct(public readonly string $path, protected string $contents) {}

    public function contents(): string
    {
        return $this->contents;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function misses(): array
    {
        return $this->misses;
    }

    /**
     * 替换全部出现的 $search（至少出现一次）。
     */
    public function replace(string $search, string $replace, string $description): static
    {
        if (! str_contains($this->contents, $search)) {
            return $this->miss($description, $replace);
        }

        $this->contents = str_replace($search, $replace, $this->contents);

        return $this;
    }

    /**
     * 用 $lines 替换 $after 之后的第一个 `//` 占位行（官方模板在空方法体 / 空数组中的写法）；$lines 为空时保留占位行。
     *
     * @param  list<string>  $lines
     */
    public function fill(string $after, array $lines, string $description): static
    {
        if ($lines === []) {
            return $this;
        }

        $start = strpos($this->contents, $after);

        if ($start === false || preg_match('/^[ \t]*\/\/[ \t]*$/m', $this->contents, $match, PREG_OFFSET_CAPTURE, $start) !== 1) {
            return $this->miss($description, implode("\n", $lines));
        }

        $this->contents = substr_replace($this->contents, implode("\n", $lines), $match[0][1], strlen($match[0][0]));

        return $this;
    }

    /**
     * 在包含 $needle 的第一行之后插入 $lines。
     *
     * @param  list<string>  $lines
     */
    public function insertAfter(string $needle, array $lines, string $description): static
    {
        if ($lines === []) {
            return $this;
        }

        $position = strpos($this->contents, $needle);

        if ($position === false) {
            return $this->miss($description, implode("\n", $lines));
        }

        $end = strpos($this->contents, "\n", $position);
        $end = $end === false ? strlen($this->contents) : $end;
        $this->contents = substr_replace($this->contents, "\n".implode("\n", $lines), $end, 0);

        return $this;
    }

    /**
     * 在包含 $needle 的第一行之前插入 $code（$needle 通常是方法签名）。
     */
    public function insertBefore(string $needle, string $code, string $description): static
    {
        $position = strpos($this->contents, $needle);

        if ($position === false) {
            return $this->miss($description, $code);
        }

        $lineStart = strrpos(substr($this->contents, 0, $position), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $this->contents = substr_replace($this->contents, rtrim($code, "\n")."\n", $lineStart, 0);

        return $this;
    }

    /**
     * 追加到类体末尾（最后一个 `}` 之前）。
     */
    public function appendToClass(string $code, string $description): static
    {
        $position = strrpos($this->contents, '}');

        if ($position === false || trim($code) === '') {
            return trim($code) === '' ? $this : $this->miss($description, $code);
        }

        $this->contents = rtrim(substr($this->contents, 0, $position))."\n".rtrim($code, "\n")."\n}\n";

        return $this;
    }

    /**
     * 为类加 PHPDoc 标签（如 `@mixin \App\Models\Tag`）：已有类注释时追加，否则新建。
     */
    public function addClassDocTag(string $tag): static
    {
        if (preg_match('/^(?:final |abstract |readonly )*class \w+/m', $this->contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $this->miss('类声明', " * {$tag}");
        }

        $offset = $match[0][1];
        $before = rtrim(substr($this->contents, 0, $offset));

        if (str_ends_with($before, '*/')) {
            $lastLine = (int) strrpos($before, "\n");
            $this->contents = substr_replace($this->contents, "\n *\n * {$tag}", $lastLine, 0);
        } else {
            $this->contents = substr_replace($this->contents, "/**\n * {$tag}\n */\n", $offset, 0);
        }

        return $this;
    }

    protected function miss(string $description, string $snippet): static
    {
        $this->misses[] = [$description, $snippet];

        return $this;
    }
}
