<?php

use App\Core\System\Actions\AuthenticateUser;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 登录校验（Fortify::authenticateUsing，设计文档 §7.2）
|--------------------------------------------------------------------------
|
| HTTP 层的行为（422 的格式、限流）见 tests/Http/Admin/AuthenticationTest.php。
|
*/

it('returns the user for the right username and password', function (): void {
    $user = User::factory()->create(['username' => 'alice']);

    expect(resolve(AuthenticateUser::class)->handle('alice', 'password')?->is($user))->toBeTrue();
});

it('returns null for wrong credentials', function (string $username, string $password): void {
    User::factory()->create(['username' => 'alice']);

    expect(resolve(AuthenticateUser::class)->handle($username, $password))->toBeNull();
})->with([
    'wrong password' => ['alice', 'wrong-password'],
    'unknown username' => ['bob', 'password'],
]);

it('rejects a disabled account', function (): void {
    User::factory()->disabled()->create(['username' => 'alice']);

    // 错误挂在 username 上，见 tests/Http/Admin/AuthenticationTest.php
    expect(fn (): ?User => resolve(AuthenticateUser::class)->handle('alice', 'password'))
        ->toThrow(ValidationException::class, 'This account has been disabled.');
});

it('rehashes a password hashed with outdated options', function (): void {
    $user = User::factory()->create(['username' => 'alice']);
    // 绕过 hashed 类型转换（它拒绝配置不同的哈希值）
    DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('password', ['rounds' => 5])]);

    resolve(AuthenticateUser::class)->handle('alice', 'password');

    expect(Hash::needsRehash($user->refresh()->password))->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});
