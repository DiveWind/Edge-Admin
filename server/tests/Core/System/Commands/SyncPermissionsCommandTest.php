<?php

use App\Core\System\Enums\{PermissionType, Status};
use App\Core\System\Models\{Permission, Role};
use App\Core\System\Services\MenuRegistry;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| core:sync-permissions：同步声明的节点（设计文档 §8.2）
|--------------------------------------------------------------------------
|
| 每个测试用一个只含测试声明的 MenuRegistry，System 自己的节点见“同步 System 的节点”。
|
*/

/**
 * 用给定的声明替换容器中的 MenuRegistry。
 *
 * @param  list<array<string, mixed>>  $nodes
 */
function declareMenus(array $nodes): MenuRegistry
{
    $registry = new MenuRegistry;
    $registry->register($nodes);
    app()->instance(MenuRegistry::class, $registry);

    return $registry;
}

/**
 * 测试用的声明：目录 demo，下有菜单 demo.page（按钮 create）与外链 demo.docs。
 *
 * @return list<array<string, mixed>>
 */
function demoMenus(string $component = '/demo/page/list', string $link = 'https://example.com/docs'): array
{
    return [[
        'name' => 'demo',
        'type' => 'catalog',
        'path' => '/demo',
        'meta' => ['title' => 'demo.title', 'icon' => 'lucide:box', 'order' => 100],
        'children' => [
            [
                'name' => 'demo.page',
                'type' => 'menu',
                'path' => '/demo/page',
                'component' => $component,
                'meta' => ['title' => 'demo.page.title', 'keepAlive' => true],
                'buttons' => ['create' => 'common.create'],
            ],
            [
                'name' => 'demo.docs',
                'type' => 'link',
                'path' => '/demo/docs',
                'meta' => ['title' => 'demo.docs.title', 'link' => $link],
            ],
        ],
    ]];
}

function node(string $name): Permission
{
    return Permission::query()->where('name', $name)->sole();
}

it('creates the declared nodes as built-in nodes and the super-admin role', function (): void {
    app()->setLocale('zh_CN');
    declareMenus(demoMenus());

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('新增 4 个，修正 0 个，收编 0 个，转为自定义 0 个')
        ->expectsOutputToContain('已创建超管角色 super-admin')
        ->assertSuccessful();

    expect(node('demo'))
        ->parent_id->toBeNull()
        ->type->toBe(PermissionType::Catalog)
        ->title->toBe('demo.title')
        ->icon->toBe('lucide:box')
        ->sort->toBe(100)
        ->status->toBe(Status::Enabled)
        ->meta->toBeNull()
        ->is_builtin->toBeTrue()
        ->and(node('demo.page'))
        ->parent_id->toBe(node('demo')->id)
        ->component->toBe('/demo/page/list')
        ->meta->toBe(['keepAlive' => true])
        ->and(node('demo.page.create'))
        ->parent_id->toBe(node('demo.page')->id)
        ->type->toBe(PermissionType::Button)
        ->title->toBe('common.create')
        ->path->toBeNull()
        ->and(node('demo.docs'))
        ->type->toBe(PermissionType::Link)
        ->meta->toBe(['link' => 'https://example.com/docs'])
        ->and(Role::query()->where('name', Role::SUPER_ADMIN)->sole())
        ->status->toBe(Status::Enabled)
        ->title->toBe('超级管理员');
});

it('is idempotent', function (): void {
    declareMenus(demoMenus());
    $this->artisan('core:sync-permissions')->assertSuccessful();
    $before = Permission::query()->orderBy('id')->get()->toArray();

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('节点已是最新，没有变更')
        ->assertSuccessful();

    expect(Permission::query()->orderBy('id')->get()->toArray())->toBe($before)
        ->and(Role::query()->count())->toBe(1);
});

it('keeps the adjustments made by administrators', function (): void {
    declareMenus(demoMenus());
    $this->artisan('core:sync-permissions')->assertSuccessful();
    $other = Permission::factory()->create();
    node('demo.page')->forceFill([
        'parent_id' => $other->id,
        'title' => 'Custom title',
        'icon' => 'lucide:star',
        'sort' => 5,
        'status' => Status::Disabled,
        'meta' => ['keepAlive' => false, 'hideInMenu' => true],
    ])->save();

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('没有变更')
        ->assertSuccessful();

    expect(node('demo.page'))
        ->parent_id->toBe($other->id)
        ->title->toBe('Custom title')
        ->icon->toBe('lucide:star')
        ->sort->toBe(5)
        ->status->toBe(Status::Disabled)
        ->meta->toBe(['keepAlive' => false, 'hideInMenu' => true]);
});

it('corrects the structural fields from the declaration', function (): void {
    declareMenus(demoMenus());
    $this->artisan('core:sync-permissions')->assertSuccessful();
    node('demo.page')->forceFill(['type' => PermissionType::Embedded, 'path' => '/moved', 'component' => '/old', 'meta' => ['iframeSrc' => 'https://old.example.com', 'hideInTab' => true]])->save();
    node('demo.docs')->forceFill(['meta' => ['link' => 'https://old.example.com', 'badge' => 'new']])->save();
    declareMenus(demoMenus(component: '/demo/page/index', link: 'https://example.com/v2'));

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('新增 0 个，修正 2 个')
        ->assertSuccessful();

    expect(node('demo.page'))
        ->type->toBe(PermissionType::Menu)
        ->path->toBe('/demo/page')
        ->component->toBe('/demo/page/index')
        ->meta->toBe(['hideInTab' => true])
        ->and(node('demo.docs')->meta)->toBe(['badge' => 'new', 'link' => 'https://example.com/v2']);
});

it('adopts a custom node with the same name', function (): void {
    $catalog = Permission::factory()->create(['name' => 'demo', 'title' => 'My demo', 'path' => '/custom', 'is_builtin' => false]);
    declareMenus(demoMenus());

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('新增 3 个，修正 0 个，收编 1 个')
        ->expectsOutputToContain('收编同名的自定义节点')
        ->assertSuccessful();

    expect(node('demo'))
        ->id->toBe($catalog->id)
        ->is_builtin->toBeTrue()
        ->path->toBe('/demo')
        ->title->toBe('My demo')
        ->and(node('demo.page')->parent_id)->toBe($catalog->id);
});

it('releases removed built-in nodes as custom nodes and keeps their grants', function (): void {
    declareMenus(demoMenus());
    $this->artisan('core:sync-permissions')->assertSuccessful();
    $role = Role::factory()->create();
    $role->givePermissionTo('demo', 'demo.docs');
    $menus = demoMenus();
    unset($menus[0]['children'][1]);
    declareMenus([[...$menus[0], 'children' => array_values($menus[0]['children'])]]);

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('转为自定义 1 个')
        ->expectsOutputToContain('demo.docs')
        ->assertSuccessful();

    expect(node('demo.docs')->is_builtin)->toBeFalse()
        ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['demo', 'demo.docs']);
});

it('does not recreate an existing super-admin role', function (): void {
    declareMenus([]);
    $role = Role::factory()->superAdmin()->disabled()->create(['title' => 'Root']);

    $this->artisan('core:sync-permissions')
        ->doesntExpectOutputToContain('已创建超管角色')
        ->assertSuccessful();

    expect(Role::query()->sole())
        ->id->toBe($role->id)
        ->title->toBe('Root');
});

it('syncs the nodes declared by the Core modules', function (): void {
    $this->artisan('core:sync-permissions')->assertSuccessful();

    // 只看 Core 模块的节点：业务模块（app/Modules）同样在 MenuRegistry 中注册，测试不依赖有哪些业务模块
    expect(Permission::query()->orderBy('id')->pluck('name')->filter(fn (string $name): bool => in_array(Str::before($name, '.'), CORE_CATALOGS, true))->values()->all())->toBe([
        'system',
        'system.user', 'system.user.create', 'system.user.update', 'system.user.delete', 'system.user.status', 'system.user.password', 'system.user.export',
        'system.role', 'system.role.create', 'system.role.update', 'system.role.delete', 'system.role.status',
        'system.menu', 'system.menu.create', 'system.menu.update', 'system.menu.delete',
        'system.dict', 'system.dict.create', 'system.dict.update', 'system.dict.delete',
        'system.setting', 'system.setting.update',
        'system.attachment', 'system.attachment.delete',
        'monitor', 'monitor.online', 'monitor.online.logout', 'monitor.operation-log', 'monitor.login-log',
        'monitor.schedule', 'monitor.schedule.run', 'monitor.failed-job', 'monitor.failed-job.retry', 'monitor.failed-job.delete',
        'monitor.health', 'monitor.health.check',
        'monitor.backup', 'monitor.backup.run',
        'message', 'message.announcement', 'message.announcement.create', 'message.announcement.update', 'message.announcement.delete',
        'message.announcement.publish', 'message.announcement.revoke',
    ])->and(node('system.menu')->component)->toBe('/system/menu/list');
});

it('keeps the adjustments made to built-in nodes in menu management', function (): void {
    $this->artisan('core:sync-permissions')->assertSuccessful();
    $actor = superAdminActor();
    $catalog = Permission::factory()->create(['name' => 'custom', 'path' => '/custom']);

    $this->actingAs($actor)
        ->putJson('/api/admin/system/menus/'.node('system.role')->id, [
            'parent_id' => $catalog->id,
            'type' => 'menu',
            'name' => 'system.role',
            'path' => '/system/role',
            'component' => '/system/role/list',
            'status' => 'disabled',
            'meta' => ['title' => 'Roles', 'icon' => 'lucide:users', 'order' => 1, 'hideInMenu' => true, 'keepAlive' => true],
        ])
        ->assertOk();

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('没有变更')
        ->assertSuccessful();

    expect(node('system.role'))
        ->parent_id->toBe($catalog->id)
        ->status->toBe(Status::Disabled)
        ->title->toBe('Roles')
        ->icon->toBe('lucide:users')
        ->sort->toBe(1)
        ->meta->toBe(['keepAlive' => true, 'hideInMenu' => true])
        ->is_builtin->toBeTrue();
});

it('fails without writing anything when a declared path is taken by another node', function (): void {
    $custom = Permission::factory()->create(['name' => 'custom', 'path' => '/demo/docs']);
    declareMenus(demoMenus());

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('demo.docs 的 path /demo/docs 已被节点 custom')
        ->expectsOutputToContain('本次同步没有写入任何数据')
        ->expectsOutputToContain('重新执行 php artisan core:sync-permissions')
        ->assertFailed();

    expect(Permission::query()->pluck('name')->all())->toBe(['custom'])
        ->and(Role::query()->exists())->toBeFalse()
        ->and($custom->fresh()?->path)->toBe('/demo/docs');
});

it('swaps the paths of declared nodes', function (): void {
    declareMenus(demoMenus());
    $this->artisan('core:sync-permissions')->assertSuccessful();
    $menus = demoMenus();
    $menus[0]['children'][0]['path'] = '/demo/docs';
    $menus[0]['children'][1]['path'] = '/demo/page';
    declareMenus($menus);

    $this->artisan('core:sync-permissions')
        ->expectsOutputToContain('修正 2 个')
        ->assertSuccessful();

    expect(node('demo.page')->path)->toBe('/demo/docs')
        ->and(node('demo.docs')->path)->toBe('/demo/page');
});
