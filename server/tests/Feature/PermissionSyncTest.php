<?php

use App\Enums\PermissionType;
use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;

test('permission sync is idempotent and marks builtin nodes', function () {
    $this->artisan('permission:sync')->assertSuccessful();
    $first = Permission::query()->orderBy('id')->get(['id', 'code', 'parent_id', 'type', 'path'])->toArray();

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->orderBy('id')->get(['id', 'code', 'parent_id', 'type', 'path'])->toArray())->toBe($first)
        ->and(Permission::query()->where('is_system', false)->exists())->toBeFalse()
        ->and(Permission::query()->where('code', 'system')->value('type'))->toBe(PermissionType::Directory)
        ->and(Permission::query()->where('code', 'system.user')->first(['type', 'path', 'icon'])?->toArray())
        ->toBe(['type' => 'menu', 'path' => '/system/users', 'icon' => 'UserOutlined'])
        ->and(Permission::query()->where('code', 'system.user.reset-password')->value('type'))->toBe(PermissionType::Action);
});

test('permission sync keeps changes made in the menu manager', function () {
    $this->artisan('permission:sync');
    $monitor = Permission::query()->where('code', 'monitor')->sole();
    $user = Permission::query()->where('code', 'system.user')->sole();
    $user->update([
        'name' => '成员管理', 'icon' => 'SmileOutlined', 'sort' => 99, 'is_visible' => false,
        'status' => Status::Disabled, 'parent_id' => $monitor->id, 'remark' => 'moved',
    ]);

    $this->artisan('permission:sync')->assertSuccessful();

    expect($user->fresh()->only(['name', 'icon', 'sort', 'is_visible', 'status', 'parent_id', 'remark']))->toBe([
        'name' => '成员管理', 'icon' => 'SmileOutlined', 'sort' => 99, 'is_visible' => false,
        'status' => Status::Disabled, 'parent_id' => $monitor->id, 'remark' => 'moved',
    ]);
});

test('permission sync restores structural fields of builtin nodes', function () {
    $this->artisan('permission:sync');
    Permission::query()->where('code', 'system.user')->update(['path' => '/somewhere', 'type' => 'directory', 'is_system' => false]);

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->where('code', 'system.user')->first(['type', 'path', 'is_system'])?->toArray())
        ->toBe(['type' => 'menu', 'path' => '/system/users', 'is_system' => true]);
});

test('permission sync keeps custom nodes and releases builtin nodes removed from the config', function () {
    $this->artisan('permission:sync');
    $role = Role::factory()->create();
    $custom = Permission::factory()->menu()->create(['code' => 'custom.page']);
    $legacy = Permission::factory()->system()->create(['code' => 'legacy.thing.view']);
    $role->permissions()->attach([$custom->id, $legacy->id]);

    $this->artisan('permission:sync')
        ->expectsOutputToContain('legacy.thing.view')
        ->assertSuccessful();

    expect($custom->fresh())->not->toBeNull()
        ->and($legacy->fresh()?->is_system)->toBeFalse()
        ->and($role->permissions()->count())->toBe(2);
});

test('permission sync creates the declared tree under the current parent of an existing node', function () {
    config()->set('permissions', [
        ['code' => 'demo', 'name' => '示例', 'path' => '/demo', 'icon' => 'AppstoreOutlined', 'children' => [
            ['code' => 'demo.item', 'name' => '条目', 'path' => '/demo/items', 'visible' => false, 'actions' => ['view' => '查看']],
        ]],
    ]);

    $this->artisan('permission:sync')->assertSuccessful();

    $directory = Permission::query()->where('code', 'demo')->sole();
    $menu = Permission::query()->where('code', 'demo.item')->sole();

    expect(Permission::query()->count())->toBe(3)
        ->and($directory->type)->toBe(PermissionType::Directory)
        ->and($menu->only(['type', 'path', 'is_visible', 'parent_id']))
        ->toBe(['type' => PermissionType::Menu, 'path' => '/demo/items', 'is_visible' => false, 'parent_id' => $directory->id])
        ->and(Permission::query()->where('code', 'demo.item.view')->value('parent_id'))->toBe($menu->id);

    // 已有的上级被移动 / 新配置追加子节点：新节点挂在数据库中按 code 找到的上级下
    $other = Permission::factory()->directory()->create();
    $menu->update(['parent_id' => $other->id]);
    config()->set('permissions.0.children.0.actions.create', '新增');

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->where('code', 'demo.item.create')->value('parent_id'))->toBe($menu->id)
        ->and($menu->fresh()?->parent_id)->toBe($other->id);
});
