<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Models\ScheduledTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * schedule-monitor:sync 同步的一个定时任务（与包写入的格式相同）：默认是从未运行过的每天执行的命令任务。
 *
 * @extends Factory<ScheduledTask>
 */
class ScheduledTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2).':run',
            'type' => 'command',
            'cron_expression' => '0 0 * * *',
            'timezone' => '',
            'grace_time_in_minutes' => 5,
        ];
    }

    /**
     * 正在运行：已开始，且晚于上次结束。
     */
    public function running(): static
    {
        return $this->state(fn (): array => [
            'last_started_at' => now()->subMinute(),
            'last_finished_at' => now()->subDay(),
        ]);
    }

    /**
     * 上次运行已结束。
     */
    public function finished(): static
    {
        return $this->state(fn (): array => [
            'last_started_at' => now()->subMinutes(2),
            'last_finished_at' => now()->subMinute(),
        ]);
    }
}
