<?php

namespace App\Core\System\Contracts;

use App\Core\System\Data\MenuNodeData;
use InvalidArgumentException;

/**
 * 菜单与权限节点的声明（Core 的扩展点，设计文档 §5.5、§8.2）：单例，只保存启动时注册的静态声明。
 *
 * 各模块在自己 Provider 的 boot() 中注册本模块的节点（System 自己的节点也这样注册），
 * 由同步命令 `php artisan core:sync-permissions` 统一写入 permissions 表：
 *
 * ```php
 * public function boot(MenuRegistry $menus): void
 * {
 *     $menus->register(require __DIR__.'/resources/menus.php');
 * }
 * ```
 *
 * 声明格式与 Vben 的路由节点同构：`name`、`type`、`path`、`component`、`meta`（title、icon、order 与白名单键）、
 * `children`，以及只用于 menu 的 `buttons`（`键 => 标题`，展开为 `<父 name>.<键>` 的按钮节点）。
 */
interface MenuRegistry
{
    /**
     * 注册一组根节点（及其子孙），通常是 `require` 模块的 resources/menus.php 的结果。
     * 声明有误（不是数组、name 重复、类型或层级不合法、menu 缺少 component 等）时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $nodes): void;

    /**
     * 全部已声明的节点（按声明顺序，父节点在子节点之前），以权限码为键。
     *
     * @return array<string, MenuNodeData>
     */
    public function all(): array;

    /**
     * 权限码是否已声明。
     */
    public function has(string $name): bool;
}
