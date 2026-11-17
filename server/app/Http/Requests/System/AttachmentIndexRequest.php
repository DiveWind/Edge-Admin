<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class AttachmentIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'size', 'created_at'];

    protected function filterRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:128'],
            'uploaded_by' => ['nullable', 'integer'],
            'created_from' => ['nullable', Rule::date()],
            'created_to' => ['nullable', Rule::date()->afterOrEqual('created_from')],
        ];
    }
}
