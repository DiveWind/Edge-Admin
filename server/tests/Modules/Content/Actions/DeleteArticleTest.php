<?php

use App\Core\System\Models\User;
use App\Modules\Content\Actions\DeleteArticle;
use App\Modules\Content\Models\Article;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| DeleteArticle：并发时状态已变 → 422，记录已不存在 → 404（设计文档 §10.1）
|--------------------------------------------------------------------------
*/

it('returns 422 when the article was deleted by someone else meanwhile', function (): void {
    $article = Article::factory()->create();
    $stale = Article::query()->findOrFail($article->id);
    resolve(DeleteArticle::class)->handle($article, User::factory()->create());

    expect(fn () => resolve(DeleteArticle::class)->handle($stale, User::factory()->create()))
        ->toThrow(UnprocessableEntityHttpException::class, 'The article has already been deleted.');
});

it('returns 404 when the article was permanently deleted meanwhile', function (): void {
    $article = Article::factory()->trashed()->create();
    $stale = Article::query()->withTrashed()->findOrFail($article->id);
    $stale->forceDelete();

    expect(fn () => resolve(DeleteArticle::class)->handle($article, User::factory()->create()))
        ->toThrow(ModelNotFoundException::class);
});
