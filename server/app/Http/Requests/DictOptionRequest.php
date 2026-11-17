<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/options/dicts?codes=a,b
 */
class DictOptionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codes' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->string('codes')->explode(',')->map(trim(...))->filter()->unique()->values()->all();
    }
}
