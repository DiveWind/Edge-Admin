<?php

use App\Core\Monitor\Models\{FailedJob, ScheduledTask, ScheduledTaskLog};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| 定时任务、执行记录与失败任务的 Policy（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 各接口的 403 由控制器测试覆盖；这里覆盖接口用不到的标准方法一律拒绝（超管也一样），以及不按数据范围过滤。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('denies the standard methods that have no endpoint, even for super admins', function (string $model, array $records, array $classes): void {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create();
    $gate = Gate::forUser(superAdminActor());

    foreach ($records as $ability) {
        expect($gate->allows($ability, $record))->toBeFalse("{$model}::{$ability}");
    }

    foreach ($classes as $ability) {
        expect($gate->allows($ability, $model))->toBeFalse("{$model}::{$ability}");
    }
})->with([
    'scheduled task' => [ScheduledTask::class, ['update', 'delete', 'restore', 'forceDelete'], ['create', 'deleteAny', 'restoreAny', 'forceDeleteAny']],
    'scheduled task log' => [ScheduledTaskLog::class, ['update', 'delete', 'restore', 'forceDelete'], ['create', 'deleteAny', 'restoreAny', 'forceDeleteAny']],
    'failed job' => [FailedJob::class, ['update', 'restore', 'forceDelete'], ['create', 'restoreAny', 'forceDeleteAny']],
]);

it('is not limited by the data scope of the actor', function (): void {
    $actor = userWithDataScope('self', 'monitor.schedule.run', 'monitor.failed-job.retry', 'monitor.failed-job.delete');
    $gate = Gate::forUser($actor);
    $task = ScheduledTask::factory()->create();
    $job = FailedJob::factory()->create();

    expect($gate->allows('view', $task))->toBeTrue()
        ->and($gate->allows('run', $task))->toBeTrue()
        ->and($gate->allows('view', ScheduledTaskLog::factory()->create()))->toBeTrue()
        ->and($gate->allows('view', $job))->toBeTrue()
        ->and($gate->allows('retry', $job))->toBeTrue()
        ->and($gate->allows('retryAny', FailedJob::class))->toBeTrue()
        ->and($gate->allows('delete', $job))->toBeTrue()
        ->and($gate->allows('deleteAny', FailedJob::class))->toBeTrue();
});
