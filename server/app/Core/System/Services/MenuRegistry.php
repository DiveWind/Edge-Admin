<?php

namespace App\Core\System\Services;

use App\Core\System\Data\MenuNodeData;
use App\Core\System\Enums\PermissionType;
use App\Core\System\Models\Permission;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * 节点的声明（Core 的扩展点，设计文档 §5.5、§8.2）：`#[Singleton]`，只保存启动时注册的静态声明，不保存请求数据（§10.3）。
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
 *
 * 注册时逐个校验，一组声明全部合法才登记：
 * - name：小写点分、以字母开头（`system`、`system.user`、`system.user.create`），全局唯一
 *   （Permission::isValidName()，与菜单管理的写接口同一规则）；
 * - type：PermissionType 的值，层级合法（catalog / menu / embedded / link 在根或 catalog 下，button 在 menu 下）；
 * - path：路由节点必填、以 `/` 开头且互不相同；按钮没有 path；
 * - component：menu 必填，其他类型不能有；embedded 必须有 meta.iframeSrc，link 必须有 meta.link；
 * - meta：title 必填；icon 为字符串；order 为整数；其余键只能是 Permission::META_KEYS 中的，且适用于该类型
 *   （PermissionType::allowsMetaKey()：按钮没有路由 meta，iframeSrc 只用于 embedded，link 只用于 link）；
 * - buttons：只用于 menu，`键 => 标题`，展开为 `<父 name>.<键>` 的按钮节点。
 */
#[Singleton]
class MenuRegistry
{
    /**
     * 节点的键。
     *
     * @var list<string>
     */
    private const array KEYS = ['name', 'type', 'path', 'component', 'meta', 'children', 'buttons'];

    private const string BUTTON_KEY_PATTERN = '/^[a-z0-9_-]+$/';

    /**
     * @var array<string, MenuNodeData>
     */
    private array $nodes = [];

    /**
     * 注册一组根节点（及其子孙），通常是 `require` 模块的 resources/menus.php 的结果。
     * 声明有误（不是数组、name 重复、类型或层级不合法、menu 缺少 component 等）时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $nodes): void
    {
        if (! is_array($nodes)) {
            $this->fail('register()', '声明必须是节点数组');
        }

        $declared = [];

        foreach ($nodes as $node) {
            $this->collect($node, null, $declared);
        }

        $this->nodes = [...$this->nodes, ...$declared];
    }

    /**
     * 全部已声明的节点（按声明顺序，父节点在子节点之前），以权限码为键。
     *
     * @return array<string, MenuNodeData>
     */
    public function all(): array
    {
        return $this->nodes;
    }

    /**
     * 权限码是否已声明。
     */
    public function has(string $name): bool
    {
        return isset($this->nodes[$name]);
    }

    /**
     * 校验一个节点并收集它及其子孙（父节点在前）。
     *
     * @param  array<string, MenuNodeData>  $declared  本次注册已收集的节点
     *
     * @throws InvalidArgumentException
     */
    private function collect(mixed $node, ?MenuNodeData $parent, array &$declared): void
    {
        $where = $parent instanceof MenuNodeData ? "{$parent->name} 的子节点" : '根节点';

        if (! is_array($node)) {
            $this->fail($where, '节点必须是数组');
        }

        $name = $node['name'] ?? null;

        if (! is_string($name) || ! Permission::isValidName($name)) {
            $this->fail($where, 'name 必须是小写点分、以字母开头的权限码，如 system.user');
        }

        if (($unknown = array_diff(array_map(strval(...), array_keys($node)), self::KEYS)) !== []) {
            $this->fail($name, '未知的键 '.implode('、', $unknown).'（允许 '.implode('、', self::KEYS).'）');
        }

        $type = is_string($node['type'] ?? null) ? PermissionType::tryFrom($node['type']) : null;

        if ($type === null) {
            $this->fail($name, 'type 必须是 '.implode(' / ', array_column(PermissionType::cases(), 'value')));
        }

        if (! $type->canBeChildOf($parent?->type)) {
            $this->fail($name, "{$type->value} 不能挂在".($parent instanceof MenuNodeData ? " {$parent->type->value} 下" : '根上'));
        }

        $meta = $node['meta'] ?? null;

        if (! is_array($meta)) {
            $this->fail($name, 'meta 必须是数组，至少包含 title');
        }

        $data = $this->node($name, $type, $parent?->name, $node, $meta);

        $this->add($data, $declared);

        $children = $node['children'] ?? [];
        $buttons = $node['buttons'] ?? [];

        if (! is_array($children) || ! is_array($buttons)) {
            $this->fail($name, 'children 与 buttons 必须是数组');
        }

        if ($buttons !== [] && $type !== PermissionType::Menu) {
            $this->fail($name, 'buttons 只能用于 menu 节点');
        }

        foreach ($children as $child) {
            $this->collect($child, $data, $declared);
        }

        foreach ($buttons as $key => $title) {
            if (! is_string($key) || preg_match(self::BUTTON_KEY_PATTERN, $key) !== 1 || ! is_string($title) || $title === '') {
                $this->fail($name, 'buttons 的格式为 `键 => 标题`，键为小写字母、数字、- 或 _');
            }

            $this->add(new MenuNodeData(name: "{$name}.{$key}", type: PermissionType::Button, parent: $name, title: $title), $declared);
        }
    }

    /**
     * 校验节点的字段并转换为 MenuNodeData。
     *
     * @param  array<array-key, mixed>  $node
     * @param  array<array-key, mixed>  $meta
     *
     * @throws InvalidArgumentException
     */
    private function node(string $name, PermissionType $type, ?string $parent, array $node, array $meta): MenuNodeData
    {
        $title = $meta['title'] ?? null;
        $icon = $meta['icon'] ?? null;
        $order = $meta['order'] ?? 0;
        $extra = array_diff_key($meta, array_flip(['title', 'icon', 'order']));

        if (! is_string($title) || $title === '') {
            $this->fail($name, 'meta.title 必填（前端 i18n key 或纯文本）');
        }

        if ($icon !== null && ! is_string($icon)) {
            $this->fail($name, 'meta.icon 必须是字符串');
        }

        if (! is_int($order)) {
            $this->fail($name, 'meta.order 必须是整数');
        }

        if (($unknown = array_diff(array_map(strval(...), array_keys($extra)), Permission::META_KEYS)) !== []) {
            $this->fail($name, 'meta 中不允许的键 '.implode('、', $unknown).'（允许 title、icon、order 与 '.implode('、', Permission::META_KEYS).'）');
        }

        if (($mismatched = array_filter(array_map(strval(...), array_keys($extra)), fn (string $key): bool => ! $type->allowsMetaKey($key))) !== []) {
            $this->fail($name, "{$type->value} 不能有 meta.".implode('、meta.', $mismatched));
        }

        $path = $node['path'] ?? null;
        $component = $node['component'] ?? null;

        if ($type->isRoute() && (! is_string($path) || ! str_starts_with($path, '/'))) {
            $this->fail($name, 'path 必填，以 / 开头');
        }

        if (! $type->isRoute() && $path !== null) {
            $this->fail($name, '按钮没有 path');
        }

        if ($type->usesComponent() && (! is_string($component) || $component === '')) {
            $this->fail($name, 'menu 必须有 component（相对 src/views 的路径，如 /system/user/list）');
        }

        if (! $type->usesComponent() && $component !== null) {
            $this->fail($name, "{$type->value} 不能有 component");
        }

        if (($key = $type->requiredMetaKey()) !== null && (! is_string($extra[$key] ?? null) || $extra[$key] === '')) {
            $this->fail($name, "{$type->value} 必须有 meta.{$key}");
        }

        /** @var array<string, mixed> $extra */
        return new MenuNodeData(
            name: $name,
            type: $type,
            parent: $parent,
            title: $title,
            path: is_string($path) ? $path : null,
            component: is_string($component) ? $component : null,
            icon: $icon,
            sort: $order,
            meta: $extra,
        );
    }

    /**
     * 登记节点：权限码全局唯一，路由节点的 path 互不相同。
     *
     * @param  array<string, MenuNodeData>  $declared
     *
     * @throws InvalidArgumentException
     */
    private function add(MenuNodeData $node, array &$declared): void
    {
        if (isset($this->nodes[$node->name]) || isset($declared[$node->name])) {
            $this->fail($node->name, '权限码重复声明');
        }

        if ($node->path !== null) {
            foreach ([...$this->nodes, ...$declared] as $other) {
                if ($other->path === $node->path) {
                    $this->fail($node->name, "path {$node->path} 与 {$other->name} 重复");
                }
            }
        }

        $declared[$node->name] = $node;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fail(string $where, string $message): never
    {
        throw new InvalidArgumentException("菜单声明错误（{$where}）：{$message}。");
    }
}
