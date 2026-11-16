<?php

use App\Models\Permission;
use App\Models\Role;

beforeEach(function () {
    $this->artisan('permission:sync');
    actingAsUser(superAdmin());
    $this->system = Permission::query()->where('code', 'system')->sole();
    $this->users = Permission::query()->where('code', 'system.user')->sole();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuPayload(array $overrides = []): array
{
    return [
        'type' => 'menu', 'parent_id' => null, 'name' => '报表', 'code' => 'report.sales', 'path' => '/report/sales',
        'icon' => 'BarChartOutlined', 'is_external' => false, 'is_visible' => true, 'status' => 'active', 'sort' => 3, ...$overrides,
    ];
}

test('the menu tree can be filtered and contains the menu fields', function () {
    $this->getJson('/api/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'dashboard')
        ->assertJsonPath('data.0.type', 'menu')
        ->assertJsonPath('data.0.path', '/dashboard')
        ->assertJsonPath('data.0.children.0.code', 'dashboard.view')
        ->assertJsonStructure(['data' => [[
            'id', 'parent_id', 'name', 'code', 'type', 'path', 'icon', 'is_external', 'is_visible', 'status', 'is_system', 'sort', 'remark', 'children',
        ]]]);

    $this->getJson('/api/permissions?type=directory')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/api/permissions?code=system.user.re')->assertOk()->assertJsonPath('data.0.code', 'system.user.reset-password');
    $this->getJson('/api/permissions?type=folder')->assertUnprocessable()->assertJsonValidationErrors('type');
});

test('custom directories, menus, actions and external links can be created', function () {
    $directory = $this->postJson('/api/permissions', menuPayload(['type' => 'directory', 'code' => 'report', 'path' => null]))
        ->assertCreated()->assertJsonPath('data.is_system', false)->json('data.id');

    $menu = $this->postJson('/api/permissions', menuPayload(['parent_id' => $directory]))
        ->assertCreated()->assertJsonPath('data.path', '/report/sales')->json('data.id');

    $this->postJson('/api/permissions', ['type' => 'action', 'parent_id' => $menu, 'name' => '导出', 'code' => 'report.sales.export',
        'path' => '/ignored', 'icon' => 'ExportOutlined', 'is_external' => true, 'status' => 'active'])
        ->assertCreated()
        ->assertJsonPath('data.path', null)
        ->assertJsonPath('data.icon', null)
        ->assertJsonPath('data.is_external', false);

    $this->postJson('/api/permissions', menuPayload(['code' => 'link.docs', 'is_external' => true, 'path' => 'https://ant.design']))
        ->assertCreated()->assertJsonPath('data.is_external', true);

    $this->getJson("/api/permissions/{$menu}")->assertOk()->assertJsonPath('data.parent_id', $directory);
});

test('node fields are validated', function (array $overrides, array $errors) {
    $this->postJson('/api/permissions', menuPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'menu without path' => [['path' => null], ['path']],
    'relative path' => [['path' => 'report'], ['path']],
    'external link without url' => [['is_external' => true, 'path' => '/report'], ['path']],
    'bad code' => [['code' => 'Report Sales'], ['code']],
    'duplicate code' => [['code' => 'system.user'], ['code']],
    'bad icon' => [['icon' => 'bar-chart'], ['icon']],
    'unknown type' => [['type' => 'button'], ['type']],
]);

test('the tree hierarchy is enforced', function () {
    $view = Permission::query()->where('code', 'system.user.view')->sole();

    // 菜单下只能放操作、操作必须放在菜单下、操作没有子节点、操作码必须含 .
    $this->postJson('/api/permissions', menuPayload(['parent_id' => $this->users->id]))->assertJsonValidationErrors('parent_id');
    $this->postJson('/api/permissions', menuPayload(['type' => 'directory', 'parent_id' => $this->users->id]))->assertJsonValidationErrors('parent_id');
    $this->postJson('/api/permissions', ['type' => 'action', 'name' => 'x', 'code' => 'x.y', 'status' => 'active'])->assertJsonValidationErrors('parent_id');
    $this->postJson('/api/permissions', ['type' => 'action', 'parent_id' => $this->system->id, 'name' => 'x', 'code' => 'x.y', 'status' => 'active'])
        ->assertJsonValidationErrors('parent_id');
    $this->postJson('/api/permissions', ['type' => 'action', 'parent_id' => $view->id, 'name' => 'x', 'code' => 'x.y', 'status' => 'active'])
        ->assertJsonValidationErrors('parent_id');
    $this->postJson('/api/permissions', ['type' => 'action', 'parent_id' => $this->users->id, 'name' => 'x', 'code' => 'export', 'status' => 'active'])
        ->assertJsonValidationErrors('code');
});

test('a node cannot be moved under itself or its descendants', function () {
    $parent = Permission::factory()->directory()->create();
    $child = Permission::factory()->directory()->childOf($parent)->create();
    $payload = fn (int $parentId): array => ['type' => 'directory', 'parent_id' => $parentId, 'name' => $parent->name, 'code' => $parent->code, 'status' => 'active'];

    $this->putJson("/api/permissions/{$parent->id}", $payload($parent->id))->assertJsonValidationErrors('parent_id');
    $this->putJson("/api/permissions/{$parent->id}", $payload($child->id))->assertJsonValidationErrors('parent_id');
    $this->putJson("/api/permissions/{$parent->id}", $payload($this->system->id))->assertOk()->assertJsonPath('data.parent_id', $this->system->id);
});

test('the type cannot change to one that rejects the existing children', function () {
    $menu = Permission::factory()->menu()->create(['code' => 'report.sales']);
    Permission::factory()->childOf($menu)->create();

    $this->putJson("/api/permissions/{$menu->id}", menuPayload(['type' => 'directory']))->assertJsonValidationErrors('type');
    $this->putJson("/api/permissions/{$menu->id}", menuPayload(['name' => '销售报表']))->assertOk()->assertJsonPath('data.name', '销售报表');
});

test('builtin nodes keep their code, type and path but other fields can be edited', function () {
    $payload = ['type' => 'menu', 'parent_id' => $this->system->id, 'name' => '成员管理', 'code' => 'system.user', 'path' => '/members',
        'icon' => 'SmileOutlined', 'is_visible' => false, 'status' => 'active', 'sort' => 9];

    $this->putJson("/api/permissions/{$this->users->id}", [...$payload, 'code' => 'system.member', 'type' => 'directory'])
        ->assertUnprocessable()->assertJsonValidationErrors(['code', 'type']);

    $this->putJson("/api/permissions/{$this->users->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.name', '成员管理')
        ->assertJsonPath('data.icon', 'SmileOutlined')
        ->assertJsonPath('data.is_visible', false)
        ->assertJsonPath('data.path', '/system/users');
});

test('nodes containing the menu management permissions cannot be disabled', function () {
    $payload = ['type' => 'directory', 'parent_id' => null, 'name' => '系统管理', 'code' => 'system', 'status' => 'disabled'];

    $this->putJson("/api/permissions/{$this->system->id}", $payload)->assertJsonValidationErrors('status');

    $monitor = Permission::query()->where('code', 'monitor')->sole();
    $this->putJson("/api/permissions/{$monitor->id}", [...$payload, 'name' => '系统监控', 'code' => 'monitor'])
        ->assertOk()->assertJsonPath('data.status', 'disabled');
});

test('builtin nodes and nodes with children cannot be deleted', function () {
    $menu = Permission::factory()->menu()->create();
    Permission::factory()->childOf($menu)->create();

    $this->deleteJson("/api/permissions/{$this->users->id}")->assertForbidden()->assertJsonPath('message', __('policy.permission.system'));
    $this->deleteJson("/api/permissions/{$menu->id}")->assertForbidden()->assertJsonPath('message', __('policy.permission.has_children'));
});

test('deleting a node detaches it from roles', function () {
    $menu = Permission::factory()->menu()->create();
    $role = Role::factory()->create();
    $role->permissions()->attach($menu);

    $this->deleteJson("/api/permissions/{$menu->id}")->assertNoContent();

    $this->assertModelMissing($menu);
    expect($role->permissions()->count())->toBe(0);
});

test('menu management requires the matching permission codes', function () {
    actingAsUser(userWithPermissions(['system.permission.view']));

    $this->getJson('/api/permissions')->assertOk();
    $this->postJson('/api/permissions', menuPayload())->assertForbidden();
    $this->putJson("/api/permissions/{$this->users->id}", menuPayload())->assertForbidden();
    $this->deleteJson("/api/permissions/{$this->users->id}")->assertForbidden();
});

test('changes are written to the operation log', function () {
    $this->postJson('/api/permissions', menuPayload())->assertCreated();

    $this->assertDatabaseHas('operation_logs', ['route_name' => 'permissions.store', 'summary' => '新增菜单']);
});
