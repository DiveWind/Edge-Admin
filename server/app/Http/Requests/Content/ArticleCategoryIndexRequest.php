<?php

namespace App\Http\Requests\Content;

use App\Enums\Status;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ArticleCategoryIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'sort', 'created_at'];

    protected string $defaultSort = 'sort';

    protected function filterRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ];
    }
}
