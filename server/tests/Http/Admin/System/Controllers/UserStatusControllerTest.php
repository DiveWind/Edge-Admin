<?php

use App\Core\System\Models\User;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| PUT /system/users/{user}/status：启用 / 停用用户（设计文档 §6.5、§7.3、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->user = User::factory()->create(['username' => 'alice']);
});

it('returns 401 for guests', function (): void {
    $this->putJson("/api/admin/system/users/{$this->user->id}/status", ['status' => 'disabled'])->assertUnauthorized();
});

it('returns 403 without the system.user.status code', function (): void {
    $this->actingAs(userWithDataScope('all', 'system.user.update'))
        ->putJson("/api/admin/system/users/{$this->user->id}/status", ['status' => 'disabled'])
        ->assertForbidden();

    expect($this->user->fresh()?->isEnabled())->toBeTrue();
});

it('returns 403 outside the data scope', function (): void {
    $this->actingAs(userWithPermissions('system.user.status'))
        ->putJson("/api/admin/system/users/{$this->user->id}/status", ['status' => 'disabled'])
        ->assertForbidden();
});

it('returns 403 for yourself', function (): void {
    $actor = userWithPermissions('system.user.status');

    $this->actingAs($actor)
        ->putJson("/api/admin/system/users/{$actor->id}/status", ['status' => 'disabled'])
        ->assertForbidden()
        ->assertJsonPath('message', 'You cannot disable yourself.');
});

it('returns 403 to operators who are not super admins for super admin users', function (): void {
    $superAdmin = superAdminActor();

    $this->actingAs(userWithDataScope('all', 'system.user.status'))
        ->putJson("/api/admin/system/users/{$superAdmin->id}/status", ['status' => 'disabled'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only super admins can manage super admin users.');
});

it('disables and enables the user', function (): void {
    $actor = userWithDataScope('all', 'system.user.status');

    $this->actingAs($actor)
        ->putJson("/api/admin/system/users/{$this->user->id}/status", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.roles', []);
    $this->assertDatabaseHas('users', ['id' => $this->user->id, 'status' => 'disabled']);

    $this->actingAs($actor)
        ->putJson("/api/admin/system/users/{$this->user->id}/status", ['status' => 'enabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'enabled');
});

it('lets the self scope change the users they created', function (): void {
    $actor = userWithPermissions('system.user.status');
    $own = User::factory()->create(['created_by' => $actor->id]);

    $this->actingAs($actor)->putJson("/api/admin/system/users/{$own->id}/status", ['status' => 'disabled'])->assertOk();
});

it('logs out the sessions and revokes the tokens of the disabled user right away', function (): void {
    $browser = new VbenClient($this);
    $browser->login('alice')->assertOk();
    $browser->get('/api/admin/user/info')->assertOk();
    $this->user->createToken('device');

    $admin = new VbenClient($this);
    userWithDataScope('all', 'system.user.status')->forceFill(['username' => 'admin'])->save();
    $admin->login('admin')->assertOk();
    $admin->put("/api/admin/system/users/{$this->user->id}/status", ['status' => 'disabled'])->assertOk();

    $browser->get('/api/admin/user/info')->assertUnauthorized();
    $admin->get('/api/admin/user/info')->assertOk();
    expect($this->user->tokens()->count())->toBe(0);
});

it('validates the status', function (array $payload): void {
    $this->actingAs(superAdminActor())
        ->putJson("/api/admin/system/users/{$this->user->id}/status", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
})->with([
    'missing' => [[]],
    'unknown value' => [['status' => 'active']],
]);
