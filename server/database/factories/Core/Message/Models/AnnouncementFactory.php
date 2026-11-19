<?php

namespace Database\Factories\Core\Message\Models;

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget};
use App\Core\Message\Models\Announcement;
use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 公告：默认是发给全部用户的草稿（info 级别）。
 *
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'level' => 'info',
            'status' => AnnouncementStatus::Draft,
            'target' => AnnouncementTarget::All,
            'created_by' => User::factory(),
        ];
    }

    /**
     * 已发布（不发送通知：需要通知时走 PublishAnnouncement）。
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
            'published_by' => User::factory(),
        ]);
    }

    /**
     * 已撤回。
     */
    public function revoked(): static
    {
        return $this->published()->state(fn (): array => [
            'status' => AnnouncementStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }

    /**
     * 目标为指定角色。
     */
    public function forRoles(Role ...$roles): static
    {
        return $this->state(fn (): array => ['target' => AnnouncementTarget::Roles])
            ->afterCreating(fn (Announcement $announcement) => $announcement->roles()->sync(array_map(fn (Role $role): int => $role->id, $roles)));
    }
}
