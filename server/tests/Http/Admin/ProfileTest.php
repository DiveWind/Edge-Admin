<?php

use App\Core\System\Actions\StoreAttachment;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Attachment, User};
use Illuminate\Support\Facades\{DB, Hash, Storage};
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
    it('updates the name and email', function (): void {
        $this->client->put('/api/admin/user/profile-information', [
            'name' => 'Alice Liddell',
            'email' => 'liddell@example.com',
        ])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect($this->user->refresh())
            ->name->toBe('Alice Liddell')
            ->email->toBe('liddell@example.com')
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
        'avatar that is not an attachment id' => [['avatar_id' => 'https://example.com/alice.png'], 'avatar_id'],
    ]);
});

describe('avatar', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    it('sets an own uploaded image as the avatar and replaces the previous one', function (): void {
        $previous = Attachment::factory()->boundTo($this->user, User::AVATAR_COLLECTION)->withFile()->create();
        $upload = resolve(StoreAttachment::class)->handle(uploadedFile('me.png', pngBytes()), $this->user);

        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', 'avatar_id' => $upload->id])->assertOk();

        expect($this->user->avatar()->sole()->id)->toBe($upload->id)
            ->and(Attachment::query()->find($previous->id))->toBeNull();
        Storage::disk('local')->assertMissing($previous->path);
    });

    it('keeps the avatar when the current one is submitted again, and removes it with null', function (): void {
        $avatar = Attachment::factory()->for($this->user, 'creator')->boundTo($this->user, User::AVATAR_COLLECTION)->withFile()->create();

        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', 'avatar_id' => $avatar->id])->assertOk();
        expect($this->user->avatar()->sole()->id)->toBe($avatar->id);

        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice'])->assertOk();
        expect($this->user->avatar()->sole()->id)->toBe($avatar->id);

        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', 'avatar_id' => null])->assertOk();
        expect($this->user->avatar()->first())->toBeNull();
        Storage::disk('local')->assertMissing($avatar->path);
    });

    it('rejects the attachment of someone else, a bound attachment and a non-image', function (Closure $attachment, string $message): void {
        $this->client->put('/api/admin/user/profile-information', ['name' => 'Alice', 'avatar_id' => $attachment($this->user)->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar_id', [$message]);

        expect($this->user->avatar()->first())->toBeNull();
    })->with([
        'uploaded by someone else' => [fn (User $user): Attachment => Attachment::factory()->create(), 'The selected avatar is invalid.'],
        'avatar of another user' => [fn (User $user): Attachment => Attachment::factory()->for($user, 'creator')->boundTo(User::factory()->create(), User::AVATAR_COLLECTION)->create(), 'The selected avatar is invalid.'],
        'not an image' => [fn (User $user): Attachment => Attachment::factory()->for($user, 'creator')->ofType('application/pdf', 'pdf')->create(), 'The avatar field must be a file of type: image/jpeg, image/png, image/gif, image/webp.'],
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
