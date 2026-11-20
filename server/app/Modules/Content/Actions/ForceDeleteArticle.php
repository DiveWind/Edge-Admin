<?php

namespace App\Modules\Content\Actions;

use App\Core\Monitor\Actions\LogAudit;
use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

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

            if (! $locked->trashed()) {
                throw new BusinessRuleViolation(__('content::article.messages.force_delete_trashed_only'));
            }

            $before = $locked->auditSnapshot();

            $locked->forceDelete();

            $this->audit->handle($actor, $locked, 'force_deleted', $before);
        });
    }
}
