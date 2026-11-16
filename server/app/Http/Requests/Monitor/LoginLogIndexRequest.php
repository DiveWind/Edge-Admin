<?php

namespace App\Http\Requests\Monitor;

use App\Enums\LoginStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class LoginLogIndexRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:64'],
            'ip' => ['nullable', 'string', 'max:45'],
            'status' => ['nullable', Rule::enum(LoginStatus::class)],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ];
    }
}
