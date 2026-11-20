<?php

use App\Core\System\Actions\{ChangeUserStatus, DeleteUser, UpdateUser};
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\SuperAdminGuard;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 系统至少保留一个启用的超管（设计文档 §8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->superAdmin = superAdminActor();
});

/**
 * 在事务中检查 $user 能否不再是启用的超管。
 */
function ensureSuperAdminRemains(User $user, ?string $field): void
{
    DB::transaction(fn () => resolve(SuperAdminGuard::class)->ensureRemains($user, $field));
}

it('rejects the last enabled super admin on the given field', function (string $field, string $message): void {
    expect(fn () => ensureSuperAdminRemains($this->superAdmin, $field))->toThrow(function (ValidationException $e) use ($field, $message): void {
        expect($e->errors())->toBe([$field => [$message]]);
    });
})->with([
    'status' => ['status', 'The last enabled super admin cannot be disabled.'],
    'roles' => ['roles', 'The super admin role cannot be removed from the last enabled super admin.'],
]);

it('rejects deleting the last enabled super admin with a message only', function (): void {
    expect(fn () => ensureSuperAdminRemains($this->superAdmin, null))
        ->toThrow(BusinessRuleViolation::class, 'The last enabled super admin cannot be deleted.');
});

it('does not count disabled super admins', function (): void {
    User::factory()->disabled()->create()->assignRole(Role::SUPER_ADMIN);

    expect(fn () => ensureSuperAdminRemains($this->superAdmin, 'status'))->toThrow(ValidationException::class);
});

it('passes when another enabled super admin remains', function (): void {
    superAdminActor();

    ensureSuperAdminRemains($this->superAdmin, 'status');
})->throwsNoExceptions();

it('passes for users who are not enabled super admins', function (User $user): void {
    ensureSuperAdminRemains($user, 'status');
})->with([
    'no role' => fn (): User => User::factory()->create(),
    'disabled super admin' => fn (): User => User::factory()->disabled()->create()->assignRole(Role::SUPER_ADMIN),
])->throwsNoExceptions();

/*
|--------------------------------------------------------------------------
| 锁顺序（§8.6）：可能减少启用超管的事务，第一条语句是锁住 super-admin 角色行
|--------------------------------------------------------------------------
|
| SQLite 不生成 FOR UPDATE，这里检查语句的顺序：拿锁之前不读写任何其他行（MySQL REPEATABLE READ 下不会在拿锁前建立快照，
| 与其他路径的加锁顺序一致，避免死锁）。MySQL 上的并发实测见设计文档 §14。
|
*/

/**
 * 执行 $action 期间的 SQL（按顺序）。
 *
 * @return list<string>
 */
function queriesDuring(Closure $action): array
{
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $action();

    return $queries;
}

it('locks the super admin role before anything else on every path that may remove an enabled super admin', function (Closure $action): void {
    $other = superAdminActor();
    $target = User::factory()->create()->assignRole(Role::SUPER_ADMIN);
    $target->load('roles');

    $queries = queriesDuring(fn () => $action($target, $other));

    expect($queries[0] ?? null)->toStartWith('select * from "roles" where "name" = ?');
})->with([
    'delete' => fn (User $user) => resolve(DeleteUser::class)->handle($user),
    'disable' => fn (User $user, User $actor) => resolve(ChangeUserStatus::class)->handle($user, Status::Disabled, $actor),
    'update with roles' => fn (User $user, User $actor) => resolve(UpdateUser::class)->handle($user, ['name' => 'Changed'], [], $actor),
    'update with a disabled status' => fn (User $user, User $actor) => resolve(UpdateUser::class)->handle($user, ['name' => 'Changed', 'status' => 'disabled'], null, $actor),
]);

it('reads the super admin role first and then counts its enabled holders', function (): void {
    $other = superAdminActor();
    $other->load('roles');

    $queries = queriesDuring(fn () => ensureSuperAdminRemains($other, 'status'));

    expect($queries)->toHaveCount(2)
        ->and($queries[0])->toStartWith('select * from "roles" where "name" = ?')
        ->and($queries[1])->toContain('"model_has_roles"."role_id" = ?');
});
