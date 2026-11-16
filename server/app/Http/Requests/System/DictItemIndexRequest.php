<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * 字典项列表必须指定所属字典类型。
 */
class DictItemIndexRequest extends ListRequest
{
    protected array $sortable = ['id', 'sort', 'created_at'];

    protected string $defaultSort = 'sort';

    protected function filterRules(): array
    {
        return [
            'dict_type_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ];
    }
}
