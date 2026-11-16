<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsUser(superAdmin());

    $this->uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $this->uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => $this->uuid, 'displayName' => 'App\\Jobs\\RunScheduledTask', 'job' => 'x', 'data' => []]),
        'exception' => "RuntimeException: boom\n#0 trace",
        'failed_at' => now(),
    ]);
});

test('failed jobs can be listed', function () {
    $this->getJson('/api/failed-jobs')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $this->uuid)
        ->assertJsonPath('data.0.name', 'App\\Jobs\\RunScheduledTask')
        ->assertJsonPath('data.0.exception', 'RuntimeException: boom')
        ->assertJsonStructure(['data' => [['id', 'uuid', 'connection', 'queue', 'name', 'exception', 'failed_at']]]);
});

test('failed jobs can be retried', function () {
    $this->postJson("/api/failed-jobs/{$this->uuid}/retry")->assertNoContent();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);
});

test('failed jobs can be deleted', function () {
    $this->deleteJson("/api/failed-jobs/{$this->uuid}")->assertNoContent();
    $this->deleteJson("/api/failed-jobs/{$this->uuid}")->assertNotFound();
});
