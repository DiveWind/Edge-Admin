<?php

namespace App\Core\Generator\Editing;

/**
 * 在入口的 routes.php 中登记一个模块的路由（幂等），由 core:make-crud 调用（设计文档 §10.4）。
 *
 * 入口的路由文件只描述结构（分组导入各模块的控制器、apiResources），格式固定，按两个锚点插入：
 *
 * - 已有本模块的分组（`Route::prefix('<前缀>')->name('<前缀>.')->group(function (): void {` … `});`）时，追加到分组的末尾；
 * - 否则在需要登录的分组（`Route::middleware('auth:sanctum')->group(function (): void {` … 顶格的 `});`）的末尾新增本模块的分组。
 *
 * 已有的路由（按整行比较）不重复添加；控制器的导入按 Pint 的格式重排。找不到锚点时抛出 UnexpectedFormatException，
 * 附上需要手工粘贴的分组。
 */
class RoutesFile
{
    /**
     * 登记后的内容；路由都已登记时返回 null。
     *
     * @param  list<string>  $routes  路由语句（每条一行，不含缩进）
     * @param  list<string>  $classes  路由中用到、需要导入的类
     *
     * @throws UnexpectedFormatException
     */
    public static function registerModuleRoutes(string $code, string $prefix, string $comment, array $routes, array $classes): ?string
    {
        $opening = "Route::prefix('{$prefix}')->name('{$prefix}.')->group(function (): void {";
        $snippet = implode("\n", [
            ...array_map(fn (string $class): string => "use {$class};", $classes),
            '',
            "// {$comment}",
            $opening,
            ...array_map(fn (string $route): string => '    '.$route, $routes),
            '});',
        ]);

        $existing = array_map(trim(...), explode("\n", $code));
        $missing = array_values(array_filter($routes, fn (string $route): bool => ! in_array($route, $existing, true)));

        if ($missing === []) {
            return null;
        }

        if (preg_match('/^(?<indent>[ ]*)'.preg_quote($opening, '/').'\n(?<body>(?:.*\n)*?)\k<indent>\}\);$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];
            $indent = $match['indent'][0].'    ';
            $code = substr_replace($code, $body.implode('', array_map(fn (string $route): string => $indent.$route."\n", $missing)), $offset, strlen($body));
        } elseif (preg_match('/^Route::middleware\(\'auth:sanctum\'\)->group\(function \(\): void \{\n(?<body>(?:.*\n)*?)\}\);$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];
            $group = implode("\n", [
                "    // {$comment}",
                '    '.$opening,
                ...array_map(fn (string $route): string => '        '.$route, $routes),
                '    });',
            ])."\n";
            $code = substr_replace($code, $body.(trim($body) === '' ? '' : "\n").$group, $offset, strlen($body));
        } else {
            throw new UnexpectedFormatException("找不到需要登录的分组 `Route::middleware('auth:sanctum')->group(function (): void {` 与顶格的 `});`", $snippet);
        }

        try {
            $imports = PhpImports::parse($code);

            foreach ($classes as $class) {
                $imports->add($class);
            }
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }

        return $imports->render();
    }
}
