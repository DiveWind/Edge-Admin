<?php

use App\Core\System\Models\Permission;

/*
|--------------------------------------------------------------------------
| GET /system/roles/permission-tree：授权用的节点树（设计文档 §6.5）
|--------------------------------------------------------------------------
*/

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/system/roles/permission-tree')->assertUnauthorized();
});

it('returns 403 without the role create or update code', function (): void {
    syncBuiltinPermissions();

    $this->actingAs(userWithPermissions('system.role', 'system.role.delete'))
        ->getJson('/api/admin/system/roles/permission-tree')
        ->assertForbidden();
});

it('is available with the role create or update code', function (string $code): void {
    syncBuiltinPermissions();

    $this->actingAs(userWithPermissions($code))
        ->getJson('/api/admin/system/roles/permission-tree')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'system')
        ->assertJsonPath('data.0.children.*.name', ['system.user', 'system.role', 'system.menu', 'system.dict', 'system.setting', 'system.attachment'])
        ->assertJsonPath('data.0.children.1.children.*.name', ['system.role.create', 'system.role.update', 'system.role.delete', 'system.role.status']);
})->with(['system.role.create', 'system.role.update']);

it('outputs the enabled nodes with the compact fields, ordered by sort and id', function (): void {
    syncBuiltinPermissions();
    $catalog = Permission::factory()->catalog()->create(['name' => 'demo', 'title' => 'demo.title', 'icon' => 'lucide:box', 'sort' => 0]);
    $second = Permission::factory()->menu()->childOf($catalog)->create(['name' => 'demo.second', 'title' => 'demo.second.title', 'sort' => 2]);
    Permission::factory()->button()->childOf($second)->create(['name' => 'demo.second.create', 'title' => 'common.create']);
    Permission::factory()->link()->childOf($catalog)->create(['name' => 'demo.first', 'title' => 'demo.first.title', 'sort' => 1]);
    $disabled = Permission::factory()->menu()->childOf($catalog)->disabled()->create(['name' => 'demo.disabled']);
    Permission::factory()->button()->childOf($disabled)->create(['name' => 'demo.disabled.create']);
    Permission::factory()->catalog()->create(['name' => 'empty', 'title' => 'empty.title', 'sort' => 1]);

    $this->actingAs(superAdminActor())
        ->getJson('/api/admin/system/roles/permission-tree')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['demo', 'empty', 'system', 'monitor', 'message'])
        ->assertJsonPath('data.0', ['name' => 'demo', 'type' => 'catalog', 'meta' => ['title' => 'demo.title', 'icon' => 'lucide:box'], 'children' => [
            ['name' => 'demo.first', 'type' => 'link', 'meta' => ['title' => 'demo.first.title', 'icon' => null], 'children' => []],
            ['name' => 'demo.second', 'type' => 'menu', 'meta' => ['title' => 'demo.second.title', 'icon' => null], 'children' => [
                ['name' => 'demo.second.create', 'type' => 'button', 'meta' => ['title' => 'common.create', 'icon' => null], 'children' => []],
            ]],
        ]])
        ->assertJsonPath('data.1', ['name' => 'empty', 'type' => 'catalog', 'meta' => ['title' => 'empty.title', 'icon' => null], 'children' => []]);
});
