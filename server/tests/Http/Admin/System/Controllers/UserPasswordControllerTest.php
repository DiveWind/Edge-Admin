<?php

use App\Core\System\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| PUT /system/users/{user}/password：重置用户的密码（设计文档 §6.5、§7.4、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->user = User::factory()->create(['username' => 'alice']);
});

it('returns 401 for guests', function (): void {
    $this->putJson("/api/admin/system/users/{$this->user->id}/password", ['password' => 'new-password'])->assertUnauthorized();
});

it('returns 403 without the system.user.password code', function (): void {
    $this->actingAs(userWithDataScope('all', 'system.user.update'))
        ->putJson("/api/admin/system/users/{$this->user->id}/password", ['password' => 'new-password'])
        ->assertForbidden();

    expect(Hash::check('password', (string) $this->user->fresh()?->password))->toBeTrue();
});

it('returns 403 outside the data scope', function (): void {
    $this->actingAs(userWithPermissions('system.user.password'))
        ->putJson("/api/admin/system/users/{$this->user->id}/password", ['password' => 'new-password'])
        ->assertForbidden();
});

it('returns 403 for yourself', function (): void {
    $actor = userWithPermissions('system.user.password');

    $this->actingAs($actor)
        ->putJson("/api/admin/system/users/{$actor->id}/password", ['password' => 'new-password'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Change your own password in your profile settings.');
});

it('returns 403 to operators who are not super admins for super admin users', function (): void {
    $superAdmin = superAdminActor();

    $this->actingAs(userWithDataScope('all', 'system.user.password'))
        ->putJson("/api/admin/system/users/{$superAdmin->id}/password", ['password' => 'new-password'])
        ->assertForbidden();
});

it('resets the password and logs out the sessions and tokens of the user', function (): void {
    $browser = new VbenClient($this);
    $browser->login('alice')->assertOk();
    $this->user->createToken('device');

    $admin = new VbenClient($this);
    userWithPermissions('system.user.password')->forceFill(['username' => 'admin'])->save();
    $this->user->forceFill(['created_by' => User::query()->where('username', 'admin')->value('id')])->save();
    $admin->login('admin')->assertOk();

    $admin->put("/api/admin/system/users/{$this->user->id}/password", ['password' => 'new-password'])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    expect(Hash::check('new-password', (string) $this->user->fresh()?->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0);
    $browser->get('/api/admin/user/info')->assertUnauthorized();
    $admin->get('/api/admin/user/info')->assertOk();
    new VbenClient($this)->login('alice', 'new-password')->assertOk();
});

it('validates the password', function (array $payload, string $field): void {
    $this->actingAs(superAdminActor())
        ->putJson("/api/admin/system/users/{$this->user->id}/password", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing' => [[], 'password'],
    'too short' => [['password' => 'short'], 'password'],
    'unknown field' => [['password' => 'new-password', 'current_password' => 'password'], 'current_password'],
]);
