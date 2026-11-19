<?php

use App\Modules\Content\Actions\PublishArticle;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| 发布文章：POST /content/articles/{article}/publish（设计文档 §6.11）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $this->postJson('/api/admin/content/articles/'.Article::factory()->create()->id.'/publish')->assertUnauthorized();
});

it('returns 403 without the code or outside the data scope', function (): void {
    $article = Article::factory()->create();

    $this->actingAs(userWithPermissions('content.article', 'content.article.update'))->postJson("/api/admin/content/articles/{$article->id}/publish")->assertForbidden();
    $this->actingAs(userWithPermissions('content.article.publish'))->postJson("/api/admin/content/articles/{$article->id}/publish")->assertForbidden();

    expect($article->refresh()->isDraft())->toBeTrue();
});

it('publishes the own draft at the current time', function (): void {
    Date::setTestNow('2026-11-19 10:00:00');
    $actor = userWithPermissions('content.article.publish');
    $article = Article::factory()->create(['created_by' => $actor->id]);

    $this->actingAs($actor)
        ->postJson("/api/admin/content/articles/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_at', now()->toIso8601String())
        ->assertJsonPath('data.content', $article->content);

    expect($article->refresh())
        ->status->toBe(ArticleStatus::Published)
        ->published_at->toEqual(now());
});

it('returns 403 for an article that is already published', function (): void {
    $article = Article::factory()->published()->create();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/content/articles/{$article->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only draft articles can be published.');
});

it('returns 422 when the article was published meanwhile (the lock rechecks the status)', function (): void {
    $article = Article::factory()->create();
    $stale = Article::query()->findOrFail($article->id);
    Article::query()->whereKey($article->id)->update(['status' => ArticleStatus::Published]);

    expect(fn () => resolve(PublishArticle::class)->handle($stale, superAdminActor()))
        ->toThrow(UnprocessableEntityHttpException::class, 'Only draft articles can be published.');
});

it('returns 404 for a deleted article', function (): void {
    $article = Article::factory()->trashed()->create();

    $this->actingAs(superAdminActor())->postJson("/api/admin/content/articles/{$article->id}/publish")->assertNotFound();
});
