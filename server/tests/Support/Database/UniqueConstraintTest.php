<?php

use App\Core\System\Models\{DictType, User};
use App\Support\Database\UniqueConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 唯一索引冲突的兜底（设计文档 §5.4）
|--------------------------------------------------------------------------
*/

it('saves the model when nothing conflicts', function (): void {
    $type = new DictType()->forceFill(['code' => 'demo', 'name' => 'Demo']);

    UniqueConstraint::save($type, ['code' => 'Code']);

    expect($type->exists)->toBeTrue();
});

it('turns a unique violation into a 422 on the only field, without querying', function (): void {
    DictType::factory()->create(['code' => 'taken']);

    expect(fn () => UniqueConstraint::save(new DictType()->forceFill(['code' => 'taken', 'name' => 'Demo']), ['code' => 'Code']))
        ->toThrow(function (ValidationException $e): void {
            expect($e->errors())->toBe(['code' => ['The Code has already been taken.']]);
        });
});

it('finds the conflicting field among several, ignoring the record itself', function (): void {
    User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);

    $bob->email = 'alice@example.com';

    expect(fn () => UniqueConstraint::save($bob, ['username' => 'Username', 'email' => 'Email']))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['email']);
        });
});

it('rolls back only to its savepoint, leaving the caller transaction usable (PostgreSQL aborts the transaction otherwise)', function (): void {
    DictType::factory()->create(['code' => 'taken']);

    DB::transaction(function (): void {
        $level = DB::transactionLevel();

        try {
            UniqueConstraint::save(new DictType()->forceFill(['code' => 'taken', 'name' => 'Duplicate']), ['code' => 'Code']);
        } catch (ValidationException) {
            //
        }

        expect(DB::transactionLevel())->toBe($level);

        DictType::factory()->create(['code' => 'after']);
    });

    expect(DictType::query()->where('code', 'after')->exists())->toBeTrue()
        ->and(DictType::query()->where('name', 'Duplicate')->exists())->toBeFalse();
});
