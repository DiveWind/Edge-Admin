<?php

use App\Core\System\Actions\{ChangeUserStatus, CreateUser, DeleteUser, UpdateUser};
use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
        ->toThrow(UnprocessableEntityHttpException::class, 'The last enabled super admin cannot be deleted.');

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
