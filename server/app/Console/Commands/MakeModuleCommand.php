<?php

namespace App\Console\Commands;

use App\Console\Generator\{FieldParser, ModuleBlueprint, SourceFile, StubRenderer, TextInserter, TypeScriptImports};
use Closure;
use Composer\Autoload\ClassLoader;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

/**
 * 业务模块生成器：编排官方 make 命令生成骨架（命名空间、路径、stub 解析全走官方逻辑，模板为 stubs/*.stub），
 * 再补全字段相关代码、登记权限 / 路由 / 操作日志并生成前端页面。用法与模板约定见 docs/generator.md。
 */
#[Signature('edge:make-module
    {name : 模型名（单数，StudlyCase，如 Tag）}
    {--module= : 所属模块，决定权限码前缀、命名空间与菜单分组（如 content）}
    {--fields= : 字段定义 name:type[:参数][:rules]，逗号分隔}
    {--data-scope : 接入数据权限（HasDataScope，增加 department_id / created_by）}
    {--soft-deletes : 启用软删除}
    {--with-options : 同时生成选项接口 GET /api/options/<路由>（启用项 [{id, name}]，需要 name 字段）}
    {--no-web : 不生成前端代码}
    {--force : 覆盖已存在的文件}
    {--dry-run : 只打印将要创建 / 修改的文件，不写入}')]
#[Description('生成一个完整的业务模块：调用官方 make 命令生成模型 / 迁移 / 工厂 / 请求 / 资源 / Policy / 控制器 / 测试并补全字段，登记菜单权限、路由、操作日志与前端页面')]
class MakeModuleCommand extends Command implements PromptsForMissingInput
{
    /**
     * 官方 / 项目 make 命令生成的文件：[说明, 命令, 参数, 目标路径, 补全回调, 动作]。动作为 create / overwrite。
     *
     * @var list<array{command: string, arguments: array<string, mixed>, path: string, patch: Closure(SourceFile): SourceFile, action: string}>
     */
    protected array $artifacts = [];

    /**
     * 登记类修改与前端文件：路径 => [动作, 新内容]。动作为 create / overwrite / update。
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $changes = [];

    /**
     * 跳过或需要手动处理的事项：[说明, 需手动粘贴的代码片段]。
     *
     * @var list<array{0: string, 1: string|null}>
     */
    protected array $notes = [];

    protected Filesystem $files;

    protected StubRenderer $stubs;

    protected ModuleBlueprint $blueprint;

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        [$this->files, $this->artifacts, $this->changes, $this->notes] = [$files, [], [], []];
        $this->stubs = new StubRenderer(base_path('stubs/edge'));

        try {
            $this->blueprint = new ModuleBlueprint(
                name: (string) $this->argument('name'),
                module: (string) ($this->option('module') ?: throw new InvalidArgumentException('请通过 --module 指定所属模块（如 content）。')),
                fields: FieldParser::parse((string) $this->option('fields')),
                dataScope: (bool) $this->option('data-scope'),
                softDeletes: (bool) $this->option('soft-deletes'),
                withOptions: (bool) $this->option('with-options'),
            );

            $this->resolveOptionRoutes();
            $this->planArtifacts();
            $this->planRegistrations();
            $this->planWeb();
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->report();
            $this->components->warn('--dry-run：未写入任何文件，也未调用 make 命令。');

            return self::SUCCESS;
        }

        try {
            $this->makeArtifacts();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($this->changes as $path => [, $content]) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $content);
        }

        $this->report();

        if ($this->artifacts !== [] || $this->changes !== []) {
            $this->formatPhpFiles();
            $this->formatWebFiles();
            $this->nextSteps();
        }

        return self::SUCCESS;
    }

    /**
     * 后端文件全部由 make 命令创建（stubs/ 下的模板），这里只决定调用哪些命令、以及创建后如何补全。
     */
    protected function planArtifacts(): void
    {
        $class = $this->blueprint->class;
        $module = $this->blueprint->moduleStudly();
        $table = $this->blueprint->table();

        $this->artifact('make:model', ['name' => $class], app_path("Models/{$class}.php"), $this->patchModel(...));
        $this->artifact('make:factory', ['name' => "{$class}Factory", '--model' => $class], database_path("factories/{$class}Factory.php"), $this->patchFactory(...));
        $this->artifact('make:migration', ['name' => "create_{$table}_table", '--create' => $table], $this->migrationPath(), $this->patchMigration(...));
        $this->artifact('make:policy', ['name' => "{$class}Policy", '--model' => $class], app_path("Policies/{$class}Policy.php"), $this->patchPolicy(...));
        $this->artifact('make:request', ['name' => "{$module}/Store{$class}Request"], app_path("Http/Requests/{$module}/Store{$class}Request.php"), $this->patchRules(...));
        $this->artifact('make:request', ['name' => "{$module}/Update{$class}Request"], app_path("Http/Requests/{$module}/Update{$class}Request.php"), $this->patchRules(...));
        $this->artifact('make:index-request', ['name' => "{$module}/{$class}IndexRequest"], app_path("Http/Requests/{$module}/{$class}IndexRequest.php"), $this->patchIndexRequest(...));
        $this->artifact('make:resource', ['name' => "{$class}Resource"], app_path("Http/Resources/{$class}Resource.php"), $this->patchResource(...));
        $this->artifact('make:controller', [
            'name' => $this->blueprint->controllerName(),
            '--model' => $class,
            '--type' => $this->blueprint->dataScope ? 'module.data-scope' : 'module',
        ], app_path("Http/Controllers/Api/{$module}/{$class}Controller.php"), $this->patchController(...));

        if ($this->blueprint->withOptions) {
            $this->artifact('make:option-controller', ['name' => "{$class}OptionController", '--model' => $class], app_path("Http/Controllers/Api/Options/{$class}OptionController.php"), $this->patchOptionController(...));
        }

        $this->artifact('make:test', ['name' => "{$class}Test", '--pest' => true], base_path("tests/Feature/{$class}Test.php"), $this->patchTest(...));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  Closure(SourceFile): SourceFile  $patch
     */
    protected function artifact(string $command, array $arguments, string $path, Closure $patch): void
    {
        $exists = $this->files->exists($path);

        if ($exists && ! $this->option('force')) {
            $this->notes[] = ["已存在，跳过（--force 覆盖）：{$this->relative($path)}", null];

            return;
        }

        $this->artifacts[] = ['command' => $command, 'arguments' => $arguments, 'path' => $path, 'patch' => $patch, 'action' => $exists ? 'overwrite' : 'create'];
    }

    /**
     * 依次调用 make 命令（--force 时传给支持它的命令），再补全生成的骨架。
     *
     * @throws RuntimeException
     */
    protected function makeArtifacts(): void
    {
        foreach ($this->artifacts as $artifact) {
            ['command' => $command, 'arguments' => $arguments, 'path' => $path] = $artifact;

            $before = $command === 'make:migration' ? $this->migrations() : [];
            $force = [];

            // 覆盖：支持 --force 的命令直接传；不支持的（make:factory）先删除旧文件；迁移见下方沿用原文件名
            if ($artifact['action'] === 'overwrite' && $command !== 'make:migration') {
                if ($this->getApplication()?->find($command)->getDefinition()->hasOption('force')) {
                    $force = ['--force' => true];
                } else {
                    $this->files->delete($path);
                }
            }

            if ($this->callSilently($command, [...$arguments, ...$force, '--no-interaction' => true]) !== self::SUCCESS) {
                throw new RuntimeException("{$command} 执行失败。");
            }

            if ($command === 'make:migration') {
                $created = array_values(array_diff($this->migrations(), $before))[0] ?? throw new RuntimeException('make:migration 没有生成迁移文件。');

                // 覆盖已有迁移时沿用原文件名，避免已执行过的迁移被当作新迁移。
                if ($created !== $path) {
                    $this->files->move($created, $path);
                }
            }

            $this->files->exists($path) || throw new RuntimeException("{$command} 没有在预期位置生成文件：{$this->relative($path)}");

            $file = ($artifact['patch'])(new SourceFile($path, $this->files->get($path)));
            $this->files->put($path, $file->contents());

            foreach ($file->misses() as [$description, $snippet]) {
                $this->notes[] = ["{$this->relative($path)}：模板中未找到 {$description}（stubs/ 被定制过？），请手动补充：", $snippet];
            }
        }
    }

    protected function migrationPath(): string
    {
        $table = $this->blueprint->table();

        return $this->files->glob(database_path("migrations/*_create_{$table}_table.php"))[0]
            ?? database_path('migrations/'.now()->format('Y_m_d_His')."_create_{$table}_table.php");
    }

    /**
     * @return list<string>
     */
    protected function migrations(): array
    {
        return array_values(array_map(strval(...), $this->files->glob(database_path('migrations/*.php'))));
    }

    protected function patchModel(SourceFile $file): SourceFile
    {
        $variables = $this->blueprint->variables();
        $traits = [
            ...($this->blueprint->dataScope ? ['App\Models\Concerns\HasDataScope'] : []),
            ...($this->blueprint->softDeletes ? ['Illuminate\Database\Eloquent\SoftDeletes'] : []),
        ];

        $file->replace('#[Fillable([])]', "#[Fillable([{$variables['fillable']}])]", '#[Fillable([])]')
            ->fill('function casts()', [
                ...$this->fragments('cast'),
                ...($this->blueprint->dataScope ? ["            'department_id' => 'integer',", "            'created_by' => 'integer',"] : []),
            ], 'casts() 中的 // 占位行');

        if ($traits !== []) {
            // trait 名不会被 Pint 自动导入，这里显式导入
            $file->addImports($traits, 'use 导入')
                ->replace('use HasFactory, StoresDatesInAppTimezone;', 'use HasFactory, StoresDatesInAppTimezone, '.implode(', ', array_map(class_basename(...), $traits)).';', 'trait 声明 use HasFactory, StoresDatesInAppTimezone;');
        }

        return $file->appendToClass(implode("\n", $this->fragments('relation'))."\n".$this->stubs->render('model_filter', $variables, $this->blueprint->fields), 'filter() 作用域与关联');
    }

    protected function patchFactory(SourceFile $file): SourceFile
    {
        return $file->fill('function definition()', [
            ...$this->fragments('factory'),
            ...($this->blueprint->dataScope ? ["            'department_id' => null,", "            'created_by' => null,"] : []),
        ], 'definition() 中的 // 占位行');
    }

    protected function patchMigration(SourceFile $file): SourceFile
    {
        $file->insertAfter('$table->id();', [
            ...$this->fragments('migration'),
            ...($this->blueprint->dataScope ? [
                "            \$table->foreignId('department_id')->nullable()->index();",
                "            \$table->foreignId('created_by')->nullable()->index();",
            ] : []),
        ], '$table->id();');

        return $this->blueprint->softDeletes
            ? $file->insertAfter('$table->timestamps();', ['            $table->softDeletes();'], '$table->timestamps();')
            : $file;
    }

    /**
     * 数据权限：单条读写与列表 visibleTo() 规则一致（isVisibleTo）。
     */
    protected function patchPolicy(SourceFile $file): SourceFile
    {
        if (! $this->blueprint->dataScope) {
            return $file;
        }

        $variable = $this->blueprint->variables()['variable'];
        $visible = "return \${$variable}->isVisibleTo(\$actor) ? Response::allow() : Response::deny();";

        return $file->replace('return Response::allow();', $visible, 'return Response::allow();')
            ->insertBefore('public function update(', implode("\n", [
                "    public function view(\\App\\Models\\User \$actor, \\App\\Models\\{$this->blueprint->class} \${$variable}): Response",
                '    {',
                "        {$visible}",
                '    }',
                '',
            ]), 'public function update(');
    }

    protected function patchRules(SourceFile $file): SourceFile
    {
        return $file->fill('function rules()', $this->fragments('rules'), 'rules() 中的 // 占位行');
    }

    protected function patchIndexRequest(SourceFile $file): SourceFile
    {
        return $file->replace("protected array \$sortable = ['id', 'created_at'];", "protected array \$sortable = [{$this->blueprint->variables()['sortable']}];", '$sortable 声明')
            ->fill('function filterRules()', $this->fragments('filter_rule'), 'filterRules() 中的 // 占位行');
    }

    protected function patchResource(SourceFile $file): SourceFile
    {
        return $file->addClassDocTag("@mixin \\App\\Models\\{$this->blueprint->class}")
            ->fill('function toArray(', [
                "            'id' => \$this->id,",
                ...$this->fragments('resource'),
                ...($this->blueprint->dataScope ? ["            'department_id' => \$this->department_id,", "            'created_by' => \$this->created_by,"] : []),
                "            'created_at' => \$this->created_at?->toIso8601String(),",
                "            'updated_at' => \$this->updated_at?->toIso8601String(),",
            ], 'toArray() 中的 // 占位行');
    }

    /**
     * stubs/controller.module(.data-scope).stub 中官方命令不认识的占位符在这里替换。
     */
    protected function patchController(SourceFile $file): SourceFile
    {
        foreach ([
            '{{ permission }}' => $this->blueprint->permission(),
            '{{ routeParameter }}' => $this->blueprint->routeParameter(),
            '{{ requestNamespace }}' => $this->blueprint->requestNamespace(),
        ] as $placeholder => $value) {
            if (str_contains($file->contents(), $placeholder)) {
                $file->replace($placeholder, $value, $placeholder);
            }
        }

        return $file;
    }

    /**
     * 有 status 字段时只返回启用项，有 sort 字段时按其排序。
     */
    protected function patchOptionController(SourceFile $file): SourceFile
    {
        $variables = $this->blueprint->variables();
        $query = "{$this->blueprint->class}::query()"
            .($variables['status_field'] !== '' ? "->where('{$variables['status_field']}', \\App\\Enums\\Status::Active)" : '')
            .($variables['sort_field'] !== '' ? "->orderBy('{$variables['sort_field']}')" : '')
            ."->orderBy('id')";

        return $file->replace("{$this->blueprint->class}::query()->orderBy('id')", $query, "{$this->blueprint->class}::query()->orderBy('id')");
    }

    /**
     * make:test 负责文件位置，内容为模块的接口测试（stubs/edge/test.stub）。
     */
    protected function patchTest(SourceFile $file): SourceFile
    {
        return new SourceFile($file->path, $this->stubs->render('test', $this->blueprint->variables(), $this->blueprint->fields));
    }

    /**
     * @return list<string>
     */
    protected function fragments(string $section): array
    {
        return $this->stubs->fragments($section, $this->blueprint->variables(), $this->blueprint->fields);
    }

    /**
     * foreign 字段的选项接口：本模块（--with-options）或 routes/api.php 中已有的 `/api/options/<关联表>` 才可用，
     * 否则前端下拉生成 TODO 占位。
     */
    protected function resolveOptionRoutes(): void
    {
        $routesPath = base_path('routes/api.php');
        $routes = $this->files->exists($routesPath) ? $this->files->get($routesPath) : '';

        foreach ($this->blueprint->fields as $field) {
            $route = $field->relatedRoute();

            if ($route === null) {
                continue;
            }

            $own = $this->blueprint->withOptions && $field->argument === $this->blueprint->table();

            if ($own || preg_match('/Route::get\(\''.preg_quote($route, '/').'\',\s*[\w\\\\]*OptionController::class\)/', $routes) === 1) {
                $field->optionsRoute = $route;

                continue;
            }

            $this->notes[] = [
                "外键 {$field->name} 没有可用的选项接口 /api/options/{$route}，前端下拉生成了 TODO 占位："
                ."可对 {$field->relatedModel()} 使用 --with-options 生成，或手写 Options 控制器后改为 fetchSelectOptions('{$route}')。",
                null,
            ];
        }
    }

    /**
     * 在已有文件的锚点处登记：菜单权限、资源路由与其数字 id 参数约束、选项路由、操作日志摘要。
     */
    protected function planRegistrations(): void
    {
        $variables = $this->blueprint->variables();
        $module = $this->blueprint->module;
        $node = $this->stubs->render('permission', $variables);

        $this->insert(config_path('permissions.php'), "'code' => '{$this->blueprint->permission()}'", [
            "// edge:permissions:{$module}" => $node,
            '// edge:permissions' => $this->stubs->render('permission_group', [...$variables, 'permission_node' => self::indent($node, 8)]),
        ]);

        $this->insert(base_path('routes/api.php'), "'{$this->blueprint->route()}' => ", ['// edge:resources' => $this->stubs->render('resource_route', $variables)]);
        $this->insert(base_path('routes/api.php'), "'{$this->blueprint->routeParameter()}',", ['// edge:route-keys' => $this->stubs->render('route_key', $variables)]);

        if ($this->blueprint->withOptions) {
            $this->insert(base_path('routes/api.php'), "{$this->blueprint->class}OptionController::class", ['// edge:options' => $this->stubs->render('option_route', $variables)]);
        }

        foreach ($this->files->glob(lang_path('*/operations.php')) as $path) {
            $locale = basename(dirname($path));
            $stub = $this->stubs->exists("operations.{$locale}") ? "operations.{$locale}" : 'operations';

            $this->insert($path, "'{$this->blueprint->route()}.store'", ['// edge:operations' => $this->stubs->render($stub, $variables)]);
        }
    }

    protected function planWeb(): void
    {
        if ($this->option('no-web')) {
            return;
        }

        $web = rtrim(config()->string('edge.generator.web_path'), '/');

        if (! $this->files->isDirectory($web)) {
            $this->notes[] = ["前端目录 {$web} 不存在，已跳过前端代码（可设置 EDGE_GENERATOR_WEB_PATH）。", null];

            return;
        }

        $variables = $this->blueprint->variables();
        $fields = $this->blueprint->fields;

        foreach ($this->blueprint->webFiles() as $stub => $path) {
            $this->create("{$web}/{$path}", TypeScriptImports::organize($this->stubs->render($stub, $variables, $fields)));
        }

        foreach (config()->array('edge.generator.web_locales') as $locale) {
            $localeVariables = [...$variables, 'locale' => $locale];
            $localeStub = $this->stubs->exists("web/locale.{$locale}") ? "web/locale.{$locale}" : 'web/locale';

            $this->create("{$web}/src/locales/{$locale}/pages/{$variables['web.locale_file']}.ts", TypeScriptImports::organize($this->stubs->render($localeStub, $localeVariables, $fields)));

            $index = "{$web}/src/locales/{$locale}.ts";
            $this->insert($index, "/pages/{$variables['web.locale_file']}'", ['// edge:locale-imports' => $this->stubs->render('web/locale_import', $localeVariables)]);
            $this->insert($index, "...{$variables['web.locale_module']},", ['// edge:locale-spread' => $this->stubs->render('web/locale_spread', $localeVariables)]);
        }

        $routeNode = $this->stubs->render('web/route', $variables);
        $this->insert("{$web}/config/routes.ts", "path: '{$variables['web.path']}'", [
            "// edge:routes:{$this->blueprint->module}" => $routeNode,
            '// edge:routes' => $this->stubs->render('web/route_group', [...$variables, 'route_node' => self::indent($routeNode, 4)]),
        ]);

        $this->insert("{$web}/src/access.ts", "'{$this->blueprint->permission()}.view'", [
            '// edge:permission-codes' => $this->stubs->render('web/permission_codes', $variables),
        ]);
    }

    /**
     * 计划创建前端文件；已存在且未指定 --force 时跳过。
     */
    protected function create(string $path, string $content): void
    {
        $exists = $this->files->exists($path);

        if ($exists && ! $this->option('force')) {
            $this->notes[] = ["已存在，跳过（--force 覆盖）：{$this->relative($path)}", null];

            return;
        }

        $this->changes[$path] = [$exists ? 'overwrite' : 'create', rtrim($content, "\n")."\n"];
    }

    /**
     * 在第一个存在的锚点前插入对应片段；文件已含 $identity 时视为已登记（幂等）。
     *
     * @param  array<string, string>  $snippetsByAnchor  锚点 => 片段，按优先顺序
     */
    protected function insert(string $path, string $identity, array $snippetsByAnchor): void
    {
        if (! array_key_exists($path, $this->changes) && ! $this->files->exists($path)) {
            $this->notes[] = ["文件不存在，未登记：{$this->relative($path)}", null];

            return;
        }

        $content = $this->changes[$path][1] ?? $this->files->get($path);

        if (str_contains($content, $identity)) {
            return;
        }

        foreach ($snippetsByAnchor as $anchor => $snippet) {
            $updated = TextInserter::insertBefore($content, $anchor, $snippet);

            if ($updated !== null) {
                $this->changes[$path] = [$this->changes[$path][0] ?? 'update', $updated];

                return;
            }
        }

        $this->notes[] = [
            sprintf('未找到锚点 %s，请手动把以下代码加到 %s（或先在文件中添加锚点注释后重新执行）', implode(' / ', array_keys($snippetsByAnchor)), $this->relative($path)),
            (string) reset($snippetsByAnchor),
        ];
    }

    protected function report(): void
    {
        $labels = ['create' => '创建', 'overwrite' => '覆盖', 'update' => '修改'];

        foreach ($this->artifacts as ['command' => $command, 'path' => $path, 'action' => $action]) {
            $this->components->twoColumnDetail($this->relative($path), "<fg=gray>{$command}</> {$labels[$action]}");
        }

        foreach ($this->changes as $path => [$action]) {
            $this->components->twoColumnDetail($this->relative($path), $labels[$action]);
        }

        foreach ($this->notes as [$note, $snippet]) {
            $this->components->warn($note);

            if ($snippet !== null) {
                $this->line($snippet);
                $this->newLine();
            }
        }

        if ($this->artifacts === [] && $this->changes === []) {
            $this->components->info('没有需要写入的文件（模块可能已生成）。');
        }
    }

    /**
     * 用 Pint 整理生成 / 修改的 PHP 文件：先拆开分组导入，再按 pint.json（group_import 等规则）重新导入、分组、排序，
     * 因此补全代码中的完整类名会变成 use 导入，已有的 `use A\{B, C};` 也会合并新导入的类。
     */
    protected function formatPhpFiles(): void
    {
        $files = array_values(array_filter(
            [...array_column($this->artifacts, 'path'), ...array_keys($this->changes)],
            fn (string $path): bool => str_ends_with($path, '.php'),
        ));

        if ($files === []) {
            return;
        }

        $pint = dirname((string) (new ReflectionClass(ClassLoader::class))->getFileName(), 2).'/bin/pint';
        $config = base_path('pint.json');

        if (! is_file($pint)) {
            $this->components->warn('未找到 vendor/bin/pint，生成的 PHP 文件未格式化：请执行 composer format。');

            return;
        }

        $split = Process::path(base_path())->run([$pint, '--no-config', '--preset=laravel', '--quiet', ...$files]);
        $group = $split->successful()
            ? Process::path(base_path())->run([$pint, '--quiet', ...(is_file($config) ? ["--config={$config}"] : []), ...$files])
            : $split;

        if ($group->failed()) {
            $this->components->warn('Pint 格式化失败（文件已写入），请执行 composer format 检查：');
            $this->line(trim($group->output()."\n".$group->errorOutput()));
        }
    }

    /**
     * 用前端项目自己的 Biome 格式化生成 / 修改的 TS 文件（导入已由 TypeScriptImports 去重，这里只做格式化与导入排序）。
     * 前端未安装依赖或执行失败时只给出提示，不影响生成结果。
     */
    protected function formatWebFiles(): void
    {
        $web = rtrim(config()->string('edge.generator.web_path'), '/');
        $files = array_values(array_filter(
            array_keys($this->changes),
            fn (string $path): bool => str_starts_with($path, "{$web}/") && preg_match('/\.tsx?$/', $path) === 1,
        ));

        if ($files === []) {
            return;
        }

        $relative = array_map(fn (string $path): string => substr($path, strlen($web) + 1), $files);
        $command = 'npx biome check --write '.implode(' ', $relative);

        if (! is_file("{$web}/node_modules/.bin/biome")) {
            $this->components->warn("前端未安装依赖，未格式化生成的前端文件；安装后可在 web 目录执行：{$command}");

            return;
        }

        $result = Process::path($web)->timeout(120)->run(["{$web}/node_modules/.bin/biome", 'check', '--write', ...$relative]);

        if ($result->failed()) {
            $this->components->warn("Biome 格式化前端文件失败（生成的文件已写入），请在 web 目录检查：{$command}");
            $this->line(trim($result->output()."\n".$result->errorOutput()));

            return;
        }

        $this->components->info('已用 Biome 格式化生成的前端文件。');
    }

    protected function nextSteps(): void
    {
        $this->components->info("模块 {$this->blueprint->class} 已生成，接下来：");
        $this->components->bulletList([
            'php artisan permission:sync && php artisan migrate（菜单自动出现在侧边栏，非超级管理员需在角色管理中授权）',
            "php artisan test --filter={$this->blueprint->class}Test",
            '在 docs/architecture.md 中补充接口契约，按需补充 Policy 规则、Seeder 与中文文案',
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => ['模型名（单数 StudlyCase）', '如 Tag、ArticleTag']];
    }

    /**
     * 缺少 --module / --fields 时交互式询问（--no-interaction 时直接报错，见 handle()）。
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->askForModuleAndFields($input);
    }

    /**
     * 完全交互（只敲了命令名、没给任何选项）时，与官方 make:model 一样再询问可选能力。
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        $this->askForModuleAndFields($input);

        $hasName = collect(FieldParser::parse((string) $input->getOption('fields')))->contains('name', 'name');
        $features = multiselect(
            label: '需要哪些可选能力？',
            options: [
                'data-scope' => '数据权限（department_id / created_by）',
                'soft-deletes' => '软删除',
                ...($hasName ? ['with-options' => '选项接口 GET /api/options/<路由>'] : []),
                'web' => '生成前端页面',
            ],
            default: ['web'],
        );

        foreach (['data-scope', 'soft-deletes', 'with-options'] as $option) {
            $input->setOption($option, in_array($option, $features, true));
        }

        $input->setOption('no-web', ! in_array('web', $features, true));
    }

    protected function askForModuleAndFields(InputInterface $input): void
    {
        if (! $input->getOption('module')) {
            $input->setOption('module', text(
                label: '所属模块（权限码前缀 / 命名空间 / 菜单分组）',
                placeholder: '如 content',
                required: true,
                validate: fn (string $value): ?string => preg_match('/^[a-z][a-z0-9\-]*$/', $value) === 1 ? null : '只能包含小写字母、数字和短横线，且以字母开头。',
            ));
        }

        if (! $input->getOption('fields')) {
            $input->setOption('fields', text(
                label: '字段定义（name:type[:参数][:rules]，逗号分隔）',
                placeholder: 'name:string:required|max:50,sort:integer,status:status',
                required: true,
                validate: function (string $value): ?string {
                    try {
                        FieldParser::parse($value) !== [] || throw new InvalidArgumentException('请至少声明一个字段。');

                        return null;
                    } catch (InvalidArgumentException $e) {
                        return $e->getMessage();
                    }
                },
            ));
        }
    }

    protected function relative(string $path): string
    {
        return str_starts_with($path, base_path().'/') ? substr($path, strlen(base_path()) + 1) : $path;
    }

    protected static function indent(string $text, int $spaces): string
    {
        return implode("\n", array_map(
            fn (string $line): string => $line === '' ? '' : str_repeat(' ', $spaces).$line,
            explode("\n", rtrim($text, "\n")),
        ));
    }
}
