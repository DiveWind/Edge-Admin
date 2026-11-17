<?php

use App\Models\{Permission, Role, User};

test('user without the permission receives 403', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/users')->assertForbidden()->assertJsonStructure(['message']);
});

test('user with the permission can access the endpoint', function () {
    actingAsUser(userWithPermissions(['system.user.view']));

    $this->getJson('/api/users')->assertOk();
    $this->postJson('/api/users', [])->assertForbidden();
});

test('super admin passes every permission check', function (string $method, string $uri) {
    actingAsUser(superAdmin());

    $this->json($method, $uri)->assertSuccessful();
})->with([
    ['GET', '/api/users'],
    ['GET', '/api/roles'],
    ['GET', '/api/permissions'],
    ['GET', '/api/departments'],
    ['GET', '/api/positions'],
    ['GET', '/api/login-logs'],
    ['GET', '/api/operation-logs'],
    ['GET', '/api/scheduled-tasks'],
    ['GET', '/api/scheduled-task-logs'],
    ['GET', '/api/failed-jobs'],
    ['GET', '/api/announcements'],
    ['GET', '/api/article-categories'],
    ['GET', '/api/articles'],
    ['GET', '/api/dashboard'],
]);

test('permissions granted to a role take effect for its users', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $this->artisan('permission:sync');

    actingAsUser(superAdmin());
    $permissionId = Permission::query()->where('code', 'system.role.view')->value('id');

    $this->putJson("/api/roles/{$role->id}", [
        'name' => $role->name,
        'code' => $role->code,
        'data_scope' => 'all',
        'status' => 'active',
        'permission_ids' => [$permissionId],
    ])->assertOk();

    actingAsUser($user->fresh());

    $this->getJson('/api/roles')->assertOk();
    $this->getJson('/api/users')->assertForbidden();
});

test('disabled roles do not grant permissions', function () {
    $user = userWithPermissions(['system.user.view']);
    $user->roles()->update(['status' => 'disabled']);

    actingAsUser($user->fresh());

    $this->getJson('/api/users')->assertForbidden();
});

test('me lists the permission codes of active roles', function () {
    actingAsUser(userWithPermissions(['dashboard.view', 'content.article.view']));

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.is_super_admin', false)
        ->assertJsonPath('data.permissions', ['dashboard.view', 'content.article.view']);
});
