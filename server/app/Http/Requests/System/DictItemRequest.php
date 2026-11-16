<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\DictItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DictItemRequest extends FormRequest
{
    /**
     * 同一字典类型下 value 唯一。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DictItem|null $dictItem */
        $dictItem = $this->route('dict_item');

        return [
            'dict_type_id' => ['required', 'integer', Rule::exists('dict_types', 'id')],
            'label' => ['required', 'string', 'max:255'],
            'value' => [
                'required', 'string', 'max:64',
                Rule::unique('dict_items')->where('dict_type_id', $this->integer('dict_type_id'))->ignore($dictItem),
            ],
            'color' => ['nullable', 'string', 'max:32'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(Status::class)],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
