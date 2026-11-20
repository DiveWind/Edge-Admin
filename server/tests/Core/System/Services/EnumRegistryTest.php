<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\DictItem;
use App\Core\System\Services\EnumRegistry;
use Tests\Core\System\Services\Fixtures\{BadColorEnum, PlainEnum, UnitEnum};

/*
|--------------------------------------------------------------------------
| EnumRegistry：可下发给前端的枚举（显式白名单，设计文档 §6.7）
|--------------------------------------------------------------------------
*/

it('is a singleton that holds the Core enums', function (): void {
    expect(resolve(EnumRegistry::class))->toBe(resolve(EnumRegistry::class))
        ->and(resolve(EnumRegistry::class)->names())->toContain('status', 'permission_type', 'setting_type', 'login_event', 'login_failure_reason');
});

it('returns the options in case order with the labels of the current locale', function (): void {
    $registry = new EnumRegistry;
    $registry->register(['content.status' => Status::class]);
    app()->setLocale('zh_CN');

    expect($registry->has('content.status'))->toBeTrue()
        ->and($registry->options('content.status'))->toBe([
            ['value' => 'enabled', 'label' => '启用', 'color' => 'success'],
            ['value' => 'disabled', 'label' => '停用', 'color' => 'error'],
        ])
        ->and(fn (): array => $registry->options('missing'))->toThrow(InvalidArgumentException::class, '枚举 missing 未登记');
});

it('rejects invalid registrations', function (mixed $enums, string $message): void {
    $registry = new EnumRegistry;
    $registry->register(['status' => Status::class]);

    expect(fn () => $registry->register($enums))->toThrow(InvalidArgumentException::class, $message)
        ->and($registry->names())->toBe(['status']);
})->with([
    'not an array' => [Status::class, '必须是'],
    'not a class name' => [['other' => 42], '必须是实现'],
    'invalid name' => [['Status' => Status::class], '不合法'],
    'duplicate name' => [['status' => Status::class], '重复登记'],
    'not an enum' => [['user' => stdClass::class], '必须是实现'],
    'without HasLabel' => [['plain' => PlainEnum::class], '必须是实现'],
    'not backed' => [['unit' => UnitEnum::class], '必须是实现'],
    'invalid color' => [['bad' => BadColorEnum::class], 'One 的颜色“red; background: url(x)”不合法'],
]);

it('outputs the color only for enums that implement HasColor', function (): void {
    $registry = resolve(EnumRegistry::class);

    expect($registry->options('login_event'))->each(fn ($option) => $option->toHaveKey('color')->color->toMatch(DictItem::COLOR_PATTERN))
        ->and($registry->options('permission_type')[0])->toBe(['value' => 'catalog', 'label' => 'Catalog']);
});
