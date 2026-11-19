<?php

use App\Core\System\Actions\EnsureSuperAdminRemains;
use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
    DB::transaction(fn () => resolve(EnsureSuperAdminRemains::class)->handle($user, $field));
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
        ->toThrow(UnprocessableEntityHttpException::class, 'The last enabled super admin cannot be deleted.');
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
