<?php

namespace App\Modules\Content\Actions;

use App\Core\Monitor\Actions\LogAudit;
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
 * - 审计日志 `content.article.created`（Monitor 的 LogAudit，同一事务）。
 */
class CreateArticle
{
    public function __construct(
        private readonly SyncAttachments $syncAttachments,
        private readonly LogAudit $audit,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段：Article 的 `#[Fillable]` 与 cover_id（附件 id 或 null，可省略），其余键忽略
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, User $actor): Article
    {
        return DB::transaction(function () use ($attributes, $actor): Article {
            $article = new Article;
            $article->fill(array_intersect_key($attributes, array_flip($article->getFillable())));
            $article->status = ArticleStatus::Draft;
            $article->created_by = $actor->id;
            $article->save();

            if (array_key_exists('cover_id', $attributes)) {
                $this->syncAttachments->handle($article, Article::COVER_COLLECTION, $attributes['cover_id'], 'cover_id');
            }

            $this->audit->handle($actor, $article, 'created');

            return $article->refresh();
        });
    }
}
