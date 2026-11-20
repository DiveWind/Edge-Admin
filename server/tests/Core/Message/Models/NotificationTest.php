<?php

use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Actions\DeleteUser;
use App\Core\System\Models\User;
use Tests\Core\Message\Fixtures\DemoNotification;

/*
|--------------------------------------------------------------------------
| 通知的模型：按记录查找、用户删除时清理、已读通知的定期清理（设计文档 §6.9）
|--------------------------------------------------------------------------
*/

it('finds the notifications about a record through the stored subject', function (): void {
    $user = User::factory()->create();
    [$first, $second] = Announcement::factory()->count(2)->create();

    $user->notify(new DemoNotification(subject: $first));
    $user->notify(new DemoNotification(subject: $second));
    $user->notify(new DemoNotification);

    expect(Notification::query()->about($first)->count())->toBe(1)
        ->and(Notification::query()->about($first)->sole()->data['subject'])->toBe(['type' => 'announcement', 'id' => $first->id]);
});

it('deletes the notifications of a deleted user', function (): void {
    [$deleted, $kept] = User::factory()->count(2)->create();
    $deleted->notify(new DemoNotification);
    $kept->notify(new DemoNotification);

    resolve(DeleteUser::class)->handle($deleted);

    expect(Notification::query()->pluck('notifiable_id')->all())->toBe([$kept->id]);
});

it('prunes the read notifications older than the configured days every day', function (): void {
    config(['core.message.notification.read_ttl_days' => 30]);
    $oldRead = Notification::factory()->read()->create(['created_at' => now()->subDays(31)]);
    $oldUnread = Notification::factory()->create(['created_at' => now()->subDays(31)]);
    $recentRead = Notification::factory()->read()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();

    expect(Notification::query()->pluck('id')->sort()->values()->all())->toBe(collect([$oldUnread->id, $recentRead->id])->sort()->values()->all())
        ->and(Notification::query()->find($oldRead->id))->toBeNull();
});
