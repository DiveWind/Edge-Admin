<?php

use App\Core\Monitor\Models\{Activity, LoginLog, OperationLog};
use App\Core\System\Models\User;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Context;

/*
|--------------------------------------------------------------------------
| activitylog 的日志模型（设计文档 §6.6、§8.4）
|--------------------------------------------------------------------------
*/

it('is the activity model of activitylog', function (): void {
    expect(activity('content')->log('created'))->toBeInstanceOf(Activity::class);
});

it('records the request id of the context on every activity', function (): void {
    expect(Activity::REQUEST_ID)->toBe(AssignRequestId::CONTEXT_KEY);

    Context::add(Activity::REQUEST_ID, 'request-0001');

    $audit = activity('content')->withProperties(['title' => 'Hello'])->log('created');
    $explicit = activity('content')->withProperties(['request_id' => 'request-0002'])->log('updated');

    expect($audit?->getProperty('request_id'))->toBe('request-0001')
        ->and($audit?->getProperty('title'))->toBe('Hello')
        ->and($explicit?->getProperty('request_id'))->toBe('request-0002');
});

it('adds no request id outside of a request', function (): void {
    expect(activity('content')->log('created')?->properties?->has('request_id'))->toBeFalse();
});

it('scopes the operation and login logs to their log names', function (): void {
    $operation = OperationLog::factory()->create();
    $login = LoginLog::factory()->create();
    activity('content')->log('created');

    expect(OperationLog::query()->pluck('id')->all())->toBe([$operation->id])
        ->and(LoginLog::query()->pluck('id')->all())->toBe([$login->id])
        ->and(OperationLog::query()->find($login->id))->toBeNull()
        ->and(Activity::query()->count())->toBe(3);
});

describe('data scope', function (): void {
    beforeEach(function (): void {
        syncBuiltinPermissions();
        $this->actor = userWithPermissions('monitor.operation-log');
        $this->created = User::factory()->create(['created_by' => $this->actor->id]);
        $this->stranger = User::factory()->create();

        $this->ownLog = OperationLog::factory()->causedBy($this->actor)->create();
        $this->createdLog = OperationLog::factory()->causedBy($this->created)->create();
        $this->strangerLog = OperationLog::factory()->causedBy($this->stranger)->create();
        $this->anonymousLog = OperationLog::factory()->create();
    });

    it('shows the logs of the visible users only', function (): void {
        expect(OperationLog::query()->visibleTo($this->actor)->orderBy('id')->pluck('id')->all())
            ->toBe([$this->ownLog->id, $this->createdLog->id])
            ->and($this->createdLog->isVisibleTo($this->actor))->toBeTrue()
            ->and($this->strangerLog->isVisibleTo($this->actor))->toBeFalse()
            ->and($this->anonymousLog->isVisibleTo($this->actor))->toBeFalse();
    });

    it('shows every log, including those without a causer, when the data scope is unrestricted', function (bool $superAdmin): void {
        $actor = $superAdmin ? superAdminActor() : userWithDataScope('all', 'monitor.operation-log');

        expect(OperationLog::query()->visibleTo($actor)->count())->toBe(4)
            ->and($this->anonymousLog->isVisibleTo($actor))->toBeTrue();
    })->with(['all scope' => false, 'super admin' => true]);

    it('hides the logs of deleted users from restricted actors', function (): void {
        $this->created->delete();

        expect(OperationLog::query()->visibleTo($this->actor)->pluck('id')->all())->toBe([$this->ownLog->id])
            ->and($this->createdLog->refresh()->isVisibleTo($this->actor))->toBeFalse();
    });
});
