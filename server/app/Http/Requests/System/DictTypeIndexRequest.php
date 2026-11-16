<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class DictTypeIndexRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ];
    }
}
