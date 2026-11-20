<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\{PermissionType, Status};
use App\Core\System\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 节点。默认是根上的目录（自定义节点）；子节点用 childOf()，类型用 menu()、button() 等状态。
 *
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 列出全部字段（可空的也写上）：严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::lower(fake()->unique()->bothify('node_####??'));

        return [
            'parent_id' => null,
            'type' => PermissionType::Catalog,
            'name' => $name,
            'guard_name' => 'web',
            'title' => fake()->words(2, true),
            'path' => '/'.$name,
            'component' => null,
            'icon' => null,
            'sort' => 0,
            'status' => Status::Enabled,
            'meta' => null,
            'is_builtin' => false,
        ];
    }

    /**
     * 挂在 $parent 下：权限码为 `<父 name>.<随机>`，路径在父路径之下（按钮没有路径）。
     */
    public function childOf(Permission $parent): static
    {
        return $this->state(function (array $attributes) use ($parent): array {
            $key = Str::lower(fake()->unique()->bothify('n####??'));
            $type = $attributes['type'] ?? PermissionType::Catalog;

            return [
                'parent_id' => $parent->id,
                'name' => $parent->name.'.'.$key,
                'path' => $type === PermissionType::Button ? null : rtrim((string) $parent->path, '/').'/'.$key,
            ];
        });
    }

    public function catalog(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => PermissionType::Catalog, 'component' => null]);
    }

    /**
     * 菜单（页面）：component 为相对 src/views 的路径。
     */
    public function menu(?string $component = null): static
    {
        return $this->state(function (array $attributes) use ($component): array {
            $path = $attributes['path'] ?? null;

            return [
                'type' => PermissionType::Menu,
                'component' => $component ?? (is_string($path) ? $path : '/page').'/index',
            ];
        });
    }

    /**
     * 内嵌页：meta.iframeSrc。
     */
    public function embedded(string $url = 'https://example.com'): static
    {
        return $this->state(fn (array $attributes): array => ['type' => PermissionType::Embedded, 'component' => null, 'meta' => ['iframeSrc' => $url]]);
    }

    /**
     * 外链：meta.link。
     */
    public function link(string $url = 'https://example.com'): static
    {
        return $this->state(fn (array $attributes): array => ['type' => PermissionType::Link, 'component' => null, 'meta' => ['link' => $url]]);
    }

    /**
     * 按钮：没有路径与组件，只作为权限码。
     */
    public function button(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => PermissionType::Button, 'path' => null, 'component' => null]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }

    /**
     * 内置节点（由同步命令维护）。
     */
    public function builtin(): static
    {
        return $this->state(fn (array $attributes): array => ['is_builtin' => true]);
    }
}
