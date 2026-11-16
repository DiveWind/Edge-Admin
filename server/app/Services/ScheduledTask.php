<?php

namespace App\Services;

use App\Models\ScheduledTaskLog;
use Carbon\CarbonInterface;

/**
 * Laravel Schedule 中的一个 Artisan 命令任务。
 */
final readonly class ScheduledTask
{
    public function __construct(
        public string $key,
        public string $command,
        public ?string $description,
        public string $expression,
        public CarbonInterface $nextRunAt,
        public ?ScheduledTaskLog $lastLog = null,
    ) {}

    public function withLastLog(?ScheduledTaskLog $log): self
    {
        return new self($this->key, $this->command, $this->description, $this->expression, $this->nextRunAt, $log);
    }
}
