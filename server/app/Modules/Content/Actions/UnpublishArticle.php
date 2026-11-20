<?php

namespace App\Modules\Content\Actions;

use App\Core\Monitor\Actions\LogAudit;
use App\Core\System\Models\User;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * 取消发布（POST /content/articles/{article}/unpublish，设计文档 §6.11）：已发布 → 草稿，published_at 置空。
 *
 * 只有已发布的能取消发布：ArticlePolicy 返回 403；并发时在事务中锁行复核，已不是已发布 → 422（message）。
 * 审计日志 `content.article.unpublished`（status、published_at 的变化）。
 */
class UnpublishArticle
{
    public function __construct(private readonly LogAudit $audit) {}

    /**
     * Execute the action.
     *
     * @throws BusinessRuleViolation
     */
    public function handle(Article $article, User $actor): Article
    {
        return DB::transaction(function () use ($article, $actor): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);

            if (! $locked->isPublished()) {
                throw new BusinessRuleViolation(__('content::article.messages.unpublish_published_only'));
            }

            $before = $locked->auditSnapshot();

            $locked->status = ArticleStatus::Draft;
            $locked->published_at = null;
            $locked->save();

            $this->audit->handle($actor, $locked, 'unpublished', $before);

            return $locked;
        });
    }
}
