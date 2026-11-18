<?php

namespace App\Core\System\Enums;

use App\Core\System\Contracts\HasLabel;

/**
 * 菜单与权限节点的类型（设计文档 §6.2、§8.2），采用 Vben 的 5 种：
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
     * Vben 内嵌页与外链使用的前端组件。
     */
    public const string IFRAME_COMPONENT = 'IFrameView';

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
     * /menu/all 中输出的 component：目录不输出（null），菜单为节点的 component，内嵌页与外链为 IFrameView。
     */
    public function routeComponent(?string $component): ?string
    {
        return match ($this) {
            self::Menu => $component,
            self::Embedded, self::Link => self::IFRAME_COMPONENT,
            self::Catalog, self::Button => null,
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
