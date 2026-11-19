<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 恢复已删除的文章（POST /content/articles/{article}/restore，设计文档 §6.11、§10.1）：状态与封面不变。
 *
 * 只有已删除的能恢复：ArticlePolicy 返回 403；并发时（另一人已恢复）在事务中锁行复核，已不是已删除 → 422（message），
 * 已被彻底删除 → 404。审计日志 `content.article.restored`（只记事件）。
 */
class RestoreArticle
{
    public function __construct(private readonly LogArticleActivity $log) {}

    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Article $article, User $actor): Article
    {
        return DB::transaction(function () use ($article, $actor): Article {
            $locked = Article::query()->withTrashed()->lockForUpdate()->findOrFail($article->id);

            if (! $locked->trashed()) {
                throw new UnprocessableEntityHttpException(__('content::article.messages.restore_trashed_only'));
            }

            $locked->restore();

            $this->log->handle($actor, $locked, LogArticleActivity::RESTORED);

            return $locked;
        });
    }
}
