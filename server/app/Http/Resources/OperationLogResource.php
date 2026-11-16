<?php

namespace App\Http\Resources;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationLog
 */
class OperationLogResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn (User $user): array => $user->only('id', 'name')),
            'method' => $this->method,
            'path' => $this->path,
            'route_name' => $this->route_name,
            'summary' => $this->summary,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'status_code' => $this->status_code,
            'duration_ms' => $this->duration_ms,
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
