<?php

use App\Core\System\Models\Attachment;
use App\Modules\Content\Actions\RestoreArticle;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;

/*
|--------------------------------------------------------------------------
| 恢复已删除的文章：POST /content/articles/{article}/restore（软删除整套，设计文档 §6.11、§10.1）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $this->postJson('/api/admin/content/articles/'.Article::factory()->trashed()->create()->id.'/restore')->assertUnauthorized();
});

it('returns 403 without the restore code (delete is not enough) or outside the data scope', function (): void {
    $article = Article::factory()->trashed()->create();

    $this->actingAs(userWithDataScope('all', 'content.article', 'content.article.delete'))->postJson("/api/admin/content/articles/{$article->id}/restore")->assertForbidden();
    $this->actingAs(userWithPermissions('content.article.restore'))->postJson("/api/admin/content/articles/{$article->id}/restore")->assertForbidden();

    expect($article->refresh()->trashed())->toBeTrue();
});

it('restores the own deleted article with its status and cover', function (): void {
    $actor = userWithPermissions('content.article.restore');
    $article = Article::factory()->published()->trashed()->create(['created_by' => $actor->id]);
    $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->create();

    $this->actingAs($actor)
        ->postJson("/api/admin/content/articles/{$article->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $article->id)
        ->assertJsonPath('data.deleted_at', null)
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.cover.id', $cover->id);

    expect($article->refresh()->trashed())->toBeFalse();
});

it('returns 403 for an article that is not deleted', function (): void {
    $article = Article::factory()->create();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/content/articles/{$article->id}/restore")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only deleted articles can be restored.');
});

it('returns 422 when the article was restored meanwhile', function (): void {
    $article = Article::factory()->trashed()->create();
    $stale = Article::query()->withTrashed()->findOrFail($article->id);
    Article::query()->withTrashed()->whereKey($article->id)->update(['deleted_at' => null]);

    expect(fn () => resolve(RestoreArticle::class)->handle($stale, superAdminActor()))
        ->toThrow(BusinessRuleViolation::class, 'Only deleted articles can be restored.');
});

it('returns 404 for unknown ids', function (): void {
    $this->actingAs(superAdminActor())->postJson('/api/admin/content/articles/999999/restore')->assertNotFound();
});
