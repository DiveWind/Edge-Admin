<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Actions\SyncAttachments;
use App\Core\System\Models\User;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增文章（POST /content/articles，设计文档 §6.11）：新增的文章是草稿，created_by 为操作者。
 *
 * - content 在写入属性时净化（模型的 SanitizedHtml）；category 已由 InDict 校验（启用的字典项）；
 * - 封面（cover_id，已由 OwnedAttachment 校验归属与 MIME）：绑定到 `cover` 用途（SyncAttachments，加锁复核，422 挂在 cover_id）；
 * - 审计日志 `content.article.created`（LogArticleActivity，同一事务）。
 */
class CreateArticle
{
    /**
     * 可写入的字段（其余键忽略）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['title', 'summary', 'content', 'category'];

    public function __construct(
        private readonly SyncAttachments $syncAttachments,
        private readonly LogArticleActivity $log,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段：ATTRIBUTES 与 cover_id（附件 id 或 null，可省略）
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, User $actor): Article
    {
        return DB::transaction(function () use ($attributes, $actor): Article {
            $article = new Article(array_intersect_key($attributes, array_flip(self::ATTRIBUTES)));
            $article->status = ArticleStatus::Draft;
            $article->created_by = $actor->id;
            $article->save();

            self::syncCover($this->syncAttachments, $article, $attributes);

            $this->log->handle($actor, $article, LogArticleActivity::CREATED);

            return $article->refresh();
        });
    }

    /**
     * 提交了 cover_id 时绑定封面（未提交表示不变；null 去掉封面；被替换、去掉的原封面连同文件删除）。
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public static function syncCover(SyncAttachments $syncAttachments, Article $article, array $attributes): void
    {
        if (! array_key_exists('cover_id', $attributes)) {
            return;
        }

        $cover = $attributes['cover_id'];

        $syncAttachments->handle($article, Article::COVER_COLLECTION, is_int($cover) || is_string($cover) ? [$cover] : [], 'cover_id');
    }
}
