<?php

use Illuminate\Support\Facades\Process;

/*
|--------------------------------------------------------------------------
| 常驻内存（Octane）：同一个应用实例处理一组请求，检查请求之间的状态泄露（设计文档 §10.3、§10.5）
|--------------------------------------------------------------------------
|
| Fixtures/octane-smoke.php 在子进程中启动 Octane 的 Worker（与 FrankenPHP / RoadRunner 的 worker 相同：启动一次应用，
| 每个请求一个沙箱，请求结束时执行 config/octane.php 的监听），用独立的 SQLite 库发一组交替的请求：登录状态、数据范围、
| 授权变更立即生效、语言、请求 ID、响应后写入的操作日志、停用账号立即失效。同一组请求在 CI 中另用真实的 FrankenPHP 跑一遍
| （composer test:octane，2 个 worker）。
|
*/

it('serves interleaved requests of different users from one Octane worker without leaking state between them', function (): void {
    $result = Process::timeout(120)->run([PHP_BINARY, __DIR__.'/Fixtures/octane-smoke.php', 'worker']);
    $report = json_decode($result->output(), true);

    expect($report)->toBeArray($result->output().$result->errorOutput())
        ->and($report['failed'])->toBe([])
        ->and($report['passed'])->not->toBeEmpty()
        ->and($result->exitCode())->toBe(0);
});
