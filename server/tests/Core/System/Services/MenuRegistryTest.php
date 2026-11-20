<?php

use App\Core\System\Enums\PermissionType;
use App\Core\System\Services\MenuRegistry;

/*
|--------------------------------------------------------------------------
| MenuRegistry：菜单与权限节点的声明（设计文档 §8.2）
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuNode(string $name, string $type = 'menu', array $overrides = []): array
{
    return [
        'name' => $name,
        'type' => $type,
        ...($type === 'button' ? [] : ['path' => '/'.str_replace('.', '/', $name)]),
        ...($type === 'menu' ? ['component' => '/'.str_replace('.', '/', $name).'/list'] : []),
        'meta' => ['title' => "{$name}.title"],
        ...$overrides,
    ];
}

it('is a singleton that holds the System nodes', function (): void {
    expect(resolve(MenuRegistry::class))->toBe(resolve(MenuRegistry::class))
        ->and(resolve(MenuRegistry::class)->has('system.user.password'))->toBeTrue()
        ->and(resolve(MenuRegistry::class)->has('system.user.unknown'))->toBeFalse();
});

it('flattens the declaration with parents first and expands the buttons', function (): void {
    $registry = new MenuRegistry;

    $registry->register([
        menuNode('demo', 'catalog', [
            'meta' => ['title' => 'demo.title', 'icon' => 'lucide:box', 'order' => 5, 'hideInMenu' => true],
            'children' => [menuNode('demo.page', 'menu', ['buttons' => ['create' => 'common.create', 'export' => 'demo.export']])],
        ]),
    ]);
    $registry->register([menuNode('other', 'embedded', ['meta' => ['title' => 'other.title', 'iframeSrc' => 'https://example.com']])]);

    $nodes = $registry->all();

    expect(array_keys($nodes))->toBe(['demo', 'demo.page', 'demo.page.create', 'demo.page.export', 'other'])
        ->and($nodes['demo'])
        ->parent->toBeNull()
        ->type->toBe(PermissionType::Catalog)
        ->icon->toBe('lucide:box')
        ->sort->toBe(5)
        ->meta->toBe(['hideInMenu' => true])
        ->and($nodes['demo.page'])
        ->parent->toBe('demo')
        ->component->toBe('/demo/page/list')
        ->and($nodes['demo.page.export'])
        ->parent->toBe('demo.page')
        ->type->toBe(PermissionType::Button)
        ->title->toBe('demo.export')
        ->path->toBeNull()
        ->and($nodes['other']->structuralMeta())->toBe(['iframeSrc' => 'https://example.com']);
});

it('rejects invalid declarations', function (mixed $nodes, string $message): void {
    $registry = new MenuRegistry;

    expect(fn () => $registry->register($nodes))->toThrow(InvalidArgumentException::class, $message)
        ->and($registry->all())->toBe([]);
})->with([
    'not an array' => ['menus', '声明必须是节点数组'],
    'upper-case name' => [[menuNode('Demo', 'catalog')], 'name 必须是小写点分、以字母开头的权限码'],
    'name starting with a digit' => [[menuNode('2fa', 'catalog')], 'name 必须是小写点分、以字母开头的权限码'],
    'unknown key' => [[menuNode('demo', 'catalog', ['redirect' => '/x'])], '未知的键 redirect'],
    'unknown type' => [[menuNode('demo', 'page')], 'type 必须是'],
    'missing title' => [[menuNode('demo', 'catalog', ['meta' => []])], 'meta.title 必填'],
    'meta key outside the whitelist' => [[menuNode('demo', 'catalog', ['meta' => ['title' => 'x', 'redirect' => '/x']])], 'meta 中不允许的键 redirect'],
    'non-integer order' => [[menuNode('demo', 'catalog', ['meta' => ['title' => 'x', 'order' => '1']])], 'meta.order 必须是整数'],
    'button at the root' => [[menuNode('demo', 'button')], 'button 不能挂在根上'],
    'menu under a menu' => [[menuNode('demo', 'menu', ['children' => [menuNode('demo.page')]])], 'menu 不能挂在 menu 下'],
    'button under a catalog' => [[menuNode('demo', 'catalog', ['children' => [menuNode('demo.save', 'button')]])], 'button 不能挂在 catalog 下'],
    'buttons on a catalog' => [[menuNode('demo', 'catalog', ['buttons' => ['create' => 'common.create']])], 'buttons 只能用于 menu 节点'],
    'invalid button key' => [[menuNode('demo', 'menu', ['buttons' => ['Create' => 'common.create']])], 'buttons 的格式为'],
    'menu without component' => [[menuNode('demo', 'menu', ['component' => null])], 'menu 必须有 component'],
    'catalog with component' => [[menuNode('demo', 'catalog', ['component' => '/demo'])], 'catalog 不能有 component'],
    'path without a slash' => [[menuNode('demo', 'catalog', ['path' => 'demo'])], 'path 必填，以 / 开头'],
    'button with path' => [[menuNode('demo', 'menu', ['children' => [menuNode('demo.save', 'button', ['path' => '/save'])]])], '按钮没有 path'],
    'link meta on a menu' => [[menuNode('demo', 'menu', ['meta' => ['title' => 'x', 'link' => 'https://example.com']])], 'menu 不能有 meta.link'],
    'route meta on a button' => [[menuNode('demo', 'menu', ['children' => [menuNode('demo.save', 'button', ['meta' => ['title' => 'x', 'hideInMenu' => true]])]])], 'button 不能有 meta.hideInMenu'],
    'embedded without iframeSrc' => [[menuNode('demo', 'embedded')], 'embedded 必须有 meta.iframeSrc'],
    'link without link' => [[menuNode('demo', 'link')], 'link 必须有 meta.link'],
    'duplicate name' => [[menuNode('demo', 'catalog'), menuNode('demo', 'catalog', ['path' => '/other'])], '权限码重复声明'],
    'duplicate button name' => [[menuNode('demo', 'menu', ['children' => [menuNode('demo.create', 'button')], 'buttons' => ['create' => 'common.create']])], '（demo.create）：权限码重复声明'],
    'duplicate path' => [[menuNode('demo', 'catalog'), menuNode('other', 'catalog', ['path' => '/demo'])], 'path /demo 与 demo 重复'],
]);

it('rejects names already registered by another module', function (): void {
    $registry = new MenuRegistry;
    $registry->register([menuNode('demo', 'catalog')]);

    expect(fn () => $registry->register([menuNode('other', 'catalog'), menuNode('demo', 'catalog', ['path' => '/demo2'])]))
        ->toThrow(InvalidArgumentException::class, '（demo）：权限码重复声明')
        ->and(array_keys($registry->all()))->toBe(['demo']);
});
