<?php

namespace App\Http\Requests\Content;

use App\Enums\PublishStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    /**
     * 编辑时的记录级授权（Policy），先于字段校验执行。
     */
    public function authorize(): Response
    {
        $article = $this->route('article');

        return $article === null ? Response::allow() : Gate::inspect('update', $article);
    }

    /**
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
        ];
    }
}
