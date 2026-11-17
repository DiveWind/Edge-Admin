<?php

use App\Console\Generator\{FieldParser, StubRenderer, TypeScriptImports};
use Illuminate\Support\Facades\{File, Process};
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| 代码生成器
|--------------------------------------------------------------------------
|
| 每个测试把应用的 base / app / database / lang / config 路径指向临时目录（setBasePath），
| 官方 make 命令与 edge:make-module 都写入那里；临时目录从仓库复制 stubs/、pint.json 与含锚点的登记文件，
| 前端写入 edge.generator.web_path 指向的临时目录。仓库本身不会被修改。
|
*/

/**
 * 仓库的 Pint（测试中 base_path() 指向临时目录，因此按本文件位置定位）。
 */
const PINT = __DIR__.'/../../vendor/bin/pint';

const ALL_FIELDS = 'title:string:required|max:100,body:text:nullable,price:decimal,quantity:integer,enabled:boolean,'
    .'starts_on:date:nullable,published_at:datetime:nullable,meta:json:nullable,status:status,'
    .'category_id:foreign:article_categories,source:dict:article_source,level:string:in:low,high';

beforeEach(function () {
    $this->root = storage_path('framework/testing/generator-'.Str::random(8));
    $this->base = "{$this->root}/server";
    $this->web = "{$this->root}/web";

    File::copyDirectory(base_path('stubs'), "{$this->base}/stubs");

    foreach (['pint.json', 'config/permissions.php', 'routes/api.php', 'lang/zh_CN/operations.php', 'lang/en/operations.php'] as $file) {
        File::ensureDirectoryExists(dirname("{$this->base}/{$file}"));
        File::copy(base_path($file), "{$this->base}/{$file}");
    }

    foreach (['app/Models', 'database/migrations', 'database/factories', 'tests/Feature'] as $directory) {
        File::ensureDirectoryExists("{$this->base}/{$directory}");
    }

    File::ensureDirectoryExists("{$this->web}/config");
    File::ensureDirectoryExists("{$this->web}/src");
    File::put("{$this->web}/config/routes.ts", "const routes = [\n  // edge:routes\n];\n\nexport default routes;\n");
    File::put("{$this->web}/src/access.ts", "export type PermissionCode =\n  | 'dashboard.view'\n  // edge:permission-codes\n  | never;\n");

    foreach (['zh-CN', 'en-US'] as $locale) {
        File::ensureDirectoryExists("{$this->web}/src/locales/{$locale}/pages");
        File::put("{$this->web}/src/locales/{$locale}.ts", "import menu from './{$locale}/menu';\n// edge:locale-imports\n\nexport default {\n  ...menu,\n  // edge:locale-spread\n};\n");
    }

    [$storage, $bootstrap] = [storage_path(), $this->app->bootstrapPath()];
    $this->app->setBasePath($this->base);
    $this->app->useAppPath("{$this->base}/app")
        ->useConfigPath("{$this->base}/config")
        ->useDatabasePath("{$this->base}/database")
        ->useLangPath("{$this->base}/lang")
        ->useStoragePath($storage)
        ->useBootstrapPath($bootstrap);
    $this->app->forgetInstance('migration.creator');

    config(['edge.generator.web_path' => $this->web]);

    // 与真实项目一样，刚生成的类可被自动加载（make:controller --model 会检查模型类是否存在）
    $this->autoload = function (string $class): void {
        foreach (['App\\' => "{$this->base}/app/", 'Database\\Factories\\' => "{$this->base}/database/factories/"] as $prefix => $directory) {
            $file = $directory.str_replace('\\', '/', Str::after($class, $prefix)).'.php';

            if (str_starts_with($class, $prefix) && is_file($file)) {
                require_once $file;
            }
        }
    };
    spl_autoload_register($this->autoload);
});

afterEach(function () {
    spl_autoload_unregister($this->autoload);
    File::deleteDirectory($this->root);
});

/**
 * 临时目录中的全部文件（相对临时根目录）=> 内容。
 *
 * @return array<string, string>
 */
function generatedFiles(string $root): array
{
    return collect(File::allFiles($root))
        ->reject(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), '/stubs/'))
        ->mapWithKeys(fn (SplFileInfo $file): array => [Str::after($file->getPathname(), $root.'/') => File::get($file->getPathname())])
        ->all();
}

/**
 * 在临时目录中对指定文件执行 `pint --test`（使用复制过去的 pint.json）。
 *
 * @param  list<string>  $paths
 */
function pintPasses(string $base, array $paths): bool
{
    return Process::path($base)->run([PINT, '--test', "--config={$base}/pint.json", ...$paths])->successful();
}

describe('official make commands with the published stubs', function () {
    test('generate skeletons that follow the project conventions', function () {
        $this->artisan('make:model', ['name' => 'Foo', '-m' => true, '-f' => true])->assertSuccessful();
        $this->artisan('make:controller', ['name' => 'Api/Demo/FooController', '--api' => true, '--model' => 'Foo', '--requests' => true])->assertSuccessful();
        $this->artisan('make:policy', ['name' => 'FooPolicy', '--model' => 'Foo'])->assertSuccessful();
        $this->artisan('make:resource', ['name' => 'FooResource'])->assertSuccessful();

        $files = generatedFiles($this->base);

        expect($files['app/Models/Foo.php'])
            ->toContain('#[Fillable([])]')
            ->toContain('use HasFactory, StoresDatesInAppTimezone;')
            ->toContain('protected function casts(): array')
            ->not->toContain('$fillable');
        expect($files['app/Http/Requests/StoreFooRequest.php'])->not->toContain('function authorize(')->toContain('public function rules(): array');
        expect($files['app/Policies/FooPolicy.php'])->toContain('public function delete(User $actor, Foo $foo): Response')->not->toContain('viewAny');
        expect($files['app/Http/Controllers/Api/Demo/FooController.php'])
            ->toContain('public function store(StoreFooRequest $request): FooResource')
            ->toContain('public function destroy(Foo $foo): Response');
        expect($files['app/Http/Resources/FooResource.php'])->toContain("return [\n            //\n        ];");

        foreach (array_keys($files) as $path) {
            if (str_ends_with($path, '.php')) {
                expect(Process::run(['php', '-l', "{$this->base}/{$path}"])->successful())->toBeTrue("{$path} 存在语法错误");
            }
        }

        // 同命名空间的导入需由 `composer format`（Pint 两遍）合并成 use A\{B, C}; 之后整体通过 pint --test
        Process::path($this->base)->run([PINT, '--no-config', '--preset=laravel', '--quiet', 'app', 'database']);
        Process::path($this->base)->run([PINT, '--quiet', "--config={$this->base}/pint.json", 'app', 'database']);

        expect(pintPasses($this->base, ['app', 'database']))->toBeTrue();
    });

    test('project make commands behave like the official generators', function () {
        $this->artisan('make:index-request', ['name' => 'Demo/FooIndexRequest'])->assertSuccessful();
        $this->artisan('make:option-controller', ['name' => 'FooOptionController'])->assertSuccessful();

        expect(File::get("{$this->base}/app/Http/Requests/Demo/FooIndexRequest.php"))
            ->toContain('namespace App\Http\Requests\Demo;')
            ->toContain('class FooIndexRequest extends ListRequest');
        expect(File::get("{$this->base}/app/Http/Controllers/Api/Options/FooOptionController.php"))
            ->toContain('use App\Models\Foo;')
            ->toContain("Foo::query()->orderBy('id')->get(['id', 'name'])");

        // 已存在时与官方命令一样报错且不覆盖；--force 覆盖；stubs/ 中的模板即生成依据
        File::put("{$this->base}/stubs/index-request.stub", "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n");
        $this->artisan('make:index-request', ['name' => 'Demo/FooIndexRequest'])->expectsOutputToContain('already exists');
        expect(File::get("{$this->base}/app/Http/Requests/Demo/FooIndexRequest.php"))->toContain('extends ListRequest');

        $this->artisan('make:index-request', ['name' => 'Demo/FooIndexRequest', '--force' => true])->assertSuccessful();
        expect(File::get("{$this->base}/app/Http/Requests/Demo/FooIndexRequest.php"))->toBe("<?php\n\nnamespace App\Http\Requests\Demo;\n\nclass FooIndexRequest {}\n");
    });
});

test('it orchestrates the make commands into a complete backend and frontend module', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => ALL_FIELDS, '--data-scope' => true, '--soft-deletes' => true])
        ->expectsOutputToContain('make:model')
        ->expectsOutputToContain('make:controller')
        ->assertSuccessful();

    $files = generatedFiles($this->root);

    expect(array_keys($files))->toContain(
        'server/app/Models/Ticket.php',
        'server/database/factories/TicketFactory.php',
        'server/app/Http/Requests/Crm/TicketIndexRequest.php',
        'server/app/Http/Requests/Crm/StoreTicketRequest.php',
        'server/app/Http/Requests/Crm/UpdateTicketRequest.php',
        'server/app/Http/Resources/TicketResource.php',
        'server/app/Policies/TicketPolicy.php',
        'server/app/Http/Controllers/Api/Crm/TicketController.php',
        'server/tests/Feature/TicketTest.php',
        'web/src/pages/crm/tickets/index.tsx',
        'web/src/pages/crm/tickets/service.ts',
        'web/src/pages/crm/tickets/data.d.ts',
        'web/src/locales/zh-CN/pages/crm-tickets.ts',
        'web/src/locales/en-US/pages/crm-tickets.ts',
    );

    $migration = collect($files)->first(fn (string $content, string $path): bool => str_ends_with($path, '_create_tickets_table.php'));
    expect($migration)
        ->toContain("\$table->string('title', 100);")
        ->toContain("\$table->text('body')->nullable();")
        ->toContain("\$table->foreignId('category_id')->constrained('article_categories')->restrictOnDelete();")
        ->toContain("\$table->string('status', 20)->default('active')->index();")
        ->toContain("\$table->foreignId('department_id')->nullable()->index();")
        ->toContain("\$table->timestamps();\n            \$table->softDeletes();");

    // 补全代码里的完整类名已被 Pint 导入并分组
    expect($files['server/app/Models/Ticket.php'])
        ->toContain('use App\Models\Concerns\{HasDataScope, StoresDatesInAppTimezone};')
        ->toContain('use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};')
        ->toContain('use HasDataScope, HasFactory, SoftDeletes, StoresDatesInAppTimezone;')
        ->toContain("#[Fillable(['title', 'body', 'price', 'quantity', 'enabled', 'starts_on', 'published_at', 'meta', 'status', 'category_id', 'source', 'level'])]")
        ->toContain("'status' => Status::class,")
        ->toContain("'meta' => 'array',")
        ->toContain("'created_by' => 'integer',")
        ->toContain('public function category(): BelongsTo')
        ->toContain('protected function filter(Builder $query, array $filters): void')
        ->not->toContain('\\App\\');
    expect($files['server/database/factories/TicketFactory.php'])->toContain('use App\Models\{ArticleCategory, Ticket};')->toContain("'category_id' => ArticleCategory::factory(),");

    expect($files['server/app/Http/Requests/Crm/StoreTicketRequest.php'])
        ->toContain("'title' => ['required', 'string', 'max:100'],")
        ->toContain("'category_id' => ['required', 'integer', Rule::exists('article_categories', 'id')],")
        ->toContain("'source' => ['required', 'string', new InDict('article_source')],")
        ->toContain("'level' => ['required', 'string', 'max:255', 'in:low,high'],")
        ->toContain("'starts_on' => ['nullable', Rule::date()],")
        ->toContain('use App\Rules\InDict;')
        ->not->toContain('function authorize(');
    expect($files['server/app/Http/Requests/Crm/TicketIndexRequest.php'])->toContain("'starts_on' => ['nullable', Rule::date()],")->toContain("protected array \$sortable = ['id', 'price', 'quantity', 'starts_on', 'published_at', 'created_at'];");
    expect($files['server/app/Http/Resources/TicketResource.php'])
        ->toContain('@mixin Ticket')
        ->toContain("'id' => \$this->id,")
        ->toContain("'starts_on' => \$this->starts_on?->toDateString(),")
        ->toContain("'updated_at' => \$this->updated_at?->toIso8601String(),");

    expect($files['server/app/Http/Controllers/Api/Crm/TicketController.php'])
        ->toContain('use App\Http\Requests\Crm\{StoreTicketRequest, TicketIndexRequest, UpdateTicketRequest};')
        ->toContain("#[Authorize('crm.ticket.view')]\n    #[Authorize('view', 'ticket')]")
        ->toContain("#[Authorize('crm.ticket.delete')]\n    #[Authorize('delete', 'ticket')]")
        ->toContain('->visibleTo($actor)')
        ->not->toContain('{{')
        ->not->toContain('Gate');
    expect($files['server/app/Policies/TicketPolicy.php'])
        ->toContain('public function view(User $actor, Ticket $ticket): Response')
        ->toContain('return $ticket->isVisibleTo($actor) ? Response::allow() : Response::deny();');

    expect($files['server/config/permissions.php'])
        ->toContain("'code' => 'crm',")
        ->toContain("'code' => 'crm.ticket',")
        ->toContain("'path' => '/crm/tickets',")
        ->toContain("'type' => 'directory',\n        'path' => '/crm',\n        'icon' => 'AppstoreOutlined',")
        ->toContain('// edge:permissions:crm');
    expect($files['server/routes/api.php'])
        ->toContain("'tickets' => TicketController::class,\n        // edge:resources")
        ->toContain("'ticket',\n    // edge:route-keys")
        ->toContain('use App\Http\Controllers\Api\Crm\TicketController;')
        ->not->toContain('can:');
    expect($files['server/lang/zh_CN/operations.php'])->toContain("'tickets.store' => '新增Ticket',");
    expect($files['server/lang/en/operations.php'])->toContain("'tickets.destroy' => 'Delete Ticket',");

    expect($files['web/config/routes.ts'])->toContain("path: '/crm/tickets',")->toContain("locale: 'permission.crm.ticket',")->toContain('// edge:routes:crm');
    expect($files['web/src/access.ts'])->toContain("| 'crm.ticket.delete'");
    expect($files['web/src/locales/en-US.ts'])->toContain("import crmTickets from './en-US/pages/crm-tickets';")->toContain('...crmTickets,');
    expect($files['web/src/pages/crm/tickets/data.d.ts'])->toContain('starts_on: string | null;')->toContain('department_id: API.Id | null;');

    // 前端：导入按实际使用的控件生成（无未使用导入），字段规则下发为前端校验
    $index = $files['web/src/pages/crm/tickets/index.tsx'];
    $form = $files['web/src/pages/crm/tickets/components/TicketForm.tsx'];
    expect($index)
        ->toStartWith("import { PlusOutlined } from '@ant-design/icons';")
        ->toContain("import TicketForm from './components/TicketForm';")
        ->toContain("import { booleanTransform, tableRequest } from '@/utils/table';")
        ->toContain("request: () => fetchSelectOptions('article-categories'),")
        ->not->toContain('ProFormText');
    expect($form)
        ->toContain("import type { Ticket, TicketFormValues } from '../data';")
        ->toContain('toOffsetDateTime,')
        ->toContain("fieldProps={{ maxLength: 100 }}\n        rules={[{ required: true }]}")
        ->toContain('transform={(value: unknown) => ({ starts_on: value ?? null })}')
        ->not->toContain('booleanTransform');
    expect(substr_count($form, "from '@ant-design/pro-components';"))->toBe(2);
    expect(preg_match_all('/^import /m', $form))->toBe(substr_count($form, 'import '));

    expect($files['server/tests/Feature/TicketTest.php'])
        ->toContain("->assertJsonPath('data.published_at', CarbonImmutable::parse('2026-01-01T08:00:00+00:00')")
        ->toContain("\$this->assertSoftDeleted('tickets', ['id' => \$id]);");

    $php = array_values(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, 'server/') && str_ends_with($path, '.php')));

    foreach ($php as $path) {
        expect(Process::run(['php', '-l', "{$this->root}/{$path}"])->successful())->toBeTrue("{$path} 存在语法错误");
    }

    expect(pintPasses($this->base, array_map(fn (string $path): string => Str::after($path, 'server/'), $php)))->toBeTrue();

    foreach ($files as $path => $content) {
        expect($content)->not->toMatch('/\{\{\s*[a-z_.]+\s*\}\}/', "{$path} 残留模板占位符");
    }
});

test('the generated menu node shows up in the dynamic menu after permission:sync', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => 'name:string'])->assertSuccessful();

    config()->set('permissions', require "{$this->base}/config/permissions.php");
    $this->artisan('permission:sync')->assertSuccessful();
    actingAsUser(superAdmin());

    $crm = collect($this->getJson('/api/auth/menus')->assertOk()->json('data'))->firstWhere('code', 'crm');

    expect($crm)->toMatchArray(['type' => 'directory', 'path' => '/crm', 'icon' => 'AppstoreOutlined'])
        ->and($crm['children'][0])->toMatchArray(['code' => 'crm.ticket', 'type' => 'menu', 'path' => '/crm/tickets', 'name' => 'Tickets']);
});

test('--with-options generates an option endpoint and foreign fields without one get a TODO', function () {
    $this->artisan('edge:make-module', ['name' => 'Tag', '--module' => 'content', '--fields' => 'name:string,sort:integer,status:status', '--with-options' => true])
        ->assertSuccessful();
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => 'name:string,tag_id:foreign:tags,owner_id:foreign:accounts:nullable'])
        ->expectsOutputToContain('外键 owner_id 没有可用的选项接口 /api/options/accounts')
        ->assertSuccessful();

    $files = generatedFiles($this->root);

    expect($files['server/app/Http/Controllers/Api/Options/TagOptionController.php'])
        ->toContain("Tag::query()->where('status', Status::Active)->orderBy('sort')->orderBy('id')")
        ->toContain('use App\Enums\Status;');
    expect($files['server/routes/api.php'])
        ->toContain("Route::get('tags', TagOptionController::class)->name('tags');\n        // edge:options")
        ->toMatch('/^use App\\\\Http\\\\Controllers\\\\Api\\\\Options\\\\\{[^}]*TagOptionController[^}]*\};$/m');
    expect($files['server/tests/Feature/TagTest.php'])->toContain("test('tag options can be listed by any user'");

    expect($files['web/src/pages/crm/tickets/components/TicketForm.tsx'])
        ->toContain("request={() => fetchSelectOptions('tags')}")
        ->toContain('// TODO 后端没有 /api/options/accounts')
        ->toContain('request={async () => []}');
    expect($files['web/src/locales/en-US/pages/crm-tickets.ts'])->toContain("'pages.crm.tickets.ownerId': 'Owner',");
});

test('--with-options requires a name field', function () {
    $this->artisan('edge:make-module', ['name' => 'Tag', '--module' => 'content', '--fields' => 'title:string', '--with-options' => true])
        ->expectsOutputToContain('--with-options 需要模块包含 name 字段')
        ->assertFailed();
});

test('running the generator twice does not duplicate anything', function () {
    $arguments = ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string,status:status'];

    $this->artisan('edge:make-module', $arguments)->assertSuccessful();
    $first = generatedFiles($this->root);

    $this->artisan('edge:make-module', $arguments)
        ->expectsOutputToContain('已存在，跳过（--force 覆盖）：app/Models/Ticket.php')
        ->expectsOutputToContain('没有需要写入的文件')
        ->assertSuccessful();

    expect(generatedFiles($this->root))->toBe($first)
        ->and(substr_count($first['server/routes/api.php'], "'tickets' =>"))->toBe(1)
        ->and(substr_count($first['server/routes/api.php'], "'ticket',"))->toBe(1)
        ->and(substr_count($first['server/config/permissions.php'], "'code' => 'content.ticket'"))->toBe(1)
        ->and(substr_count($first['server/config/permissions.php'], "'code' => 'content',"))->toBe(1);
});

test('--force regenerates files through the make commands and keeps the migration file name', function () {
    $arguments = ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string'];
    $this->artisan('edge:make-module', $arguments)->assertSuccessful();
    $migration = File::glob("{$this->base}/database/migrations/*_create_tickets_table.php");
    File::put("{$this->base}/app/Models/Ticket.php", 'changed');

    $this->artisan('edge:make-module', [...$arguments, '--force' => true])->assertSuccessful();

    expect(File::get("{$this->base}/app/Models/Ticket.php"))->toContain('class Ticket extends Model')->toContain("#[Fillable(['name'])]")
        ->and(substr_count(File::get("{$this->base}/routes/api.php"), "'tickets' =>"))->toBe(1)
        ->and(File::glob("{$this->base}/database/migrations/*_create_tickets_table.php"))->toBe($migration);
});

test('--dry-run lists the planned make commands and writes nothing', function () {
    $before = generatedFiles($this->root);

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string', '--dry-run' => true])
        ->expectsOutputToContain('app/Models/Ticket.php')
        ->expectsOutputToContain('make:controller')
        ->expectsOutputToContain('--dry-run')
        ->assertSuccessful();

    expect(generatedFiles($this->root))->toBe($before);
});

test('frontend generation can be disabled or is skipped when the web directory is missing', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string', '--no-web' => true])->assertSuccessful();
    expect(File::exists("{$this->web}/src/pages/content/tickets/index.tsx"))->toBeFalse();

    config(['edge.generator.web_path' => "{$this->root}/missing-web"]);
    $this->artisan('edge:make-module', ['name' => 'Coupon', '--module' => 'content', '--fields' => 'name:string'])
        ->expectsOutputToContain('不存在，已跳过前端代码')
        ->assertSuccessful();
    expect(File::exists("{$this->base}/app/Models/Coupon.php"))->toBeTrue();
});

test('missing anchors are reported instead of guessed', function () {
    File::put("{$this->web}/src/access.ts", "export type PermissionCode = 'dashboard.view';\n");

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string'])
        ->expectsOutputToContain('未找到锚点 // edge:permission-codes')
        ->assertSuccessful();

    expect(File::get("{$this->web}/src/access.ts"))->toBe("export type PermissionCode = 'dashboard.view';\n");
});

test('customized stubs are used by the make commands, and missing fill slots are reported with the code to add', function () {
    File::put("{$this->base}/stubs/edge/fragments/rules.string.stub", "            '{{ field.name }}' => ['custom', 'rule'],\n");
    File::put("{$this->base}/stubs/model.stub", str_replace('#[Fillable([])]', '#[Fillable([\'legacy\'])]', File::get("{$this->base}/stubs/model.stub")));

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string,quantity:integer', '--no-web' => true])
        ->expectsOutputToContain('app/Models/Ticket.php：模板中未找到 #[Fillable([])]')
        ->expectsOutputToContain("#[Fillable(['name', 'quantity'])]")
        ->assertSuccessful();

    expect(File::get("{$this->base}/app/Models/Ticket.php"))->toContain("#[Fillable(['legacy'])]");
    expect(File::get("{$this->base}/app/Http/Requests/Content/StoreTicketRequest.php"))
        ->toContain("'name' => ['custom', 'rule'],")
        ->toContain("'quantity' => ['required', 'integer'],");
});

test('missing input is asked for interactively with Laravel Prompts', function () {
    $this->artisan('edge:make-module')
        ->expectsQuestion('模型名（单数 StudlyCase）', 'Ticket')
        ->expectsQuestion('所属模块（权限码前缀 / 命名空间 / 菜单分组）', 'crm')
        ->expectsQuestion('字段定义（name:type[:参数][:rules]，逗号分隔）', 'name:string,status:status')
        ->expectsChoice('需要哪些可选能力？', ['soft-deletes', 'with-options'], [
            'data-scope' => '数据权限（department_id / created_by）',
            'soft-deletes' => '软删除',
            'with-options' => '选项接口 GET /api/options/<路由>',
            'web' => '生成前端页面',
        ])
        ->assertSuccessful();

    expect(File::get("{$this->base}/app/Models/Ticket.php"))->toContain('SoftDeletes')
        ->and(File::exists("{$this->base}/app/Http/Controllers/Api/Options/TicketOptionController.php"))->toBeTrue()
        ->and(File::exists("{$this->web}/src/pages/crm/tickets/index.tsx"))->toBeFalse();
});

test('invalid definitions fail with a message', function (array $arguments, string $message) {
    $this->artisan('edge:make-module', ['name' => 'Ticket', ...$arguments, '--no-interaction' => true])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(File::exists("{$this->base}/app/Models/Ticket.php"))->toBeFalse();
})->with([
    'missing module' => [['--fields' => 'name:string'], '--module'],
    'missing fields' => [['--module' => 'content'], '--fields'],
    'unknown type' => [['--module' => 'content', '--fields' => 'name:uuid'], '类型 [uuid] 不支持'],
    'foreign without table' => [['--module' => 'content', '--fields' => 'user_id:foreign'], '需要参数'],
    'reserved field' => [['--module' => 'content', '--fields' => 'created_by:integer'], '自动维护'],
    'bad module' => [['--module' => 'Content', '--fields' => 'name:string'], '--module'],
]);

describe('template rendering', function () {
    test('conditional blocks are evaluated from the inside out and drop standalone tag lines', function () {
        $template = "a\n{{#if outer}}\nb {{#unless inner}}c{{else}}d{{/unless}}\n{{/if}}\n{{#if missing}}\ne\n{{/if}}\nf";

        expect(StubRenderer::conditionals($template, ['outer' => 'true', 'inner' => '']))->toBe("a\nb c\nf")
            ->and(StubRenderer::conditionals($template, ['outer' => 'true', 'inner' => 'true']))->toBe("a\nb d\nf")
            ->and(StubRenderer::conditionals($template, ['outer' => null]))->toBe("a\nf");
    });

    test('typescript imports anywhere in the output are merged, deduplicated and moved to the top', function () {
        $output = TypeScriptImports::organize(implode("\n", [
            "import { useState } from 'react';",
            "import type React from 'react';",
            '',
            'const columns = [',
            "import { b } from './local';",
            "import { useRef, useState } from 'react';",
            "import { z } from '@/utils/z';",
            '];',
        ]));

        expect($output)->toBe(implode("\n", [
            "import type React from 'react';",
            "import { useRef, useState } from 'react';",
            "import { z } from '@/utils/z';",
            "import { b } from './local';",
            '',
            'const columns = [',
            '];',
        ]));
    });

    test('the field DSL keeps commas inside rules', function () {
        $fields = FieldParser::parse('level:string:required|in:low,mid,high,owner_id:foreign:users:nullable');

        expect($fields)->toHaveCount(2)
            ->and($fields[0]->rules)->toBe(['required', 'in:low,mid,high'])
            ->and($fields[1]->argument)->toBe('users')
            ->and($fields[1]->isNullable())->toBeTrue();
    });
});
