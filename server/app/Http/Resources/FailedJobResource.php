<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 失败队列任务（queue.failer 返回的原始记录；uuid 驱动下其 id 即 uuid）。
 *
 * @property object{id: string, connection: string, queue: string, payload: string, exception: string, failed_at: string} $resource
 */
class FailedJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{displayName?: string} $payload */
        $payload = json_decode($this->resource->payload, true) ?: [];

        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->id,
            'connection' => $this->resource->connection,
            'queue' => $this->resource->queue,
            'name' => $payload['displayName'] ?? null,
            'exception' => Str::limit(Str::before($this->resource->exception, "\n"), 500),
            'failed_at' => Carbon::parse($this->resource->failed_at)->toIso8601String(),
        ];
    }
}
