<?php

namespace App\Console\Generator;

/**
 * 基于锚点注释的幂等文本插入（与语法树无关）：
 *
 * - 锚点是独占一行的注释，如 `// edge:routes`；片段插入到锚点行之前，并按锚点的缩进对齐；
 * - 文件中已包含片段的"标识"文本时不重复插入（由调用方检查），因此重复执行生成器不会产生重复内容；
 * - PHP 片段可以写完整类名，导入由随后的 Pint 整理。
 */
class TextInserter
{
    /**
     * 在锚点前插入片段；锚点不存在时返回 null。
     */
    public static function insertBefore(string $content, string $anchor, string $snippet): ?string
    {
        $lines = explode("\n", $content);
        $index = self::anchorLine($content, $anchor);

        if ($index === null) {
            return null;
        }

        $indent = substr($lines[$index], 0, strlen($lines[$index]) - strlen(ltrim($lines[$index])));

        $indented = array_map(
            fn (string $line): string => $line === '' ? '' : $indent.$line,
            explode("\n", str_ends_with($snippet, "\n") ? substr($snippet, 0, -1) : $snippet),
        );

        array_splice($lines, $index, 0, $indented);

        return implode("\n", $lines);
    }

    protected static function anchorLine(string $content, string $anchor): ?int
    {
        foreach (explode("\n", $content) as $index => $line) {
            if (trim($line) === $anchor) {
                return $index;
            }
        }

        return null;
    }
}
