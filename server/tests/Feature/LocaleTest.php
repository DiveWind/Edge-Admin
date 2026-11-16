<?php

use App\Models\OperationLog;
use App\Models\User;

test('the api language follows Accept-Language', function (string $header, string $message, string $contentLanguage) {
    $this->withHeader('Accept-Language', $header)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', $message)
        ->assertHeader('Content-Language', $contentLanguage);
})->with([
    'zh-CN' => ['zh-CN', '未登录或登录已过期。', 'zh-CN'],
    'zh_CN' => ['zh_CN,zh;q=0.9', '未登录或登录已过期。', 'zh-CN'],
    'en-US' => ['en-US,en;q=0.9', 'Unauthenticated.', 'en'],
    'en' => ['en', 'Unauthenticated.', 'en'],
    'unsupported falls back to APP_LOCALE' => ['fr-FR', '未登录或登录已过期。', 'zh-CN'],
]);

test('APP_LOCALE is used without Accept-Language', function () {
    $this->withHeader('Accept-Language', '')
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', '未登录或登录已过期。');
});

test('missing models return a localized 404 without leaking the model class', function () {
    actingAsUser(superAdmin());

    $this->getJson('/api/users/999999')->assertNotFound()->assertExactJson(['message' => '资源不存在。']);
    $this->withHeader('Accept-Language', 'en')->getJson('/api/users/999999')->assertNotFound()->assertExactJson(['message' => 'Not Found']);
});

test('business messages, validation and operation summaries are localized', function () {
    $actor = actingAsUser(superAdmin());

    $this->withHeader('Accept-Language', 'en')
        ->deleteJson("/api/users/{$actor->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'You cannot delete yourself.');

    $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/positions', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'The name field is required.');

    $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/positions', ['name' => 'QA', 'code' => 'qa', 'status' => 'active'])
        ->assertCreated();

    expect($actor->refresh())->toBeInstanceOf(User::class)
        ->and(OperationLog::query()->latest('id')->value('summary'))->toBe('Create position');
});

test('the api rate limit returns a localized 429', function () {
    config(['edge.rate_limit.api_per_minute' => 1]);

    $this->getJson('/api/options/settings')->assertOk();
    $this->getJson('/api/options/settings')
        ->assertTooManyRequests()
        ->assertJsonPath('message', '请求过于频繁，请稍后再试。')
        ->assertHeader('Retry-After');
});
