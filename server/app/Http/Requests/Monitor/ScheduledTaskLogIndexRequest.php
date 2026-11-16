<?php

namespace App\Http\Requests\Monitor;

use App\Enums\TaskStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ScheduledTaskLogIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'started_at', 'duration_ms'];

    protected function filterRules(): array
    {
        return [
            'task' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ];
    }
}
