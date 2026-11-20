<?php

use App\Core\Monitor\Models\Activity;
use App\Core\System\Models\{Attachment, User};
use App\Modules\Content\Actions\{CreateArticle, DeleteArticle, ForceDeleteArticle, PublishArticle, RestoreArticle, UnpublishArticle, UpdateArticle};
use App\Modules\Content\Models\Article;
use Illuminate\Support\Facades\{DB, Storage};

/*
|--------------------------------------------------------------------------
| 文章的审计日志（日志名 content，设计文档 §5.5、§6.11）
|--------------------------------------------------------------------------
|
| 每个写操作的 Action 在同一事务中调用 Monitor 的 LogAudit（文章实现 Auditable 提供快照）：event / description 为稳定的标识，
| 字段变化用 old / attributes，大字段 content 只记 { sha256, length }。
|
*/

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * 最近一条 content 日志。
 */
function lastArticleActivity(): Activity
{
    return Activity::query()->where('log_name', 'content')->latest('id')->firstOrFail();
}

/**
 * 正文的审计摘要。
 *
 * @return array{sha256: string, length: int}
 */
function contentDigest(string $html): array
{
    return ['sha256' => hash('sha256', $html), 'length' => mb_strlen($html)];
}

it('logs the creation with all audited fields, the causer and a digest of the content', function (): void {
    $actor = User::factory()->create(['username' => 'alice']);
    $cover = Attachment::factory()->create(['created_by' => $actor->id]);

    $article = resolve(CreateArticle::class)->handle(['title' => 'Hello', 'summary' => null, 'content' => '<p>Body<script>x</script></p>', 'category' => 'news', 'cover_id' => $cover->id], $actor);

    $activity = lastArticleActivity();

    expect($activity)
        ->event->toBe('created')
        ->description->toBe('content.article.created')
        ->subject_type->toBe('content.article')
        ->subject_id->toBe($article->id)
        ->causer_type->toBe('user')
        ->causer_id->toBe($actor->id)
        ->and($activity->properties->all())->toBeIgnoringKeyOrder([
            'username' => 'alice',
            'attributes' => [
                'title' => 'Hello',
                'summary' => null,
                'category' => 'news',
                'status' => 'draft',
                'published_at' => null,
                'cover_id' => $cover->id,
                'content' => contentDigest('<p>Body</p>'),
            ],
        ]);
});

it('logs only the changed fields of an update, with the content as a digest instead of the full text', function (): void {
    $article = Article::factory()->create(['title' => 'Old', 'summary' => 'Same', 'content' => '<p>Old</p>']);
    $content = '<p>'.str_repeat('Long text. ', 3000).'</p>';

    resolve(UpdateArticle::class)->handle($article, ['title' => 'New', 'summary' => 'Same', 'content' => $content, 'category' => 'news'], User::factory()->create());

    $activity = lastArticleActivity();

    expect($activity->event)->toBe('updated')
        ->and($activity->getProperty('old'))->toBeIgnoringKeyOrder(['title' => 'Old', 'content' => contentDigest('<p>Old</p>')])
        ->and($activity->getProperty('attributes'))->toBeIgnoringKeyOrder(['title' => 'New', 'content' => contentDigest($content)])
        ->and(strlen((string) json_encode($activity->properties)))->toBeLessThan(1000);
});

it('logs a cover change and skips updates without changes', function (): void {
    $actor = User::factory()->create();
    $article = Article::factory()->create(['created_by' => $actor->id]);
    $cover = Attachment::factory()->create(['created_by' => $actor->id]);
    $attributes = $article->only(['title', 'summary', 'content', 'category']);

    resolve(UpdateArticle::class)->handle($article, [...$attributes, 'cover_id' => $cover->id], $actor);

    expect(lastArticleActivity()->getProperty('old'))->toBe(['cover_id' => null])
        ->and(lastArticleActivity()->getProperty('attributes'))->toBe(['cover_id' => $cover->id]);

    $count = Activity::query()->where('log_name', 'content')->count();
    resolve(UpdateArticle::class)->handle($article, $attributes, $actor);

    expect(Activity::query()->where('log_name', 'content')->count())->toBe($count);
});

it('logs publishing and unpublishing with the status changes', function (): void {
    $actor = User::factory()->create();
    $article = Article::factory()->create();

    $published = resolve(PublishArticle::class)->handle($article, $actor);

    expect(lastArticleActivity())
        ->event->toBe('published')
        ->description->toBe('content.article.published')
        ->and(lastArticleActivity()->getProperty('old'))->toBe(['status' => 'draft', 'published_at' => null])
        ->and(lastArticleActivity()->getProperty('attributes'))->toBe(['status' => 'published', 'published_at' => $published->published_at?->toIso8601String()]);

    resolve(UnpublishArticle::class)->handle($article, $actor);

    expect(lastArticleActivity()->event)->toBe('unpublished')
        ->and(lastArticleActivity()->getProperty('attributes'))->toBe(['status' => 'draft', 'published_at' => null]);
});

it('logs deleting and restoring as events, and the last state when permanently deleting', function (): void {
    $actor = User::factory()->create();
    $article = Article::factory()->create(['title' => 'Gone', 'content' => '<p>Bye</p>']);

    resolve(DeleteArticle::class)->handle($article, $actor);
    expect(lastArticleActivity())->event->toBe('deleted')->description->toBe('content.article.deleted')
        ->and(lastArticleActivity()->properties->keys()->all())->toBe(['username']);

    resolve(RestoreArticle::class)->handle($article, $actor);
    expect(lastArticleActivity())->event->toBe('restored')->description->toBe('content.article.restored');

    resolve(DeleteArticle::class)->handle($article, $actor);
    resolve(ForceDeleteArticle::class)->handle($article, $actor);

    expect(lastArticleActivity())
        ->event->toBe('force_deleted')
        ->description->toBe('content.article.force_deleted')
        ->subject_id->toBe($article->id)
        ->and(lastArticleActivity()->getProperty('old'))->toMatchArray(['title' => 'Gone', 'content' => contentDigest('<p>Bye</p>')]);
});

it('writes the log in the same transaction as the change', function (): void {
    $article = Article::factory()->create();

    expect(fn () => DB::transaction(function () use ($article): void {
        resolve(PublishArticle::class)->handle($article, User::factory()->create());

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);

    expect($article->refresh()->isDraft())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'content')->where('event', 'published')->exists())->toBeFalse();
});
