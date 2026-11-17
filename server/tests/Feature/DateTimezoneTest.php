<?php

use App\Models\{Article, LoginLog};

/*
| 日期时间输入统一换算到应用时区（契约第 2 节）：带偏移的 ISO 8601 按偏移换算，不带偏移的按应用时区解释，
| 输出始终为带偏移的 ISO 8601。
*/

beforeEach(function () {
    config(['app.timezone' => 'Asia/Shanghai']);
    date_default_timezone_set('Asia/Shanghai');
});

test('datetime input with an offset is converted to the app timezone', function (string|DateTimeInterface $input) {
    actingAsUser(superAdmin());
    $article = Article::factory()->create(['published_at' => $input]);

    expect($article->getRawOriginal('published_at'))->toBe('2026-11-16 18:30:00');

    $this->getJson("/api/articles/{$article->id}")
        ->assertOk()
        ->assertJsonPath('data.published_at', '2026-11-16T18:30:00+08:00');
})->with([
    'utc offset' => '2026-11-16T10:30:00+00:00',
    'zulu' => '2026-11-16T10:30:00Z',
    'negative offset' => '2026-11-16T05:30:00-05:00',
    'utc date instance' => fn () => now('UTC')->setDateTime(2026, 11, 16, 10, 30),
]);

test('datetime input without an offset is interpreted in the app timezone', function (string $input) {
    actingAsUser(superAdmin());
    $article = Article::factory()->create(['published_at' => $input]);

    $this->getJson("/api/articles/{$article->id}")->assertJsonPath('data.published_at', '2026-11-16T18:30:00+08:00');
})->with(['2026-11-16 18:30:00', '2026-11-16T18:30:00']);

test('date range filters convert offset input to the app timezone date', function () {
    actingAsUser(userWithPermissions(['monitor.login-log.view']));
    $log = LoginLog::factory()->create(['created_at' => '2026-11-17 10:00:00']);
    LoginLog::factory()->create(['created_at' => '2026-11-18 10:00:00']);

    // 2026-11-16T20:00:00-05:00 = 2026-11-17 09:00（Asia/Shanghai）
    $this->getJson('/api/login-logs?'.http_build_query(['created_from' => '2026-11-17', 'created_to' => '2026-11-16T20:00:00-05:00']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $log->id);
});
