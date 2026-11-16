<?php

namespace App\Http\Resources;

use App\Services\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ScheduledTask $resource
 */
class ScheduledTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'command' => $this->resource->command,
            'description' => $this->resource->description,
            'expression' => $this->resource->expression,
            'next_run_at' => $this->resource->nextRunAt->toIso8601String(),
            'last_log' => $this->resource->lastLog ? new ScheduledTaskLogResource($this->resource->lastLog) : null,
        ];
    }
}
