<?php

namespace App\Http\Requests\Content;

use App\Enums\PublishStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ArticleIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'created_at', 'published_at'];

    protected function filterRules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(PublishStatus::class)],
            'source' => ['nullable', 'string', 'max:64'],
        ];
    }
}
