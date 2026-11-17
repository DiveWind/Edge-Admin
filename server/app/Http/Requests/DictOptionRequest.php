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
        return array_values($this->string('codes')->explode(',')->map(fn (string $code): string => trim($code))->filter()->unique()->all());
    }
}
