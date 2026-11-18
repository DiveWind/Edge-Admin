<?php

use App\Core\System\Models\User;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| GET /user/info：当前用户信息（设计文档 §6.2）
|--------------------------------------------------------------------------
*/

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/user/info')
        ->assertUnauthorized()
        ->assertExactJson(['code' => 401, 'data' => null, 'message' => 'Unauthenticated.']);
});

it('returns the Vben user info of the logged-in user', function (): void {
    $user = User::factory()->create([
        'username' => 'alice',
        'name' => 'Alice Liddell',
        'email' => 'alice@example.com',
        'avatar' => 'https://example.com/alice.png',
    ]);
    $client = new VbenClient($this);
    $client->login('alice');

    $client->get('/api/admin/user/info')
        ->assertOk()
        ->assertExactJson([
            'code' => 0,
            'data' => [
                'userId' => (string) $user->id,
                'username' => 'alice',
                'realName' => 'Alice Liddell',
                'email' => 'alice@example.com',
                'avatar' => 'https://example.com/alice.png',
                'twoFactorEnabled' => false,
                'roles' => [],
                'desc' => '',
                'homePath' => '/analytics',
            ],
            'message' => 'ok',
        ]);
});

it('returns a null email and avatar and the configured home path', function (): void {
    config(['core.apps.admin.home_path' => '/workspace']);

    $this->actingAs(User::factory()->create(['email' => null, 'avatar' => null]))
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.avatar', null)
        ->assertJsonPath('data.homePath', '/workspace');
});

it('reports two-factor authentication as enabled only once it is confirmed', function (bool $confirmed): void {
    $user = User::factory()->withTwoFactorAuthentication()->create(['two_factor_confirmed_at' => $confirmed ? now() : null]);

    $this->actingAs($user)
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonPath('data.twoFactorEnabled', $confirmed);
})->with([
    'enabled but not confirmed' => false,
    'confirmed' => true,
]);

it('accepts Sanctum tokens as well', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonPath('data.userId', (string) $user->id);
});
