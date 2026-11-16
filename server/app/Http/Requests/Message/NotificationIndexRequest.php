<?php

namespace App\Http\Requests\Message;

use App\Http\Requests\ListRequest;

class NotificationIndexRequest extends ListRequest
{
    protected array $sortable = ['created_at'];

    protected string $defaultSort = '-created_at';

    protected function filterRules(): array
    {
        return [
            'read' => ['nullable', 'boolean'],
        ];
    }
}
