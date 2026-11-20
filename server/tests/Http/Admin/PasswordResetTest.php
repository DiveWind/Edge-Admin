<?php

use App\Core\System\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\{Notification, Password};
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 找回密码与重置密码（Fortify，设计文档 §6.2、§7.2）
|--------------------------------------------------------------------------
*/

/**
 * 找回密码的中性提示：无论邮箱是否注册都相同。
 */
const PASSWORD_RESET_LINK_MESSAGE = 'If the email address is registered, you will receive a password reset email.';

beforeEach(function (): void {
    $this->user = User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
    $this->client = new VbenClient($this);
    $this->client->get('/api/admin/sanctum/csrf-cookie');
});

it('emails a reset link that points to the frontend page', function (): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['message' => PASSWORD_RESET_LINK_MESSAGE], 'message' => 'ok']);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification): bool {
        $url = $notification->toMail($this->user)->actionUrl;

        return $url === 'http://localhost:5999/#/auth/reset-password?'.http_build_query(['token' => $notification->token, 'email' => 'alice@example.com']);
    });
});

it('uses the configured frontend address', function (): void {
    Notification::fake();
    config(['core.system.auth.frontend_url' => 'https://admin.example.com/', 'core.system.auth.reset_password_path' => '/reset']);

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])->assertOk();

    Notification::assertSentTo($this->user, ResetPassword::class, fn (ResetPassword $notification): bool => str_starts_with(
        $notification->toMail($this->user)->actionUrl,
        'https://admin.example.com/reset?token=',
    ));
});

it('answers an unknown email address exactly like a registered one', function (): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['message' => PASSWORD_RESET_LINK_MESSAGE], 'message' => 'ok']);

    Notification::assertNothingSent();
});

it('answers repeated requests with the same message without sending another email', function (): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])->assertOk();

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['message' => PASSWORD_RESET_LINK_MESSAGE], 'message' => 'ok']);

    Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
});

it('still validates the email address', function (array $data, string $message): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', $data)
        ->assertUnprocessable()
        ->assertExactJson(['code' => 422, 'data' => null, 'message' => $message, 'errors' => ['email' => [$message]]]);

    Notification::assertNothingSent();
})->with([
    'missing' => [[], 'The email field is required.'],
    'invalid' => [['email' => 'alice'], 'The email field must be a valid email address.'],
]);

it('answers in the language of the Accept-Language header', function (): void {
    Notification::fake();

    foreach (['alice@example.com', 'nobody@example.com'] as $email) {
        $this->client->withLanguage('zh-CN')
            ->post('/api/admin/forgot-password', ['email' => $email])
            ->assertOk()
            ->assertJsonPath('data.message', '如果该邮箱已注册，将收到重置密码的邮件。');
    }

    $this->client->withLanguage('zh-CN')
        ->post('/api/admin/forgot-password', ['email' => 'alice'])
        ->assertJsonPath('message', '邮箱 不是有效的邮箱地址。');
});

it('resets the password with the emailed token and logs out every session', function (): void {
    $other = new VbenClient($this);
    $other->login('alice')->assertOk();
    $other->get('/api/admin/user/info')->assertOk();
    $token = Password::broker()->createToken($this->user);

    $this->client->post('/api/admin/reset-password', [
        'token' => $token,
        'email' => 'alice@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['message' => 'Your password has been reset.'], 'message' => 'ok']);

    $other->get('/api/admin/user/info')->assertUnauthorized();

    $this->client->login('alice', 'password')->assertUnprocessable();
    $this->client->login('alice', 'new-password')->assertOk();
});

it('rejects an invalid token', function (): void {
    $this->client->post('/api/admin/reset-password', [
        'token' => 'invalid',
        'email' => 'alice@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['This password reset token is invalid.']);

    $this->client->login('alice')->assertOk();
});

it('does not reveal whether the email address is registered when resetting', function (): void {
    $this->client->post('/api/admin/reset-password', [
        'token' => 'invalid',
        'email' => 'nobody@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertUnprocessable()
        ->assertExactJson([
            'code' => 422,
            'data' => null,
            'message' => 'This password reset token is invalid.',
            'errors' => ['email' => ['This password reset token is invalid.']],
        ]);
});

it('validates the new password', function (): void {
    $this->client->post('/api/admin/reset-password', [
        'token' => Password::broker()->createToken($this->user),
        'email' => 'alice@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'other-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});
