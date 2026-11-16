<?php

namespace App\Http\Requests\Monitor;

use App\Http\Requests\ListRequest;

class OnlineSessionIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'last_used_at', 'created_at'];

    protected function filterRules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:64'],
            'ip' => ['nullable', 'string', 'max:45'],
        ];
    }
}
