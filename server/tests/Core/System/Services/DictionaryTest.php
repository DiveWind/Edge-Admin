<?php

use App\Core\System\Actions\{DeleteDictType, UpdateDictItem};
use App\Core\System\Models\{DictItem, DictType};
use App\Core\System\Services\Dictionary;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Dictionary：字典的读取与缓存（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->type = DictType::factory()->create(['code' => 'user_gender']);
    $this->item = DictItem::factory()->for($this->type, 'type')->create(['value' => 'male', 'label' => 'Male']);
});

it('caches the options until the cache is flushed', function (): void {
    $dictionary = resolve(Dictionary::class);
    expect($dictionary->options('user_gender'))->toBe([['value' => 'male', 'label' => 'Male', 'color' => null]]);

    // 绕过模型事件直接改库：缓存中仍是旧值
    DB::table('dict_items')->update(['label' => 'Man']);
    expect($dictionary->options('user_gender')[0]['label'])->toBe('Male');

    $dictionary->flush();
    expect($dictionary->options('user_gender')[0]['label'])->toBe('Man');
});

it('clears the cache when an item is written', function (): void {
    $dictionary = resolve(Dictionary::class);
    $dictionary->options('user_gender');

    resolve(UpdateDictItem::class)->handle($this->item, ['label' => 'Man']);

    expect($dictionary->options('user_gender')[0]['label'])->toBe('Man');
});

it('clears the cache when a type is deleted together with its items', function (): void {
    $dictionary = resolve(Dictionary::class);
    $dictionary->options('user_gender');

    resolve(DeleteDictType::class)->handle($this->type);

    expect($dictionary->options('user_gender'))->toBe([]);
});

it('clears the cache only after the transaction commits', function (): void {
    $dictionary = resolve(Dictionary::class);
    $dictionary->options('user_gender');

    $version = Dictionary::cache()->version();

    DB::transaction(function () use ($version): void {
        $this->item->forceFill(['label' => 'Man'])->save();

        expect(Dictionary::cache()->version())->toBe($version);
    });

    expect(Dictionary::cache()->version())->not->toBe($version)
        ->and($dictionary->options('user_gender')[0]['label'])->toBe('Man');
});
