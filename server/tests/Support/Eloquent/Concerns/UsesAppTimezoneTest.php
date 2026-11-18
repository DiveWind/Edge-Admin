<?php

use App\Core\System\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| 模型时间统一使用应用时区（设计文档 §10.1）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['app.timezone' => 'Asia/Shanghai']);
    date_default_timezone_set('Asia/Shanghai');
});

afterEach(function (): void {
    date_default_timezone_set('UTC');
});

it('converts input to the app timezone before storing it', function (mixed $input): void {
    $user = new User;
    $user->email_verified_at = $input;

    expect($user->getAttributes()['email_verified_at'])->toBe('2026-11-16 18:30:00');
})->with([
    'UTC offset' => '2026-11-16T10:30:00+00:00',
    'Zulu' => '2026-11-16T10:30:00Z',
    'other offset' => '2026-11-16T05:30:00-05:00',
    'no offset: app timezone' => '2026-11-16 18:30:00',
    'date object in another timezone' => fn (): CarbonImmutable => CarbonImmutable::parse('2026-11-16 10:30:00', 'UTC'),
    'native DateTime' => fn (): DateTime => new DateTime('2026-11-16 11:30:00', new DateTimeZone('Europe/Paris')),
    'timestamp' => fn (): int => CarbonImmutable::parse('2026-11-16T10:30:00Z')->getTimestamp(),
]);

it('keeps null values', function (): void {
    $user = new User;
    $user->email_verified_at = null;

    expect($user->getAttributes()['email_verified_at'])->toBeNull();
});

it('serializes dates as ISO 8601 with the offset of the app timezone', function (): void {
    $user = User::factory()->make(['email_verified_at' => '2026-11-16T10:30:00Z']);

    expect($user->toArray()['email_verified_at'])->toBe('2026-11-16T18:30:00+08:00');
});

it('stores the converted value in the database and reads back the same instant', function (): void {
    $user = User::factory()->create(['email_verified_at' => '2026-11-16T10:30:00Z']);

    expect(DB::table('users')->where('id', $user->id)->value('email_verified_at'))->toBe('2026-11-16 18:30:00')
        ->and($user->fresh()?->email_verified_at?->equalTo(CarbonImmutable::parse('2026-11-16T10:30:00Z')))->toBeTrue();
});

it('parses query input in the app timezone', function (): void {
    $value = (new User)->asAppDateTime('2026-11-16T10:30:00Z');

    expect($value->getTimezone()->getName())->toBe('Asia/Shanghai')
        ->and($value->format('Y-m-d H:i:s'))->toBe('2026-11-16 18:30:00');
});
