<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| core:make-crud：一个模型的完整增删改查（在隔离的临时项目中执行，设计文档 §10.4）
|--------------------------------------------------------------------------
|
| 这里检查生成了哪些文件、共享文件的登记与幂等；生成物能否不手改就通过全部检查由 EndToEndTest（generator-e2e 组）在项目副本中验证。
|
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);
    $this->project->put('app/Http/Admin/System/Resources/ExportResource.php', '<?php');
    $this->project->put('app/Http/Admin/AdminServiceProvider.php', "<?php\n\nnamespace App\\Http\\Admin;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AdminServiceProvider extends ServiceProvider\n{\n    public const string EXPORT_LIMITER = 'admin-exports';\n}\n");

    $this->artisan('core:make-module', ['name' => 'Blog'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

it('generates every file of the CRUD with all options', function (): void {
    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog', '--soft-deletes' => true, '--export' => true, '--audit' => true])->assertSuccessful();

    expect($this->project->files('app/Modules/Blog'))->toContain(
        'app/Modules/Blog/Models/Post.php', 'app/Modules/Blog/Policies/PostPolicy.php',
        'app/Modules/Blog/Actions/CreatePost.php', 'app/Modules/Blog/Actions/UpdatePost.php', 'app/Modules/Blog/Actions/DeletePost.php',
        'app/Modules/Blog/Actions/RestorePost.php', 'app/Modules/Blog/Actions/ForceDeletePost.php', 'app/Modules/Blog/resources/exporters.php',
        'app/Modules/Blog/Queries/PostList.php', 'app/Modules/Blog/Exporters/PostExporter.php',
        'app/Modules/Blog/resources/lang/en/post.php', 'app/Modules/Blog/resources/lang/zh_CN/post.php',
    )
        ->and($this->project->files('app/Http/Admin/Blog'))->toBe([
            'app/Http/Admin/Blog/Controllers/ForceDeletePostController.php', 'app/Http/Admin/Blog/Controllers/PostController.php',
            'app/Http/Admin/Blog/Controllers/PostExportController.php', 'app/Http/Admin/Blog/Controllers/RestorePostController.php',
            'app/Http/Admin/Blog/Requests/PostExportRequest.php', 'app/Http/Admin/Blog/Requests/PostIndexRequest.php', 'app/Http/Admin/Blog/Requests/PostRequest.php',
            'app/Http/Admin/Blog/Resources/PostResource.php',
        ])
        ->and($this->project->files('tests'))->toBe([
            'tests/Http/Admin/Blog/Controllers/ForceDeletePostControllerTest.php', 'tests/Http/Admin/Blog/Controllers/PostControllerTest.php',
            'tests/Http/Admin/Blog/Controllers/PostExportControllerTest.php', 'tests/Http/Admin/Blog/Controllers/RestorePostControllerTest.php',
            'tests/Modules/Blog/Actions/PostActivityTest.php',
        ])
        ->and($this->project->read('app/Modules/Blog/Actions/CreatePost.php'))->toContain("\$this->audit->handle(\$actor, \$post, 'created');")
        ->and($this->project->read('app/Modules/Blog/Models/Post.php'))->toContain('implements Auditable')
        ->and($this->project->read('app/Modules/Blog/BlogServiceProvider.php'))->toContain("'blog.post' => Post::class,", "\$exporters->register(require __DIR__.'/resources/exporters.php');")
        ->and($this->project->read('app/Modules/Blog/resources/exporters.php'))->toContain("'blog.post' => PostExporter::class,")
        ->and($this->project->read('app/Modules/Blog/README.md'))->toContain('## Post（完整增删改查）', '`POST /blog/posts/{post}/restore`', '**加字段时要改的地方**')
        ->and($this->project->read('tests/Http/Admin/Blog/Controllers/PostControllerTest.php'))->toContain("route('admin.blog.posts.index')", 'only lists the posts within the data scope (self)');

    $menus = require $this->project->path('app/Modules/Blog/resources/menus.php');

    expect($menus[0])->toMatchArray(['name' => 'blog', 'type' => 'catalog', 'path' => '/blog'])
        ->and($menus[0]['children'][0])->toMatchArray(['name' => 'blog.post', 'type' => 'menu', 'path' => '/blog/post', 'component' => '/blog/post/list'])
        ->and(array_keys($menus[0]['children'][0]['buttons']))->toBe(['create', 'update', 'delete', 'restore', 'force-delete', 'export'])
        ->and($this->project->read('app/Http/Admin/routes.php'))->toContain(
            "use App\\Http\\Admin\\AdminServiceProvider;\nuse App\\Http\\Admin\\Blog\\Controllers\\{ForceDeletePostController, PostController, PostExportController, RestorePostController};",
            "    // Blog（app/Modules/Blog，接口见模块 README）\n    Route::prefix('blog')->name('blog.')->group(function (): void {\n        Route::post('posts/export', PostExportController::class)",
            "        Route::apiResource('posts', PostController::class)->whereNumber('post');\n    });\n});",
        )
        ->and($this->project->pintFailures())->toBeNull();
});

it('adds a second model to the same catalog, route group and README without the optional parts', function (): void {
    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog'])->assertSuccessful();
    $this->artisan('core:make-crud', ['name' => 'Tag', '--module' => 'Blog', '--no-data-scope' => true])->assertSuccessful();

    $menus = require $this->project->path('app/Modules/Blog/resources/menus.php');
    $routes = $this->project->read('app/Http/Admin/routes.php');

    expect($menus)->toHaveCount(1)
        ->and(array_column($menus[0]['children'], 'name'))->toBe(['blog.post', 'blog.tag'])
        ->and(array_keys($menus[0]['children'][0]['buttons']))->toBe(['create', 'update', 'delete'])
        ->and(substr_count($routes, "Route::prefix('blog')"))->toBe(1)
        ->and($routes)->toContain("        Route::apiResource('posts', PostController::class)->whereNumber('post');\n        Route::apiResource('tags', TagController::class)->whereNumber('tag');\n    });")
        ->and($this->project->read('app/Modules/Blog/Models/Tag.php'))->not->toContain('HasDataScope')
        ->and($this->project->exists('app/Modules/Blog/Actions/RestorePost.php'))->toBeFalse()
        ->and($this->project->exists('app/Modules/Blog/Exporters/PostExporter.php'))->toBeFalse()
        ->and($this->project->read('app/Modules/Blog/README.md'))->toContain('## Post（完整增删改查）', "## Tag（完整增删改查）\n")
        ->and(strpos($this->project->read('app/Modules/Blog/README.md'), '## Tag'))->toBeLessThan(strpos($this->project->read('app/Modules/Blog/README.md'), '## 删除本模块'))
        ->and($this->project->pintFailures())->toBeNull();
});

it('fails without writing anything when the model exists, and regenerates idempotently with --force', function (): void {
    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog'])->assertSuccessful();
    $shared = ['app/Modules/Blog/resources/menus.php', 'app/Http/Admin/routes.php', 'app/Modules/Blog/BlogServiceProvider.php', 'app/Modules/Blog/README.md'];
    $edited = array_map($this->project->read(...), $shared);

    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog'])
        ->expectsOutputToContain('已存在：完整增删改查用于新模型')
        ->assertFailed();

    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog', '--force' => true])->assertSuccessful();

    expect(array_map($this->project->read(...), $shared))->toBe($edited)
        ->and($this->project->files('database/migrations'))->toHaveCount(1);
});

it('rejects restricted entries and entries without exports', function (array $options, string $message): void {
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog', ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($this->project->exists('app/Modules/Blog/Models'))->toBeFalse();
})->with([
    'restricted entry' => [['--app' => 'device'], '受限的入口'],
    'no exports' => [['--app' => 'portal', '--export' => true], '入口 portal 不存在'],
]);

it('prints the route group to paste when routes.php has an unexpected format', function (): void {
    $this->project->put('app/Http/Admin/routes.php', "<?php\n\nrequire __DIR__.'/other.php';\n");

    $this->artisan('core:make-crud', ['name' => 'Post', '--module' => 'Blog'])
        ->expectsOutputToContain('无法在 [app/Http/Admin/routes.php] 中登记')
        ->expectsOutputToContain("Route::prefix('blog')->name('blog.')->group(function (): void {")
        ->assertFailed();

    expect($this->project->exists('app/Http/Admin/Blog/Controllers/PostController.php'))->toBeTrue();
});
