<?php

namespace App\Core\System\Enums;

use App\Support\Enums\HasLabel;

/**
 * 节点的类型（设计文档 §6.2、§8.2），采用 Vben 的 5 种：
 *
 * | 类型 | 含义 | /menu/all 中的输出 |
 * |---|---|---|
 * | catalog | 目录 | 不输出 component，只有 children |
 * | menu | 菜单（页面） | component 为相对 src/views 的路径 |
 * | embedded | 内嵌页 | component: IFrameView，meta.iframeSrc |
 * | link | 外链 | component: IFrameView，meta.link |
 * | button | 按钮 | 不输出，只作为权限码 |
 *
 * 层级：catalog、menu、embedded、link 只能挂在根或 catalog 下；button 只能挂在 menu 下（§8.2、§8.6）。
 *
 * 实现 HasLabel：显示名称由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum PermissionType: string implements HasLabel
{
    case Catalog = 'catalog';
    case Menu = 'menu';
    case Embedded = 'embedded';
    case Link = 'link';
    case Button = 'button';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Catalog => __('Catalog'),
            self::Menu => __('Menu'),
            self::Embedded => __('Embedded'),
            self::Link => __('Link'),
            self::Button => __('Button'),
        };
    }

    /**
     * 能否挂在 $parent 类型的节点下（null 表示根）。
     */
    public function canBeChildOf(?self $parent): bool
    {
        return match ($this) {
            self::Button => $parent === self::Menu,
            default => in_array($parent, [null, self::Catalog], true),
        };
    }

    /**
     * 层级不合法（canBeChildOf() 为 false）时的提示（§8.2）：菜单管理的新增、修改共用。
     */
    public function hierarchyMessage(): string
    {
        return $this === self::Button
            ? __('Buttons can only be placed under a menu.')
            : __('Catalogs, menus, embedded pages and links can only be placed at the root or under a catalog.');
    }

    /**
     * 能否容纳 $child 类型的子节点（与 canBeChildOf() 同一规则）。
     */
    public function canContain(self $child): bool
    {
        return $child->canBeChildOf($this);
    }

    /**
     * 是否为路由节点：出现在 /menu/all 中、需要 path（按钮只作为权限码，没有 path）。
     */
    public function isRoute(): bool
    {
        return $this !== self::Button;
    }

    /**
     * 是否使用节点自己的 component 列（只有 menu）。
     */
    public function usesComponent(): bool
    {
        return $this === self::Menu;
    }

    /**
     * meta 列中的键 $key（Permission::META_KEYS 之一）是否适用于本类型：按钮只作为权限码，没有路由 meta；
     * iframeSrc 只用于内嵌页，link 只用于外链。与类型不符的键在声明与写接口中都被拒绝。
     */
    public function allowsMetaKey(string $key): bool
    {
        return match ($this) {
            self::Button => false,
            default => match ($key) {
                'iframeSrc' => $this === self::Embedded,
                'link' => $this === self::Link,
                default => true,
            },
        };
    }

    /**
     * 节点必须在 meta 中提供的键：内嵌页的 iframeSrc、外链的 link。
     */
    public function requiredMetaKey(): ?string
    {
        return match ($this) {
            self::Embedded => 'iframeSrc',
            self::Link => 'link',
            default => null,
        };
    }
}
