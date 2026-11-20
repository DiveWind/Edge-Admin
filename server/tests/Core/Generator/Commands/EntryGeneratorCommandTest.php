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
    it('creates the resource controller with its requests, resource, query and actions', function (): void {
        $this->artisan('core:make-controller', ['name' => 'Article', '--module' => 'Content', '--model' => 'Article'])
            ->expectsOutputToContain("Route::apiResource('articles', ArticleController::class)->whereNumber('article');")
            ->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Controllers/ArticleController.php'))->toContain(
            'namespace App\Http\Admin\Content\Controllers;',
            "#[Authorize('viewAny', Article::class)]\n    public function index(ArticleIndexRequest \$request, ArticleList \$list, #[CurrentUser] User \$actor): AnonymousResourceCollection",
            'ArticleResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()))',
            "#[Authorize('view', 'article')]\n    public function show(Article \$article): ArticleResource",
            'new ArticleResource($article->load(ArticleResource::relations()))',
            "#[Authorize('create', Article::class)]\n    public function store(ArticleRequest \$request, CreateArticle \$action, #[CurrentUser] User \$actor): ArticleResource",
            "#[Authorize('update', 'article')]\n    public function update(ArticleRequest \$request, Article \$article, UpdateArticle \$action, #[CurrentUser] User \$actor): ArticleResource",
            "#[Authorize('delete', 'article')]\n    public function destroy(Article \$article, DeleteArticle \$action, #[CurrentUser] User \$actor): void",
        )
            ->and($this->project->read('app/Http/Admin/Content/Requests/ArticleIndexRequest.php'))->toContain('class ArticleIndexRequest extends ListRequest', "'filter.name' => ['sometimes', 'string', 'max:255'],", "'filter.created_at'")
            ->and($this->project->read('app/Http/Admin/Content/Requests/ArticleRequest.php'))->toContain("'name' => ['required', 'string', 'max:255'],", '__("content::article.attributes.{$field}")')
            ->and($this->project->read('app/Http/Admin/Content/Resources/ArticleResource.php'))->toContain('@mixin Article', "'name' => \$this->name,", "'creator' => \$this->creator?->only(['id', 'username', 'name']),", "return ['creator'];")
            ->and($this->project->read('app/Modules/Content/Queries/ArticleList.php'))->toContain('->visibleTo($actor)', "AllowedFilter::partial('name')")
            ->and($this->project->exists('app/Modules/Content/Actions/CreateArticle.php'))->toBeTrue()
            ->and($this->project->exists('app/Modules/Content/Actions/DeleteArticle.php'))->toBeTrue();
    });

    it('does not print the route when the controller is already registered', function (): void {
        $this->project->put('app/Http/Admin/routes.php', "<?php\n\nRoute::apiResource('articles', ArticleController::class);\n");

        $this->artisan('core:make-controller', ['name' => 'Article', '--module' => 'Content', '--model' => 'Article'])
            ->doesntExpectOutputToContain('路由不会自动登记')
            ->assertSuccessful();
    });

    it('creates single action controllers by name, with their routes', function (string $name, string $route, string $contains): void {
        $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content', '--soft-deletes' => true])->assertSuccessful();

        $this->artisan('core:make-controller', ['name' => $name, '--module' => 'Content', '--model' => 'Comment', '--invokable' => true])
            ->expectsOutputToContain($route)
            ->assertSuccessful();

        expect($this->project->read("app/Http/Admin/Content/Controllers/{$name}Controller.php"))->toContain('public function __invoke(', $contains);
    })->with([
        'restore' => ['RestoreComment', "Route::post('comments/{comment}/restore', RestoreCommentController::class)->whereNumber('comment')->withTrashed()->name('comments.restore');", "#[Authorize('restore', 'comment')]"],
        'other action' => ['PublishComment', "Route::post('comments/{comment}/publish', PublishCommentController::class)->whereNumber('comment')->name('comments.publish');", 'PublishComment $action'],
        'force delete' => ['ForceDeleteComment', "Route::delete('comments/{comment}/force-delete', ForceDeleteCommentController::class)->whereNumber('comment')->withTrashed()->name('comments.force-delete');", "#[Authorize('forceDelete', 'comment')]"],
    ]);

    it('creates a plain single action controller', function (): void {
        $this->artisan('core:make-controller', ['name' => 'Dashboard', '--module' => 'Content', '--invokable' => true])
            ->expectsOutputToContain("Route::post('dashboard', DashboardController::class)->name('dashboard');")
            ->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Controllers/DashboardController.php'))->toContain("    public function __invoke(): void\n    {\n        //\n    }");
    });

    it('creates the export controller with its request and exporter', function (): void {
        $this->project->put('app/Http/Admin/System/Resources/ExportResource.php', '<?php');
        $this->project->put('app/Http/Admin/AdminServiceProvider.php', "<?php\n\nnamespace App\\Http\\Admin;\n\nclass AdminServiceProvider { public const string EXPORT_LIMITER = 'admin-exports'; }\n");

        $this->artisan('core:make-controller', ['name' => 'ArticleExport', '--module' => 'Content', '--model' => 'Article', '--invokable' => true])
            ->expectsOutputToContain("Route::post('articles/export', ArticleExportController::class)->middleware('throttle:'.AdminServiceProvider::EXPORT_LIMITER)->name('articles.export');")
            ->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Controllers/ArticleExportController.php'))->toContain("#[Authorize('content.article.export')]", "\$action->handle(\$actor, 'content.article',")
            ->and($this->project->read('app/Http/Admin/Content/Requests/ArticleExportRequest.php'))->toContain('class ArticleExportRequest extends ArticleIndexRequest', "unset(\$rules['page'], \$rules['pageSize']);")
            ->and($this->project->read('app/Modules/Content/Exporters/ArticleExporter.php'))->toContain('private readonly ArticleList $list')
            ->and($this->project->read('app/Modules/Content/resources/exporters.php'))->toContain("'content.article' => ArticleExporter::class,");
    });

    it('rejects an export controller in an entry without exports', function (): void {
        $this->artisan('core:make-controller', ['name' => 'ArticleExport', '--module' => 'Content', '--model' => 'Article', '--invokable' => true])
            ->expectsOutputToContain('不支持导出')
            ->assertFailed();
    });

    it('rejects a force delete controller for a model without soft deletes', function (): void {
        $this->artisan('core:make-controller', ['name' => 'ForceDeleteArticle', '--module' => 'Content', '--model' => 'Article', '--invokable' => true])
            ->expectsOutputToContain('没有软删除')
            ->assertFailed();
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

        expect($this->project->read('app/Http/Admin/Content/Requests/CommentIndexRequest.php'))->toContain('class CommentIndexRequest extends ListRequest', "// 'filter.status'");
    });

    it('validates the trashed filter of a model with soft deletes', function (): void {
        $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content', '--soft-deletes' => true])->assertSuccessful();

        $this->artisan('core:make-request', ['name' => 'CommentIndex', '--list' => true, '--module' => 'Content'])->assertSuccessful();

        expect($this->project->read('app/Http/Admin/Content/Requests/CommentIndexRequest.php'))->toContain("'filter.trashed' => ['sometimes', 'string', 'in:with,only'],");
    });

    it('requires the list request for an export request', function (): void {
        $this->artisan('core:make-request', ['name' => 'ArticleExport', '--export' => true, '--module' => 'Content'])
            ->expectsOutputToContain('请先执行 php artisan core:make-request ArticleIndex --list')
            ->assertFailed();
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
