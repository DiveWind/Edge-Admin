<?php

use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use Illuminate\Contracts\Session\Session as SessionStore;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| SessionPolicy：在线会话的查看与强制下线（设计文档 §6.6、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['session.driver' => 'database']);
    syncBuiltinPermissions();
});

/**
 * 让 $session 成为本次请求的会话。
 */
function startSessionAs(Session $session): void
{
    $store = resolve(SessionStore::class);
    $store->setId($session->id);
    $store->start();
}

it('requires monitor.online to view and monitor.online.logout to log out', function (): void {
    $session = Session::factory()->create();
    $viewer = userWithDataScope('all', 'monitor.online');
    $operator = userWithDataScope('all', 'monitor.online.logout');
    $nobody = userWithDataScope('all', 'system.user');

    expect(Gate::forUser($viewer)->allows('viewAny', Session::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('view', $session))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('delete', $session))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('delete', $session))->toBeTrue()
        ->and(Gate::forUser($nobody)->allows('viewAny', Session::class))->toBeFalse()
        ->and(Gate::forUser($nobody)->allows('view', $session))->toBeFalse();
});

it('denies the abilities sessions do not have', function (string $ability, bool $onRecord): void {
    $session = Session::factory()->create();

    expect(Gate::forUser(superAdminActor())->allows($ability, $onRecord ? $session : Session::class))->toBeFalse();
})->with([
    'create' => ['create', false],
    'update' => ['update', true],
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
    'deleteAny' => ['deleteAny', false],
    'restoreAny' => ['restoreAny', false],
    'forceDeleteAny' => ['forceDeleteAny', false],
]);

it('does not log out the current session, but other sessions of the same user', function (): void {
    $actor = superAdminActor();
    $current = Session::factory()->for($actor)->create();
    $other = Session::factory()->for($actor)->create();
    startSessionAs($current);

    expect(Gate::forUser($actor)->inspect('delete', $current))
        ->allowed()->toBeFalse()
        ->message()->toBe('You cannot log out your current session. Sign out instead.')
        ->and(Gate::forUser($actor)->allows('delete', $other))->toBeTrue();
});

it('lets only super admins log out super admins', function (): void {
    $session = Session::factory()->for(superAdminActor())->create();

    expect(Gate::forUser(userWithDataScope('all', 'monitor.online.logout'))->inspect('delete', $session))
        ->allowed()->toBeFalse()
        ->message()->toBe('Only super admins can manage super admin users.')
        ->and(Gate::forUser(superAdminActor())->allows('delete', $session))->toBeTrue();
});

it('checks the data scope of the owner', function (): void {
    $actor = userWithPermissions('monitor.online.logout');
    $created = Session::factory()->for(User::factory()->state(['created_by' => $actor->id]))->create();
    $stranger = Session::factory()->create();
    $guest = Session::factory()->guest()->create();

    expect(Gate::forUser($actor)->allows('delete', $created))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('delete', $stranger))->toBeFalse()
        ->and(Gate::forUser(superAdminActor())->allows('delete', $guest))->toBeFalse();
});
