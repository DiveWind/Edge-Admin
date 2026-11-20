<?php

namespace App\Core\Generator\Editing;

/**
 * 在模块的 resources/menus.php 中登记一个模型的菜单节点（幂等），由 core:make-crud 调用（设计文档 §8.2、§10.4）。
 *
 * - 文件还是 core:make-module 生成的空声明（`return [];`）时，改为一个目录节点，菜单节点是它的唯一子节点；
 * - 已有同名目录节点（`'name' => '<目录>',`）时，把菜单节点追加到它的 `'children' => [` … `],` 的末尾；
 * - 没有这个目录时，把目录节点（含菜单节点）追加到顶层数组的末尾。
 *
 * 预期的格式与 Content 模块、core:make-crud 生成的一致（每层缩进 4 个空格，每个键独占一行）；
 * 找不到上述位置时抛出 UnexpectedFormatException，不猜测位置。菜单节点已声明时返回 null。
 */
class MenusFile
{
    /**
     * 登记后的内容；菜单节点已声明时返回 null。
     *
     * @param  array{name: string, path: string, title: string, icon: string}  $catalog  目录节点
     * @param  array{name: string, path: string, component: string, title: string, icon: string, buttons: array<string, string>}  $menu  菜单节点
     *
     * @throws UnexpectedFormatException
     */
    public static function registerMenu(string $code, array $catalog, array $menu): ?string
    {
        $snippet = self::catalog($catalog, $menu, '');

        if (preg_match('/^\s*\'name\' => \''.preg_quote($menu['name'], '/').'\',$/m', $code) === 1) {
            return null;
        }

        if (preg_match('/^return \[\];\n?\z/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace($code, "return [\n".self::catalog($catalog, $menu, '    ')."\n];\n", $match[0][1]);
        }

        $pattern = '/^(?<indent>[ ]+)\'name\' => \''.preg_quote($catalog['name'], '/').'\',\n(?:\k<indent>.*\n)*?\k<indent>\'children\' => \[\n(?<body>(?:.*\n)*?)\k<indent>\],\n/m';

        if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];

            return substr_replace($code, $body.self::menu($menu, $match['indent'][0].'    ')."\n", $offset, strlen($body));
        }

        if (preg_match('/^\s*\'name\' => \''.preg_quote($catalog['name'], '/').'\',$/m', $code) === 1) {
            throw new UnexpectedFormatException("目录节点 {$catalog['name']} 没有独占多行的 'children' => [ … ],", self::menu($menu, ''));
        }

        if (preg_match('/^\];\n?\z/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException('找不到文件末尾独占一行的 `];`', $snippet);
        }

        return substr_replace($code, self::catalog($catalog, $menu, '    ')."\n];\n", $match[0][1]);
    }

    /**
     * 目录节点（含一个菜单节点）的 PHP 代码，以逗号结尾。
     *
     * @param  array{name: string, path: string, title: string, icon: string}  $catalog
     * @param  array{name: string, path: string, component: string, title: string, icon: string, buttons: array<string, string>}  $menu
     */
    private static function catalog(array $catalog, array $menu, string $indent): string
    {
        return implode("\n", [
            "{$indent}[",
            "{$indent}    'name' => '{$catalog['name']}',",
            "{$indent}    'type' => 'catalog',",
            "{$indent}    'path' => '{$catalog['path']}',",
            "{$indent}    'meta' => ['title' => '{$catalog['title']}', 'icon' => '{$catalog['icon']}', 'order' => 100],",
            "{$indent}    'children' => [",
            self::menu($menu, $indent.'        '),
            "{$indent}    ],",
            "{$indent}],",
        ]);
    }

    /**
     * 菜单节点（含按钮）的 PHP 代码，以逗号结尾。
     *
     * @param  array{name: string, path: string, component: string, title: string, icon: string, buttons: array<string, string>}  $menu
     */
    private static function menu(array $menu, string $indent): string
    {
        $buttons = array_map(fn (string $key, string $title): string => "{$indent}        '{$key}' => '{$title}',", array_keys($menu['buttons']), $menu['buttons']);

        return implode("\n", [
            "{$indent}[",
            "{$indent}    'name' => '{$menu['name']}',",
            "{$indent}    'type' => 'menu',",
            "{$indent}    'path' => '{$menu['path']}',",
            "{$indent}    'component' => '{$menu['component']}',",
            "{$indent}    'meta' => ['title' => '{$menu['title']}', 'icon' => '{$menu['icon']}'],",
            "{$indent}    'buttons' => [",
            ...$buttons,
            "{$indent}    ],",
            "{$indent}],",
        ]);
    }
}
