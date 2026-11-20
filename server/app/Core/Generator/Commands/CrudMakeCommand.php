<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\{MenusFile, ModuleProviderFile, ModuleReadmeFile, RoutesFile};
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-crud：一个模型的完整增删改查（设计文档 §10.4），写法与 Content 模块的文章一致，生成后不手改即通过 composer ci。
 *
 * 依次：把菜单节点写入模块的 resources/menus.php（没有时创建）→ 把路由写入入口的 routes.php（模块分组）→ 调用 core:make-model、
 * core:make-action（Create / Update / Delete，软删除另有 Restore / ForceDelete；--audit 时模型实现 Auditable，写操作调用 LogAudit）、core:make-query、
 * core:make-exporter（--export）、core:make-request（列表、表单、导出）、core:make-resource、core:make-controller（资源 + 单动作）
 * → 生成授权测试 → 在模块 README 中加入该模型一节。共享文件的修改都是幂等的。
 *
 * 字段只有占位字段 name（不提供字段声明，理由见设计文档 §10.4）；加字段时要改的地方写在模块 README 中。
 */
#[Signature('core:make-crud
    {name : 模型名（单数），如 Article}
    {--soft-deletes : 软删除整套：恢复、彻底删除、“已删除”筛选（设计文档 §10.1）}
    {--export : 异步导出：导出类、export 按钮、导出请求与入口（设计文档 §6.10）}
    {--audit : 审计日志：模型实现 Monitor 的 Auditable，写操作在同一事务中调用 LogAudit（日志名为模块名，设计文档 §5.5、§6.11）}
    {--no-data-scope : 不使用数据范围（默认使用 HasDataScope，按 created_by，设计文档 §8.4）}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('为模块中的一个模型生成完整增删改查：模型、Policy、Action、Query、接口、菜单节点、路由、测试与 README')]
class CrudMakeCommand extends EntryGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'CRUD';

    protected function stubName(): string
    {
        return 'model';
    }

    protected function subNamespace(): string
    {
        return 'Models';
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, array{string, string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => ['模型名？', '如 Article']];
    }

    /**
     * 输入错误时返回错误信息：入口须受信任并提供 ListRequest（导出另需导出的 Resource 与限流器）；模型已存在时须加 --force。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        if (! $this->trusted()) {
            return "入口 {$this->appKey()} 是受限的入口，不能直接使用模型（设计文档 §5.2）：完整增删改查只用于受信任的入口。";
        }

        if (! $this->files->exists($this->entryPath('Requests/ListRequest.php'))) {
            return "入口 {$this->appKey()} 没有 Requests/ListRequest.php，不能生成列表接口。";
        }

        if ($this->option('export') && ($error = ControllerMakeCommand::exportError($this->entryPath(), $this->appName())) !== null) {
            return $error;
        }

        return $this->moduleModel($this->getNameInput()) !== null && ! $this->option('force')
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/{$this->getNameInput()}.php] 已存在：完整增删改查用于新模型，覆盖请加 --force。"
            : null;
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $this->failed = false;

        if (($error = $this->inputError()) !== null) {
            $this->components->error(self::punctuate($error));

            return $this->markFailed();
        }

        $model = $this->getNameInput();

        $this->registerMenu($model);
        $this->registerRoutes($model);

        if ($this->generate('core:make-model', [...$this->moduleArguments($model), '--soft-deletes' => (bool) $this->option('soft-deletes'), '--no-data-scope' => (bool) $this->option('no-data-scope'), '--audit' => (bool) $this->option('audit')]) !== self::SUCCESS) {
            return $this->markFailed();
        }

        $status = max([self::SUCCESS, ...array_map(fn (array $step): int => $this->generate(...$step), $this->steps($model))]);

        foreach ($this->tests($model) as $stub => $path) {
            if (! $this->writeFromStub($stub, $path, $this->crudReplacements($model), 'Test', $this->crudSections())) {
                $status = self::FAILURE;
            }
        }

        $readme = $this->modulePath('README.md');
        $section = $this->replace($this->files->get($this->resolveStub('crud.readme')), $this->crudReplacements($model), $this->crudSections());

        if ($this->files->exists($readme)) {
            $this->register($readme, "{$model} 一节", fn (string $markdown): ?string => ModuleReadmeFile::addSection($markdown, $section));
        } else {
            $this->files->put($readme, "# {$this->moduleName()}\n\n".$section);
        }

        if ($status !== self::SUCCESS || $this->failed) {
            return $this->markFailed();
        }

        $this->components->info(self::punctuate("{$model} 的完整增删改查已生成，接着执行："));
        $this->components->bulletList([
            'php artisan migrate（建表）与 php artisan core:sync（同步菜单与权限节点）',
            'composer ci（生成物不手改即可通过）',
            "按模块 README 中 {$model} 一节的“加字段时要改的地方”补上业务字段",
        ]);

        return null;
    }

    /**
     * 菜单节点写入模块的 resources/menus.php（没有时先按模板创建，并在 Provider 中登记）。
     */
    private function registerMenu(string $model): void
    {
        $path = $this->modulePath('resources/menus.php');
        $replacements = $this->crudReplacements($model);

        if (! $this->files->exists($path)) {
            $this->writeFromStub('module.menus', $path, $replacements, 'Declarations');
            $this->register($this->moduleProvider()['path'], '菜单与权限节点 resources/menus.php', fn (string $code): ?string => ModuleProviderFile::registerMenus($code));
        }

        $buttons = ['create' => 'common.create', 'update' => 'common.edit', 'delete' => 'common.delete'];

        if ($this->option('soft-deletes')) {
            $buttons = [...$buttons, 'restore' => 'common.restore', 'force-delete' => 'common.forceDelete'];
        }

        if ($this->option('export')) {
            $buttons['export'] = 'common.export';
        }

        $catalog = ['name' => $replacements['catalog'], 'path' => '/'.$replacements['routePrefix'], 'title' => $replacements['catalog'].'.title', 'icon' => 'lucide:folder'];
        $menu = [
            'name' => $replacements['permission'],
            'path' => $replacements['menuPath'],
            'component' => $replacements['menuPath'].'/list',
            'title' => $replacements['permission'].'.title',
            'icon' => 'lucide:list',
            'buttons' => $buttons,
        ];

        $this->register($path, "菜单 {$menu['name']}", fn (string $code): ?string => MenusFile::registerMenu($code, $catalog, $menu));
    }

    /**
     * 路由写入入口 routes.php 的模块分组（需要登录的分组中）。
     */
    private function registerRoutes(string $model): void
    {
        $namespace = $this->entryNamespace().'\\'.$this->moduleName().'\\Controllers\\';
        $controllers = [];

        if ($this->option('export')) {
            $controllers["{$model}ExportController"] = 'controller.export';
        }

        if ($this->option('soft-deletes')) {
            $controllers["Restore{$model}Controller"] = 'controller.invokable.model';
            $controllers["ForceDelete{$model}Controller"] = 'controller.force-delete';
        }

        $controllers["{$model}Controller"] = 'controller.model';

        $routes = array_map(fn (string $controller, string $stub): string => ControllerMakeCommand::routeLine($stub, $controller, $model, $this->appName()), array_keys($controllers), $controllers);
        $classes = array_map(fn (string $controller): string => $namespace.$controller, array_keys($controllers));

        if ($this->option('export')) {
            $classes[] = $this->entryNamespace().'\\'.$this->appName().'ServiceProvider';
        }

        $prefix = Str::kebab($this->moduleName());
        $comment = "{$this->moduleName()}（app/{$this->moduleKind()}/{$this->moduleName()}，接口见模块 README）";

        $this->register($this->entryPath('routes.php'), "路由 {$prefix}/".Str::of($model)->kebab()->plural(), fn (string $code): ?string => RoutesFile::registerModuleRoutes($code, $prefix, $comment, $routes, $classes));
    }

    /**
     * 模型之后依次调用的生成命令：[命令, 参数]。
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function steps(string $model): array
    {
        $module = $this->moduleArguments($model);
        $withModel = [...$module, '--model' => $model];
        $entry = ['--app' => $this->appKey(), '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')];
        $actions = ['Create', 'Update', 'Delete', ...($this->option('soft-deletes') ? ['Restore', 'ForceDelete'] : [])];

        return [
            ...array_map(fn (string $verb): array => ['core:make-action', [...$withModel, 'name' => $verb.$model]], $actions),
            ['core:make-query', [...$withModel, 'name' => "{$model}List"]],
            ...($this->option('export') ? [['core:make-exporter', $module]] : []),
            ['core:make-request', [...$entry, 'name' => "{$model}Index", '--list' => true, '--model' => $model]],
            ['core:make-request', [...$entry, 'name' => $model, '--model' => $model]],
            ...($this->option('export') ? [['core:make-request', [...$entry, 'name' => "{$model}Export", '--export' => true]]] : []),
            ['core:make-resource', [...$entry, 'name' => $model, '--model' => $model]],
            ['core:make-controller', [...$entry, 'name' => $model, '--model' => $model]],
            ...($this->option('soft-deletes') ? [
                ['core:make-controller', [...$entry, 'name' => "Restore{$model}", '--model' => $model, '--invokable' => true]],
                ['core:make-controller', [...$entry, 'name' => "ForceDelete{$model}", '--model' => $model, '--invokable' => true]],
            ] : []),
            ...($this->option('export') ? [['core:make-controller', [...$entry, 'name' => "{$model}Export", '--model' => $model, '--invokable' => true]]] : []),
        ];
    }

    /**
     * 生成的测试：模板 => 路径。
     *
     * @return array<string, string>
     */
    private function tests(string $model): array
    {
        $controllers = $this->laravel->basePath("tests/Http/{$this->appName()}/{$this->moduleName()}/Controllers");

        return array_filter([
            'crud.test.controller' => "{$controllers}/{$model}ControllerTest.php",
            'crud.test.restore' => $this->option('soft-deletes') ? "{$controllers}/Restore{$model}ControllerTest.php" : null,
            'crud.test.force-delete' => $this->option('soft-deletes') ? "{$controllers}/ForceDelete{$model}ControllerTest.php" : null,
            'crud.test.export' => $this->option('export') ? "{$controllers}/{$model}ExportControllerTest.php" : null,
            'crud.test.activity' => $this->option('audit') ? $this->laravel->basePath("tests/{$this->moduleKind()}/{$this->moduleName()}/Actions/{$model}ActivityTest.php") : null,
        ]);
    }

    /**
     * 生成模块中的类时的共同参数。
     *
     * @return array<string, mixed>
     */
    private function moduleArguments(string $model): array
    {
        return ['name' => $model, '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')];
    }

    /**
     * 测试、README 与菜单的模板替换内容。
     *
     * @return array<string, string>
     */
    private function crudReplacements(string $model): array
    {
        $prefix = Str::kebab($this->moduleName());
        $uri = Str::of($model)->kebab()->plural()->toString();
        $options = array_filter(['--soft-deletes' => $this->option('soft-deletes'), '--export' => $this->option('export'), '--audit' => $this->option('audit'), '--no-data-scope' => $this->option('no-data-scope')]);

        return [
            ...$this->replacements($this->qualifyClass($model)),
            ...$this->modelReplacements($model),
            'catalog' => Str::snake($this->moduleName()),
            'catalogPath' => $prefix,
            'routePrefix' => $prefix,
            'uri' => $uri,
            'routeName' => "{$this->appKey()}.{$prefix}.{$uri}",
            'menuPath' => "/{$prefix}/".Str::kebab($model),
            'routesFile' => $this->relativePath($this->entryPath('routes.php')),
            'kindDir' => $this->moduleKind(),
            'table' => Str::snake(Str::pluralStudly($model)),
            'modelSnake' => Str::snake($model),
            'options' => implode('', array_map(fn (string $option): string => ' '.$option, array_keys($options))),
        ];
    }

    /**
     * 测试与 README 的条件块。
     *
     * @return array<string, bool>
     */
    private function crudSections(): array
    {
        return [
            'softDeletes' => (bool) $this->option('soft-deletes'),
            'export' => (bool) $this->option('export'),
            'audit' => (bool) $this->option('audit'),
            'dataScope' => ! $this->option('no-data-scope'),
        ];
    }
}
