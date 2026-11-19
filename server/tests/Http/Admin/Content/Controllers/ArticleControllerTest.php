<?php

use App\Core\System\Actions\SyncDicts;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Attachment, DictItem};
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 文章管理：/content/articles 的列表、详情、新增、修改、删除（业务模块示范，设计文档 §6.11）
|--------------------------------------------------------------------------
|
| 发布、取消发布、恢复、彻底删除、导出见同目录的其他测试；审计日志的内容见 tests/Modules/Content/Actions。
| 分类的字典（content.article_category）由模块声明，beforeEach 中同步到库（core:sync-dicts）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    resolve(SyncDicts::class)->handle();
    Storage::fake('local');
});

/**
 * 新增文章的请求体。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function articlePayload(array $overrides = []): array
{
    return [...['title' => 'Hello', 'summary' => 'Short', 'content' => '<p>Body</p>', 'category' => 'news'], ...$overrides];
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/content/articles')->assertUnauthorized();
    });

    it('returns 403 without the content.article code', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/content/articles')
            ->assertForbidden();
    });

    it('paginates the articles with their fields and the signed cover, without content', function (): void {
        $article = Article::factory()->published()->create(['title' => 'Hello', 'summary' => 'Short', 'category' => 'tutorial']);
        $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->create();
        Article::factory()->create();
        $creator = $article->creator;

        $response = $this->actingAs(userWithDataScope('all', 'content.article'))
            ->getJson('/api/admin/content/articles?sort=id&pageSize=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonMissingPath('data.items.0.content');

        expect($response->json('data.items.0'))->toMatchArray([
            'id' => $article->id,
            'title' => 'Hello',
            'summary' => 'Short',
            'category' => 'tutorial',
            'status' => 'published',
            'published_at' => $article->published_at?->toIso8601String(),
            'created_by' => $article->created_by,
            'creator' => ['id' => $creator?->id, 'username' => $creator?->username, 'name' => $creator?->name],
            'deleted_at' => null,
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ])->and($response->json('data.items.0.cover.id'))->toBe($cover->id)
            ->and($response->json('data.items.0.cover.preview_url'))->toContain("/attachments/{$cover->id}/preview?expires=");
    });

    it('only lists the articles within the data scope (self)', function (): void {
        $actor = userWithPermissions('content.article');
        $own = Article::factory()->create(['created_by' => $actor->id]);
        Article::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/content/articles')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$own->id]);
    });

    it('filters by title, category, status and time ranges', function (string $query, int $count): void {
        Article::factory()->create(['title' => 'Release notes', 'category' => 'news', 'created_at' => '2026-11-01 10:00:00']);
        Article::factory()->published()->create(['title' => 'Getting started', 'category' => 'tutorial', 'published_at' => '2026-11-10 10:00:00']);
        Article::factory()->published()->create(['title' => 'Old release', 'category' => 'news', 'published_at' => '2026-10-10 10:00:00']);

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/content/articles?{$query}")
            ->assertOk()
            ->assertJsonPath('data.total', $count);
    })->with([
        'title contains' => ['filter[title]=release', 2],
        'category' => ['filter[category]=tutorial', 1],
        'status' => ['filter[status]=published', 2],
        'published at' => ['filter[published_at]=2026-11-01T00:00:00%2B08:00,', 1],
        'created at' => ['filter[created_at]=,2026-11-02T00:00:00%2B08:00', 1],
    ]);

    it('sorts by the allowed fields', function (): void {
        $older = Article::factory()->published()->create(['published_at' => '2026-10-01 10:00:00']);
        $newer = Article::factory()->published()->create(['published_at' => '2026-11-01 10:00:00']);

        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/content/articles?sort=-published_at')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$newer->id, $older->id]);
    });

    it('returns 422 for filters and sorts outside the allowlist', function (string $query, string $field): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/content/articles?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'filter' => ['filter[content]=x', 'filter'],
        'sort' => ['sort=title', 'sort'],
        'status value' => ['filter[status]=archived', 'filter.status'],
        'trashed value' => ['filter[trashed]=all', 'filter.trashed'],
        'time range' => ['filter[created_at]=2026-11-01,2026-11-02', 'filter.created_at'],
    ]);

    it('hides the deleted articles unless filter[trashed] asks for them', function (string $trashed, array $expected): void {
        $kept = Article::factory()->create();
        $deleted = Article::factory()->trashed()->create();
        $ids = ['kept' => $kept->id, 'deleted' => $deleted->id];

        $response = $this->actingAs(userWithDataScope('all', 'content.article', 'content.article.restore'))
            ->getJson('/api/admin/content/articles?sort=id'.($trashed === '' ? '' : "&filter[trashed]={$trashed}"))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', array_map(fn (string $key): int => $ids[$key], $expected));

        if (in_array('deleted', $expected, true)) {
            expect(collect($response->json('data.items'))->firstWhere('id', $deleted->id)['deleted_at'])->toBe($deleted->deleted_at?->toIso8601String());
        }
    })->with([
        'default' => ['', ['kept']],
        'with' => ['with', ['kept', 'deleted']],
        'only' => ['only', ['deleted']],
    ]);

    it('requires the restore or force-delete code to see the deleted articles', function (string $code, int $status): void {
        Article::factory()->trashed()->create();

        $this->actingAs(userWithDataScope('all', 'content.article', ...array_filter([$code])))
            ->getJson('/api/admin/content/articles?filter[trashed]=only')
            ->assertStatus($status);
    })->with([
        'list only' => ['', 403],
        'restore' => ['content.article.restore', 200],
        'force delete' => ['content.article.force-delete', 200],
    ]);

    it('applies the data scope to the deleted articles too', function (): void {
        $actor = userWithPermissions('content.article', 'content.article.restore');
        $own = Article::factory()->trashed()->create(['created_by' => $actor->id]);
        Article::factory()->trashed()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/content/articles?filter[trashed]=only')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$own->id]);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/content/articles/'.Article::factory()->create()->id)->assertUnauthorized();
    });

    it('returns 403 without the code or outside the data scope', function (): void {
        $article = Article::factory()->create();

        $this->actingAs(userWithPermissions('system.user'))->getJson("/api/admin/content/articles/{$article->id}")->assertForbidden();
        $this->actingAs(userWithPermissions('content.article'))->getJson("/api/admin/content/articles/{$article->id}")->assertForbidden();
    });

    it('returns the own article with its content and cover', function (): void {
        $actor = userWithPermissions('content.article');
        $article = Article::factory()->create(['created_by' => $actor->id, 'content' => '<p>Body</p>']);
        $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->create();

        $this->actingAs($actor)
            ->getJson("/api/admin/content/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Body</p>')
            ->assertJsonPath('data.cover.id', $cover->id)
            ->assertJsonPath('data.cover.download_url', fn (string $url): bool => str_contains($url, "/attachments/{$cover->id}/download?expires="));
    });

    it('lets the article viewers open its cover through the attachment endpoint (access delegated to the article)', function (): void {
        $actor = userWithPermissions('content.article');
        $cover = Attachment::factory()->boundTo(Article::factory()->create(['created_by' => $actor->id]), Article::COVER_COLLECTION)->create();
        $other = Attachment::factory()->boundTo(Article::factory()->create(), Article::COVER_COLLECTION)->create();

        $this->actingAs($actor)->getJson("/api/admin/attachments/{$cover->id}")->assertOk();
        $this->actingAs($actor)->getJson("/api/admin/attachments/{$other->id}")->assertForbidden();
    });

    it('returns 404 for deleted, unknown and malformed ids', function (Closure $id): void {
        $this->actingAs(superAdminActor())->getJson('/api/admin/content/articles/'.$id())->assertNotFound();
    })->with([
        'deleted' => [fn (): int => Article::factory()->trashed()->create()->id],
        'missing' => [fn (): string => '999999'],
        'not a number' => [fn (): string => 'abc'],
    ]);
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/content/articles', articlePayload())->assertUnauthorized();
    });

    it('returns 403 without the content.article.create code', function (): void {
        $this->actingAs(userWithPermissions('content.article', 'content.article.update'))
            ->postJson('/api/admin/content/articles', articlePayload())
            ->assertForbidden();

        expect(Article::query()->count())->toBe(0);
    });

    it('creates a draft with sanitized content and an own image as cover', function (): void {
        $actor = userWithPermissions('content.article.create');
        $upload = Attachment::factory()->create(['created_by' => $actor->id]);

        $this->actingAs($actor)
            ->postJson('/api/admin/content/articles', articlePayload([
                'content' => '<p onclick="steal()">Body<script>alert(1)</script></p><a href="javascript:alert(1)">x</a>',
                'category' => 'tutorial',
                'cover_id' => $upload->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.category', 'tutorial')
            ->assertJsonPath('data.content', '<p>Body</p><a rel="noopener noreferrer">x</a>')
            ->assertJsonPath('data.cover.id', $upload->id)
            ->assertJsonPath('data.created_by', $actor->id);

        $article = Article::query()->sole();

        expect($article->content)->toBe('<p>Body</p><a rel="noopener noreferrer">x</a>')
            ->and($upload->refresh()->isBoundTo($article, Article::COVER_COLLECTION))->toBeTrue();
    });

    it('validates the fields', function (Closure $payload, string $field): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/content/articles', articlePayload($payload()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(Article::query()->where('title', 'Hello')->exists())->toBeFalse();
    })->with([
        'missing title' => [fn (): array => ['title' => null], 'title'],
        'title too long' => [fn (): array => ['title' => str_repeat('a', 256)], 'title'],
        'summary too long' => [fn (): array => ['summary' => str_repeat('a', Article::SUMMARY_MAX_LENGTH + 1)], 'summary'],
        'missing content' => [fn (): array => ['content' => null], 'content'],
        'content too long' => [fn (): array => ['content' => str_repeat('a', Article::CONTENT_MAX_LENGTH + 1)], 'content'],
        'missing category' => [fn (): array => ['category' => null], 'category'],
        'unknown category' => [fn (): array => ['category' => 'gossip'], 'category'],
        'cover of someone else' => [fn (): array => ['cover_id' => Attachment::factory()->create()->id], 'cover_id'],
        'cover already bound elsewhere' => [fn (): array => ['cover_id' => Attachment::factory()->boundTo(Article::factory()->create(), Article::COVER_COLLECTION)->create()->id], 'cover_id'],
        'cover is not an image' => [fn (): array => ['cover_id' => Attachment::factory()->ofType('application/pdf', 'pdf')->create(['created_by' => auth()->id()])->id], 'cover_id'],
        'status is not writable' => [fn (): array => ['status' => 'published'], 'status'],
        'unknown field' => [fn (): array => ['created_by' => 1], 'created_by'],
    ]);

    it('rejects a disabled category', function (string $disabled): void {
        if ($disabled === 'item') {
            DictItem::query()->where('value', 'news')->sole()->update(['status' => Status::Disabled]);
        } else {
            DictItem::query()->where('value', 'news')->sole()->type()->sole()->update(['status' => Status::Disabled]);
        }

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/content/articles', articlePayload(['category' => 'news']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    })->with(['item', 'type']);
});

describe('update', function (): void {
    it('returns 401 for guests', function (): void {
        $this->putJson('/api/admin/content/articles/'.Article::factory()->create()->id, articlePayload())->assertUnauthorized();
    });

    it('returns 403 without the code or outside the data scope', function (): void {
        $article = Article::factory()->create();

        $this->actingAs(userWithPermissions('content.article'))->putJson("/api/admin/content/articles/{$article->id}", articlePayload())->assertForbidden();
        $this->actingAs(userWithPermissions('content.article.update'))->putJson("/api/admin/content/articles/{$article->id}", articlePayload())->assertForbidden();

        expect($article->refresh()->title)->not->toBe('Hello');
    });

    it('updates the own article, keeping it published', function (): void {
        $actor = userWithPermissions('content.article.update');
        $article = Article::factory()->published()->create(['created_by' => $actor->id]);

        $this->actingAs($actor)
            ->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['title' => 'Renamed', 'summary' => null, 'content' => '<p>New<img src="data:image/png;base64,AAAA"></p>']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.content', '<p>New<img /></p>')
            ->assertJsonPath('data.status', 'published');
    });

    it('keeps, replaces or removes the cover', function (): void {
        $actor = userWithPermissions('content.article.update');
        $article = Article::factory()->create(['created_by' => $actor->id]);
        $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->withFile()->create();

        $this->actingAs($actor)->putJson("/api/admin/content/articles/{$article->id}", articlePayload())->assertOk()->assertJsonPath('data.cover.id', $cover->id);
        $this->actingAs($actor)->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['cover_id' => $cover->id]))->assertOk()->assertJsonPath('data.cover.id', $cover->id);

        $replacement = Attachment::factory()->withFile()->create(['created_by' => $actor->id]);
        $this->actingAs($actor)->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['cover_id' => $replacement->id]))->assertOk()->assertJsonPath('data.cover.id', $replacement->id);

        expect(Attachment::query()->find($cover->id))->toBeNull();
        Storage::disk('local')->assertMissing($cover->path);

        $this->actingAs($actor)->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['cover_id' => null]))->assertOk()->assertJsonPath('data.cover', null);

        expect(Attachment::query()->find($replacement->id))->toBeNull();
    });

    it('returns 422 for a cover of someone else', function (): void {
        $actor = userWithPermissions('content.article.update');
        $article = Article::factory()->create(['created_by' => $actor->id]);

        $this->actingAs($actor)
            ->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['cover_id' => Attachment::factory()->create()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_id']);
    });

    it('accepts the current category unchanged even if it has been disabled (the original value is kept)', function (string $disabled): void {
        $article = Article::factory()->create(['category' => 'news']);
        $item = DictItem::query()->where('value', 'news')->sole();

        if ($disabled === 'item') {
            $item->update(['status' => Status::Disabled]);
        } else {
            $item->type()->sole()->update(['status' => Status::Disabled]);
        }

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['title' => 'Renamed', 'category' => 'news']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.category', 'news');
    })->with(['item', 'type']);

    it('only accepts enabled categories when the category changes', function (): void {
        $article = Article::factory()->create(['category' => 'news']);
        DictItem::query()->whereIn('value', ['news', 'tutorial'])->get()->each->update(['status' => Status::Disabled]);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['category' => 'tutorial']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/content/articles/{$article->id}", articlePayload(['category' => 'case']))
            ->assertOk()
            ->assertJsonPath('data.category', 'case');

        expect($article->refresh()->category)->toBe('case');
    });

    it('returns 404 for a deleted article', function (): void {
        $article = Article::factory()->trashed()->create();

        $this->actingAs(superAdminActor())->putJson("/api/admin/content/articles/{$article->id}", articlePayload())->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $this->deleteJson('/api/admin/content/articles/'.Article::factory()->create()->id)->assertUnauthorized();
    });

    it('returns 403 without the code or outside the data scope', function (): void {
        $article = Article::factory()->create();

        $this->actingAs(userWithPermissions('content.article', 'content.article.update'))->deleteJson("/api/admin/content/articles/{$article->id}")->assertForbidden();
        $this->actingAs(userWithPermissions('content.article.delete'))->deleteJson("/api/admin/content/articles/{$article->id}")->assertForbidden();

        expect($article->refresh()->trashed())->toBeFalse();
    });

    it('soft deletes the own article of any status and keeps its cover', function (): void {
        $actor = userWithPermissions('content.article.delete');
        $article = Article::factory()->published()->create(['created_by' => $actor->id]);
        $cover = Attachment::factory()->boundTo($article, Article::COVER_COLLECTION)->withFile()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/admin/content/articles/{$article->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect($article->refresh()->trashed())->toBeTrue()
            ->and(Attachment::query()->find($cover->id))->not->toBeNull();
        Storage::disk('local')->assertExists($cover->path);
    });

    it('returns 404 for an article that is already deleted', function (): void {
        $article = Article::factory()->trashed()->create();

        $this->actingAs(superAdminActor())->deleteJson("/api/admin/content/articles/{$article->id}")->assertNotFound();
    });
});
