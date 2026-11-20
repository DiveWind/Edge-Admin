<?php

use App\Core\Generator\Commands\CoreGeneratorCommand;
use App\Core\Generator\GeneratorServiceProvider;
use Illuminate\Support\ServiceProvider;
use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| 所有 core:make-* 命令共同的行为：模板覆盖与发布、生成物符合 Pint
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);

    $this->artisan('core:make-module', ['name' => 'Content'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

it('prefers a stub in the project stubs directory and still normalizes its imports', function (): void {
    $this->project->put('stubs/core.action.stub', <<<'PHP'
        <?php

        namespace {{ namespace }};

        use Illuminate\Support\Str;
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Cache;

        class {{ class }}
        {
            public function handle(): string
            {
                return DB::transaction(fn (): string => Str::lower(Cache::get('{{ module }}')));
            }
        }

        PHP);

    $this->artisan('core:make-action', ['name' => 'PublishArticle', '--module' => 'Content'])->assertSuccessful();

    expect($this->project->read('app/Modules/Content/Actions/PublishArticle.php'))->toContain(
        "use Illuminate\\Support\\Facades\\{Cache, DB};\nuse Illuminate\\Support\\Str;",
        "Cache::get('Content')",
    );
});

it('publishes its stubs with stub:publish', function (): void {
    $this->artisan('stub:publish')->assertSuccessful();

    foreach (GeneratorServiceProvider::stubs() as $name => $path) {
        expect($this->project->read("stubs/core.{$name}.stub"))->toBe(file_get_contents($path));
    }

    expect(array_keys(GeneratorServiceProvider::stubs()))->toContain('action', 'model', 'module.provider', 'app.provider', 'controller.model');
});

it('does not mention the internal development steps in the stubs', function (): void {
    foreach (GeneratorServiceProvider::stubs() as $name => $path) {
        expect(preg_match('/第\s*\d+\s*步/u', (string) file_get_contents($path)))->toBe(0, "stubs/{$name}.stub");
    }
});

it('offers its stubs alone under the core-stubs publish tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(GeneratorServiceProvider::class, 'core-stubs');

    expect(array_keys($paths))->toEqualCanonicalizing(array_values(GeneratorServiceProvider::stubs()))
        ->and($paths[GeneratorServiceProvider::stubs()['action']])->toEndWith('/stubs/core.action.stub');
});

it('generates code and edits shared files in the project code style', function (): void {
    $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();
    $this->artisan('core:make-module', ['name' => 'Device'])->assertSuccessful();
    $this->artisan('core:make-module', ['name' => 'Monitor', '--core' => true])->assertSuccessful();

    $commands = [
        ['core:make-model', ['name' => 'Article', '--module' => 'Content']],
        ['core:make-model', ['name' => 'Comment', '--module' => 'Content']],
        ['core:make-model', ['name' => 'Event', '--module' => 'Monitor', '--core' => true]],
        ['core:make-action', ['name' => 'PublishArticle', '--module' => 'Content']],
        ['core:make-query', ['name' => 'ArticleStatistics', '--module' => 'Content']],
        ['core:make-enum', ['name' => 'ArticleStatus', '--module' => 'Content']],
        ['core:make-enum', ['name' => 'EventLevel', '--module' => 'Monitor', '--core' => true]],
        ['core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device']],
        ['core:make-contract', ['name' => 'DeviceStatus', '--module' => 'Device']],
        ['core:make-controller', ['name' => 'Article', '--module' => 'Content', '--model' => 'Article']],
        ['core:make-controller', ['name' => 'Event', '--module' => 'Monitor', '--core' => true, '--model' => 'Event']],
        ['core:make-controller', ['name' => 'Heartbeat', '--app' => 'device', '--module' => 'Device']],
        ['core:make-request', ['name' => 'StoreArticle', '--module' => 'Content']],
        ['core:make-resource', ['name' => 'DeviceConfig', '--app' => 'device', '--module' => 'Device']],
    ];

    foreach ($commands as [$command, $arguments]) {
        $this->artisan($command, $arguments)->assertSuccessful();
    }

    expect($this->project->pintFailures())->toBeNull();
});

it('edits the shared files of this project idempotently and in its code style', function (): void {
    $shared = ['config/core.php', 'bootstrap/providers.php', 'phpunit.xml', '.env.example'];
    $this->project->copyFromProject(...$shared);

    $this->artisan('core:make-app', ['name' => 'GeneratorProbe', '--restricted' => true])->assertSuccessful();
    $this->artisan('core:make-module', ['name' => 'GeneratorProbe'])->assertSuccessful();
    $this->artisan('core:make-model', ['name' => 'Probe', '--module' => 'GeneratorProbe'])->assertSuccessful();

    $edited = array_map($this->project->read(...), $shared);

    $this->artisan('core:make-app', ['name' => 'GeneratorProbe', '--restricted' => true, '--force' => true])->assertSuccessful();
    $this->artisan('core:make-module', ['name' => 'GeneratorProbe', '--force' => true])->assertSuccessful();

    expect(array_map($this->project->read(...), $shared))->toBe($edited)
        ->and(require $this->project->path('bootstrap/providers.php'))->toContain('App\Http\GeneratorProbe\GeneratorProbeServiceProvider', 'App\Modules\GeneratorProbe\GeneratorProbeServiceProvider')
        ->and((require $this->project->path('config/core.php'))['apps']['generator_probe']['trusted'])->toBeFalse()
        ->and($this->project->pintFailures())->toBeNull();
});

it('keeps or drops the conditional blocks of a stub, together with the lines of their markers', function (): void {
    $stub = "a\n{{#on}}\nb{{^off}} c{{/off}}\n{{/on}}\n{{^on}}\nd\n{{/on}}\n{{#off}}\ne\n{{/off}}\nf{{#unknown}} g{{/unknown}}\n";

    expect(CoreGeneratorCommand::renderSections($stub, ['on' => true, 'off' => false]))->toBe("a\nb c\nf\n")
        ->and(CoreGeneratorCommand::renderSections($stub, ['on' => false, 'off' => true]))->toBe("a\nd\ne\nf\n");
});
