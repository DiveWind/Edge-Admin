<?php

use App\Core\System\Actions\{ChangeUserStatus, CreateUser, DeleteUser, SyncUserRoles, UpdateUser};
use App\Core\System\Enums\Status;
use App\Core\System\Events\{UserDeleted, UserDeleting};
use App\Core\System\Models\{Role, User};
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\{DB, Event};
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 用户管理的 Action（设计文档 §8.5、§8.6）：接口无法直接触发的分支
|--------------------------------------------------------------------------
|
| 通过接口时，非超管操作超管用户先被 UserPolicy 拒绝（403），而超管操作者自己就是一个启用的超管，
| 因此“最后一个启用的超管不能删除 / 停用”只会在并发（两个超管同时停用对方）时出现，这里直接调用 Action 验证。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->superAdmin = superAdminActor();
    $this->actor = User::factory()->create();
});

it('does not disable the last enabled super admin', function (): void {
    expect(fn () => resolve(ChangeUserStatus::class)->handle($this->superAdmin, Status::Disabled, $this->actor))
        ->toThrow(ValidationException::class, 'The last enabled super admin cannot be disabled.');

    expect($this->superAdmin->fresh()?->isEnabled())->toBeTrue();
});

it('does not disable the last enabled super admin through an update either', function (): void {
    expect(fn () => resolve(UpdateUser::class)->handle($this->superAdmin, ['name' => 'Changed', 'status' => 'disabled'], null, $this->actor))
        ->toThrow(ValidationException::class, 'The last enabled super admin cannot be disabled.');

    expect($this->superAdmin->fresh())->name->not->toBe('Changed')->isEnabled()->toBeTrue();
});

it('disables a super admin while another one remains', function (): void {
    superAdminActor();

    resolve(ChangeUserStatus::class)->handle($this->superAdmin, Status::Disabled, $this->actor);

    expect($this->superAdmin->fresh()?->status)->toBe(Status::Disabled);
});

it('does not delete the last enabled super admin', function (): void {
    expect(fn () => resolve(DeleteUser::class)->handle($this->superAdmin))
        ->toThrow(BusinessRuleViolation::class, 'The last enabled super admin cannot be deleted.');

    $this->assertModelExists($this->superAdmin);
});

it('returns 422 on the unique field when a concurrent request saved it first', function (string $field, array $attributes): void {
    User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);

    expect(fn () => resolve(CreateUser::class)->handle([...['username' => 'bob', 'name' => 'Bob', 'email' => 'bob@example.com'], ...$attributes], 'password', [], $this->actor))
        ->toThrow(function (ValidationException $e) use ($field): void {
            expect(array_keys($e->errors()))->toBe([$field]);
        });

    $this->assertDatabaseMissing('users', ['name' => 'Bob']);
})->with([
    'username, in another case' => ['username', ['username' => 'ALICE']],
    'email' => ['email', ['email' => 'alice@example.com']],
]);

it('reports the email when updating a user to a taken email', function (): void {
    User::factory()->create(['email' => 'alice@example.com']);

    expect(fn () => resolve(UpdateUser::class)->handle($this->actor, ['email' => 'alice@example.com'], null, $this->superAdmin))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['email']);
        });
});

it('dispatches UserDeleting inside the transaction before the delete, and UserDeleted only after the commit', function (): void {
    $user = User::factory()->create();
    $seen = [];

    Event::listen(UserDeleting::class, function (UserDeleting $event) use (&$seen): void {
        $seen[] = ['deleting', $event->user->exists && User::query()->whereKey($event->user->id)->exists(), DB::transactionLevel()];
    });
    Event::listen(UserDeleted::class, function (UserDeleted $event) use (&$seen): void {
        $seen[] = ['deleted', $event->user->exists, DB::transactionLevel()];
    });

    $level = DB::transactionLevel();
    resolve(DeleteUser::class)->handle($user);

    expect($seen)->toBe([['deleting', true, $level + 1], ['deleted', false, $level]]);
});

it('does not dispatch UserDeleted when the deletion is rolled back', function (): void {
    $user = User::factory()->create();
    Event::listen(UserDeleting::class, fn (): never => throw new RuntimeException('cleanup failed'));
    $deleted = false;
    Event::listen(UserDeleted::class, function () use (&$deleted): void {
        $deleted = true;
    });

    expect(fn () => resolve(DeleteUser::class)->handle($user))->toThrow(RuntimeException::class, 'cleanup failed');

    expect($deleted)->toBeFalse()
        ->and($user->fresh())->not->toBeNull();
});

it('rejects a role deleted after the request was validated instead of silently dropping it, keeping the current roles', function (): void {
    $kept = Role::factory()->create();
    $user = User::factory()->create()->assignRole($kept);

    expect(fn () => resolve(SyncUserRoles::class)->handle($user, [$kept->name, 'deleted-meanwhile'], $this->superAdmin))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['roles']);
        })
        ->and($user->roles()->pluck('name')->all())->toBe([$kept->name]);
});

it('locks the super admin role first when it changes the roles of an existing user on its own', function (): void {
    $user = User::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = str_replace(['"', '`'], '', $query->sql);
    });

    resolve(SyncUserRoles::class)->handle($user, [], $this->superAdmin);

    expect($queries[0] ?? null)->toStartWith('select * from roles where name = ?');
});
