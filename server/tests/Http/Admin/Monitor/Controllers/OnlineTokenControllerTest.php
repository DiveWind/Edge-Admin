<?php

use App\Core\System\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| 在线会话页的 token：/monitor/online/tokens 的列表与吊销（设计文档 §6.6、§7.3）
|--------------------------------------------------------------------------
|
| 规则矩阵（当前 token、超管保护、数据范围）见 PersonalAccessTokenPolicyTest。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * $user 的 token 与明文（Bearer 用）。
 *
 * @return array{0: PersonalAccessToken, 1: string}
 */
function issueToken(User $user, string $name = 'device'): array
{
    $token = $user->createToken($name);

    return [$token->accessToken, $token->plainTextToken];
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/online/tokens')->assertUnauthorized();
    });

    it('returns 403 without the monitor.online code', function (): void {
        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson('/api/admin/monitor/online/tokens')
            ->assertForbidden();
    });

    it('lists the valid tokens without the token itself', function (): void {
        $this->freezeSecond();
        $alice = User::factory()->create(['username' => 'alice', 'name' => 'Alice']);
        [$first] = issueToken($alice, 'phone');
        [$second, $plain] = issueToken($alice, 'tablet');
        $alice->tokens()->create(['name' => 'expired', 'token' => hash('sha256', 'expired'), 'abilities' => ['*'], 'expires_at' => now()->subMinute()]);

        $response = $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson('/api/admin/monitor/online/tokens')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$second->id, $first->id])
            ->assertJsonPath('data.items.1', [
                'id' => $first->id,
                'name' => 'phone',
                'abilities' => ['*'],
                'user' => ['id' => $alice->id, 'username' => 'alice', 'name' => 'Alice'],
                'last_used_at' => null,
                'expires_at' => null,
                'created_at' => now()->toIso8601String(),
                'is_current' => false,
            ]);

        expect($response->getContent())->not->toContain(explode('|', $plain)[1], $second->token);
    });

    it('hides the tokens older than the sanctum expiration', function (): void {
        $alice = User::factory()->create();
        [$old] = issueToken($alice);
        $old->forceFill(['created_at' => now()->subHours(2)])->save();
        [$fresh] = issueToken($alice);
        config(['sanctum.expiration' => 60]);

        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson('/api/admin/monitor/online/tokens')
            ->assertJsonPath('data.items.*.id', [$fresh->id]);
    });

    it('marks the token used by the request', function (): void {
        [$token, $plain] = issueToken(superAdminActor());

        $this->withToken($plain)
            ->getJson('/api/admin/monitor/online/tokens')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $token->id)
            ->assertJsonPath('data.items.0.is_current', true);
    });

    it('only lists the tokens of users within the data scope, filtered by user, username and name', function (): void {
        $actor = userWithPermissions('monitor.online');
        $created = User::factory()->create(['username' => 'alice', 'created_by' => $actor->id]);
        [$phone] = issueToken($created, 'phone');
        [$tablet] = issueToken($created, 'tablet');
        issueToken(User::factory()->create());

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/online/tokens')
            ->assertJsonPath('data.items.*.id', [$tablet->id, $phone->id]);

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/online/tokens?filter[user_id]={$created->id}&filter[username]=lic&filter[name]=pho")
            ->assertJsonPath('data.items.*.id', [$phone->id]);
    });

    it('rejects unknown filters and sorts with 422', function (string $query): void {
        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson("/api/admin/monitor/online/tokens?{$query}")
            ->assertUnprocessable();
    })->with(['filter[token]=x', 'sort=token']);
});

describe('destroy', function (): void {
    it('revokes the token: the next request with it is unauthenticated', function (): void {
        [$token, $plain] = issueToken(User::factory()->create());

        $this->actingAs(userWithDataScope('all', 'monitor.online.logout'))
            ->deleteJson("/api/admin/monitor/online/tokens/{$token->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect(PersonalAccessToken::query()->find($token->id))->toBeNull();

        $this->app->make('auth')->forgetGuards();
        $this->withToken($plain)->getJson('/api/admin/user/info')->assertUnauthorized();
    });

    it('does not revoke the token used by the request', function (): void {
        [$token, $plain] = issueToken(superAdminActor());

        $this->withToken($plain)
            ->deleteJson("/api/admin/monitor/online/tokens/{$token->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot revoke the token used by this request. Sign out instead.');

        expect(PersonalAccessToken::query()->find($token->id))->not->toBeNull();
    });

    it('returns 403 without the code', function (): void {
        [$token] = issueToken(User::factory()->create());

        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->deleteJson("/api/admin/monitor/online/tokens/{$token->id}")
            ->assertForbidden();
    });

    it('returns 404 for unknown tokens', function (): void {
        $this->actingAs(superAdminActor())
            ->deleteJson('/api/admin/monitor/online/tokens/999')
            ->assertNotFound();
    });
});
