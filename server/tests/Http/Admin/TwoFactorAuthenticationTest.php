<?php

use App\Core\System\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 两步验证与确认密码（Fortify，设计文档 §6.2、§7.2）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->client = new VbenClient($this);
});

/**
 * 用户当前的动态验证码。
 */
function currentOtp(User $user): string
{
    return resolve(Google2FA::class)->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));
}

describe('confirming the password', function (): void {
    beforeEach(function (): void {
        User::factory()->create(['username' => 'alice']);
        $this->client->login('alice');
    });

    it('requires a confirmed password before managing two-factor authentication', function (): void {
        $this->client->post('/api/admin/user/two-factor-authentication')
            ->assertStatus(423)
            ->assertExactJson(['code' => 423, 'data' => null, 'message' => 'Password confirmation required.']);

        $this->client->get('/api/admin/user/confirmed-password-status')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['confirmed' => false], 'message' => 'ok']);
    });

    it('rejects a wrong password', function (): void {
        $this->client->post('/api/admin/user/confirm-password', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password', ['The provided password was incorrect.']);

        $this->client->get('/api/admin/user/confirmed-password-status')->assertJsonPath('data.confirmed', false);
    });

    it('confirms the password for the session', function (): void {
        $this->client->post('/api/admin/user/confirm-password', ['password' => 'password'])
            ->assertCreated()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->client->get('/api/admin/user/confirmed-password-status')->assertJsonPath('data.confirmed', true);
    });
});

it('enables, confirms and disables two-factor authentication', function (): void {
    $user = User::factory()->create(['username' => 'alice']);
    $this->client->login('alice');
    $this->client->post('/api/admin/user/confirm-password', ['password' => 'password']);

    $this->client->post('/api/admin/user/two-factor-authentication')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    $this->client->get('/api/admin/user/two-factor-qr-code')
        ->assertOk()
        ->assertJsonStructure(['code', 'data' => ['svg', 'url'], 'message'])
        ->assertJsonPath('data.url', fn (string $url): bool => str_starts_with($url, 'otpauth://totp/'));

    $this->client->get('/api/admin/user/two-factor-secret-key')
        ->assertOk()
        ->assertJsonPath('data.secretKey', decrypt($user->refresh()->two_factor_secret));

    // 确认之前不生效（登录不要求动态验证码）
    expect($user->two_factor_confirmed_at)->toBeNull();

    $this->client->post('/api/admin/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code', ['The provided two factor authentication code was invalid.']);

    $this->client->post('/api/admin/user/confirmed-two-factor-authentication', ['code' => currentOtp($user)])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->client->get('/api/admin/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJsonCount(8, 'data');

    $this->client->delete('/api/admin/user/two-factor-authentication')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    expect($user->refresh())
        ->two_factor_secret->toBeNull()
        ->two_factor_confirmed_at->toBeNull();
});

it('does not expose the two-factor secrets in the user info', function (): void {
    $this->actingAs(User::factory()->withTwoFactorAuthentication()->create())
        ->getJson('/api/admin/user/info')
        ->assertOk()
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes');
});

describe('logging in with two-factor authentication', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->withTwoFactorAuthentication()->create(['username' => 'alice']);
    });

    it('asks for the two-factor challenge without logging in', function (): void {
        $this->client->login('alice')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['two_factor' => true], 'message' => 'ok']);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('completes the login with the authentication code', function (): void {
        $this->client->login('alice');

        $this->client->post('/api/admin/two-factor-challenge', ['code' => currentOtp($this->user)])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->client->get('/api/admin/user/info')->assertOk()->assertJsonPath('data.username', 'alice');
    });

    it('completes the login with a recovery code, which can be used only once', function (): void {
        $code = $this->user->recoveryCodes()[0];

        $this->client->login('alice');
        $this->client->post('/api/admin/two-factor-challenge', ['recovery_code' => $code])->assertOk();
        $this->client->get('/api/admin/user/info')->assertOk();

        expect($this->user->refresh()->recoveryCodes())->toHaveCount(8)->not->toContain($code);

        $this->client->post('/api/admin/logout');
        $this->client->login('alice');

        $this->client->post('/api/admin/two-factor-challenge', ['recovery_code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.recovery_code', ['The provided two factor recovery code was invalid.']);
    });

    it('rejects a wrong authentication code', function (): void {
        $this->client->login('alice');

        $this->client->post('/api/admin/two-factor-challenge', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertExactJson([
                'code' => 422,
                'data' => null,
                'message' => 'The provided two factor authentication code was invalid.',
                'errors' => ['code' => ['The provided two factor authentication code was invalid.']],
            ]);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('rejects the challenge without a pending login', function (): void {
        $this->client->get('/api/admin/sanctum/csrf-cookie');

        $this->client->post('/api/admin/two-factor-challenge', ['code' => currentOtp($this->user)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    });

    it('still rejects a disabled account before the challenge', function (): void {
        $this->user->forceFill(['status' => 'disabled'])->save();

        $this->client->login('alice')->assertJsonPath('errors.username', ['This account has been disabled.']);
    });
});
