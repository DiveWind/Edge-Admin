<?php

namespace App\Http\Requests\Content;

use App\Enums\PublishStatus;
use App\Rules\InDict;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    /**
     * source 取自数据字典 article_source，不传时为默认值 original。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('article_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'source' => ['sometimes', 'required', 'string', new InDict('article_source')],
        ];
    }
}
