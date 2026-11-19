<?php

use App\Core\System\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\{PersonalAccessToken, TransientToken};

/*
|--------------------------------------------------------------------------
| PersonalAccessTokenPolicy：token 的查看与吊销（设计文档 §6.6、§8.6；MonitorServiceProvider 中绑定）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * $user 的一个新 token。
 */
function tokenOf(User $user): PersonalAccessToken
{
    return $user->createToken('device')->accessToken;
}

it('requires monitor.online to view and monitor.online.logout to revoke', function (): void {
    $token = tokenOf(User::factory()->create());
    $viewer = userWithDataScope('all', 'monitor.online');

    expect(Gate::forUser($viewer)->allows('viewAny', PersonalAccessToken::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('delete', $token))->toBeFalse()
        ->and(Gate::forUser(userWithDataScope('all', 'monitor.online.logout'))->allows('delete', $token))->toBeTrue()
        ->and(Gate::forUser(userWithDataScope('all', 'system.user'))->allows('viewAny', PersonalAccessToken::class))->toBeFalse()
        ->and(Gate::forUser(superAdminActor())->allows('update', $token))->toBeFalse();
});

it('does not revoke the token used by the request, but other tokens of the same user', function (): void {
    $actor = superAdminActor();
    $current = tokenOf($actor);
    $other = tokenOf($actor);
    $actor->withAccessToken($current);

    expect(Gate::forUser($actor)->inspect('delete', $current))
        ->allowed()->toBeFalse()
        ->message()->toBe('You cannot revoke the token used by this request. Sign out instead.')
        ->and(Gate::forUser($actor)->allows('delete', $other))->toBeTrue();
});

it('revokes any token when the request uses the session cookie', function (): void {
    $actor = superAdminActor();
    $token = tokenOf($actor);
    $actor->withAccessToken(new TransientToken);

    expect(Gate::forUser($actor)->allows('delete', $token))->toBeTrue();
});

it('lets only super admins revoke the tokens of super admins and checks the data scope', function (): void {
    $actor = userWithPermissions('monitor.online.logout');

    expect(Gate::forUser($actor)->inspect('delete', tokenOf(superAdminActor()))->message())->toBe('Only super admins can manage super admin users.')
        ->and(Gate::forUser($actor)->allows('delete', tokenOf(User::factory()->create(['created_by' => $actor->id]))))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('delete', tokenOf(User::factory()->create())))->toBeFalse();
});
