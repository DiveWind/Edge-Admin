<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| core:make-module：模块骨架与 bootstrap/providers.php 的登记（在隔离的临时项目中执行）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);
});

afterEach(function (): void {
    $this->project->delete();
});

it('creates a business module and registers its provider in the modules group', function (): void {
    $this->artisan('core:make-module', ['name' => 'content'])
        ->expectsOutputToContain('app/Http/Admin/Content')
        ->assertSuccessful();

    expect($this->project->files('app/Modules'))->toBe([
        'app/Modules/Content/ContentServiceProvider.php', 'app/Modules/Content/README.md', 'app/Modules/Content/resources/dicts.php', 'app/Modules/Content/resources/menus.php',
    ])
        ->and($this->project->read('app/Modules/Content/ContentServiceProvider.php'))->toContain(
            'namespace App\Modules\Content;', 'class ContentServiceProvider extends ServiceProvider', 'Content（业务模块）',
            "public function boot(MenuRegistry \$menus, DictRegistry \$dicts): void\n    {\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'content');\n\n        \$menus->register(require __DIR__.'/resources/menus.php');\n        \$dicts->register(require __DIR__.'/resources/dicts.php');\n    }",
        )
        ->and(require $this->project->path('app/Modules/Content/resources/menus.php'))->toBe([])
        ->and(require $this->project->path('app/Modules/Content/resources/dicts.php'))->toBe([])
        ->and($this->project->read('app/Modules/Content/README.md'))->toContain('# Content', 'app/Http/Admin/Content', '--module=Content', '## 删除本模块', 'core:make-crud')
        ->and($this->project->exists('app/Http/Admin/Content'))->toBeFalse()
        ->and($this->project->read('bootstrap/providers.php'))->toContain(
            "use App\\Modules\\Content\\ContentServiceProvider;\nuse App\\Providers\\AppServiceProvider;",
            "    // 业务模块（app/Modules）\n    ContentServiceProvider::class,\n\n    // 入口",
        );
});

it('adds further modules to the same group', function (): void {
    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
    $this->artisan('core:make-module', ['name' => 'Billing'])->assertSuccessful();

    expect($this->project->read('bootstrap/providers.php'))
        ->toContain("    ContentServiceProvider::class,\n    BillingServiceProvider::class,\n\n    // 入口")
        ->and(substr_count($this->project->read('bootstrap/providers.php'), '// 业务模块'))->toBe(1);
});

it('creates a core module with --core', function (): void {
    $this->artisan('core:make-module', ['name' => 'Monitor', '--core' => true])->assertSuccessful();

    expect($this->project->exists('app/Core/Monitor/MonitorServiceProvider.php'))->toBeTrue()
        ->and($this->project->read('app/Core/Monitor/README.md'))->toContain('内核模块', '--module=Monitor --core')
        ->and($this->project->read('bootstrap/providers.php'))->toContain("    GeneratorServiceProvider::class,\n    MonitorServiceProvider::class,\n");
});

it('imports a provider with an alias when an entry has the same name', function (): void {
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

    $this->artisan('core:make-module', ['name' => 'Device'])->assertSuccessful();

    expect($this->project->read('bootstrap/providers.php'))->toContain('use App\Modules\Device\DeviceServiceProvider as ModulesDeviceServiceProvider;', '    ModulesDeviceServiceProvider::class,')
        ->and(require $this->project->path('bootstrap/providers.php'))->toContain('App\Http\Device\DeviceServiceProvider', 'App\Modules\Device\DeviceServiceProvider');
});

it('fails when the module exists and does not duplicate the registration with --force', function (): void {
    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
    $this->project->put('app/Modules/Content/README.md', 'edited');

    $this->artisan('core:make-module', ['name' => 'Content'])->expectsOutputToContain('Module already exists.')->assertFailed();

    expect($this->project->read('app/Modules/Content/README.md'))->toBe('edited');

    $this->artisan('core:make-module', ['name' => 'Content', '--force' => true])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/README.md'))->toContain('# Content')
        ->and(substr_count($this->project->read('bootstrap/providers.php'), 'ContentServiceProvider::class'))->toBe(1);
});

it('continues with core:make-crud when --crud is given', function (): void {
    $this->artisan('core:make-module', ['name' => 'Content', '--crud' => 'Article', '--soft-deletes' => true])->assertSuccessful();

    expect($this->project->exists('app/Modules/Content/Actions/RestoreArticle.php'))->toBeTrue()
        ->and($this->project->exists('app/Http/Admin/Content/Controllers/ArticleController.php'))->toBeTrue()
        ->and($this->project->read('app/Modules/Content/README.md'))->toContain('## Article（完整增删改查）', '--soft-deletes');
});

it('rejects an invalid --crud model name before writing anything', function (): void {
    $this->artisan('core:make-module', ['name' => 'Content', '--crud' => 'Content/Article'])
        ->expectsOutputToContain('模型名“Content/Article”不能包含子目录')
        ->assertFailed();

    expect($this->project->exists('app/Modules'))->toBeFalse();
});

it('asks for the name when it is missing', function (): void {
    $this->artisan('core:make-module')->expectsQuestion('模块名？', 'Content')->assertSuccessful();

    expect($this->project->exists('app/Modules/Content/ContentServiceProvider.php'))->toBeTrue();
});

it('rejects names that clash with entry directories or another kind of module', function (string $name, string $message): void {
    $this->project->put('app/Core/System/Models/User.php', '<?php');

    $this->artisan('core:make-module', ['name' => $name])->expectsOutputToContain($message)->assertFailed();

    expect($this->project->exists('app/Modules'))->toBeFalse();
})->with([
    'entry directory' => ['Requests', '公共目录'],
    'core module' => ['System', '不能同名'],
    'invalid' => ['9lives', '不合法'],
]);

it('prints the snippet to paste when bootstrap/providers.php has an unexpected format', function (): void {
    $this->project->put('bootstrap/providers.php', "<?php\n\nreturn array_merge([], []);\n");

    $this->artisan('core:make-module', ['name' => 'Content'])
        ->expectsOutputToContain('无法在 [bootstrap/providers.php] 中登记')
        ->expectsOutputToContain('use App\Modules\Content\ContentServiceProvider;')
        ->assertFailed();

    expect($this->project->exists('app/Modules/Content/ContentServiceProvider.php'))->toBeTrue();
});
