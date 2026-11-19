<?php

use App\Core\Monitor\Models\{Activity, LoginLog, OperationLog};
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| 日志的 Policy：OperationLogPolicy、LoginLogPolicy、ActivityPolicy（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('lets each log be viewed with its own code within the data scope', function (string $model, string $code, string $otherCode): void {
    /** @var class-string<OperationLog|LoginLog> $model */
    $actor = userWithPermissions($code);
    $own = $model::factory()->causedBy($actor)->create();
    $stranger = $model::factory()->causedBy(User::factory()->create())->create();

    expect(Gate::forUser($actor)->allows('viewAny', $model))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('view', $own))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('view', $stranger))->toBeFalse()
        ->and(Gate::forUser(userWithDataScope('all', $otherCode))->allows('viewAny', $model))->toBeFalse()
        ->and(Gate::forUser(userWithDataScope('all', $otherCode))->allows('view', $own))->toBeFalse();
})->with([
    'operation log' => [OperationLog::class, 'monitor.operation-log', 'monitor.login-log'],
    'login log' => [LoginLog::class, 'monitor.login-log', 'monitor.operation-log'],
]);

it('denies every write to the logs, even for super admins', function (string $model): void {
    /** @var class-string<Model> $model */
    $log = $model === Activity::class ? activity('content')->log('created') : $model::factory()->create();
    $gate = Gate::forUser(superAdminActor());

    foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($gate->allows($ability, $log))->toBeFalse("{$model}::{$ability}");
    }

    foreach (['create', 'deleteAny', 'restoreAny', 'forceDeleteAny'] as $ability) {
        expect($gate->allows($ability, $model))->toBeFalse("{$model}::{$ability}");
    }
})->with([OperationLog::class, LoginLog::class, Activity::class]);

it('does not expose the audit logs of modules yet', function (): void {
    expect(Gate::forUser(superAdminActor())->allows('viewAny', Activity::class))->toBeFalse()
        ->and(Gate::forUser(superAdminActor())->allows('view', activity('content')->log('created')))->toBeFalse();
});
