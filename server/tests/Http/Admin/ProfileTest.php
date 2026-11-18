<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 修改个人资料与密码（Fortify，设计文档 §7.2、§7.4）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->user = User::factory()->create(['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com']);
    $this->client = new VbenClient($this);
    $this->client->login('alice');
});

describe('profile information', function (): void {
    it('updates the name, email and avatar', function (): void {
        $this->client->put('/api/admin/user/profile-information', [
            'name' => 'Alice Liddell',
            'email' => 'liddell@example.com',
            'avatar' => 'https://example.com/alice.png',
        ])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect($this->user->refresh())
            ->name->toBe('Alice Liddell')
            ->email->toBe('liddell@example.com')
            ->avatar->toBe('https://example.com/alice.png')
            ->email_verified_at->toBeNull();
    });

    it('keeps the fields that are not submitted', function (): void {
        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice Liddell'])->assertOk();

        expect($this->user->refresh())
            ->email->toBe('alice@example.com')
            ->email_verified_at->not->toBeNull();
    });

    it('ignores the username and status', function (): void {
        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', 'username' => 'root', 'status' => 'disabled'])->assertOk();

        expect($this->user->refresh())
            ->username->toBe('alice')
            ->status->toBe(Status::Enabled);
    });

    it('validates the input', function (array $input, string $field): void {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'invalid email' => [['email' => 'not-an-email'], 'email'],
        'email taken by another user' => [['email' => 'taken@example.com'], 'email'],
        'avatar with a script URL' => [['avatar' => 'javascript:alert(1)'], 'avatar'],
    ]);
});

describe('password', function (): void {
    it('updates the password and logs out the other sessions and tokens', function (): void {
        $other = new VbenClient($this);
        $other->login('alice')->assertOk();
        $this->user->createToken('device');

        $this->client->put('/api/admin/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue()
            ->and($this->user->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(1);

        $this->client->get('/api/admin/user/info')->assertOk();
        $other->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('rejects a wrong current password', function (): void {
        $this->client->put('/api/admin/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password', ['The provided password does not match your current password.']);

        expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
    });

    it('requires the confirmation of the new password', function (): void {
        $this->client->put('/api/admin/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'other-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });
});

it('requires a logged-in session', function (): void {
    $this->client->post('/api/admin/logout');

    $this->client->put('/api/admin/user/profile-information', ['name' => 'Mallory'])->assertUnauthorized();
    $this->client->put('/api/admin/user/password', ['current_password' => 'password', 'password' => 'x', 'password_confirmation' => 'x'])->assertUnauthorized();

    expect($this->user->refresh()->name)->toBe('Alice');
});
