<?php

use App\Core\System\Actions\RevokeUserSessions;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| 让用户的会话与 token 失效（设计文档 §7.3、§7.4）
|--------------------------------------------------------------------------
*/

/**
 * 在 sessions 表中为用户（null 为未登录）写入一个会话。
 */
function storeSession(string $id, ?User $user): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user?->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);
}

/**
 * sessions 表中剩下的会话 ID。
 *
 * @return list<string>
 */
function storedSessionIds(): array
{
    return DB::table('sessions')->orderBy('id')->pluck('id')->all();
}

beforeEach(function (): void {
    config(['session.driver' => 'database']);

    $this->user = User::factory()->create();
    $this->other = User::factory()->create();

    storeSession('user-1', $this->user);
    storeSession('user-2', $this->user);
    storeSession('other', $this->other);
    storeSession('guest', null);

    $this->user->createToken('device');
    $this->other->createToken('device');
});

it('deletes the sessions and tokens of the user only', function (): void {
    resolve(RevokeUserSessions::class)->handle($this->user);

    expect(storedSessionIds())->toBe(['guest', 'other'])
        ->and($this->user->tokens()->count())->toBe(0)
        ->and($this->other->tokens()->count())->toBe(1);
});

it('keeps the given session', function (): void {
    resolve(RevokeUserSessions::class)->handle($this->user, exceptSessionId: 'user-2');

    expect(storedSessionIds())->toBe(['guest', 'other', 'user-2']);
});

it('leaves the sessions table alone with other session drivers', function (): void {
    config(['session.driver' => 'redis']);

    resolve(RevokeUserSessions::class)->handle($this->user);

    expect(storedSessionIds())->toHaveCount(4)
        ->and($this->user->tokens()->count())->toBe(0);
});
