<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Core\System\Models\{Export, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 导出任务：默认是排队中的用户列表导出（xlsx，没有筛选）。完成的文件用 AttachmentFactory 的 boundTo($export, Export::FILE_COLLECTION)。
 *
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exporter' => 'system.user',
            'format' => ExportFormat::Xlsx,
            'status' => ExportStatus::Queued,
            'params' => [],
            'locale' => 'en',
        ];
    }

    /**
     * 进行中。
     */
    public function running(): static
    {
        return $this->state(fn (): array => ['status' => ExportStatus::Running, 'started_at' => now()]);
    }

    /**
     * 已完成（不含文件）。
     */
    public function completed(int $rows = 0): static
    {
        return $this->state(fn (): array => ['status' => ExportStatus::Completed, 'row_count' => $rows, 'started_at' => now(), 'finished_at' => now()]);
    }

    /**
     * 失败。
     */
    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => ExportStatus::Failed, 'started_at' => now(), 'finished_at' => now()]);
    }
}
