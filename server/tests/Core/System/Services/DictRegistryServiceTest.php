<?php

use App\Core\System\Contracts\DictRegistry;
use App\Core\System\Data\{DictItemData, DictTypeData};
use App\Core\System\Services\DictRegistryService;

/*
|--------------------------------------------------------------------------
| DictRegistry：模块声明的字典类型与初始字典项（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

it('is a singleton', function (): void {
    expect(resolve(DictRegistry::class))->toBe(resolve(DictRegistry::class));
});

it('keeps the declared types and items, defaulting sort to the position', function (): void {
    $registry = new DictRegistryService;

    $registry->register([[
        'code' => 'content.article_source',
        'name' => 'dict.source',
        'items' => [
            ['value' => 'original', 'label' => 'dict.original', 'color' => 'success'],
            ['value' => 'reprint', 'label' => 'dict.reprint', 'sort' => 9],
        ],
    ]]);

    expect($registry->has('content.article_source'))->toBeTrue()
        ->and($registry->all())->toEqual(['content.article_source' => new DictTypeData(
            code: 'content.article_source',
            name: 'dict.source',
            items: [
                'original' => new DictItemData(value: 'original', label: 'dict.original', color: 'success', sort: 0),
                'reprint' => new DictItemData(value: 'reprint', label: 'dict.reprint', sort: 9),
            ],
        )]);
});

it('rejects invalid declarations', function (mixed $types, string $message): void {
    $registry = new DictRegistryService;
    $registry->register([['code' => 'taken', 'name' => 'Taken']]);

    expect(fn () => $registry->register($types))->toThrow(InvalidArgumentException::class, $message)
        ->and($registry->all())->toHaveCount(1);
})->with([
    'not a list' => [['code' => 'x'], '声明必须是字典类型的列表'],
    'invalid code' => [[['code' => 'Bad-Code', 'name' => 'x']], 'code 必须是小写点分'],
    'duplicate code' => [[['code' => 'taken', 'name' => 'x']], '类型编码重复声明'],
    'duplicate code in one call' => [[['code' => 'a', 'name' => 'x'], ['code' => 'a', 'name' => 'x']], '类型编码重复声明'],
    'missing name' => [[['code' => 'a']], 'name 必填'],
    'unknown key' => [[['code' => 'a', 'name' => 'x', 'status' => 'enabled']], '未知的键 status'],
    'duplicate value' => [[['code' => 'a', 'name' => 'x', 'items' => [['value' => 'v', 'label' => 'l'], ['value' => 'v', 'label' => 'l']]]], 'value v 重复'],
    'integer value' => [[['code' => 'a', 'name' => 'x', 'items' => [['value' => 1, 'label' => 'l']]]], 'value 必须是非空字符串'],
    'missing label' => [[['code' => 'a', 'name' => 'x', 'items' => [['value' => 'v']]]], 'label 必填'],
    'invalid color' => [[['code' => 'a', 'name' => 'x', 'items' => [['value' => 'v', 'label' => 'l', 'color' => 'Red!']]]], 'color 必须是'],
]);
