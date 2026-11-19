<?php

use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 在线会话：/monitor/online/sessions 的列表与强制下线（设计文档 §6.6、§7.3）
|--------------------------------------------------------------------------
|
| 会话使用 database 驱动（与默认配置相同）。强制下线的效果用 VbenClient（会话 Cookie）验证；规则矩阵见 SessionPolicyTest。
|
*/

beforeEach(function (): void {
    config(['session.driver' => 'database']);
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/online/sessions')->assertUnauthorized();
    });

    it('returns 403 without the monitor.online code', function (): void {
        $this->actingAs(userWithDataScope('all', 'monitor.operation-log'))
            ->getJson('/api/admin/monitor/online/sessions')
            ->assertForbidden();
    });

    it('lists the online sessions without exposing the session id or payload', function (): void {
        $alice = User::factory()->create(['username' => 'alice', 'name' => 'Alice']);
        $recent = Session::factory()->for($alice)->create(['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'last_activity' => now()->subMinute()->getTimestamp()]);
        $latest = Session::factory()->for($alice)->create();
        Session::factory()->guest()->create();
        Session::factory()->for($alice)->expired()->create();

        $response = $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson('/api/admin/monitor/online/sessions')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$latest->publicId(), $recent->publicId()])
            ->assertJsonPath('data.items.1', [
                'id' => substr($recent->id, 0, 16),
                'user' => ['id' => $alice->id, 'username' => 'alice', 'name' => 'Alice'],
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Firefox',
                'last_activity' => now()->subMinute()->startOfSecond()->toIso8601String(),
                'is_current' => false,
            ]);

        expect($response->getContent())->not->toContain($recent->id, $latest->id);
    });

    it('marks the current session', function (): void {
        $client = new VbenClient($this);
        superAdminActor()->forceFill(['username' => 'root'])->save();
        $client->login('root');

        $client->get('/api/admin/monitor/online/sessions')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.is_current', true);
    });

    it('only lists the sessions of users within the data scope', function (): void {
        $actor = userWithPermissions('monitor.online');
        $own = Session::factory()->for($actor)->create();
        $created = Session::factory()->for(User::factory()->state(['created_by' => $actor->id]))->create(['last_activity' => now()->subMinute()->getTimestamp()]);
        Session::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/online/sessions')
            ->assertJsonPath('data.items.*.id', [$own->publicId(), $created->publicId()]);
    });

    it('filters by the user, username and IP address', function (string $filter): void {
        $alice = User::factory()->create(['username' => 'alice']);
        $match = Session::factory()->for($alice)->create(['ip_address' => '10.9.8.7']);
        Session::factory()->for(User::factory()->create(['username' => 'bob']))->create(['ip_address' => '192.168.0.1']);

        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson('/api/admin/monitor/online/sessions?'.str_replace(':id', (string) $alice->id, $filter))
            ->assertJsonPath('data.items.*.id', [$match->publicId()]);
    })->with(['filter[user_id]=:id', 'filter[username]=LIC', 'filter[ip_address]=10.9.']);

    it('rejects unknown filters and sorts with 422', function (string $query): void {
        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson("/api/admin/monitor/online/sessions?{$query}")
            ->assertUnprocessable();
    })->with(['filter[payload]=x', 'sort=id', 'filter[user_id]=abc']);

    it('lists nothing when the session driver is not database', function (): void {
        Session::factory()->create();
        config(['session.driver' => 'redis']);

        $this->actingAs(userWithDataScope('all', 'monitor.online'))
            ->getJson('/api/admin/monitor/online/sessions')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['items' => [], 'total' => 0], 'message' => 'ok']);
    });
});

describe('destroy', function (): void {
    it('logs out the session: the next request of that browser is unauthenticated', function (): void {
        User::factory()->create(['username' => 'alice']);
        $alice = new VbenClient($this);
        $alice->login('alice')->assertOk();
        $session = Session::query()->sole();

        $this->actingAs(userWithDataScope('all', 'monitor.online.logout'))
            ->deleteJson("/api/admin/monitor/online/sessions/{$session->publicId()}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect(Session::query()->find($session->id))->toBeNull();
        $alice->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('does not log out the current session, but another session of the same user', function (): void {
        superAdminActor()->forceFill(['username' => 'root'])->save();
        $phone = new VbenClient($this);
        $phone->login('root');
        $phoneSession = Session::query()->sole();
        $laptop = new VbenClient($this);
        $laptop->login('root');
        $laptopSession = Session::query()->whereKeyNot($phoneSession->id)->sole();

        $laptop->delete("/api/admin/monitor/online/sessions/{$laptopSession->publicId()}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot log out your current session. Sign out instead.');

        $laptop->delete("/api/admin/monitor/online/sessions/{$phoneSession->publicId()}")->assertOk();

        $phone->get('/api/admin/user/info')->assertUnauthorized();
        $laptop->get('/api/admin/user/info')->assertOk();
    });

    it('returns 403 without the code, for super admins of others and outside of the data scope', function (Closure $actor, Closure $owner): void {
        $session = Session::factory()->for($owner())->create();

        $this->actingAs($actor())
            ->deleteJson("/api/admin/monitor/online/sessions/{$session->publicId()}")
            ->assertForbidden();

        expect(Session::query()->find($session->id))->not->toBeNull();
    })->with([
        'without the code' => [fn (): User => userWithDataScope('all', 'monitor.online'), fn () => User::factory()->create()],
        'super admin session' => [fn (): User => userWithDataScope('all', 'monitor.online.logout'), superAdminActor(...)],
        'outside of the data scope' => [fn (): User => userWithPermissions('monitor.online.logout'), fn () => User::factory()->create()],
    ]);

    it('returns 404 for unknown, expired or guest sessions and invalid ids', function (string $id): void {
        Session::factory()->expired()->create(['id' => 'expiredSession'.str_repeat('a', 26)]);
        Session::factory()->guest()->create(['id' => 'guestSession00000'.str_repeat('b', 23)]);

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/monitor/online/sessions/{$id}")
            ->assertNotFound();
    })->with(['unknownSession00', 'expiredSessionaa', 'guestSession0000', 'too-short', str_repeat('a', 40)]);

    it('returns 404 when the session driver is not database', function (): void {
        $session = Session::factory()->create();
        config(['session.driver' => 'redis']);

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/monitor/online/sessions/{$session->publicId()}")
            ->assertNotFound();
    });
});
