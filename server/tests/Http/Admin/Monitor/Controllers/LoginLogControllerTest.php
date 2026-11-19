<?php

use App\Core\Monitor\Enums\LoginFailureReason;
use App\Core\Monitor\Models\{LoginLog, OperationLog};
use App\Core\System\Models\User;

/*
|--------------------------------------------------------------------------
| 登录日志：/monitor/login-logs 的列表与详情（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/login-logs')->assertUnauthorized();
    });

    it('returns 403 without the monitor.login-log code', function (): void {
        $this->actingAs(userWithDataScope('all', 'monitor.operation-log'))
            ->getJson('/api/admin/monitor/login-logs')
            ->assertForbidden();
    });

    it('lists the login logs, newest first', function (): void {
        $actor = userWithDataScope('all', 'monitor.login-log');
        $login = LoginLog::factory()->causedBy($actor)->create(['created_at' => '2026-11-19 08:00:00']);
        $failed = LoginLog::factory()->failed(LoginFailureReason::Disabled)->causedBy($actor)->create();
        OperationLog::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/login-logs')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$failed->id, $login->id])
            ->assertJsonPath('data.items.0.event', 'failed')
            ->assertJsonPath('data.items.0.reason', 'disabled')
            ->assertJsonPath('data.items.0.via_remember', null)
            ->assertJsonPath('data.items.1', [
                'id' => $login->id,
                'event' => 'login',
                'reason' => null,
                'via_remember' => false,
                'user_id' => $actor->id,
                'username' => $actor->username,
                'ip_address' => $login->getProperty('ip_address'),
                'user_agent' => $login->getProperty('user_agent'),
                'request_id' => $login->getProperty('request_id'),
                'created_at' => '2026-11-19T08:00:00+00:00',
            ]);
    });

    it('only lists the logs of users within the data scope, and anonymous ones for unrestricted actors', function (): void {
        $actor = userWithPermissions('monitor.login-log');
        $own = LoginLog::factory()->causedBy($actor)->create();
        LoginLog::factory()->causedBy(User::factory()->create())->create();
        $anonymous = LoginLog::factory()->failed()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/login-logs')
            ->assertJsonPath('data.items.*.id', [$own->id]);

        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson('/api/admin/monitor/login-logs')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.0.id', $anonymous->id);
    });

    it('filters the failed attempts by events and reasons', function (): void {
        $actor = userWithDataScope('all', 'monitor.login-log');
        LoginLog::factory()->create();
        $failed = LoginLog::factory()->failed()->create();
        $twoFactor = LoginLog::factory()->failed(LoginFailureReason::TwoFactor)->create();
        $lockout = LoginLog::factory()->create(['event' => 'lockout', 'description' => 'lockout']);

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/login-logs?filter[event]=failed,lockout&sort=id')
            ->assertJsonPath('data.items.*.id', [$failed->id, $twoFactor->id, $lockout->id]);

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/login-logs?filter[reason]=two_factor')
            ->assertJsonPath('data.items.*.id', [$twoFactor->id]);
    });

    it('filters by the username, IP address and account', function (string $query): void {
        $alice = User::factory()->create(['username' => 'alice']);
        $match = LoginLog::factory()->causedBy($alice)->withProperties(['ip_address' => '10.9.8.7'])->create();
        LoginLog::factory()->causedBy(User::factory()->create(['username' => 'bob']))->create();

        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson('/api/admin/monitor/login-logs?'.str_replace(':id', (string) $alice->id, $query))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$match->id]);
    })->with(['filter[username]=lic', 'filter[ip_address]=10.9.', 'filter[user_id]=:id']);

    it('rejects invalid filters with 422', function (string $query, string $field): void {
        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson("/api/admin/monitor/login-logs?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'event' => ['filter[event]=failed,unknown', 'filter.event'],
        'reason' => ['filter[reason]=wrong', 'filter.reason'],
        'unknown filter' => ['filter[password]=x', 'filter'],
    ]);
});

describe('show', function (): void {
    it('shows a login log', function (): void {
        $log = LoginLog::factory()->failed()->create();

        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson("/api/admin/monitor/login-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.reason', 'invalid_credentials');
    });

    it('returns 403 without the code or outside of the data scope', function (User $actor): void {
        $log = LoginLog::factory()->causedBy(User::factory()->create())->create();

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/login-logs/{$log->id}")
            ->assertForbidden();
    })->with([
        'without the code' => fn (): User => userWithDataScope('all', 'monitor.operation-log'),
        'outside of the data scope' => fn (): User => userWithPermissions('monitor.login-log'),
    ]);

    it('returns 404 for other logs', function (): void {
        $log = OperationLog::factory()->create();

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/monitor/login-logs/{$log->id}")
            ->assertNotFound();
    });
});
