<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Actions\SyncAttachments;
use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 修改文章（PUT /content/articles/{article}，设计文档 §6.11）：任何状态都可以修改，已发布的修改后仍是已发布。
 *
 * - 字段同 CreateArticle；未提交 cover_id 表示封面不变，null 去掉封面，另一个附件即替换（原封面连同文件删除）；
 * - 在事务中锁行后写入（文章在请求期间被删除 → 404）；
 * - 审计日志 `content.article.updated`：只记变化了的字段，没有变化时不写（LogArticleActivity）。
 */
class UpdateArticle
{
    public function __construct(
        private readonly SyncAttachments $syncAttachments,
        private readonly LogArticleActivity $log,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（同 CreateArticle）
     *
     * @throws ValidationException
     */
    public function handle(Article $article, array $attributes, User $actor): Article
    {
        return DB::transaction(function () use ($article, $attributes, $actor): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);
            $before = $this->log->snapshot($locked);

            $locked->fill(array_intersect_key($attributes, array_flip(CreateArticle::ATTRIBUTES)));
            $locked->save();

            CreateArticle::syncCover($this->syncAttachments, $locked, $attributes);

            $this->log->handle($actor, $locked, LogArticleActivity::UPDATED, $before);

            return $locked->refresh();
        });
    }
}
