<?php

use App\Core\Monitor\Models\{Activity, LoginLog, OperationLog, Session};
use App\Core\Monitor\Policies\PersonalAccessTokenPolicy;
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Monitor 模块的注册（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

it('registers the morph aliases of its models', function (): void {
    expect(Relation::getMorphedModel('activity'))->toBe(Activity::class)
        ->and(Relation::getMorphedModel('operation_log'))->toBe(OperationLog::class)
        ->and(Relation::getMorphedModel('login_log'))->toBe(LoginLog::class)
        ->and(Relation::getMorphedModel('session'))->toBe(Session::class);
});

it('binds the policy of the Sanctum tokens', function (): void {
    expect(Gate::getPolicyFor(PersonalAccessToken::class))->toBeInstanceOf(PersonalAccessTokenPolicy::class);
});

it('cleans the old logs every day', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'activitylog:clean'));

    expect($events)->toHaveCount(1)
        ->and($events->sole()->command)->toContain('--force')
        ->and($events->sole()->expression)->toBe('0 0 * * *');
});

it('deletes the logs older than the configured days', function (): void {
    config(['activitylog.clean_after_days' => 30]);
    $old = OperationLog::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = LoginLog::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

    expect(Activity::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(Activity::query()->find($old->id))->toBeNull();
});
