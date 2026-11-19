<?php

use App\Core\System\Models\{Role, User};

/*
|--------------------------------------------------------------------------
| PUT /system/roles/{role}/status：启用 / 停用角色（设计文档 §6.5、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->role = Role::factory()->create();
    $this->role->givePermissionTo('system', 'system.user');
});

it('returns 401 for guests', function (): void {
    $this->putJson("/api/admin/system/roles/{$this->role->id}/status", ['status' => 'disabled'])->assertUnauthorized();
});

it('returns 403 without the system.role.status code', function (): void {
    $this->actingAs(userWithPermissions('system.role.update'))
        ->putJson("/api/admin/system/roles/{$this->role->id}/status", ['status' => 'disabled'])
        ->assertForbidden();

    $this->assertDatabaseHas('roles', ['id' => $this->role->id, 'status' => 'enabled']);
});

it('disables and enables the role', function (): void {
    $actor = userWithPermissions('system.role.status');

    $this->actingAs($actor)
        ->putJson("/api/admin/system/roles/{$this->role->id}/status", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->role->id)
        ->assertJsonPath('data.status', 'disabled');
    $this->assertDatabaseHas('roles', ['id' => $this->role->id, 'status' => 'disabled']);

    $this->actingAs($actor)
        ->putJson("/api/admin/system/roles/{$this->role->id}/status", ['status' => 'enabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'enabled');
});

it('validates the status', function (array $payload): void {
    $this->actingAs(superAdminActor())
        ->putJson("/api/admin/system/roles/{$this->role->id}/status", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
})->with([
    'missing' => [[]],
    'unknown value' => [['status' => 'active']],
]);

it('returns 422 when disabling the super admin role', function (): void {
    $superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->sole();

    $this->actingAs(superAdminActor())
        ->putJson("/api/admin/system/roles/{$superAdmin->id}/status", ['status' => 'disabled'], ['Accept-Language' => 'zh-CN'])
        ->assertUnprocessable()
        ->assertJsonPath('message', '超级管理员角色不能停用。')
        ->assertJsonPath('errors.status.0', '超级管理员角色不能停用。');

    $this->assertDatabaseHas('roles', ['id' => $superAdmin->id, 'status' => 'enabled']);
});

it('returns 403 to operators who are not super admins for the super admin role', function (): void {
    $superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->sole();

    $this->actingAs(userWithPermissions('system.role.status'))
        ->putJson("/api/admin/system/roles/{$superAdmin->id}/status", ['status' => 'enabled'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only super admins can manage the super admin role.');
});

it('removes the codes and menus of the role right away when it is disabled', function (): void {
    $member = User::factory()->create()->assignRole($this->role);

    $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')->assertJsonPath('data', ['system', 'system.user']);
    $this->actingAs($member->fresh())->getJson('/api/admin/menu/all')->assertJsonPath('data.0.children.*.name', ['system.user']);

    $this->actingAs(superAdminActor())
        ->putJson("/api/admin/system/roles/{$this->role->id}/status", ['status' => 'disabled'])
        ->assertOk();

    $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')->assertExactJson(['code' => 0, 'data' => [], 'message' => 'ok']);
    $this->actingAs($member->fresh())->getJson('/api/admin/menu/all')->assertExactJson(['code' => 0, 'data' => [], 'message' => 'ok']);
});
