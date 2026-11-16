<?php

namespace App\Http\Requests\Content;

use App\Enums\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(Status::class)],
        ];
    }
}
