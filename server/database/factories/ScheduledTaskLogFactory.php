<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\ScheduledTaskLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledTaskLog>
 */
class ScheduledTaskLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days');

        return [
            'task' => 'model:prune',
            'status' => TaskStatus::Success,
            'output' => null,
            'duration_ms' => 120,
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
        ];
    }
}
