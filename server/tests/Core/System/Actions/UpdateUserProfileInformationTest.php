<?php

use App\Core\System\Actions\UpdateUserProfileInformation;
use App\Core\System\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| 修改个人资料：邮箱变更与邮箱验证（设计文档 §7.2）
|--------------------------------------------------------------------------
|
| HTTP 层的行为（校验、忽略的字段）见 tests/Http/Admin/ProfileTest.php。
|
*/

it('sends the verification email to the new address when email verification is enabled', function (): void {
    config(['fortify.features' => [...config()->array('fortify.features'), Features::emailVerification()]]);
    Notification::fake();
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->handle($user, ['email' => 'new@example.com']);

    expect($user->refresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('sends no verification email when email verification is disabled', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->handle($user, ['email' => 'new@example.com']);

    expect($user->refresh()->email_verified_at)->toBeNull();
    Notification::assertNothingSent();
});

it('keeps the verification when the email does not change', function (): void {
    config(['fortify.features' => [...config()->array('fortify.features'), Features::emailVerification()]]);
    Notification::fake();
    $user = User::factory()->create(['email' => 'alice@example.com']);

    resolve(UpdateUserProfileInformation::class)->handle($user, ['name' => 'Alice', 'email' => 'alice@example.com']);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('returns 422 on email when a concurrent write already took the address', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    expect(fn () => resolve(UpdateUserProfileInformation::class)->handle($user, ['email' => 'taken@example.com']))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['email']);
        });
});
