<?php

use App\Core\System\Contracts\SettingRegistry;
use App\Core\System\Enums\SettingType;
use App\Core\System\Services\SettingRegistryService;

/*
|--------------------------------------------------------------------------
| SettingRegistry：模块声明的系统参数分组（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

it('is a singleton that holds the System groups', function (): void {
    expect(resolve(SettingRegistry::class))->toBe(resolve(SettingRegistry::class))
        ->and(array_keys(resolve(SettingRegistry::class)->all()))->toBe(['site.name', 'site.login_notice', 'auth.login_rate_limit'])
        ->and(resolve(SettingRegistry::class)->get('auth.login_rate_limit'))
        ->type->toBe(SettingType::Integer)
        ->config->toBe('core.auth.login_rate_limit')
        ->public->toBeFalse();
});

it('orders the groups by order, then by registration', function (): void {
    $registry = new SettingRegistryService;
    $setting = ['type' => 'string', 'name' => 'x', 'default' => ''];

    $registry->register(['late' => ['title' => 'late', 'order' => 20, 'settings' => ['a' => $setting]]]);
    $registry->register([
        'first' => ['title' => 'first', 'order' => 10, 'settings' => ['a' => $setting]],
        'second' => ['title' => 'second', 'order' => 20, 'settings' => ['a' => $setting, 'b' => $setting]],
    ]);

    expect(array_column($registry->groups(), 'key'))->toBe(['first', 'late', 'second'])
        ->and(array_keys($registry->all()))->toBe(['first.a', 'late.a', 'second.a', 'second.b'])
        ->and($registry->get('second.b'))->name->toBe('b')->group->toBe('second')
        ->and($registry->has('second.c'))->toBeFalse()
        ->and($registry->has('second'))->toBeFalse()
        ->and($registry->group('missing'))->toBeNull();
});

it('rejects invalid declarations', function (array $setting, string $message, string $group = 'demo'): void {
    $registry = new SettingRegistryService;
    $registry->register(['taken' => ['title' => 'x', 'settings' => ['a' => ['type' => 'string', 'name' => 'x', 'default' => '']]]]);

    expect(fn () => $registry->register([$group => ['title' => 'demo.title', 'settings' => ['value' => $setting]]]))
        ->toThrow(InvalidArgumentException::class, $message)
        ->and($registry->groups())->toHaveCount(1);
})->with([
    'invalid group' => [['type' => 'string', 'name' => 'x', 'default' => ''], '分组必须是小写标识', 'Demo'],
    'duplicate group' => [['type' => 'string', 'name' => 'x', 'default' => ''], '分组重复注册', 'taken'],
    'unknown type' => [['type' => 'number', 'name' => 'x', 'default' => 1], 'type 必须是'],
    'missing name' => [['type' => 'string', 'default' => ''], 'name 必填'],
    'no default' => [['type' => 'string', 'name' => 'x'], 'default（默认值）与 config'],
    'both defaults' => [['type' => 'int', 'name' => 'x', 'default' => 1, 'config' => 'app.x'], 'default（默认值）与 config'],
    'default of another type' => [['type' => 'int', 'name' => 'x', 'default' => '1'], 'default 不是 int 类型'],
    'unknown key' => [['type' => 'string', 'name' => 'x', 'default' => '', 'group' => 'other'], '未知的键 group'],
    'rules not a list' => [['type' => 'string', 'name' => 'x', 'default' => '', 'rules' => 'max:5'], 'rules 必须是'],
    'public not a boolean' => [['type' => 'string', 'name' => 'x', 'default' => '', 'public' => 1], 'public 必须是布尔值'],
    'invalid dict code' => [['type' => 'string', 'name' => 'x', 'default' => '', 'dict' => 'Bad-Code'], 'dict 必须是字典类型的编码'],
    'dict on a bool' => [['type' => 'bool', 'name' => 'x', 'default' => false, 'dict' => 'yes_no'], 'bool 类型不能引用字典'],
    'dict on json' => [['type' => 'json', 'name' => 'x', 'default' => [], 'dict' => 'tags'], 'json 类型不能引用字典'],
]);

it('accepts dictionaries on string and int settings', function (string $type, mixed $default): void {
    $registry = new SettingRegistryService;
    $registry->register(['demo' => ['title' => 'demo', 'settings' => ['value' => ['type' => $type, 'name' => 'x', 'default' => $default, 'dict' => 'demo.level']]]]);

    expect($registry->get('demo.value')?->dict)->toBe('demo.level');
})->with(['string' => ['string', 'basic'], 'int' => ['int', 1]]);
