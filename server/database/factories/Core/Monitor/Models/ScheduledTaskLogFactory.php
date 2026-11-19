<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Models\{ScheduledTask, ScheduledTaskLog};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 定时任务的一条执行记录（与包写入的格式相同）：默认是一次成功结束的记录。
 *
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
        return [
            'monitored_scheduled_task_id' => ScheduledTask::factory(),
            'type' => ScheduledTaskLog::TYPE_FINISHED,
            'meta' => ['runtime' => 0.25, 'exit_code' => 0, 'memory' => 2097152, 'output' => null],
        ];
    }

    /**
     * 开始的记录。
     */
    public function starting(): static
    {
        return $this->state(fn (): array => ['type' => ScheduledTaskLog::TYPE_STARTING, 'meta' => ['memory' => 2097152]]);
    }

    /**
     * 失败的记录（任务抛出异常）。
     */
    public function failed(string $message = 'Something went wrong.'): static
    {
        return $this->state(fn (): array => ['type' => ScheduledTaskLog::TYPE_FAILED, 'meta' => ['failure_message' => $message]]);
    }
}
