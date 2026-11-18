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

it('creates the model, migration, factory and policy and registers the morph alias', function (): void {
    $this->artisan('core:make-model', ['name' => 'article', '--module' => 'Content'])->assertSuccessful();

    $migrations = $this->project->files('database/migrations');

    expect($this->project->read('app/Modules/Content/Models/Article.php'))
        ->toContain('namespace App\Modules\Content\Models;', 'use HasFactory, UsesAppTimezone;', '#[Fillable([])]', 'protected function casts(): array', '@use HasFactory<ArticleFactory>', '`content.article`')
        ->and($migrations)->toHaveCount(1)
        ->and($migrations[0])->toMatch('/^database\/migrations\/\d{4}_\d{2}_\d{2}_\d{6}_create_articles_table\.php$/')
        ->and($this->project->read($migrations[0]))->toContain("Schema::create('articles', function (Blueprint \$table): void {", "Schema::dropIfExists('articles');")
        ->and($this->project->read('database/factories/Modules/Content/Models/ArticleFactory.php'))
        ->toContain('namespace Database\Factories\Modules\Content\Models;', 'use App\Modules\Content\Models\Article;', '@extends Factory<Article>')
        ->and($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
            "use App\\Modules\\Content\\Models\\Article;\nuse Illuminate\\Database\\Eloquent\\Relations\\Relation;\nuse Illuminate\\Support\\ServiceProvider;",
            "    public function boot(): void\n    {\n        Relation::morphMap([\n            'content.article' => Article::class,\n        ]);\n    }",
        );
});

it('creates a policy with every standard method denying by default', function (): void {
    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();

    $policy = $this->project->read('app/Modules/Content/Policies/ArticlePolicy.php');

    foreach (['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny'] as $method) {
        expect($policy)->toContain("public function {$method}(User \$user): bool\n    {\n        return false;\n    }");
    }

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $method) {
        expect($policy)->toContain("public function {$method}(User \$user, Article \$article): bool\n    {\n        return false;\n    }");
    }

    expect($policy)->toContain("use App\\Core\\System\\Models\\User;\nuse App\\Modules\\Content\\Models\\Article;", 'TODO: 第 5 步')
        ->and(substr_count($policy, 'return false;'))->toBe(10);
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

it('inserts the morph map at the start of a boot method that already has statements', function (): void {
    $provider = $this->project->read('app/Modules/Content/ContentServiceProvider.php');
    $this->project->put('app/Modules/Content/ContentServiceProvider.php', str_replace(
        "    public function boot(): void\n    {\n        //\n    }",
        "    public function boot(): void\n    {\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'content');\n    }",
        $provider,
    ));

    $this->artisan('core:make-model', ['name' => 'Article', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
        "        Relation::morphMap([\n            'content.article' => Article::class,\n        ]);\n\n        \$this->loadTranslationsFrom(",
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

    expect($this->project->read('app/Modules/Content/Policies/ArticleReviewPolicy.php'))->toContain('class ArticleReviewPolicy', 'public function view(User $user, Article $article): bool');
});
