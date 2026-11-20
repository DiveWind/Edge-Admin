<?php

use App\Core\System\Models\Attachment;
use App\Modules\Content\Actions\ForceDeleteArticle;
use App\Modules\Content\Models\Article;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 彻底删除已删除的文章：DELETE /content/articles/{article}/force-delete（软删除整套，设计文档 §6.11、§10.1）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

it('returns 401 for guests', function (): void {
    $this->deleteJson('/api/admin/content/articles/'.Article::factory()->trashed()->create()->id.'/force-delete')->assertUnauthorized();
});

it('returns 403 without the force-delete code (delete and restore are not enough) or outside the data scope', function (): void {
    $article = Article::factory()->trashed()->create();

    $this->actingAs(userWithDataScope('all', 'content.article', 'content.article.delete', 'content.article.restore'))
        ->deleteJson("/api/admin/content/articles/{$article->id}/force-delete")
        ->assertForbidden();
    $this->actingAs(userWithPermissions('content.article.force-delete'))
        ->deleteJson("/api/admin/content/articles/{$article->id}/force-delete")
        ->assertForbidden();

    expect(Article::query()->withTrashed()->find($article->id))->not->toBeNull();
});

it('permanently deletes the own deleted article together with the cover file', function (): void {
    $actor = userWithPermissions('content.article.force-delete');
    $article = Article::factory()->trashed()->create(['created_by' => $actor->id]);
    $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->withFile()->create();

    $this->actingAs($actor)
        ->deleteJson("/api/admin/content/articles/{$article->id}/force-delete")
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    expect(Article::query()->withTrashed()->find($article->id))->toBeNull()
        ->and(Attachment::query()->find($cover->id))->toBeNull();
    Storage::disk('local')->assertMissing($cover->path);
});

it('returns 403 for an article that is not deleted (delete it first)', function (): void {
    $article = Article::factory()->create();

    $this->actingAs(superAdminActor())
        ->deleteJson("/api/admin/content/articles/{$article->id}/force-delete")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only deleted articles can be permanently deleted. Delete the article first.');

    expect($article->refresh()->exists)->toBeTrue();
});

it('returns 422 when the article was restored meanwhile', function (): void {
    $article = Article::factory()->trashed()->create();
    $stale = Article::query()->withTrashed()->findOrFail($article->id);
    Article::query()->withTrashed()->whereKey($article->id)->update(['deleted_at' => null]);

    expect(fn () => resolve(ForceDeleteArticle::class)->handle($stale, superAdminActor()))
        ->toThrow(BusinessRuleViolation::class);

    expect(Article::query()->find($article->id))->not->toBeNull();
});

it('returns 404 for unknown ids', function (): void {
    $this->actingAs(superAdminActor())->deleteJson('/api/admin/content/articles/999999/force-delete')->assertNotFound();
});
