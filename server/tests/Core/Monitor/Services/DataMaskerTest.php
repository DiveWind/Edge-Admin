<?php

use App\Core\Monitor\Services\DataMasker;

/*
|--------------------------------------------------------------------------
| 脱敏与截断的共用规则（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 数组的键名规则（递归、通配、截断）由 tests/Core/Monitor/Actions/LogOperationTest.php 覆盖；这里覆盖文本中的写法。
|
*/

it('masks the values of sensitive keys in text', function (string $text, string $masked): void {
    expect(resolve(DataMasker::class)->maskText($text))->toBe($masked);
})->with([
    'query string' => ['GET /callback?access_token=abc123&state=ok', 'GET /callback?access_token=******&state=ok'],
    'json' => ['{"user":"alice","Password":"p@ss word","nested":{"client_secret":"x"}}', '{"user":"alice","Password":"******","nested":{"client_secret":"******"}}'],
    'colon' => ['password: hunter2, user: alice', 'password: ******, user: alice'],
    'php array' => ["['recovery_code' => 'abcd-efgh']", "['recovery_code' => '******']"],
    'exact key' => ['code=123456 zipcode=100000 codes=1', 'code=****** zipcode=100000 codes=1'],
    'escaped quote' => ['"token":"a\"b" next', '"token":"******" next'],
]);

it('leaves text without sensitive keys unchanged', function (): void {
    $text = 'SQLSTATE[HY000]: General error: 5 database is locked (Connection: sqlite, SQL: select * from "users")';

    expect(resolve(DataMasker::class)->maskText($text))->toBe($text);
});

it('uses the keys added in the configuration and ignores wildcard-only keys for text', function (): void {
    config(['core.monitor.masked_keys' => ['id_card*', '*']]);

    expect(resolve(DataMasker::class)->maskText('id_card_no=110101 name=alice'))->toBe('id_card_no=****** name=alice');
});

it('truncates by characters', function (): void {
    $masker = resolve(DataMasker::class);

    expect($masker->truncate('一二三四五六', 5))->toBe('一二三四五…(truncated)')
        ->and($masker->truncate('abcde', 5))->toBe('abcde');
});
