<?php

use App\Core\System\Services\{AllDataScope, DataScopeRegistry, SelfDataScope};

/*
|--------------------------------------------------------------------------
| 数据范围的登记（设计文档 §8.4）
|--------------------------------------------------------------------------
*/

it('registers the all and self scopes of Core by default', function (): void {
    $registry = resolve(DataScopeRegistry::class);

    expect($registry->keys())->toBe(['all', 'self'])
        ->and($registry->get('self'))->toBeInstanceOf(SelfDataScope::class)
        ->and($registry->has('department'))->toBeFalse()
        ->and($registry->get('department'))->toBeNull();
});

it('is a singleton', function (): void {
    expect(resolve(DataScopeRegistry::class))->toBe(resolve(DataScopeRegistry::class));
});

it('rejects a duplicate key', function (): void {
    resolve(DataScopeRegistry::class)->register(new AllDataScope);
})->throws(InvalidArgumentException::class, '数据范围 key “all”重复');

it('rejects an invalid key', function (): void {
    resolve(DataScopeRegistry::class)->register(new class extends SelfDataScope
    {
        public function key(): string
        {
            return 'Own Data';
        }
    });
})->throws(InvalidArgumentException::class, '不合法');
