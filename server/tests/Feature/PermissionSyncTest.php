<?php

use App\Models\Permission;
use App\Models\Role;

test('permission sync is idempotent', function () {
    $this->artisan('permission:sync')->assertSuccessful();
    $first = Permission::query()->orderBy('id')->get(['id', 'code', 'parent_id'])->toArray();

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->orderBy('id')->get(['id', 'code', 'parent_id'])->toArray())->toBe($first)
        ->and(Permission::query()->where('code', 'system.user.reset-password')->exists())->toBeTrue();
});

test('permission sync updates names and removes undeclared permissions', function () {
    $this->artisan('permission:sync');
    $role = Role::factory()->create();
    $obsolete = Permission::factory()->create(['code' => 'legacy.thing.view']);
    $role->permissions()->attach($obsolete);
    Permission::query()->where('code', 'dashboard.view')->update(['name' => 'old']);

    $this->artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->where('code', 'legacy.thing.view')->exists())->toBeFalse()
        ->and(Permission::query()->where('code', 'dashboard.view')->value('name'))->toBe('查看')
        ->and($role->permissions()->count())->toBe(0);
});

test('permission sync creates the declared tree', function () {
    config()->set('permissions', [
        ['code' => 'demo', 'name' => '示例', 'children' => [
            ['code' => 'demo.item', 'name' => '条目', 'actions' => ['view' => '查看']],
        ]],
    ]);

    $this->artisan('permission:sync')->assertSuccessful();

    $menu = Permission::query()->where('code', 'demo.item')->sole();

    expect(Permission::query()->count())->toBe(3)
        ->and(Permission::query()->where('code', 'demo.item.view')->value('parent_id'))->toBe($menu->id)
        ->and($menu->parent_id)->toBe(Permission::query()->where('code', 'demo')->value('id'));
});
