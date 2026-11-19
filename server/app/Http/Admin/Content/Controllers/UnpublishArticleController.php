<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Models\User;
use App\Http\Admin\Content\Resources\ArticleResource;
use App\Modules\Content\Actions\UnpublishArticle;
use App\Modules\Content\Models\Article;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 取消发布（POST /content/articles/{article}/unpublish，设计文档 §6.11）：已发布 → 草稿，返回文章。
 * 只有已发布的能取消发布（ArticlePolicy::unpublish，403；并发时 422）。
 */
class UnpublishArticleController
{
    #[Authorize('unpublish', 'article')]
    public function __invoke(Article $article, UnpublishArticle $action, #[CurrentUser] User $actor): ArticleResource
    {
        $article = $action->handle($article, $actor);

        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }
}
