<?php

namespace App\Http\Requests\System;

use App\Enums\Status;
use App\Models\Position;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Position|null $position */
        $position = $this->route('position');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('positions')->ignore($position)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(Status::class)],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
