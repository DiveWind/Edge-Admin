<?php

namespace App\Core\Generator\Editing;

/**
 * 在 config/core.php 的入口清单（`'apps' => [` … `],`）末尾登记入口（幂等）。
 *
 * 预期格式：`'apps' => [` 独占一行，同一缩进的 `],` 结束；每个入口是 `'<键>' => [` 开头的一项，项之间空一行。
 */
class CoreConfigFile
{
    /**
     * 返回登记后的内容；已有同名入口时返回 null。
     *
     * @param  string  $entry  入口配置（不含缩进），如 core.app.config.stub 渲染的结果
     *
     * @throws UnexpectedFormatException
     */
    public static function registerApp(string $code, string $key, string $entry): ?string
    {
        if (preg_match('/^(?<indent>[ ]*)\'apps\' => \[\n(?<body>(?:.*\n)*?)\k<indent>\],$/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException("找不到入口清单 `'apps' => [` … `],`", self::indent($entry, '        '));
        }

        [$indent] = $match['indent'];
        [$body, $offset] = $match['body'];
        $itemIndent = $indent.'    ';

        if (preg_match('/^'.$itemIndent.'\''.preg_quote($key, '/').'\' => \[$/m', $body) === 1) {
            return null;
        }

        $new = trim($body) === ''
            ? "\n".self::indent($entry, $itemIndent)."\n\n"
            : rtrim($body)."\n\n".self::indent($entry, $itemIndent)."\n\n";

        return substr_replace($code, $new, $offset, strlen($body));
    }

    private static function indent(string $text, string $indent): string
    {
        return implode("\n", array_map(
            fn (string $line): string => $line === '' ? '' : $indent.$line,
            explode("\n", trim($text, "\n")),
        ));
    }
}
