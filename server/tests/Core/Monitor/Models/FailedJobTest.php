<?php

use App\Core\Monitor\Models\FailedJob;

/*
|--------------------------------------------------------------------------
| 失败任务的输出：脱敏与截断（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

it('drops the serialized job and masks the payload like the operation log', function (): void {
    config(['core.monitor.failed_job.max_value_length' => 8]);
    $job = FailedJob::factory()->make(['payload' => json_encode([
        'displayName' => 'App\\Jobs\\Notify',
        'data' => ['commandName' => 'App\\Jobs\\Notify', 'command' => 'eyJpdiI6...encrypted'],
        'headers' => ['Authorization-Token' => 'secret', 'X-Trace' => 'abcdefghijkl'],
    ])]);

    expect($job->payloadSummary())->toBe([
        'displayName' => 'App\\Jobs…(truncated)',
        'data' => ['commandName' => 'App\\Jobs…(truncated)'],
        'headers' => ['Authorization-Token' => '******', 'X-Trace' => 'abcdefgh…(truncated)'],
    ]);
});

it('returns an empty summary for a payload that is not JSON', function (): void {
    $job = FailedJob::factory()->make(['payload' => 'not json']);

    expect($job->payloadSummary())->toBe([])
        ->and($job->displayName())->toBeNull();
});

it('masks key-value pairs in the exception and strips the project path', function (): void {
    $job = FailedJob::factory()->make(['exception' => 'Illuminate\\Http\\Client\\RequestException: HTTP 401 for https://api.example.com/sync?api_token=tok-123&page=2 with {"password":"hunter2","user":"alice"} in '.base_path('app/Jobs/Sync.php').":10\nStack trace:\n#0 ".base_path('app/Jobs/Sync.php').'(10): secret: s3cr3t']);

    expect($job->exceptionMessage())->toBe('Illuminate\\Http\\Client\\RequestException: HTTP 401 for https://api.example.com/sync?api_token=******&page=2 with {"password":"******","user":"alice"} in app/Jobs/Sync.php:10')
        ->and($job->exceptionTrace())->toBe("Stack trace:\n#0 app/Jobs/Sync.php(10): secret: ******");
});

it('limits the exception message and the trace lines', function (): void {
    config(['core.monitor.failed_job.max_value_length' => 10, 'core.monitor.failed_job.max_trace_lines' => 2]);
    $job = FailedJob::factory()->make(['exception' => "RuntimeException: boom\n#0 a\n#1 b\n#2 c\n#3 d"]);

    expect($job->exceptionMessage())->toBe('RuntimeExc…(truncated)')
        ->and($job->exceptionTrace())->toBe("#0 a\n#1 b\n…(truncated)");
});

it('only reads the failed jobs table with the database-uuids driver', function (string $driver, int $count): void {
    FailedJob::factory()->create();
    config(['queue.failed.driver' => $driver]);

    expect(FailedJob::query()->stored()->count())->toBe($count);
})->with([['database-uuids', 1], ['file', 0], ['null', 0]]);
