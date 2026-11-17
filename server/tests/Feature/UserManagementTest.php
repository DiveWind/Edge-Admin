<?php

use App\Enums\DataScope;
use App\Models\{Department, Position, Role, User};
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = actingAsUser(superAdmin());
});

test('users can be created with roles and positions', function () {
    $department = Department::factory()->create();
    $role = Role::factory()->create();
    $position = Position::factory()->create();

    $this->postJson('/api/users', [
        'username' => 'zhangsan',
        'name' => '张三',
        'password' => 'password123',
        'department_id' => $department->id,
        'status' => 'active',
        'role_ids' => [$role->id],
        'position_ids' => [$position->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.username', 'zhangsan')
        ->assertJsonPath('data.department', ['id' => $department->id, 'name' => $department->name])
        ->assertJsonPath('data.roles.0.code', $role->code)
        ->assertJsonPath('data.positions.0.code', $position->code)
        ->assertJsonMissingPath('data.password');
});

test('username must be unique', function () {
    User::factory()->create(['username' => 'taken']);

    $this->postJson('/api/users', [
        'username' => 'taken', 'name' => 'x', 'password' => 'password123', 'status' => 'active', 'role_ids' => [], 'position_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('username');
});

test('users can be shown and updated', function () {
    $user = User::factory()->create();

    $this->getJson("/api/users/{$user->id}")->assertOk()->assertJsonStructure(['data' => ['department', 'roles', 'positions']]);

    $this->putJson("/api/users/{$user->id}", [
        'username' => $user->username, 'name' => '新名字', 'status' => 'disabled', 'role_ids' => [], 'position_ids' => [],
    ])->assertOk()->assertJsonPath('data.name', '新名字')->assertJsonPath('data.status', 'disabled');
});

test('user list can be filtered by department including children', function () {
    $parent = Department::factory()->create();
    $child = Department::factory()->childOf($parent)->create();
    User::factory()->create(['department_id' => $parent->id]);
    User::factory()->create(['department_id' => $child->id]);
    User::factory()->create();

    $this->getJson("/api/users?department_id={$parent->id}")->assertOk()->assertJsonCount(2, 'data');
});

test('users cannot delete themselves', function () {
    $this->deleteJson("/api/users/{$this->admin->id}")->assertForbidden();
});

test('the root user cannot be deleted or disabled', function () {
    $other = actingAsUser(superAdmin());
    $root = User::query()->findOrFail(User::ROOT_ID);

    expect($root->is($this->admin))->toBeTrue();

    $this->deleteJson('/api/users/'.User::ROOT_ID)->assertForbidden();
    $this->putJson('/api/users/'.User::ROOT_ID, [
        'username' => $root->username, 'name' => 'x', 'status' => 'active', 'role_ids' => [], 'position_ids' => [],
    ])->assertForbidden();

    actingAsUser($root);
    $this->putJson('/api/users/'.User::ROOT_ID, [
        'username' => $root->username, 'name' => 'x', 'status' => 'disabled', 'role_ids' => [], 'position_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'role_ids']);

    expect($other->exists)->toBeTrue();
});

test('deleting a user soft deletes it and revokes tokens', function () {
    $user = User::factory()->create();
    $user->createToken('api');

    $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

    expect($user->fresh()->trashed())->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('admins can reset a user password', function () {
    $user = User::factory()->create();

    $this->putJson("/api/users/{$user->id}/password", ['password' => 'reset-pass', 'password_confirmation' => 'reset-pass'])
        ->assertNoContent();

    expect(Hash::check('reset-pass', $user->fresh()->password))->toBeTrue();
});

test('only super admins can assign the super admin role', function () {
    $superRole = Role::query()->where('code', Role::SUPER_ADMIN)->firstOrFail();
    actingAsUser(userWithPermissions(['system.user.create']));

    $this->postJson('/api/users', [
        'username' => 'evil', 'name' => 'x', 'password' => 'password123', 'status' => 'active',
        'role_ids' => [$superRole->id], 'position_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('role_ids.0');
});

test('user status can be toggled and disabling revokes tokens', function () {
    $user = User::factory()->create();
    $user->createToken('api');

    $this->patchJson("/api/users/{$user->id}/status", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonStructure(['data' => ['department', 'roles', 'positions']]);

    expect($user->tokens()->count())->toBe(0);

    $this->patchJson("/api/users/{$user->id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
    $this->patchJson("/api/users/{$user->id}/status", ['status' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('users cannot disable themselves and the root user cannot be disabled', function () {
    $this->patchJson("/api/users/{$this->admin->id}/status", ['status' => 'disabled'])
        ->assertForbidden()
        ->assertJsonPath('message', '不能禁用自己。');

    actingAsUser(superAdmin());

    $this->patchJson('/api/users/'.User::ROOT_ID.'/status', ['status' => 'disabled'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('status updates require the update permission and respect data scope', function () {
    $department = Department::factory()->create();
    $inScope = User::factory()->create(['department_id' => $department->id]);
    $outOfScope = User::factory()->create();

    actingAsUser(userWithPermissions(['system.user.view']));
    $this->patchJson("/api/users/{$inScope->id}/status", ['status' => 'disabled'])->assertForbidden();

    actingAsUser(userWithPermissions(['system.user.update'], DataScope::Department, ['department_id' => $department->id]));
    $this->patchJson("/api/users/{$outOfScope->id}/status", ['status' => 'disabled'])->assertForbidden();
    $this->patchJson("/api/users/{$inScope->id}/status", ['status' => 'disabled'])->assertOk();
});
