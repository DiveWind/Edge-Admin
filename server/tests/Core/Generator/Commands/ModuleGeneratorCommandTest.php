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
        'namespace App\Modules\Content\Actions;', 'class PublishArticle', 'public function handle(): void', 'DB::transaction(function (): void {',
    ]],
    'query' => ['core:make-query', 'ArticleStatistics', 'app/Modules/Content/Queries/ArticleStatistics.php', [
        'namespace App\Modules\Content\Queries;', 'class ArticleStatistics', 'public function get(): array',
    ]],
    'enum' => ['core:make-enum', 'ArticleStatus', 'app/Modules/Content/Enums/ArticleStatus.php', [
        'use App\Core\System\Contracts\HasLabel;', 'enum ArticleStatus: string implements HasLabel', 'public function label(): string',
    ]],
    'data' => ['core:make-data', 'ArticleSummary', 'app/Modules/Content/Data/ArticleSummaryData.php', [
        'namespace App\Modules\Content\Data;', 'final readonly class ArticleSummaryData',
    ]],
]);

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
