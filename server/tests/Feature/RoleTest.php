<?php

use App\Models\{Department, Permission, Role, User};

beforeEach(function () {
    actingAsUser(superAdmin());
    $this->artisan('permission:sync');
});

test('roles can be created with permissions and custom departments', function () {
    $permissions = Permission::query()->limit(3)->pluck('id')->all();
    $department = Department::factory()->create();

    $id = $this->postJson('/api/roles', [
        'name' => '审核员',
        'code' => 'auditor',
        'data_scope' => 'custom',
        'status' => 'active',
        'permission_ids' => $permissions,
        'department_ids' => [$department->id],
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/roles/{$id}")
        ->assertOk()
        ->assertJsonPath('data.permission_ids', $permissions)
        ->assertJsonPath('data.department_ids', [$department->id]);
});

test('custom departments are cleared when the scope is not custom', function () {
    $role = Role::factory()->create();

    $this->putJson("/api/roles/{$role->id}", [
        'name' => $role->name, 'code' => $role->code, 'data_scope' => 'department', 'status' => 'active',
        'permission_ids' => [], 'department_ids' => [Department::factory()->create()->id],
    ])->assertOk()->assertJsonPath('data.department_ids', []);
});

test('the super admin role is protected', function () {
    $role = Role::query()->where('code', Role::SUPER_ADMIN)->firstOrFail();

    $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
    $this->putJson("/api/roles/{$role->id}", [
        'name' => '超管', 'code' => 'renamed', 'data_scope' => 'all', 'status' => 'disabled', 'permission_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['code', 'status']);
});

test('roles assigned to users cannot be deleted', function () {
    $role = Role::factory()->create();
    User::factory()->create()->roles()->attach($role);

    $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
    $this->deleteJson('/api/roles/'.Role::factory()->create()->id)->assertNoContent();
});

test('permissions are returned as a tree', function () {
    $this->getJson('/api/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'dashboard')
        ->assertJsonPath('data.0.children.0.code', 'dashboard.view')
        ->assertJsonStructure(['data' => [['id', 'parent_id', 'name', 'code', 'type', 'sort', 'children']]]);
});
