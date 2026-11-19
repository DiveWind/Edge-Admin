<?php

use App\Core\Monitor\Checks\BackupsCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 健康检查 Backups：与 backup:monitor 同一套规则（设计文档 §6.6、§6.10）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['backup.monitor_backups.0.name' => 'app', 'backup.monitor_backups.0.disks' => ['backups']]);
    Storage::fake('backups');
});

it('is ok when every destination has a recent backup', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:00:00'));
    Storage::disk('backups')->put('app/2026-11-19-01-30-00.zip', 'x');

    $result = BackupsCheck::new()->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->meta['destinations'][0])->toMatchArray(['disk' => 'backups', 'healthy' => true, 'backups_count' => 1]);
});

it('judges the age at the time it runs, not when it was registered', function (): void {
    $check = BackupsCheck::new();
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:00:00'));
    Storage::disk('backups')->put('app/2026-11-19-01-30-00.zip', 'x');

    expect($check->run()->status->value)->toBe('ok');

    $this->travelTo(CarbonImmutable::parse('2026-11-21 08:00:00'));

    $result = $check->run();

    expect($result->status->value)->toBe('failed')
        ->and($result->shortSummary)->toBe('backups');
});

it('fails without backups', function (): void {
    expect(BackupsCheck::new()->run()->status->value)->toBe('failed');
});
