<?php

namespace App\Policies;

use App\Models\{Article, User};

/**
 * 文章单条读写受数据权限约束，与列表的 visibleTo() 规则一致。
 */
class ArticlePolicy
{
    public function view(User $actor, Article $article): bool
    {
        return $article->isVisibleTo($actor);
    }

    public function update(User $actor, Article $article): bool
    {
        return $article->isVisibleTo($actor);
    }

    public function delete(User $actor, Article $article): bool
    {
        return $article->isVisibleTo($actor);
    }
}
