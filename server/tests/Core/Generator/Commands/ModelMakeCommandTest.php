<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| core:make-model（及其调用的 core:make-migration、core:make-factory、core:make-policy）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);

    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

it('creates the model, migration, factory, policy and translations and registers them in the provider', function (): void {
    $this->artisan('core:make-model', ['name' => 'article', '--module' => 'Content'])->assertSuccessful();

    $migrations = $this->project->files('database/migrations');

    expect($this->project->read('app/Modules/Content/Models/Article.php'))
        ->toContain(
            'namespace App\Modules\Content\Models;', 'use HasDataScope, HasFactory, UsesAppTimezone;', "#[Fillable(['name'])]", 'protected function casts(): array',
            '@use HasFactory<ArticleFactory>', '`content.article`', '@property string $name', 'public function creator(): BelongsTo',
        )
        ->not->toContain('SoftDeletes')
        ->and($migrations)->toHaveCount(1)
        ->and($migrations[0])->toMatch('/^database\/migrations\/\d{4}_\d{2}_\d{2}_\d{6}_create_articles_table\.php$/')
        ->and($this->project->read($migrations[0]))->toContain(
            "Schema::create('articles', function (Blueprint \$table): void {",
            "\$table->string('name');",
            "\$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();",
            "Schema::dropIfExists('articles');",
        )->not->toContain('softDeletes')
        ->and($this->project->read('database/factories/Modules/Content/Models/ArticleFactory.php'))
        ->toContain('namespace Database\Factories\Modules\Content\Models;', 'use App\Modules\Content\Models\Article;', '@extends Factory<Article>', "'name' => fake()->words(3, true),", "'created_by' => User::factory(),")
        ->not->toContain('function trashed')
        ->and($this->project->read('app/Modules/Content/resources/lang/en/article.php'))->toContain("'name' => 'name',", "'title' => 'Articles',")
        ->and($this->project->read('app/Modules/Content/resources/lang/zh_CN/article.php'))->toContain("'name' => '名称',")
        ->and($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
            "use App\\Modules\\Content\\Models\\Article;\nuse Illuminate\\Database\\Eloquent\\Relations\\Relation;\nuse Illuminate\\Support\\ServiceProvider;",
            "        Relation::morphMap([\n            'content.article' => Article::class,\n        ]);\n\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'content');",
        );

    expect(array_keys(require $this->project->path('app/Modules/Content/resources/lang/en/article.php')))
        ->toBe(array_keys(require $this->project->path('app/Modules/Content/resources/lang/zh_CN/article.php')));
});

it('creates a soft deleting model without data scope', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content', '--soft-deletes' => true, '--no-data-scope' => true])->assertSuccessful();

    [$migration] = $this->project->files('database/migrations');

    expect($this->project->read('app/Modules/Content/Models/Article.php'))->toContain('use HasFactory, SoftDeletes, UsesAppTimezone;', '@property CarbonInterface|null $deleted_at')
        ->not->toContain('HasDataScope')
        ->and($this->project->read($migration))->toContain('$table->softDeletes()->index();')
        ->and($this->project->read('database/factories/Modules/Content/Models/ArticleFactory.php'))->toContain('public function trashed(): static')
        ->and($this->project->read('app/Modules/Content/resources/lang/en/article.php'))->toContain("'restore_trashed_only' => 'Only deleted articles can be restored.',");
});

it('creates a policy with every standard method in the standard order: code, protection, data scope', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])
        ->expectsOutputToContain('app/Modules/Content/resources/menus.php')
        ->expectsOutputToContain('content.article.create、content.article.update、content.article.delete')
        ->assertSuccessful();

    $policy = $this->project->read('app/Modules/Content/Policies/ArticlePolicy.php');

    foreach (['viewAny' => '', 'create' => '.create', 'deleteAny' => '.delete'] as $method => $suffix) {
        expect($policy)->toContain("public function {$method}(User \$actor): Response\n    {\n        return \$actor->can('content.article{$suffix}') ? Response::allow() : Response::deny();\n    }");
    }

    foreach (['view' => '', 'update' => '.update', 'delete' => '.delete'] as $method => $suffix) {
        expect($policy)->toContain("public function {$method}(User \$actor, Article \$article): Response\n    {\n        if (! \$actor->can('content.article{$suffix}')) {\n            return Response::deny();\n        }\n\n        return \$article->isVisibleTo(\$actor) ? Response::allow() : Response::deny();\n    }");
    }

    foreach (['restore' => true, 'forceDelete' => true, 'restoreAny' => false, 'forceDeleteAny' => false] as $method => $withModel) {
        $parameters = $withModel ? 'User $actor, Article $article' : 'User $actor';

        expect($policy)->toContain("public function {$method}({$parameters}): Response\n    {\n        return Response::deny();\n    }");
    }

    expect($policy)->toContain("use App\\Core\\System\\Models\\User;\nuse App\\Modules\\Content\\Models\\Article;\nuse Illuminate\\Auth\\Access\\Response;", '菜单节点 `content.article` 是查看 / 列表权限')
        ->and(substr_count($policy, 'public function '))->toBe(10);
});

it('creates the soft delete rules of the policy for a soft deleting model', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content', '--soft-deletes' => true])
        ->expectsOutputToContain('content.article.restore、content.article.force-delete')
        ->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Policies/ArticlePolicy.php'))->toContain(
        "if (! \$actor->can('content.article.delete') || \$article->trashed()) {",
        "if (! \$article->trashed()) {\n            return Response::deny(__('content::article.messages.restore_trashed_only'));\n        }",
        "__('content::article.messages.force_delete_trashed_only')",
        'public function viewTrashed(User $actor): Response',
        "return \$actor->can('content.article.force-delete') ? Response::allow() : Response::deny();",
    );
});

it('only mentions the permission codes that are not declared yet', function (): void {
    $this->project->put('app/Modules/Content/resources/menus.php', "<?php\n\nreturn [['name' => 'content.article', 'buttons' => ['create' => 'x', 'update' => 'x', 'delete' => 'x']]];\n");

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])
        ->doesntExpectOutputToContain('请在 [app/Modules/Content/resources/menus.php] 中声明')
        ->assertSuccessful();
});

it('uses the permission code given with --permission', function (): void {
    $this->artisan('core:make-model', ['name' => 'ArticleCategory', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Policies/ArticleCategoryPolicy.php'))->toContain("\$actor->can('content.article_category.update')");

    $this->artisan('core:make-policy', ['name' => 'ArticleCategory', '--module' => 'Content', '--permission' => 'content.category', '--force' => true])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Policies/ArticleCategoryPolicy.php'))->toContain("\$actor->can('content.category.update')")
        ->not->toContain('content.article_category');
});

it('rejects an invalid permission code', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();

    $this->artisan('core:make-policy', ['name' => 'Article', '--module' => 'Content', '--permission' => 'Content.Article', '--force' => true])
        ->expectsOutputToContain('权限码“Content.Article”不合法')
        ->assertFailed();
});

it('appends further models to the morph map with grouped imports', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();
    $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
        'use App\Modules\Content\Models\{Article, Comment};',
        "            'content.article' => Article::class,\n            'content.comment' => Comment::class,\n        ]);",
    );
});

it('regenerates with --force without duplicating the migration or the morph alias', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();
    [$migration] = $this->project->files('database/migrations');
    $this->project->put($migration, 'edited');
    $this->travel(5)->seconds();

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content', '--force' => true])
        ->expectsOutputToContain('已登记过，跳过')
        ->assertSuccessful();

    expect($this->project->files('database/migrations'))->toBe([$migration])
        ->and($this->project->read($migration))->toContain("Schema::create('articles'")
        ->and(substr_count($this->project->read('app/Modules/Content/ContentServiceProvider.php'), "'content.article'"))->toBe(1);
});

it('fails without creating other files when the model exists', function (): void {
    $this->project->put('app/Modules/Content/Models/Article.php', '<?php // edited');

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])
        ->expectsOutputToContain('Model already exists.')
        ->assertFailed();

    expect($this->project->read('app/Modules/Content/Models/Article.php'))->toBe('<?php // edited')
        ->and($this->project->files('database/migrations'))->toBe([])
        ->and($this->project->exists('app/Modules/Content/Policies'))->toBeFalse();
});

it('keeps existing migrations, factories and policies of a new model', function (): void {
    $this->project->put('database/migrations/2020_01_01_000000_create_articles_table.php', 'existing');
    $this->project->put('app/Modules/Content/Policies/ArticlePolicy.php', 'existing');

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])
        ->expectsOutputToContain('已存在，跳过')
        ->assertSuccessful();

    expect($this->project->files('database/migrations'))->toBe(['database/migrations/2020_01_01_000000_create_articles_table.php'])
        ->and($this->project->read('app/Modules/Content/Policies/ArticlePolicy.php'))->toBe('existing')
        ->and($this->project->exists('database/factories/Modules/Content/Models/ArticleFactory.php'))->toBeTrue();
});

it('uses a plain alias for core modules and prints the snippet when the module has no provider', function (): void {
    $this->artisan('core:make-model', ['name' => 'Role', '--module' => 'System', '--core' => true])
        ->expectsOutputToContain('找不到 [app/Core/System/SystemServiceProvider.php]')
        ->expectsOutputToContain("'role' => Role::class,")
        ->assertFailed();

    expect($this->project->read('app/Core/System/Policies/RolePolicy.php'))->toContain('use App\Core\System\Models\{Role, User};')
        ->and($this->project->exists('database/factories/Core/System/Models/RoleFactory.php'))->toBeTrue();
});

it('registers the morph map and the translations in a provider whose boot method is still a placeholder', function (): void {
    $this->project->put('app/Modules/Content/ContentServiceProvider.php', implode("\n", [
        '<?php', '', 'namespace App\\Modules\\Content;', '', 'use Illuminate\\Support\\ServiceProvider;', '',
        'class ContentServiceProvider extends ServiceProvider', '{', '    public function boot(): void', '    {', '        //', '    }', '}', '',
    ]));

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
        "    public function boot(): void\n    {\n        Relation::morphMap([\n            'content.article' => Article::class,\n        ]);\n\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'content');\n    }",
    );
});

it('refuses a morph alias that belongs to another model', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();
    $provider = $this->project->read('app/Modules/Content/ContentServiceProvider.php');
    $this->project->put('app/Modules/Content/ContentServiceProvider.php', str_replace("'content.article' => Article::class", "'content.post' => Article::class,\n            'content.comment' => Article::class", $provider));

    $this->artisan('core:make-model', ['name' => 'Comment', '--module' => 'Content'])
        ->expectsOutputToContain('多态别名 content.comment 已被 App\Modules\Content\Models\Article 使用')
        ->assertFailed();
});

it('requires an existing module', function (array $options, string $message): void {
    $this->artisan('core:make-model', ['name' => 'Article', ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($this->project->files('database/migrations'))->toBe([]);
})->with([
    'no module' => [['--no-interaction' => true], '请用 --module= 指定所属模块'],
    'unknown module' => [['--module' => 'Billing'], '请先执行 php artisan core:make-module Billing'],
    'core module without --core' => [['--module' => 'System'], '内核模块需加 --core'],
    'business module with --core' => [['--module' => 'Content', '--core' => true], '不要加 --core'],
]);

it('generates migrations, factories and policies for existing models only', function (string $command): void {
    $this->artisan($command, ['name' => 'Article', '--module' => 'Content'])
        ->expectsOutputToContain('模型 [app/Modules/Content/Models/Article.php] 不存在')
        ->assertFailed();
})->with(['core:make-migration', 'core:make-factory', 'core:make-policy']);

it('generates a policy for the model given with --model', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();

    $this->artisan('core:make-policy', ['name' => 'ArticleReview', '--model' => 'Article', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Policies/ArticleReviewPolicy.php'))->toContain('class ArticleReviewPolicy', 'public function view(User $actor, Article $article): Response');
});
