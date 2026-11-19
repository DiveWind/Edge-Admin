<?php

use App\Modules\Content\Actions\UnpublishArticle;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| 取消发布：POST /content/articles/{article}/unpublish（设计文档 §6.11）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $this->postJson('/api/admin/content/articles/'.Article::factory()->published()->create()->id.'/unpublish')->assertUnauthorized();
});

it('returns 403 without the publish code or outside the data scope', function (): void {
    $article = Article::factory()->published()->create();

    $this->actingAs(userWithPermissions('content.article', 'content.article.update'))->postJson("/api/admin/content/articles/{$article->id}/unpublish")->assertForbidden();
    $this->actingAs(userWithPermissions('content.article.publish'))->postJson("/api/admin/content/articles/{$article->id}/unpublish")->assertForbidden();

    expect($article->refresh()->isPublished())->toBeTrue();
});

it('moves the own published article back to draft and clears published_at', function (): void {
    $actor = userWithPermissions('content.article.publish');
    $article = Article::factory()->published()->create(['created_by' => $actor->id]);

    $this->actingAs($actor)
        ->postJson("/api/admin/content/articles/{$article->id}/unpublish")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null);

    expect($article->refresh())
        ->status->toBe(ArticleStatus::Draft)
        ->published_at->toBeNull();
});

it('returns 403 for a draft', function (): void {
    $article = Article::factory()->create();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/content/articles/{$article->id}/unpublish")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only published articles can be unpublished.');
});

it('returns 422 when the article was unpublished meanwhile', function (): void {
    $article = Article::factory()->published()->create();
    $stale = Article::query()->findOrFail($article->id);
    Article::query()->whereKey($article->id)->update(['status' => ArticleStatus::Draft]);

    expect(fn () => resolve(UnpublishArticle::class)->handle($stale, superAdminActor()))
        ->toThrow(UnprocessableEntityHttpException::class, 'Only published articles can be unpublished.');
});
