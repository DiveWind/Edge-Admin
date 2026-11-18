<?php

use Illuminate\Support\Facades\{App, Route};

/*
|--------------------------------------------------------------------------
| 按 Accept-Language 选择提示语言（设计文档 §6.1）
|--------------------------------------------------------------------------
|
| phpunit.xml 固定 APP_LOCALE=en；测试请求默认带 `Accept-Language: en-us,en;q=0.5`，这里都显式给出请求头。
|
*/

beforeEach(function (): void {
    Route::get('_test/locale', fn (): array => ['locale' => App::getLocale(), 'message' => __('Not Found')]);
});

it('uses the supported language of the Accept-Language header', function (string $header, string $locale, string $message): void {
    $this->getJson('/_test/locale', ['Accept-Language' => $header])
        ->assertOk()
        ->assertExactJson(['locale' => $locale, 'message' => $message])
        ->assertHeader('Content-Language', str_replace('_', '-', $locale))
        ->assertHeader('Vary', 'Accept-Language');
})->with([
    'zh-CN' => ['zh-CN', 'zh_CN', '资源不存在'],
    'zh_cn' => ['zh_cn', 'zh_CN', '资源不存在'],
    'language only' => ['zh', 'zh_CN', '资源不存在'],
    'script and region' => ['zh-Hans-CN', 'zh_CN', '资源不存在'],
    'en' => ['en', 'en', 'Not Found'],
    'en-US' => ['en-US', 'en', 'Not Found'],
    'browser list' => ['zh-CN,zh;q=0.9,en;q=0.8', 'zh_CN', '资源不存在'],
    'highest quality first' => ['en;q=0.5, zh-CN;q=0.9', 'zh_CN', '资源不存在'],
    'skips unsupported languages' => ['fr-FR, en-GB;q=0.8', 'en', 'Not Found'],
]);

it('falls back to the application locale', function (string $appLocale, string $header): void {
    App::setLocale($appLocale);

    $this->getJson('/_test/locale', ['Accept-Language' => $header])
        ->assertOk()
        ->assertJsonPath('locale', $appLocale)
        ->assertHeader('Content-Language', str_replace('_', '-', $appLocale));
})->with([
    'unsupported (zh_CN)' => ['zh_CN', 'fr-FR'],
    'unsupported (en)' => ['en', 'fr-FR, de;q=0.5'],
    'wildcard' => ['zh_CN', '*'],
    'empty' => ['zh_CN', ''],
]);

it('restores the application locale after the request', function (): void {
    $this->getJson('/_test/locale', ['Accept-Language' => 'zh-CN'])->assertJsonPath('locale', 'zh_CN');

    expect(App::getLocale())->toBe('en');

    $this->getJson('/_test/locale', ['Accept-Language' => ''])->assertJsonPath('locale', 'en');
});

it('applies to every entry and to unmatched routes', function (): void {
    $this->getJson('/api/admin/missing', ['Accept-Language' => 'zh-CN'])
        ->assertNotFound()
        ->assertHeader('Content-Language', 'zh-CN')
        ->assertJsonPath('message', '资源不存在');

    $this->getJson('/missing', ['Accept-Language' => 'zh-CN'])
        ->assertNotFound()
        ->assertHeader('Content-Language', 'zh-CN');
});
