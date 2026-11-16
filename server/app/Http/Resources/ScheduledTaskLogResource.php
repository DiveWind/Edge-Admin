<?php

namespace App\Http\Resources;

use App\Models\ScheduledTaskLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScheduledTaskLog
 */
class ScheduledTaskLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'status' => $this->status,
            'output' => $this->output,
            'duration_ms' => $this->duration_ms,
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
