<?php

namespace App\Http\Requests\Message;

use App\Enums\{AnnouncementType, PublishStatus};
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class AnnouncementIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'created_at', 'published_at'];

    protected function filterRules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(AnnouncementType::class)],
            'status' => ['nullable', Rule::enum(PublishStatus::class)],
        ];
    }
}
