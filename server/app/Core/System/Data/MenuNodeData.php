<?php

namespace App\Core\System\Data;

use App\Core\System\Enums\PermissionType;

/**
 * 模块声明的一个菜单与权限节点（MenuRegistry 中注册、已校验并展开 buttons 后的形式，设计文档 §8.2）。
 *
 * 同步命令 core:sync-permissions 按它写入 permissions 表：新增节点时全部字段生效；已有节点只修正结构性字段
 * （type、path、component、meta.link、meta.iframeSrc，见 structuralMeta()），其余以数据库为准。
 */
final readonly class MenuNodeData
{
    /**
     * meta 列中属于结构性字段的键：由同步命令修正，管理员的修改会被覆盖。
     *
     * @var list<string>
     */
    public const array STRUCTURAL_META_KEYS = ['link', 'iframeSrc'];

    /**
     * @param  string  $name  权限码（同时是 Vben 的路由 name）
     * @param  ?string  $parent  上级节点的权限码，根节点为 null
     * @param  string  $title  标题（前端 i18n key 或纯文本），输出为 meta.title
     * @param  ?string  $path  路由路径，按钮为 null
     * @param  ?string  $component  页面组件，只有 menu 使用
     * @param  ?string  $icon  图标，输出为 meta.icon
     * @param  int  $sort  排序，输出为 meta.order
     * @param  array<string, mixed>  $meta  meta 列中的白名单键（Permission::META_KEYS）
     */
    public function __construct(
        public string $name,
        public PermissionType $type,
        public ?string $parent,
        public string $title,
        public ?string $path = null,
        public ?string $component = null,
        public ?string $icon = null,
        public int $sort = 0,
        public array $meta = [],
    ) {}

    /**
     * meta 中的结构性键（link、iframeSrc），没有的键不出现。
     *
     * @return array<string, mixed>
     */
    public function structuralMeta(): array
    {
        return array_intersect_key($this->meta, array_flip(self::STRUCTURAL_META_KEYS));
    }
}
