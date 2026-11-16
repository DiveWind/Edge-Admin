<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'username', 'created_at', 'last_login_at'];

    protected function filterRules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', Rule::enum(Status::class)],
            'department_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
        ];
    }
}
