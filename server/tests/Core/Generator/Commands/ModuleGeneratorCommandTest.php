<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| 生成到模块中的类：core:make-action / query / enum / data，以及共同的模块选择、名称、--force 行为
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);

    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

it('generates the class in the module directory', function (string $command, string $name, string $path, array $contains): void {
    $this->artisan($command, ['name' => $name, '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read($path))->toContain(...$contains);
})->with([
    'action' => ['core:make-action', 'publish_article', 'app/Modules/Content/Actions/PublishArticle.php', [
        'namespace App\Modules\Content\Actions;', 'class PublishArticle', 'public function handle(User $actor): void', 'DB::transaction(function (): void {',
    ]],
    'query' => ['core:make-query', 'ArticleStatistics', 'app/Modules/Content/Queries/ArticleStatistics.php', [
        'namespace App\Modules\Content\Queries;', 'class ArticleStatistics', 'public function get(User $actor): array',
    ]],
    'enum' => ['core:make-enum', 'ArticleStatus', 'app/Modules/Content/Enums/ArticleStatus.php', [
        'use App\Core\System\Contracts\HasLabel;', 'enum ArticleStatus: string implements HasLabel', 'public function label(): string',
    ]],
    'data' => ['core:make-data', 'ArticleSummary', 'app/Modules/Content/Data/ArticleSummaryData.php', [
        'namespace App\Modules\Content\Data;', 'final readonly class ArticleSummaryData',
    ]],
]);

it('generates an enum with a semantic color with --color', function (): void {
    $this->artisan('core:make-enum', ['name' => 'ArticleStatus', '--module' => 'Content', '--color' => true])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Enums/ArticleStatus.php'))->toContain(
        'use App\Core\System\Contracts\{HasColor, HasLabel};', 'enum ArticleStatus: string implements HasColor, HasLabel', 'public function color(): string',
    );
});

describe('with a model', function (): void {
    beforeEach(function (): void {
        $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content', '--soft-deletes' => true, '--audit' => true])->assertSuccessful();
    });

    it('recognizes the standard actions by name, writing the audit log because the model is auditable', function (string $name, array $contains): void {
        $this->artisan('core:make-action', ['name' => $name, '--module' => 'Content', '--model' => 'Article'])->assertSuccessful();

        expect($this->project->read("app/Modules/Content/Actions/{$name}.php"))->toContain(...$contains)
            ->not->toContain('public const')
            ->and($this->project->exists('app/Modules/Content/Actions/LogArticleActivity.php'))->toBeFalse();
    })->with([
        'create' => ['CreateArticle', ['array_intersect_key($attributes, array_flip($article->getFillable()))', 'public function handle(array $attributes, User $actor): Article', '$article->created_by = $actor->id;', "\$this->audit->handle(\$actor, \$article, 'created');", 'use App\Core\Monitor\Actions\LogAudit;']],
        'update' => ['UpdateArticle', ['public function handle(Article $article, array $attributes, User $actor): Article', 'Article::query()->lockForUpdate()->findOrFail($article->id)', '$before = $locked->auditSnapshot();']],
        'delete' => ['DeleteArticle', ['public function handle(Article $article, User $actor): void', '$locked->delete();', "'deleted'"]],
        'restore' => ['RestoreArticle', ['withTrashed()->lockForUpdate()', "throw new BusinessRuleViolation(__('content::article.messages.restore_trashed_only'));", "'restored'"]],
        'force delete' => ['ForceDeleteArticle', ['$locked->forceDelete();', "'force_deleted', \$before"]],
        'other' => ['PublishArticle', ['public function handle(Article $article, User $actor): Article', '// TODO: 复核状态']],
    ]);

    it('makes the model auditable with --audit', function (): void {
        expect($this->project->read('app/Modules/Content/Models/Article.php'))->toContain(
            'use App\Core\Monitor\Contracts\Auditable;', 'class Article extends Model implements Auditable', 'public function auditSnapshot(): array', "'name' => \$this->name,", 'public function auditDigestedFields(): array',
        );
    });

    it('does not write the audit log for models that are not auditable', function (): void {
        $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content'])->assertSuccessful();
        $this->artisan('core:make-action', ['name' => 'CreateComment', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Modules/Content/Models/Comment.php'))->not->toContain('Auditable')
            ->and($this->project->read('app/Modules/Content/Actions/CreateComment.php'))->not->toContain('LogAudit');
    });

    it('generates the list query of the model', function (): void {
        $this->artisan('core:make-query', ['name' => 'ArticleList', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Modules/Content/Queries/ArticleList.php'))->toContain(
            'public function builder(User $actor, array $parameters): QueryBuilder',
            "QueryBuilder::for(Article::query()->visibleTo(\$actor)->with(['creator']), new Request(\$parameters))",
            "AllowedFilter::partial('name'),", "DateTimeRangeFilter::allowed('created_at'),", 'AllowedFilter::trashed(),',
            "Gate::forUser(\$actor)->authorize('viewTrashed', Article::class);",
            "->defaultSort('-id');",
        );
    });

    it('generates the exporter and registers it', function (): void {
        $this->artisan('core:make-exporter', ['name' => 'Article', '--module' => 'Content'])
            ->expectsOutputToContain("'export' => 'common.export'")
            ->assertSuccessful();

        expect($this->project->read('app/Modules/Content/Exporters/ArticleExporter.php'))->toContain(
            '@implements Exporter<Article>', "return 'content.article.export';", "\$columns = ['id', 'name', 'creator', 'created_at', 'deleted_at'];",
        )
            ->and($this->project->exists('app/Modules/Content/Queries/ArticleList.php'))->toBeTrue()
            ->and($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
                'public function boot(MenuRegistry $menus, DictRegistry $dicts, ExporterRegistry $exporters): void',
                "        \$exporters->register(require __DIR__.'/resources/exporters.php');",
            )
            ->and($this->project->read('app/Modules/Content/resources/exporters.php'))->toContain(
                'use App\Modules\Content\Exporters\ArticleExporter;', "return [\n    'content.article' => ArticleExporter::class,\n];",
            );
    });

    it('rejects restore and force delete actions for models without soft deletes', function (): void {
        $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content'])->assertSuccessful();

        $this->artisan('core:make-action', ['name' => 'RestoreComment', '--module' => 'Content'])
            ->expectsOutputToContain('没有软删除')
            ->assertFailed();
    });
});

it('generates into a core module with --core', function (): void {
    $this->artisan('core:make-action', ['name' => 'SyncMenus', '--module' => 'System', '--core' => true])->assertSuccessful();

    expect($this->project->read('app/Core/System/Actions/SyncMenus.php'))->toContain('namespace App\Core\System\Actions;');
});

it('asks for the name and the module when they are missing', function (): void {
    $this->artisan('core:make-module', ['name' => 'Billing'])->assertSuccessful();

    $this->artisan('core:make-action')
        ->expectsQuestion('Action 的名称？', 'PublishArticle')
        ->expectsChoice('属于哪个模块？', 'Content', ['Billing', 'Content'])
        ->assertSuccessful();

    expect($this->project->exists('app/Modules/Content/Actions/PublishArticle.php'))->toBeTrue();
});

it('requires --module when not interactive', function (): void {
    $this->artisan('core:make-query', ['name' => 'ArticleStatistics', '--no-interaction' => true])
        ->expectsOutputToContain('请用 --module= 指定所属模块')
        ->assertFailed();
});

it('fails when the class exists and overwrites it with --force', function (): void {
    $this->project->put('app/Modules/Content/Actions/PublishArticle.php', 'edited');

    $this->artisan('core:make-action', ['name' => 'PublishArticle', '--module' => 'Content'])
        ->expectsOutputToContain('Action already exists.')
        ->assertFailed();

    expect($this->project->read('app/Modules/Content/Actions/PublishArticle.php'))->toBe('edited');

    $this->artisan('core:make-action', ['name' => 'PublishArticle', '--module' => 'Content', '--force' => true])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Actions/PublishArticle.php'))->toContain('class PublishArticle');
});

it('rejects invalid class and module names', function (array $input, string $message): void {
    $this->artisan('core:make-action', $input)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($this->project->exists('app/Modules/Content/Actions'))->toBeFalse();
})->with([
    'subdirectory' => [['name' => 'Article/Publish', '--module' => 'Content'], '不能包含子目录'],
    'namespace' => [['name' => 'App\Actions\Publish', '--module' => 'Content'], '不能包含子目录'],
    'starts with a digit' => [['name' => '2fa', '--module' => 'Content'], '不合法'],
    'reserved word' => [['name' => 'list', '--module' => 'Content'], '保留字'],
    'invalid module' => [['name' => 'Publish', '--module' => 'Con/tent'], '模块名“Con/tent”不能包含子目录'],
]);
