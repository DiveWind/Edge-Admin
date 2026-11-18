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

beforeEach(function (): void {
    $this->user = User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
    $this->client = new VbenClient($this);
    $this->client->get('/api/admin/sanctum/csrf-cookie');
});

it('emails a reset link that points to the frontend page', function (): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['message' => 'We have emailed your password reset link.'], 'message' => 'ok']);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification): bool {
        $url = $notification->toMail($this->user)->actionUrl;

        return $url === 'http://localhost:5999/#/auth/reset-password?'.http_build_query(['token' => $notification->token, 'email' => 'alice@example.com']);
    });
});

it('uses the configured frontend address', function (): void {
    Notification::fake();
    config(['core.auth.frontend_url' => 'https://admin.example.com/', 'core.auth.reset_password_path' => '/reset']);

    $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])->assertOk();

    Notification::assertSentTo($this->user, ResetPassword::class, fn (ResetPassword $notification): bool => str_starts_with(
        $notification->toMail($this->user)->actionUrl,
        'https://admin.example.com/reset?token=',
    ));
});

it('rejects an unknown email address', function (): void {
    Notification::fake();

    $this->client->post('/api/admin/forgot-password', ['email' => 'nobody@example.com'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ["We can't find a user with that email address."]);

    Notification::assertNothingSent();
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
