<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\DictType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DictTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DictType|null $dictType */
        $dictType = $this->route('dict_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('dict_types')->ignore($dictType)],
            'status' => ['required', Rule::enum(Status::class)],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
