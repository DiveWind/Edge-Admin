<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\DB;

/**
 * 删除文章（DELETE /content/articles/{article}，设计文档 §6.11、§10.1）：软删除，任何状态都可以删除。
 *
 * - 封面保留（HasAttachments 只在彻底删除时删除附件），恢复后仍在；
 * - 在事务中锁行（未删除的文章）：已被别人删除 → 404；
 * - 审计日志 `content.article.deleted`（只记事件）。
 */
class DeleteArticle
{
    public function __construct(private readonly LogArticleActivity $log) {}

    /**
     * Execute the action.
     */
    public function handle(Article $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor): void {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);
            $locked->delete();

            $this->log->handle($actor, $locked, LogArticleActivity::DELETED);
        });
    }
}
