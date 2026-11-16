<?php

namespace App\Enums;

/**
 * 菜单树节点类型：目录下可放目录 / 菜单，菜单下只能放操作，操作没有子节点。
 */
enum PermissionType: string
{
    case Directory = 'directory';
    case Menu = 'menu';
    case Action = 'action';

    /**
     * 该类型节点允许的上级类型；null 表示可以作为根节点。
     *
     * @return list<self|null>
     */
    public function allowedParents(): array
    {
        return match ($this) {
            self::Directory, self::Menu => [null, self::Directory],
            self::Action => [self::Menu],
        };
    }

    /**
     * 是否允许把 $child 类型的节点放在本类型节点下。
     */
    public function accepts(self $child): bool
    {
        return in_array($this, $child->allowedParents(), true);
    }

    /**
     * 目录与菜单出现在侧边菜单中，操作只用于按钮 / 接口鉴权。
     */
    public function isMenuEntry(): bool
    {
        return $this !== self::Action;
    }
}
