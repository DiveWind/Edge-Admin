<?php

use App\Core\System\Enums\ExportStatus;
use App\Core\System\Jobs\RunExport;
use App\Core\System\Models\Export;
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\{Queue, Storage};
use Spatie\SimpleExcel\SimpleExcelReader;

/*
|--------------------------------------------------------------------------
| 导出文章列表：POST /content/articles/export（设计文档 §6.10、§6.11）
|--------------------------------------------------------------------------
|
| 测试的队列驱动为 sync：事务提交后 RunExport 在请求中执行，库中已是执行后的状态。
| 导出的通用部分（格式、并发上限、我的导出）由 Core 的测试覆盖，这里只测文章导出自己的部分。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

/**
 * 文章导出文件中的行（第一行是表头）。
 *
 * @return list<list<mixed>>
 */
function articleExportRows(Export $export): array
{
    $file = $export->file()->sole();

    return SimpleExcelReader::create(Storage::disk($file->disk)->path($file->path), $file->extension)->noHeaderRow()->getRows()->values()->all();
}

it('returns 401 for guests', function (): void {
    $this->postJson('/api/admin/content/articles/export')->assertUnauthorized();
});

it('returns 403 without content.article.export, even with the list permission', function (): void {
    $this->actingAs(userWithPermissions('content.article'))
        ->postJson('/api/admin/content/articles/export')
        ->assertForbidden();

    expect(Export::query()->count())->toBe(0);
});

it('creates a queued export of the article list (201)', function (): void {
    Queue::fake();

    $this->actingAs(userWithPermissions('content.article.export'))
        ->withHeader('Accept-Language', 'zh-CN')
        ->postJson('/api/admin/content/articles/export', ['format' => 'csv', 'filter' => ['status' => 'published'], 'sort' => '-published_at'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'content.article')
        ->assertJsonPath('data.title', '文章')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.params', ['filter' => ['status' => 'published'], 'sort' => '-published_at']);

    Queue::assertPushed(RunExport::class);
});

it('writes the columns with translated headers and labels', function (): void {
    $actor = userWithDataScope('all', 'content.article.export');
    $article = Article::factory()->published()->create(['title' => '=HYPERLINK("x")', 'category' => 'tutorial', 'published_at' => '2026-11-19 10:00:00']);

    $this->actingAs($actor)
        ->withHeader('Accept-Language', 'zh-CN')
        ->postJson('/api/admin/content/articles/export', ['format' => 'csv'])
        ->assertCreated();

    $export = Export::query()->sole();

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and(articleExportRows($export))->toBe([
            ['ID', '标题', '分类', '状态', '发布时间', '创建人', '创建时间', '删除时间'],
            [(string) $article->id, "'=HYPERLINK(\"x\")", 'tutorial', '已发布', '2026-11-19 10:00:00', $article->creator?->username, $article->created_at?->format('Y-m-d H:i:s'), ''],
        ]);
});

it('exports no more articles than the actor can see in the list (self scope)', function (): void {
    $actor = userWithPermissions('content.article', 'content.article.export');
    $own = Article::factory()->create(['created_by' => $actor->id]);
    Article::factory()->count(2)->create();

    $listed = $this->actingAs($actor)->getJson('/api/admin/content/articles')->assertOk()->json('data.items.*.id');

    $this->actingAs($actor)->postJson('/api/admin/content/articles/export', ['format' => 'csv'])->assertCreated();

    $exported = array_map(fn (array $row): int => (int) $row[0], array_slice(articleExportRows(Export::query()->sole()), 1));

    expect($exported)->toBe($listed)->toBe([$own->id]);
});

it('exports the deleted articles only with the restore or force-delete code', function (): void {
    Queue::fake();
    Article::factory()->trashed()->create();

    $this->actingAs(userWithDataScope('all', 'content.article.export'))
        ->postJson('/api/admin/content/articles/export', ['filter' => ['trashed' => 'only']])
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not allowed to view deleted articles.');

    expect(Export::query()->count())->toBe(0);

    $this->actingAs(userWithDataScope('all', 'content.article.export', 'content.article.restore'))
        ->postJson('/api/admin/content/articles/export', ['filter' => ['trashed' => 'only']])
        ->assertCreated();
});

it('rejects filters and sorts outside of the list whitelist, like the list does', function (array $payload, string $error): void {
    Queue::fake();

    $this->actingAs(userWithPermissions('content.article.export'))
        ->postJson('/api/admin/content/articles/export', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(Export::query()->count())->toBe(0);
})->with([
    'unknown filter' => [['filter' => ['content' => 'x']], 'filter.content'],
    'unknown sort' => [['sort' => 'title'], 'sort'],
    'invalid status' => [['filter' => ['status' => 'archived']], 'filter.status'],
    'invalid trashed' => [['filter' => ['trashed' => 'all']], 'filter.trashed'],
    'invalid time range' => [['filter' => ['published_at' => '2026-11-19,2026-11-20']], 'filter.published_at'],
    'invalid format' => [['format' => 'pdf'], 'format'],
    'pagination is not part of an export' => [['page' => 2], 'page'],
]);
