<?php

namespace App\Console\Commands;

use App\Console\Generator\FieldParser;
use App\Console\Generator\ModuleBlueprint;
use App\Console\Generator\StubRenderer;
use App\Console\Generator\TextInserter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

/**
 * 业务模块生成器，用法与模板约定见 docs/generator.md。
 */
#[Signature('edge:make-module
    {name : 模型名（单数，StudlyCase，如 Tag）}
    {--module= : 所属模块，决定权限码前缀、命名空间与菜单分组（如 content）}
    {--fields= : 字段定义 name:type[:参数][:rules]，逗号分隔}
    {--data-scope : 接入数据权限（HasDataScope，增加 department_id / created_by）}
    {--soft-deletes : 启用软删除}
    {--no-web : 不生成前端代码}
    {--force : 覆盖已存在的文件}
    {--dry-run : 只打印将要创建 / 修改的文件，不写入}')]
#[Description('生成一个完整的业务模块：迁移、模型、工厂、请求、资源、Policy、控制器、测试，并登记权限点、路由、操作日志与前端页面')]
class MakeModuleCommand extends Command
{
    /**
     * 本次生成的文件变更：路径 => [动作, 新内容]。动作为 create / overwrite / update。
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
        $this->files = $files;
        $this->changes = [];
        $this->notes = [];

        /** @var list<string> $stubPaths */
        $stubPaths = config('edge.generator.stub_paths', [base_path('stubs/edge')]);
        $this->stubs = new StubRenderer($stubPaths);

        try {
            $this->blueprint = new ModuleBlueprint(
                name: (string) $this->argument('name'),
                module: (string) ($this->option('module') ?: throw new InvalidArgumentException('请通过 --module 指定所属模块（如 content）。')),
                fields: FieldParser::parse((string) $this->option('fields')),
                dataScope: (bool) $this->option('data-scope'),
                softDeletes: (bool) $this->option('soft-deletes'),
            );

            $this->planBackend();
            $this->planWeb();
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report();

        if ($this->option('dry-run')) {
            $this->components->warn('--dry-run：未写入任何文件。');

            return self::SUCCESS;
        }

        foreach ($this->changes as $path => [, $content]) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $content);
        }

        $this->nextSteps();

        return self::SUCCESS;
    }

    protected function planBackend(): void
    {
        $variables = $this->blueprint->variables();
        $fields = $this->blueprint->fields;
        $table = $this->blueprint->table();

        $existingMigration = $this->files->glob($this->basePath("database/migrations/*_create_{$table}_table.php"))[0] ?? null;
        $migrationPath = $existingMigration ?? $this->basePath('database/migrations/'.now()->format('Y_m_d_His')."_create_{$table}_table.php");

        $this->create($migrationPath, $this->render('migration', $variables));

        foreach ($this->blueprint->backendFiles() as $stub => $path) {
            $imports = $stub === 'model' ? $this->blueprint->modelImports() : [];
            $this->create($this->basePath($path), $this->stubs->render($this->resolve($stub), $variables, $fields, $imports));
        }

        $this->registerPermissions($variables);
        $this->registerRoutes($variables);
        $this->registerOperations($variables);
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function registerPermissions(array $variables): void
    {
        $path = $this->basePath('config/permissions.php');
        $module = $this->blueprint->module;
        $node = $this->stubs->snippet('permission', $variables);

        $this->insert($path, "'code' => '{$this->blueprint->permission()}'", [
            "// edge:permissions:{$module}" => $node,
            '// edge:permissions' => $this->stubs->snippet('permission_group', [...$variables, 'permission_node' => self::indent($node, 8)]),
        ]);
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function registerRoutes(array $variables): void
    {
        $path = $this->basePath('routes/api.php');
        $controller = "{$variables['controller_namespace']}\\{$this->blueprint->class}Controller";

        if ($this->insert($path, "Route::apiResource('{$this->blueprint->route()}',", ['// edge:routes' => $this->stubs->snippet('routes', $variables)])) {
            $this->changes[$path][1] = TextInserter::addImport($this->changes[$path][1], $controller);
        }
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function registerOperations(array $variables): void
    {
        foreach ($this->files->glob($this->basePath('lang/*/operations.php')) as $path) {
            $locale = basename(dirname($path));
            $stub = $this->stubs->resolve('operations', [$locale]) ?? throw new RuntimeException('模板 [operations.stub] 不存在。');

            $this->insert($path, "'{$this->blueprint->route()}.store'", ['// edge:operations' => $this->stubs->snippet($stub, $variables)]);
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
            $this->create("{$web}/{$path}", $this->stubs->render($this->resolve($stub), $variables, $fields));
        }

        /** @var list<string> $locales */
        $locales = config('edge.generator.web_locales', ['zh-CN', 'en-US']);

        foreach ($locales as $locale) {
            $localeVariables = [...$variables, 'locale' => $locale];
            $localeStub = $this->stubs->resolve('web/locale', [$locale]) ?? throw new RuntimeException('模板 [web/locale.stub] 不存在。');

            $this->create("{$web}/src/locales/{$locale}/pages/{$variables['web.locale_file']}.ts", $this->stubs->render($localeStub, $localeVariables, $fields));

            $menu = "{$web}/src/locales/{$locale}/menu.ts";
            $this->insert($menu, "'{$variables['web.menu_module']}':", ['// edge:menu' => $this->localeSnippet('web/menu_module', $locale, $localeVariables)]);
            $this->insert($menu, "'{$variables['web.menu_key']}':", ['// edge:menu' => $this->localeSnippet('web/menu_item', $locale, $localeVariables)]);

            $index = "{$web}/src/locales/{$locale}.ts";
            $this->insert($index, "/pages/{$variables['web.locale_file']}'", ['// edge:locale-imports' => $this->stubs->snippet('web/locale_import', $localeVariables)]);
            $this->insert($index, "...{$variables['web.locale_module']},", ['// edge:locale-spread' => $this->stubs->snippet('web/locale_spread', $localeVariables)]);
        }

        $routeNode = $this->stubs->snippet('web/route', $variables);
        $this->insert("{$web}/config/routes.ts", "path: '{$variables['web.path']}'", [
            "// edge:routes:{$this->blueprint->module}" => $routeNode,
            '// edge:routes' => $this->stubs->snippet('web/route_group', [...$variables, 'route_node' => self::indent($routeNode, 4)]),
        ]);

        $this->insert("{$web}/src/access.ts", "'{$this->blueprint->permission()}.view'", [
            '// edge:permission-codes' => $this->stubs->snippet('web/permission_codes', $variables),
        ]);
    }

    /**
     * 计划创建文件；已存在且未指定 --force 时跳过。
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
    protected function insert(string $path, string $identity, array $snippetsByAnchor): bool
    {
        if (! array_key_exists($path, $this->changes) && ! $this->files->exists($path)) {
            $this->notes[] = ["文件不存在，未登记：{$this->relative($path)}", null];

            return false;
        }

        $content = $this->changes[$path][1] ?? $this->files->get($path);

        if (str_contains($content, $identity)) {
            return false;
        }

        foreach ($snippetsByAnchor as $anchor => $snippet) {
            $updated = TextInserter::insertBefore($content, $anchor, $snippet);

            if ($updated !== null) {
                $this->changes[$path] = [$this->changes[$path][0] ?? 'update', $updated];

                return true;
            }
        }

        $this->notes[] = [
            sprintf('未找到锚点 %s，请手动把以下代码加到 %s（或先在文件中添加锚点注释后重新执行）', implode(' / ', array_keys($snippetsByAnchor)), $this->relative($path)),
            (string) reset($snippetsByAnchor),
        ];

        return false;
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function render(string $stub, array $variables): string
    {
        return $this->stubs->render($this->resolve($stub), $variables, $this->blueprint->fields);
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function localeSnippet(string $stub, string $locale, array $variables): string
    {
        return $this->stubs->snippet($this->stubs->resolve($stub, [$locale]) ?? throw new RuntimeException("模板 [{$stub}.stub] 不存在。"), $variables);
    }

    protected function resolve(string $stub): string
    {
        return $this->stubs->resolve($stub, $this->blueprint->variants()) ?? throw new RuntimeException("模板 [{$stub}.stub] 不存在。");
    }

    protected function report(): void
    {
        $labels = ['create' => '创建', 'overwrite' => '覆盖', 'update' => '修改'];

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

        if ($this->changes === []) {
            $this->components->info('没有需要写入的文件（模块可能已生成）。');
        }
    }

    protected function nextSteps(): void
    {
        $this->components->info("模块 {$this->blueprint->class} 已生成，接下来：");
        $this->components->bulletList([
            'php artisan migrate',
            'php artisan permission:sync',
            "php artisan test --filter={$this->blueprint->class}Test",
            'composer lint && composer analyse',
            '在 docs/architecture.md 中补充接口契约',
        ]);
    }

    protected function basePath(string $path): string
    {
        $base = config('edge.generator.base_path');

        return rtrim(is_string($base) && $base !== '' ? $base : base_path(), '/').'/'.$path;
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
