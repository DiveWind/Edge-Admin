<?php

use App\Core\System\Models\{Role, User};
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

it('lets super admins and the all scope grant every registered scope', function (array $roles): void {
    $user = User::factory()->create()->assignRole(...$roles);

    expect(resolve(DataScopeRegistry::class)->grantableKeysFor($user))->toBe(['all', 'self']);
})->with([
    'super admin with the self scope' => fn (): array => [Role::factory()->superAdmin()->create(['data_scope' => 'self'])],
    'enabled role with the all scope' => fn (): array => [Role::factory()->create(['data_scope' => 'self']), Role::factory()->create(['data_scope' => 'all'])],
]);

it('grants only the registered scopes of the enabled roles otherwise', function (): void {
    $user = User::factory()->create()->assignRole(
        Role::factory()->create(['data_scope' => 'self']),
        Role::factory()->create(['data_scope' => 'department']),
        Role::factory()->disabled()->create(['data_scope' => 'all']),
    );
    $registry = resolve(DataScopeRegistry::class);

    expect($registry->grantableKeysFor($user))->toBe(['self'])
        ->and($registry->canGrant($user, 'self'))->toBeTrue()
        ->and($registry->canGrant($user, 'all'))->toBeFalse();
});

it('grants nothing to users without enabled roles', function (): void {
    expect(resolve(DataScopeRegistry::class)->grantableKeysFor(User::factory()->create()))->toBe([]);
});

it('is unrestricted only for super admins and enabled roles with the all scope', function (array $roles, bool $unrestricted): void {
    $user = User::factory()->create()->assignRole(...$roles);

    expect(resolve(DataScopeRegistry::class)->isUnrestrictedFor($user))->toBe($unrestricted);
})->with([
    'super admin' => fn (): array => [[Role::factory()->superAdmin()->create(['data_scope' => 'self'])], true],
    'enabled all scope' => fn (): array => [[Role::factory()->create(['data_scope' => 'self']), Role::factory()->create(['data_scope' => 'all'])], true],
    'disabled all scope' => fn (): array => [[Role::factory()->disabled()->create(['data_scope' => 'all'])], false],
    'self scope' => fn (): array => [[Role::factory()->create(['data_scope' => 'self'])], false],
    'no roles' => fn (): array => [[], false],
]);
