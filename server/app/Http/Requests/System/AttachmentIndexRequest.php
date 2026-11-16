<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ListRequest;

class AttachmentIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'size', 'created_at'];

    protected function filterRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:128'],
            'uploaded_by' => ['nullable', 'integer'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ];
    }
}
