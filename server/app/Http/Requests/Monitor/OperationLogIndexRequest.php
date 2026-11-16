<?php

namespace App\Http\Requests\Monitor;

use App\Http\Requests\ListRequest;

class OperationLogIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'created_at', 'duration_ms'];

    protected function filterRules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:64'],
            'method' => ['nullable', 'string', 'in:POST,PUT,PATCH,DELETE,post,put,patch,delete'],
            'path' => ['nullable', 'string', 'max:255'],
            'status_code' => ['nullable', 'integer'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ];
    }
}
