<?php

namespace App\Policies;

use App\Models\{ArticleCategory, User};
use Illuminate\Auth\Access\Response;

class ArticleCategoryPolicy
{
    public function delete(User $actor, ArticleCategory $category): Response
    {
        return $category->articles()->withTrashed()->exists()
            ? Response::deny(__('policy.article_category.has_articles'))
            : Response::allow();
    }
}
