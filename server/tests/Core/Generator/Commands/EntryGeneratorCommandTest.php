<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| 生成到入口中的类：core:make-controller / request / resource（--app 默认 admin，目录 app/Http/<入口>/<模块>/…）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);

    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

describe('controller', function (): void {
    it('creates the model controller with its list request and resource', function (): void {
        $this->artisan('core:make-controller', ['name' => 'Article', '--module' => 'Content', '--model' => 'Article'])
            ->expectsOutputToContain("Route::apiResource('content/articles', ArticleController::class)->only(['index', 'show']);")
            ->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Controllers/ArticleController.php'))->toContain(
            'namespace App\Http\Admin\Content\Controllers;',
            "#[Authorize('viewAny', Article::class)]\n    public function index(ArticleIndexRequest \$request): AnonymousResourceCollection",
            'ArticleResource::collection(Article::query()->latest(\'id\')->paginate($request->pageSize()))',
            "#[Authorize('view', 'article')]\n    public function show(Article \$article): ArticleResource",
        )
            ->and($this->project->read('app/Http/Admin/Content/Requests/ArticleIndexRequest.php'))->toContain('use App\Http\Admin\Requests\ListRequest;', 'class ArticleIndexRequest extends ListRequest', '...parent::rules(),')
            ->and($this->project->read('app/Http/Admin/Content/Resources/ArticleResource.php'))->toContain('@mixin Article', "'created_at' => \$this->created_at?->toIso8601String(),");
    });

    it('keeps an existing list request and resource', function (): void {
        $this->project->put('app/Http/Admin/Content/Resources/ArticleResource.php', 'existing');

        $this->artisan('core:make-controller', ['name' => 'ArticleController', '--module' => 'Content', '--model' => 'Article', '--force' => true])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Resources/ArticleResource.php'))->toBe('existing')
            ->and($this->project->exists('app/Http/Admin/Content/Requests/ArticleIndexRequest.php'))->toBeTrue();
    });

    it('creates a plain controller in another entry', function (): void {
        $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

        $this->artisan('core:make-controller', ['name' => 'Heartbeat', '--app' => 'device', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Device/Content/Controllers/HeartbeatController.php'))->toContain('namespace App\Http\Device\Content\Controllers;', 'class HeartbeatController', "    //\n");
    });

    it('rejects --model in a restricted entry', function (): void {
        $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

        $this->artisan('core:make-controller', ['name' => 'Article', '--app' => 'device', '--module' => 'Content', '--model' => 'Article'])
            ->expectsOutputToContain('受限的入口，不能直接使用模型')
            ->assertFailed();

        expect($this->project->exists('app/Http/Device/Content'))->toBeFalse();
    });

    it('rejects --model when the entry has no ListRequest', function (): void {
        $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();

        $this->artisan('core:make-controller', ['name' => 'Article', '--app' => 'portal', '--module' => 'Content', '--model' => 'Article'])
            ->expectsOutputToContain('没有 Requests/ListRequest.php')
            ->assertFailed();
    });
});

describe('request', function (): void {
    it('creates a form request that only validates input', function (): void {
        $this->artisan('core:make-request', ['name' => 'StoreArticle', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Requests/StoreArticleRequest.php'))
            ->toContain('class StoreArticleRequest extends FormRequest', 'public function rules(): array')
            ->not->toContain('function authorize');
    });

    it('creates a list request with --list', function (): void {
        $this->artisan('core:make-request', ['name' => 'CommentIndexRequest', '--list' => true, '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Requests/CommentIndexRequest.php'))->toContain('class CommentIndexRequest extends ListRequest');
    });

    it('rejects --list when the entry has no ListRequest', function (): void {
        $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();

        $this->artisan('core:make-request', ['name' => 'ArticleIndex', '--list' => true, '--app' => 'portal', '--module' => 'Content'])
            ->expectsOutputToContain('没有 Requests/ListRequest.php')
            ->assertFailed();
    });
});

describe('resource', function (): void {
    it('creates a resource that lists fields explicitly', function (): void {
        $this->artisan('core:make-resource', ['name' => 'ArticleSummary', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Resources/ArticleSummaryResource.php'))
            ->toContain('class ArticleSummaryResource extends JsonResource', 'public function toArray(Request $request): array')
            ->not->toContain('@mixin');
    });

    it('creates a model resource with --model', function (): void {
        $this->artisan('core:make-resource', ['name' => 'Article', '--model' => 'article', '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Resources/ArticleResource.php'))->toContain('use App\Modules\Content\Models\Article;', "'id' => \$this->id,");
    });
});

it('rejects unknown entries, modules and models', function (string $command, array $options, string $message): void {
    $this->artisan($command, ['name' => 'Article', ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($this->project->exists('app/Http/Admin/Content'))->toBeFalse();
})->with([
    'unknown entry' => ['core:make-controller', ['--app' => 'portal', '--module' => 'Content'], '入口 portal 不存在'],
    'unknown module' => ['core:make-request', ['--module' => 'Billing'], '请先执行 php artisan core:make-module Billing'],
    'unknown model' => ['core:make-resource', ['--module' => 'Content', '--model' => 'Comment'], '模型 [app/Modules/Content/Models/Comment.php] 不存在'],
]);
