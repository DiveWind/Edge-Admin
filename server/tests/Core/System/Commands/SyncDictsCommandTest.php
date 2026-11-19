<?php

use App\Core\System\Contracts\DictRegistry;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{DictItem, DictType};
use App\Core\System\Services\{DictRegistryService, Dictionary};

/*
|--------------------------------------------------------------------------
| core:sync-dicts：同步声明的字典类型与初始字典项（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

/**
 * 用给定的声明替换容器中的 DictRegistry。
 *
 * @param  list<array<string, mixed>>  $types
 */
function declareDicts(array $types): void
{
    $registry = new DictRegistryService;
    $registry->register($types);
    app()->instance(DictRegistry::class, $registry);
}

/**
 * 测试用的声明：类型 demo.source，字典项 original、reprint。
 *
 * @param  list<array<string, mixed>>|null  $items
 * @return list<array<string, mixed>>
 */
function demoDicts(?array $items = null): array
{
    return [[
        'code' => 'demo.source',
        'name' => 'demo.source.title',
        'remark' => 'Where it comes from',
        'items' => $items ?? [
            ['value' => 'original', 'label' => 'demo.source.original', 'color' => 'success'],
            ['value' => 'reprint', 'label' => 'demo.source.reprint'],
        ],
    ]];
}

function dictItem(string $value): DictItem
{
    return DictItem::query()->where('value', $value)->sole();
}

it('creates the declared types and items as built-in and enabled', function (): void {
    declareDicts(demoDicts());

    $this->artisan('core:sync-dicts')
        ->expectsOutputToContain('类型新增 1 个、收编 0 个、转为自定义 0 个；字典项新增 2 个、收编 0 个、转为自定义 0 个')
        ->expectsOutputToContain('demo.source:original')
        ->assertSuccessful();

    expect(DictType::query()->sole())
        ->code->toBe('demo.source')
        ->name->toBe('demo.source.title')
        ->remark->toBe('Where it comes from')
        ->status->toBe(Status::Enabled)
        ->is_builtin->toBeTrue()
        ->and(dictItem('original'))
        ->label->toBe('demo.source.original')
        ->color->toBe('success')
        ->sort->toBe(0)
        ->status->toBe(Status::Enabled)
        ->is_builtin->toBeTrue()
        ->and(dictItem('reprint'))->sort->toBe(1)->color->toBeNull();
});

it('is idempotent', function (): void {
    declareDicts(demoDicts());
    $this->artisan('core:sync-dicts')->assertSuccessful();
    $before = [DictType::query()->get()->toArray(), DictItem::query()->orderBy('id')->get()->toArray()];

    $this->artisan('core:sync-dicts')
        ->expectsOutputToContain('字典已是最新，没有变更')
        ->assertSuccessful();

    expect([DictType::query()->get()->toArray(), DictItem::query()->orderBy('id')->get()->toArray()])->toBe($before);
});

it('keeps the adjustments made by administrators and their custom items', function (): void {
    declareDicts(demoDicts());
    $this->artisan('core:sync-dicts')->assertSuccessful();
    DictType::query()->sole()->forceFill(['name' => 'Source', 'remark' => null, 'status' => Status::Disabled])->save();
    dictItem('original')->forceFill(['label' => 'Original', 'color' => null, 'sort' => 9, 'status' => Status::Disabled])->save();
    DictItem::factory()->for(DictType::query()->sole(), 'type')->create(['value' => 'translated']);

    $this->artisan('core:sync-dicts')->expectsOutputToContain('字典已是最新，没有变更')->assertSuccessful();

    expect(DictType::query()->sole())->name->toBe('Source')->remark->toBeNull()->status->toBe(Status::Disabled)
        ->and(dictItem('original'))->label->toBe('Original')->color->toBeNull()->sort->toBe(9)->status->toBe(Status::Disabled)
        ->and(dictItem('translated'))->is_builtin->toBeFalse();
});

it('adopts custom types and items with the declared code and value', function (): void {
    $type = DictType::factory()->create(['code' => 'demo.source', 'name' => 'Mine']);
    DictItem::factory()->for($type, 'type')->create(['value' => 'original', 'label' => 'Mine']);
    declareDicts(demoDicts());

    $this->artisan('core:sync-dicts')
        ->expectsOutputToContain('类型新增 0 个、收编 1 个、转为自定义 0 个；字典项新增 1 个、收编 1 个、转为自定义 0 个')
        ->assertSuccessful();

    expect($type->fresh())->is_builtin->toBeTrue()->name->toBe('Mine')
        ->and(dictItem('original'))->is_builtin->toBeTrue()->label->toBe('Mine')
        ->and(dictItem('reprint'))->is_builtin->toBeTrue();
});

it('turns removed declarations into custom data without deleting anything', function (): void {
    declareDicts([...demoDicts(), ['code' => 'demo.level', 'name' => 'Level', 'items' => [['value' => 'vip', 'label' => 'VIP']]]]);
    $this->artisan('core:sync-dicts')->assertSuccessful();

    declareDicts(demoDicts([['value' => 'original', 'label' => 'demo.source.original']]));

    $this->artisan('core:sync-dicts')
        ->expectsOutputToContain('类型新增 0 个、收编 0 个、转为自定义 1 个；字典项新增 0 个、收编 0 个、转为自定义 2 个')
        ->expectsOutputToContain('demo.source:reprint')
        ->assertSuccessful();

    expect(DictType::query()->where('code', 'demo.level')->sole()->is_builtin)->toBeFalse()
        ->and(dictItem('vip')->is_builtin)->toBeFalse()
        ->and(dictItem('reprint')->is_builtin)->toBeFalse()
        ->and(dictItem('original')->is_builtin)->toBeTrue()
        ->and(DictItem::query()->count())->toBe(3);
});

it('clears the dictionary cache', function (): void {
    declareDicts(demoDicts());
    expect(resolve(Dictionary::class)->options('demo.source'))->toBe([]);

    $this->artisan('core:sync-dicts')->assertSuccessful();

    expect(resolve(Dictionary::class)->values('demo.source'))->toBe(['original', 'reprint']);
});
