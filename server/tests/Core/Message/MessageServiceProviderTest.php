<?php

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget, NotificationLevel};
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Contracts\MenuRegistry;
use App\Core\System\Services\EnumRegistry;
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Database\Eloquent\Relations\Relation;

/*
|--------------------------------------------------------------------------
| Message 模块的注册（设计文档 §6.9）
|--------------------------------------------------------------------------
*/

it('registers the morph aliases of its models', function (): void {
    expect(Relation::getMorphedModel('announcement'))->toBe(Announcement::class)
        ->and(Relation::getMorphedModel('notification'))->toBe(Notification::class);
});

it('declares the announcement menu with its buttons', function (): void {
    $menus = resolve(MenuRegistry::class);

    foreach (['message', 'message.announcement', 'message.announcement.create', 'message.announcement.update', 'message.announcement.delete', 'message.announcement.publish', 'message.announcement.revoke'] as $name) {
        expect($menus->has($name))->toBeTrue($name);
    }
});

it('registers its enums for the options endpoint', function (): void {
    $enums = resolve(EnumRegistry::class);

    expect($enums->options('notification_level'))->toBe(array_map(fn (NotificationLevel $level): array => ['value' => $level->value, 'label' => $level->label(), 'color' => $level->color()], NotificationLevel::cases()))
        ->and(array_column($enums->options('announcement_status'), 'value'))->toBe(array_column(AnnouncementStatus::cases(), 'value'))
        ->and(array_column($enums->options('announcement_target'), 'value'))->toBe(array_column(AnnouncementTarget::cases(), 'value'));
});

it('prunes the read notifications every day', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'Notification'));

    expect($events)->toHaveCount(1)
        ->and($events->sole()->expression)->toBe('0 0 * * *');
});
