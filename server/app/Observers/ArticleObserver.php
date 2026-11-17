<?php

namespace App\Observers;

use App\Enums\PublishStatus;
use App\Models\Article;

class ArticleObserver
{
    /**
     * 首次改为已发布时记录发布时间（已有发布时间则保留）。
     */
    public function saving(Article $article): void
    {
        if ($article->isDirty('status') && $article->status === PublishStatus::Published) {
            $article->published_at ??= now();
        }
    }
}
