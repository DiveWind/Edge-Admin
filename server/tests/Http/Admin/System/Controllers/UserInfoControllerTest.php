<?php

use App\Core\System\Models\{Attachment, Role, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
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
                'avatar' => null,
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

    $this->actingAs(User::factory()->create(['email' => null]))
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.avatar', null)
        ->assertJsonPath('data.homePath', '/workspace');
});

it('returns a signed preview URL of the avatar that stays the same within a day and expires', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $avatar = Attachment::factory()->boundTo($user, User::AVATAR_COLLECTION)->withFile(pngBytes())->create();

    $this->travelTo(CarbonImmutable::parse('2026-11-20 08:10:00'));
    $url = $this->actingAs($user)->getJson('/api/admin/user/info')->assertOk()->json('data.avatar');

    expect($url)->toStartWith(url("/api/admin/attachments/{$avatar->id}/preview?expires=".CarbonImmutable::parse('2026-11-22 00:00:00')->getTimestamp().'&'))
        ->and($this->getJson('/api/admin/user/info')->json('data.avatar'))->toBe($url);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

    $this->travel(49)->hours();
    $this->get($url)->assertForbidden();
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

it('returns the identifiers of the enabled roles only', function (): void {
    $user = User::factory()->create()->assignRole(
        Role::factory()->superAdmin()->create(),
        Role::factory()->create(['name' => 'editor']),
        Role::factory()->disabled()->create(['name' => 'auditor']),
    );

    $this->actingAs($user)
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonPath('data.roles', ['super-admin', 'editor']);
});
