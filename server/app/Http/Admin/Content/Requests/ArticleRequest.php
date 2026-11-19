<?php

namespace App\Http\Admin\Content\Requests;

use App\Core\System\Models\{Attachment, User};
use App\Core\System\Rules\{InDict, OwnedAttachment};
use App\Modules\Content\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * 新增、修改文章（POST /content/articles、PUT /content/articles/{article}，设计文档 §6.11）。
 *
 * 只做输入格式校验（必填、长度、类型、白名单字段）；业务约束放在 Content 模块的 Action（设计文档 §5.4）：
 * - title（最长 255）、content（HTML，最长 65535 字符，按净化前计；保存时由模型净化）、category 必填；summary 可空（纯文本，最长 500）；
 * - category：字典 content.article_category 的启用项（InDict；停用的分类、停用的类型都不通过）；修改时文章当前的分类例外，
 *   即使已停用也可以原样提交（InDict::allowing()，保持原值不拦），改成其他分类仍只接受启用项；
 * - cover_id：可空的附件 id，必须是操作者自己上传的未绑定图片，或这篇文章当前的封面（OwnedAttachment，MIME 限预览白名单）；
 *   修改时未提交表示不变，null 去掉封面。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class ArticleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $article = $this->route('article');
        $article = $article instanceof Article ? $article : null;
        $actor = $this->user();

        if (! $actor instanceof User) {
            throw new LogicException('ArticleRequest 只用于已登录的后台用户。');
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:'.Article::SUMMARY_MAX_LENGTH],
            'content' => ['required', 'string', 'max:'.Article::CONTENT_MAX_LENGTH],
            'category' => ['required', 'string', new InDict(Article::CATEGORY_DICT)->allowing($article?->category)],
            'cover_id' => ['sometimes', 'nullable', 'integer', new OwnedAttachment(
                $actor,
                record: $article,
                collection: Article::COVER_COLLECTION,
                mimeTypes: Attachment::PREVIEWABLE_MIME_TYPES,
            )],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(fn (string $field): string => __("content::article.attributes.{$field}"), [
            'title' => 'title',
            'summary' => 'summary',
            'content' => 'content',
            'category' => 'category',
            'cover_id' => 'cover_id',
        ]);
    }
}
