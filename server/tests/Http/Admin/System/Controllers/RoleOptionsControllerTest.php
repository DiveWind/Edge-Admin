<?php

use App\Core\System\Models\{Role, User};

/*
|--------------------------------------------------------------------------
| GET /system/roles/options：用户表单的角色下拉（设计文档 §6.5）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/system/roles/options')->assertUnauthorized();
});

it('returns 403 without the user create or update code', function (): void {
    $this->actingAs(userWithPermissions('system.user', 'system.role.create', 'system.role.update'))
        ->getJson('/api/admin/system/roles/options')
        ->assertForbidden();
});

it('lists the enabled roles ordered by sort and id', function (string $code): void {
    Role::query()->where('name', Role::SUPER_ADMIN)->sole()->update(['sort' => 9]);
    $own = Role::factory()->create(['name' => 'operator', 'title' => 'Operator', 'sort' => 3]);
    $own->givePermissionTo('system', 'system.user', $code);
    Role::factory()->create(['name' => 'editor', 'title' => 'Editor', 'sort' => 2]);
    Role::factory()->create(['name' => 'viewer', 'title' => 'Viewer', 'sort' => 1]);
    Role::factory()->disabled()->create(['name' => 'auditor', 'sort' => 1]);

    $this->actingAs(User::factory()->create()->assignRole($own))
        ->getJson('/api/admin/system/roles/options')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => [
            ['value' => 'viewer', 'label' => 'Viewer'],
            ['value' => 'editor', 'label' => 'Editor'],
            ['value' => 'operator', 'label' => 'Operator'],
            ['value' => Role::SUPER_ADMIN, 'label' => 'Super Admin'],
        ]]);
})->with(['system.user.create', 'system.user.update']);
