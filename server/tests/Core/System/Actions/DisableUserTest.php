<?php

use App\Core\System\Actions\DisableUser;
use App\Core\System\Enums\UserStatus;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| 停用用户（设计文档 §7.3）
|--------------------------------------------------------------------------
*/

it('disables the user and revokes the sessions and tokens', function (): void {
    config(['session.driver' => 'database']);
    [$user, $other] = User::factory()->count(2)->create();

    foreach ([$user, $other] as $owner) {
        DB::table('sessions')->insert(['id' => "session-{$owner->id}", 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $owner->createToken('device');
    }

    resolve(DisableUser::class)->handle($user);

    expect($user->refresh()->status)->toBe(UserStatus::Disabled)
        ->and(DB::table('sessions')->pluck('user_id')->all())->toBe([$other->id])
        ->and($user->tokens()->count())->toBe(0)
        ->and($other->refresh()->status)->toBe(UserStatus::Enabled)
        ->and($other->tokens()->count())->toBe(1);
});
