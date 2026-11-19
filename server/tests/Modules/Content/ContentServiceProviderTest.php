<?php

use App\Core\System\Contracts\{DictRegistry, MenuRegistry};
use App\Core\System\Services\{EnumRegistry, ExporterRegistry};
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Exporters\ArticleExporter;
use App\Modules\Content\Models\Article;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
|--------------------------------------------------------------------------
| Content 模块的注册：只通过 Core 的扩展点接入（设计文档 §5.5、§6.11）
|--------------------------------------------------------------------------
*/

it('registers the morph alias of the article', function (): void {
    expect(Relation::getMorphedModel('content.article'))->toBe(Article::class)
        ->and(new Article()->getMorphClass())->toBe('content.article');
});

it('declares the content catalog and the article menu with its buttons', function (): void {
    $menus = resolve(MenuRegistry::class);

    foreach (['content', 'content.article', ...array_map(fn (string $button): string => "content.article.{$button}", ['create', 'update', 'delete', 'publish', 'restore', 'force-delete', 'export'])] as $name) {
        expect($menus->has($name))->toBeTrue($name);
    }
});

it('declares the article category dictionary with its initial items', function (): void {
    $type = resolve(DictRegistry::class)->all()[Article::CATEGORY_DICT] ?? null;

    expect($type)->not->toBeNull()
        ->and(array_keys($type?->items ?? []))->toBe(['news', 'tutorial', 'case']);
});

it('registers the status enum for the options endpoint', function (): void {
    expect(resolve(EnumRegistry::class)->options('content.article_status'))->toBe([
        ['value' => 'draft', 'label' => ArticleStatus::Draft->label(), 'color' => 'default'],
        ['value' => 'published', 'label' => ArticleStatus::Published->label(), 'color' => 'success'],
    ]);
});

it('registers the article exporter with a declared permission code', function (): void {
    $exporter = resolve(ExporterRegistry::class)->get('content.article');

    expect($exporter)->toBeInstanceOf(ArticleExporter::class)
        ->and(resolve(MenuRegistry::class)->has($exporter->permission()))->toBeTrue();
});

it('loads its translations under the content:: namespace in every locale', function (): void {
    expect(__('content::article.status.published', [], 'zh_CN'))->toBe('已发布')
        ->and(__('content::article.status.published', [], 'en'))->toBe('Published');

    $keys = fn (string $locale): array => array_keys(Arr::dot(require base_path("app/Modules/Content/resources/lang/{$locale}/article.php")));

    foreach (config()->array('core.locales') as $locale) {
        expect($keys((string) $locale))->toEqualCanonicalizing($keys('en'), "{$locale} 的键与 en 不一致");
    }

    expect(Lang::has('content::article.export.title', 'zh_CN', false))->toBeTrue();
});
