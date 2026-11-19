<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Models\User;
use App\Http\Admin\Content\Resources\ArticleResource;
use App\Modules\Content\Actions\PublishArticle;
use App\Modules\Content\Models\Article;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 发布文章（POST /content/articles/{article}/publish，设计文档 §6.11）：草稿 → 已发布，返回文章。
 * 只有草稿能发布（ArticlePolicy::publish，403；并发时 422）。
 */
class PublishArticleController
{
    #[Authorize('publish', 'article')]
    public function __invoke(Article $article, PublishArticle $action, #[CurrentUser] User $actor): ArticleResource
    {
        $article = $action->handle($article, $actor);

        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }
}
