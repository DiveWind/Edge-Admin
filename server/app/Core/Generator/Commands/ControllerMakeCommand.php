<?php

namespace App\Core\Generator\Commands;

use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-controller：入口中的控制器（app/Http/<入口>/<模块>/Controllers），只做“授权声明 → 调用 Action / 查询 → 返回 Resource”
 * （设计文档 §5.4、§10.4），写法与 Content 的控制器一致。
 *
 * - `--model=`：资源控制器的五个方法 index（列表请求 + `<模型>List`）、show（预加载）、store（201）、update、destroy，
 *   写操作调用 Action 并传入 `#[CurrentUser]`，授权写在方法的 `#[Authorize]` 上；
 * - `--invokable`：单动作控制器（`__invoke`）。带 `--model=` 时按名称识别：`Export<模型复数>`（导出，CreateExport）、
 *   `ForceDelete<模型>`（返回 data: null）、其他 `<动作><模型>`（调用同名 Action、返回 Resource，ability 为动作名的 camelCase）。
 *
 * 缺少的列表请求、表单请求、导出请求、Resource、Query、Action 与导出类一并生成（已存在的不覆盖）。
 * 控制器还没在入口的 routes.php 中登记时，打印要添加的路由（core:make-crud 自动写入）。
 */
#[Signature('core:make-controller
    {name : 控制器类名，如 Article（生成 ArticleController）}
    {--model= : 模型名：生成该模型的资源控制器（或单动作控制器）}
    {--invokable : 单动作控制器（__invoke）}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('在入口中生成控制器（--model 资源控制器的五个方法，--invokable 单动作控制器）')]
class ControllerMakeCommand extends EntryGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Controller';

    protected function stubName(): string
    {
        $model = $this->modelOption();

        if (! $this->option('invokable')) {
            return $model === null ? 'controller' : 'controller.model';
        }

        return match (true) {
            $model === null => 'controller.invokable',
            $this->baseName() === self::exportName($model) => 'controller.export',
            $this->baseName() === "ForceDelete{$model}" => 'controller.force-delete',
            default => 'controller.invokable.model',
        };
    }

    protected function subNamespace(): string
    {
        return 'Controllers';
    }

    protected function suffix(): string
    {
        return 'Controller';
    }

    /**
     * 输入错误时返回错误信息：--model 的模型须在模块中、入口受信任且提供 ListRequest；导出需要入口提供导出记录的 Resource 与限流器。
     */
    protected function inputError(): ?string
    {
        $model = $this->modelOption();

        if (($error = parent::inputError() ?? ($model === null ? null : $this->modelError($model))) !== null) {
            return $error;
        }

        if ($model !== null && ! $this->files->exists($this->entryPath('Requests/ListRequest.php'))) {
            return "入口 {$this->appKey()} 没有 Requests/ListRequest.php，不能生成带列表接口的控制器（--model）。";
        }

        $stub = $this->stubName();

        if ($stub === 'controller.export' && ($error = self::exportError($this->entryPath(), $this->appName())) !== null) {
            return $error;
        }

        return $stub === 'controller.force-delete' && $model !== null && ! $this->modelFeatures($model)['softDeletes']
            ? "模型 {$model} 没有软删除（SoftDeletes），不能生成彻底删除的接口。"
            : null;
    }

    /**
     * 入口不支持导出时的错误信息（导出记录的 Resource 与发起导出的限流器都在入口中，设计文档 §6.10）。
     */
    public static function exportError(string $entryPath, string $app): ?string
    {
        $provider = "{$entryPath}/{$app}ServiceProvider.php";

        return is_file("{$entryPath}/System/Resources/ExportResource.php") && is_file($provider) && str_contains((string) file_get_contents($provider), 'EXPORT_LIMITER')
            ? null
            : "入口 {$app} 不支持导出：需要 System/Resources/ExportResource.php 与 {$app}ServiceProvider::EXPORT_LIMITER（同 Admin 入口，设计文档 §6.10）。";
    }

    /**
     * 生成缺少的请求、Resource、Query、Action 与导出类，并提示需要添加的路由。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $model = $this->modelOption();
        $status = self::SUCCESS;

        if ($model !== null) {
            $status = max([self::SUCCESS, ...array_map(fn (array $step): int => $this->createIfMissing(...$step), $this->dependencies($model))]);
        }

        $routes = $this->entryPath('routes.php');
        $controller = class_basename($class);

        if (! $this->files->exists($routes) || ! str_contains($this->files->get($routes), "{$controller}::class")) {
            $this->components->info(self::punctuate('路由不会自动登记（core:make-crud 除外），请在 ['.$this->relativePath($routes).'] 的需要登录的分组中添加：'));
            $this->line(implode("\n", [
                "    Route::prefix('".Str::kebab($this->moduleName())."')->name('".Str::kebab($this->moduleName()).".')->group(function (): void {",
                '        '.self::routeLine($this->stubName(), $controller, $model ?? '', $this->appName()),
                '    });',
            ]));
            $this->newLine();
        }

        return $status;
    }

    /**
     * 路由语句（写在模块分组 `Route::prefix('<模块>')->name('<模块>.')` 中，URI 与路由名相对分组）。
     *
     * @param  string  $stub  控制器的模板（决定路由的形式）
     */
    public static function routeLine(string $stub, string $controller, string $model, string $app): string
    {
        $uri = Str::of($model)->kebab()->plural()->toString();
        $parameter = Str::snake($model);
        $verb = Str::kebab(Str::chopEnd(Str::chopEnd($controller, 'Controller'), $model));

        return match ($stub) {
            'controller.model' => "Route::apiResource('{$uri}', {$controller}::class)->whereNumber('{$parameter}');",
            'controller.export' => "Route::post('{$uri}/export', {$controller}::class)->middleware('throttle:'.{$app}ServiceProvider::EXPORT_LIMITER)->name('{$uri}.export');",
            'controller.force-delete' => "Route::delete('{$uri}/{{$parameter}}/force-delete', {$controller}::class)->whereNumber('{$parameter}')->withTrashed()->name('{$uri}.force-delete');",
            'controller.invokable.model' => "Route::post('{$uri}/{{$parameter}}/{$verb}', {$controller}::class)->whereNumber('{$parameter}')".($verb === 'restore' ? '->withTrashed()' : '')."->name('{$uri}.{$verb}');",
            'controller.invokable' => "Route::post('".Str::kebab(Str::chopEnd($controller, 'Controller'))."', {$controller}::class)->name('".Str::kebab(Str::chopEnd($controller, 'Controller'))."');",
            default => "Route::apiResource('".Str::of(Str::chopEnd($controller, 'Controller'))->kebab()->plural()."', {$controller}::class);",
        };
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->modelOption() ?? '';
        $namespace = $this->entryNamespace().'\\'.$this->moduleName();
        $user = $this->userProviderModel();
        $user = is_string($user) ? $user : User::class;

        return [
            ...parent::replacements($class),
            ...($model === '' ? [] : $this->modelReplacements($model)),
            'userModel' => class_basename($user),
            'namespacedUserModel' => $user,
            'indexRequest' => $model.'IndexRequest',
            'namespacedIndexRequest' => $namespace.'\\Requests\\'.$model.'IndexRequest',
            'request' => $model.'Request',
            'namespacedRequest' => $namespace.'\\Requests\\'.$model.'Request',
            'exportRequest' => self::exportName($model).'Request',
            'namespacedExportRequest' => $namespace.'\\Requests\\'.self::exportName($model).'Request',
            'resource' => $model.'Resource',
            'namespacedResource' => $namespace.'\\Resources\\'.$model.'Resource',
            'action' => $this->baseName(),
            'ability' => Str::camel($this->verb($model)),
        ];
    }

    /**
     * 按模型文件的写法（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        $model = $this->modelOption();

        return $model === null ? [] : [...$this->modelFeatures($model), 'trashed' => $this->verb($model) === 'Restore'];
    }

    /**
     * 本控制器用到、需要一并生成的类：[命令, 参数, 目标文件]（入口中的相对模块目录、模块中的以 `module:` 开头）。
     *
     * @return list<array{string, array<string, mixed>, string}>
     */
    private function dependencies(string $model): array
    {
        $entry = ['--app' => $this->appKey(), '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')];
        $module = ['--module' => $this->moduleName(), '--core' => (bool) $this->option('core'), '--model' => $model];
        $resource = ['core:make-resource', ['name' => $model, '--model' => $model, ...$entry], "Resources/{$model}Resource.php"];

        return match ($this->stubName()) {
            'controller.model' => [
                ['core:make-request', ['name' => "{$model}Index", '--list' => true, '--model' => $model, ...$entry], "Requests/{$model}IndexRequest.php"],
                ['core:make-request', ['name' => $model, '--model' => $model, ...$entry], "Requests/{$model}Request.php"],
                $resource,
                ['core:make-query', ['name' => "{$model}List", ...$module], "module:Queries/{$model}List.php"],
                ...array_map(fn (string $verb): array => ['core:make-action', ['name' => $verb.$model, ...$module], "module:Actions/{$verb}{$model}.php"], ['Create', 'Update', 'Delete']),
            ],
            'controller.export' => [
                ['core:make-request', ['name' => "{$model}Index", '--list' => true, '--model' => $model, ...$entry], "Requests/{$model}IndexRequest.php"],
                ['core:make-request', ['name' => self::exportName($model), '--export' => true, ...$entry], 'Requests/'.self::exportName($model).'Request.php'],
                ['core:make-exporter', ['name' => $model, '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')], "module:Exporters/{$model}Exporter.php"],
            ],
            'controller.force-delete' => [
                ['core:make-action', ['name' => "ForceDelete{$model}", ...$module], "module:Actions/ForceDelete{$model}.php"],
            ],
            'controller.invokable.model' => [
                $resource,
                ['core:make-action', ['name' => $this->baseName(), ...$module], "module:Actions/{$this->baseName()}.php"],
            ],
            default => [],
        };
    }

    /**
     * 调用生成命令；目标文件已存在时不做任何事（不覆盖已有的类，也不输出）。
     *
     * @param  array<string, mixed>  $arguments
     */
    private function createIfMissing(string $command, array $arguments, string $file): int
    {
        $path = str_starts_with($file, 'module:')
            ? $this->modulePath(Str::after($file, 'module:'))
            : $this->entryPath($this->moduleName().'/'.$file);

        return $this->files->exists($path) ? self::SUCCESS : $this->call($command, [...$arguments, '--force' => false]);
    }

    /**
     * 控制器名去掉 Controller 后缀，如 PublishArticle。
     */
    private function baseName(): string
    {
        return Str::chopEnd($this->getNameInput(), 'Controller');
    }

    /**
     * 单动作控制器的动作：名称去掉模型名，如 PublishArticle → Publish。
     */
    private function verb(string $model): string
    {
        $verb = $model === '' ? $this->baseName() : Str::chopEnd($this->baseName(), $model);

        return $verb === '' ? $this->baseName() : $verb;
    }
}
