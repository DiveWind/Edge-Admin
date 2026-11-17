<?php

use App\Enums\TaskStatus;
use App\Jobs\RunScheduledTask;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Events\{ScheduledTaskFinished, ScheduledTaskStarting};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    actingAsUser(superAdmin());
});

test('scheduled tasks are read from the laravel schedule', function () {
    ScheduledTaskLog::factory()->create(['task' => 'model:prune']);

    $response = $this->getJson('/api/scheduled-tasks')
        ->assertOk()
        ->assertJsonStructure(['data' => [['key', 'command', 'description', 'expression', 'next_run_at', 'last_log']]]);

    $tasks = collect($response->json('data'))->keyBy('key');

    expect($tasks->keys()->all())->toContain('model-prune', 'sanctum-prune-expired-hours-24', 'queue-prune-failed-hours-168')
        ->and($tasks['model-prune']['command'])->toBe('model:prune')
        ->and($tasks['model-prune']['last_log']['task'])->toBe('model:prune')
        ->and($tasks['sanctum-prune-expired-hours-24']['last_log'])->toBeNull();
});

test('tasks can be run immediately through the queue', function () {
    Queue::fake();

    $this->postJson('/api/scheduled-tasks/model-prune/run')->assertAccepted();
    $this->postJson('/api/scheduled-tasks/unknown/run')->assertNotFound();

    Queue::assertPushed(RunScheduledTask::class, fn (RunScheduledTask $job) => $job->command === 'model:prune');
});

test('the run job executes the command and writes a log', function () {
    (new RunScheduledTask('model:prune'))->handle();

    $log = ScheduledTaskLog::query()->sole();

    expect($log->task)->toBe('model:prune')
        ->and($log->status)->toBe(TaskStatus::Success)
        ->and($log->finished_at)->not->toBeNull();
});

test('schedule events are recorded by listeners', function () {
    $event = collect(app(Schedule::class)->events())->first();

    event(new ScheduledTaskStarting($event));
    expect(ScheduledTaskLog::query()->sole()->status)->toBe(TaskStatus::Running);

    $event->exitCode = 0;
    event(new ScheduledTaskFinished($event, 0.5));
    expect(ScheduledTaskLog::query()->sole()->status)->toBe(TaskStatus::Success);
});

test('task logs can be listed', function () {
    ScheduledTaskLog::factory()->create(['task' => 'model:prune', 'status' => TaskStatus::Failed]);
    ScheduledTaskLog::factory()->create(['task' => 'sanctum:prune-expired --hours=24']);

    $this->getJson('/api/scheduled-task-logs?status=failed')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/scheduled-task-logs?task=sanctum')->assertOk()->assertJsonCount(1, 'data');
});

test('running the same task again while it is still queued does not queue a duplicate', function () {
    Queue::fake();
    actingAsUser(superAdmin());

    $this->postJson('/api/scheduled-tasks/model-prune/run')->assertAccepted();
    $this->postJson('/api/scheduled-tasks/model-prune/run')->assertAccepted();
    $this->postJson('/api/scheduled-tasks/sanctum-prune-expired-hours-24/run')->assertAccepted();

    Queue::assertPushed(RunScheduledTask::class, 2);
});
