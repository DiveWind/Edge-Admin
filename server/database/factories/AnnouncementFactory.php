<?php

namespace Database\Factories;

use App\Enums\AnnouncementType;
use App\Enums\PublishStatus;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
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
            'title' => fake()->realText(20),
            'content' => fake()->realText(200),
            'type' => fake()->randomElement(AnnouncementType::cases()),
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }
}
