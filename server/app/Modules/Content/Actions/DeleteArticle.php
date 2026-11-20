<?php

namespace App\Modules\Content\Actions;

use App\Core\Monitor\Actions\LogAudit;
use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * 删除文章（DELETE /content/articles/{article}，设计文档 §6.11、§10.1）：软删除，任何状态都可以删除。
 *
 * - 封面保留（HasAttachments 只在彻底删除时删除附件），恢复后仍在；
 * - 在事务中锁行（含已删除的，withTrashed）复核：并发时已被别人删除 → 422（message，并发时状态已变，§10.1）；已被彻底删除 → 404；
 * - 审计日志 `content.article.deleted`（只记事件）。
 */
class DeleteArticle
{
    public function __construct(private readonly LogAudit $audit) {}

    /**
     * Execute the action.
     *
     * @throws BusinessRuleViolation
     */
    public function handle(Article $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor): void {
            $locked = Article::query()->withTrashed()->lockForUpdate()->findOrFail($article->id);

            if ($locked->trashed()) {
                throw new BusinessRuleViolation(__('content::article.messages.already_deleted'));
            }

            $locked->delete();

            $this->audit->handle($actor, $locked, 'deleted');
        });
    }
}
