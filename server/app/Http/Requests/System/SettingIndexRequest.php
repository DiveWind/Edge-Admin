<?php

namespace App\Http\Requests\System;

use App\Http\Requests\ListRequest;

class SettingIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'key', 'created_at'];

    protected string $defaultSort = 'id';

    protected function filterRules(): array
    {
        return [
            'group' => ['nullable', 'string', 'max:64'],
            'key' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
