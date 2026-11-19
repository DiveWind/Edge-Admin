<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 彻底删除已删除的文章（DELETE /content/articles/{article}/force-delete，设计文档 §6.11、§10.1）。
 *
 * - 只有已删除的能彻底删除（先删除，两步操作避免误删）：ArticlePolicy 返回 403；并发时（另一人已恢复）锁行复核 → 422（message），
 *   已被彻底删除 → 404；
 * - 封面连同文件删除（HasAttachments：彻底删除时删除附件，文件在事务提交后删除）；
 * - 审计日志 `content.article.force_deleted`：old 为删除前的全部字段（记录本身已不存在）。
 */
class ForceDeleteArticle
{
    public function __construct(private readonly LogArticleActivity $log) {}

    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Article $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor): void {
            $locked = Article::query()->withTrashed()->lockForUpdate()->findOrFail($article->id);

            if (! $locked->trashed()) {
                throw new UnprocessableEntityHttpException(__('content::article.messages.force_delete_trashed_only'));
            }

            $before = $this->log->snapshot($locked);

            $locked->forceDelete();

            $this->log->handle($actor, $locked, LogArticleActivity::FORCE_DELETED, $before);
        });
    }
}
