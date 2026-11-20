<?php

namespace App\Modules\Content\Actions;

use App\Core\Monitor\Actions\LogAudit;
use App\Core\System\Models\User;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * 发布文章（POST /content/articles/{article}/publish，设计文档 §6.11）：草稿 → 已发布，published_at 为当前时间。
 *
 * 只有草稿能发布：ArticlePolicy 返回 403；两人同时发布时在事务中锁行复核，已不是草稿 → 422（message）。
 * 审计日志 `content.article.published`（status、published_at 的变化）。
 */
class PublishArticle
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

            if (! $locked->isDraft()) {
                throw new BusinessRuleViolation(__('content::article.messages.publish_draft_only'));
            }

            $before = $locked->auditSnapshot();

            $locked->status = ArticleStatus::Published;
            $locked->published_at = now();
            $locked->save();

            $this->audit->handle($actor, $locked, 'published', $before);

            return $locked;
        });
    }
}
