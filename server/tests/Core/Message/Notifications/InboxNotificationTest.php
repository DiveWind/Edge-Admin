<?php

use App\Core\Message\Data\NotificationContentData;
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\Message\Notifications\InboxNotification;
use App\Core\System\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\Core\Message\Fixtures\DemoNotification;

/*
|--------------------------------------------------------------------------
| 通知类的约定：InboxNotification 与 NotificationContentData（设计文档 §5.5、§6.9）
|--------------------------------------------------------------------------
*/

it('stores the fixed structure with the stable type name instead of the class name', function (): void {
    $user = User::factory()->create();
    $announcement = Announcement::factory()->create();

    $user->notify(new DemoNotification(subject: $announcement));

    $notification = Notification::query()->sole();

    expect($notification->type)->toBe('demo.example')
        ->and($notification->notifiable_type)->toBe('user')
        ->and($notification->notifiable_id)->toBe($user->id)
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data)->toBe([
            'title' => 'demo.notification.title',
            'content' => 'demo.notification.content',
            'level' => 'warning',
            'link' => '/demo/1',
            'params' => ['name' => 'Alice'],
            'subject' => ['type' => 'announcement', 'id' => $announcement->id],
        ]);
});

it('broadcasts the public fields under the same type name, without the subject', function (): void {
    $notification = new DemoNotification(subject: Announcement::factory()->create());
    $user = User::factory()->make();

    expect($notification->broadcastType())->toBe('demo.example')
        ->and($notification->databaseType($user))->toBe('demo.example')
        ->and($notification->toArray($user))->toBe([
            'title' => 'demo.notification.title',
            'content' => 'demo.notification.content',
            'level' => 'warning',
            'link' => '/demo/1',
            'params' => ['name' => 'Alice'],
        ]);
});

it('uses only the database channel by default', function (): void {
    $notification = new class extends InboxNotification
    {
        public static function type(): string
        {
            return 'demo.default';
        }

        public function toInbox(object $notifiable): NotificationContentData
        {
            return new NotificationContentData(title: 'demo');
        }
    };

    expect($notification->via(new AnonymousNotifiable))->toBe(['database']);
});

it('does not notify disabled users on any channel', function (): void {
    $user = User::factory()->disabled()->create();

    $user->notify(new DemoNotification(channels: ['database', 'mail']));

    expect(Notification::query()->count())->toBe(0);
});

it('accepts frontend routes and http(s) links', function (?string $link): void {
    expect(new NotificationContentData(title: 'demo', link: $link)->link)->toBe($link);
})->with(['route' => '/announcements/1', 'https' => 'https://example.com/a?b=c', 'http' => 'http://example.com', 'none' => null]);

it('rejects contents that break the structure', function (array $arguments): void {
    new NotificationContentData(...['title' => 'demo', ...$arguments]);
})->with([
    'empty title' => [['title' => ' ']],
    'title too long' => [['title' => str_repeat('a', 256)]],
    'content too long' => [['content' => str_repeat('a', 1001)]],
    'protocol-relative link' => [['link' => '//evil.example.com']],
    'javascript link' => [['link' => 'javascript:alert(1)']],
    'relative link' => [['link' => 'announcements/1']],
    'other scheme' => [['link' => 'ftp://example.com/file']],
    'unsaved subject' => [['subject' => new Announcement]],
])->throws(InvalidArgumentException::class);
