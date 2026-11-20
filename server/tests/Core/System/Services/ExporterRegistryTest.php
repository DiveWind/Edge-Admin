<?php

use App\Core\System\Contracts\Exporter;
use App\Core\System\Exporters\UserExporter;
use App\Core\System\Models\User;
use App\Core\System\Services\{ExporterRegistry, MenuRegistry};

/*
|--------------------------------------------------------------------------
| ExporterRegistry：已登记的导出类（显式白名单，设计文档 §6.10）
|--------------------------------------------------------------------------
*/

it('is a singleton that holds the user list export', function (): void {
    expect(resolve(ExporterRegistry::class))->toBe(resolve(ExporterRegistry::class))
        ->and(resolve(ExporterRegistry::class)->names())->toContain('user')
        ->and(resolve(ExporterRegistry::class)->get('user'))->toBeInstanceOf(UserExporter::class);
});

it('rejects invalid registrations', function (mixed $exporters, string $message): void {
    $registry = new ExporterRegistry;
    $registry->register(['user' => UserExporter::class]);

    expect(fn () => $registry->register($exporters))->toThrow(InvalidArgumentException::class, $message)
        ->and($registry->names())->toBe(['user']);
})->with([
    'not an array' => [UserExporter::class, '必须是'],
    'too many segments' => [['content.article.extra' => UserExporter::class], '不合法'],
    'an upper-case name' => [['System.User' => UserExporter::class], '不合法'],
    'duplicate name' => [['user' => UserExporter::class], '重复登记'],
    'not an exporter' => [['other' => User::class], '必须实现'],
    'not a class name' => [['other' => 42], '必须实现'],
]);

it('fails for an unregistered name', function (): void {
    expect(fn (): Exporter => new ExporterRegistry()->get('missing'))->toThrow(InvalidArgumentException::class, '导出 missing 未登记');
});

it('only holds exporters whose permission codes are declared', function (): void {
    $registry = resolve(ExporterRegistry::class);
    $menus = resolve(MenuRegistry::class);

    foreach ($registry->names() as $name) {
        expect($menus->has($registry->get($name)->permission()))->toBeTrue("导出 {$name} 的权限码未在 resources/menus.php 中声明");
    }
});
