<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
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
            'name' => $this->name,
            'url' => $this->url,
            'path' => $this->path,
            'disk' => $this->disk,
            'mime' => $this->mime,
            'size' => $this->size,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', fn (?User $uploader): ?array => $uploader?->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
