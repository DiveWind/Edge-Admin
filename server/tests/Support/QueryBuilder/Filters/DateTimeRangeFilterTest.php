<?php

use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

/*
|--------------------------------------------------------------------------
| 时间区间筛选 filter[字段]=起,止（设计文档 §6.3）
|--------------------------------------------------------------------------
|
| 应用时区为 Asia/Shanghai（+08:00），库中按应用时区存储（UsesAppTimezone）。
|
*/

beforeEach(function (): void {
    config(['app.timezone' => 'Asia/Shanghai']);
    date_default_timezone_set('Asia/Shanghai');

    // 应用时区的 08:00、12:00、20:00
    foreach (['morning' => '08:00', 'noon' => '12:00', 'evening' => '20:00'] as $name => $time) {
        User::factory()->create(['name' => $name, 'created_at' => CarbonImmutable::parse("2026-11-19 {$time}:00", 'Asia/Shanghai')]);
    }
});

afterEach(function (): void {
    date_default_timezone_set('UTC');
});

/**
 * 按 filter[created_at] 筛选后的用户名（按 id 排序）。
 *
 * @return list<string>
 */
function namesCreatedWithin(string $range): array
{
    $request = Request::create('/users', 'GET', ['filter' => ['created_at' => $range]]);

    return QueryBuilder::for(User::class, $request)
        ->allowedFilters(DateTimeRangeFilter::allowed('created_at'))
        ->orderBy('id')
        ->pluck('name')
        ->all();
}

it('filters by the range, including both ends', function (string $range, array $names): void {
    expect(namesCreatedWithin($range))->toBe($names);
})->with([
    'app timezone offset' => ['2026-11-19T08:00:00+08:00,2026-11-19T12:00:00+08:00', ['morning', 'noon']],
    'UTC (Zulu) is converted to the storage timezone' => ['2026-11-19T00:00:00Z,2026-11-19T04:00:00Z', ['morning', 'noon']],
    'other offset with milliseconds' => ['2026-11-18T23:00:00.000-05:00,2026-11-19T07:00:00.000-05:00', ['noon', 'evening']],
    'offset without colon, minutes only' => ['2026-11-19T12:00+0800,2026-11-19T20:00+0800', ['noon', 'evening']],
    'only a start' => ['2026-11-19T12:00:00+08:00,', ['noon', 'evening']],
    'only an end' => [',2026-11-19T11:59:59+08:00', ['morning']],
    'both ends empty' => [',', ['morning', 'noon', 'evening']],
    'unencoded plus sign read as a space' => ['2026-11-19T12:00:00 08:00,', ['noon', 'evening']],
]);

it('rejects malformed ranges with a 422 on filter.<field>', function (string $range, string $message): void {
    expect(fn (): array => namesCreatedWithin($range))->toThrow(function (ValidationException $e) use ($message): void {
        expect($e->errors())->toBe(['filter.created_at' => [$message]]);
    });
})->with([
    'single value' => ['2026-11-19T08:00:00+08:00', 'The time range must be a start and an end separated by a comma.'],
    'three values' => ['2026-11-19T08:00:00+08:00,,', 'The time range must be a start and an end separated by a comma.'],
    'no offset' => ['2026-11-19T08:00:00,', 'The time range must use ISO 8601 date-times with a time zone offset.'],
    'date only' => ['2026-11-19,2026-11-20', 'The time range must use ISO 8601 date-times with a time zone offset.'],
    'not a date' => ['yesterday,today', 'The time range must use ISO 8601 date-times with a time zone offset.'],
    'day does not exist' => ['2026-02-30T08:00:00+08:00,', 'The time range must use ISO 8601 date-times with a time zone offset.'],
    'hour out of range' => [',2026-11-19T24:00:00+08:00', 'The time range must use ISO 8601 date-times with a time zone offset.'],
    'start after end' => ['2026-11-20T00:00:00+08:00,2026-11-19T00:00:00+08:00', 'The start of the time range must not be later than its end.'],
]);

it('reports the error on the public filter name when the column differs', function (): void {
    $request = Request::create('/users', 'GET', ['filter' => ['registered' => 'x,']]);

    expect(fn () => QueryBuilder::for(User::class, $request)->allowedFilters(DateTimeRangeFilter::allowed('registered', 'created_at'))->get())
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['filter.registered']);
        });
});
