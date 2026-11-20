<?php

namespace App\Core\Generator\Editing;

/**
 * 在返回“名称 => 类”数组的声明文件（模块的 resources/exporters.php、resources/enums.php）中登记一项（幂等），
 * 由 core:make-exporter 调用（设计文档 §5.5、§10.4）。
 *
 * - 空声明（`return [];`）改为只含这一项的数组；
 * - 已有数组时追加到 `return [` … `];` 的末尾；类的导入按 Pint 的格式重排。
 *
 * 预期的格式与模块模板生成的一致（每项独占一行，缩进 4 个空格）；找不到上述位置时抛出 UnexpectedFormatException，不猜测位置。
 * 名称已登记为同一个类时返回 null，已登记为其他类时抛出异常。
 */
class ClassMapFile
{
    /**
     * 登记后的内容；已登记时返回 null。
     *
     * @throws UnexpectedFormatException
     */
    public static function register(string $code, string $name, string $class): ?string
    {
        $snippet = "return [\n    '{$name}' => ".class_basename($class)."::class,\n];";

        try {
            $imports = PhpImports::parse($code);
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }

        if (preg_match('/^\s*\''.preg_quote($name, '/').'\' => (?<class>[A-Za-z0-9_\\\\]+)::class,$/m', $code, $existing) === 1) {
            return $imports->resolve($existing['class']) === $class
                ? null
                : throw new UnexpectedFormatException("名称 {$name} 已登记为 {$imports->resolve($existing['class'])}", $snippet);
        }

        try {
            $short = $imports->add($class);
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }

        $code = $imports->render();
        $entry = "    '{$name}' => {$short}::class,\n";

        if (preg_match('/^return \[\];$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace($code, "return [\n{$entry}];", $match[0][1], strlen($match[0][0]));
        }

        if (preg_match('/^return \[\n(?<body>(?:.*\n)*?)\];$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];

            return substr_replace($code, $body.$entry, $offset, strlen($body));
        }

        throw new UnexpectedFormatException('找不到 `return [...];`', $snippet);
    }
}
