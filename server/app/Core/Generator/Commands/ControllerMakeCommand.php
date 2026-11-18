<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-controller：入口中的控制器（app/Http/<入口>/<模块>/Controllers），只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）。
 *
 * `--model=` 生成某个模型的只读接口：index（列表请求继承 ListRequest，`paginate($request->pageSize())`）与 show，
 * 授权写在方法的 `#[Authorize]` 上；缺少的列表请求与 Resource 一并生成（core:make-request --list、core:make-resource --model）。
 * 写接口调用模块的 Action，在完整增删改查（core:make-module --crud）就绪前手工添加。路由不自动登记，命令输出需要添加的路由。
 */
#[Signature('core:make-controller
    {name : 控制器类名，如 Article（生成 ArticleController）}
    {--model= : 模型名：生成该模型的 index / show 接口}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('在入口中生成控制器（--model 生成带列表分页与 #[Authorize] 的 index / show）')]
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
        return $this->modelOption() === null ? 'controller' : 'controller.model';
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
     * 输入错误时返回错误信息：--model 的模型须在模块中、入口受信任且提供 ListRequest。
     */
    protected function inputError(): ?string
    {
        $model = $this->modelOption();

        if (($error = parent::inputError() ?? ($model === null ? null : $this->modelError($model))) !== null) {
            return $error;
        }

        return $model !== null && ! $this->files->exists($this->entryPath('Requests/ListRequest.php'))
            ? "入口 {$this->appKey()} 没有 Requests/ListRequest.php，不能生成带列表接口的控制器（--model）。"
            : null;
    }

    /**
     * 生成缺少的列表请求与 Resource，并提示需要添加的路由。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $model = $this->modelOption();
        $status = self::SUCCESS;

        if ($model !== null) {
            $arguments = ['--app' => $this->appKey(), '--module' => $this->moduleName(), '--core' => (bool) $this->option('core'), '--force' => false];

            $status = max(
                $this->createIfMissing('core:make-request', ['name' => $model.'Index', '--list' => true, ...$arguments], "Requests/{$model}IndexRequest.php"),
                $this->createIfMissing('core:make-resource', ['name' => $model, '--model' => $model, ...$arguments], "Resources/{$model}Resource.php"),
            );
        }

        $this->components->info(self::punctuate('路由不会自动登记，请在 ['.$this->relativePath($this->entryPath('routes.php')).'] 中添加，如：'));
        $this->line('    '.$this->route(class_basename($class), $model));
        $this->newLine();

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->modelOption() ?? '';
        $namespace = $this->entryNamespace().'\\'.$this->moduleName();

        return [
            ...parent::replacements($class),
            'model' => $model,
            'namespacedModel' => $this->moduleNamespace().'\\Models\\'.$model,
            'modelVariable' => Str::camel($model),
            'indexRequest' => $model.'IndexRequest',
            'namespacedIndexRequest' => $namespace.'\\Requests\\'.$model.'IndexRequest',
            'resource' => $model.'Resource',
            'namespacedResource' => $namespace.'\\Resources\\'.$model.'Resource',
        ];
    }

    /**
     * 调用生成命令，目标文件（相对入口中的模块目录）已存在时跳过（不覆盖已有的请求与 Resource）。
     *
     * @param  array<string, mixed>  $arguments
     */
    private function createIfMissing(string $command, array $arguments, string $file): int
    {
        $path = $this->entryPath($this->moduleName().'/'.$file);

        if ($this->files->exists($path)) {
            $this->components->twoColumnDetail("[{$this->relativePath($path)}]", '<fg=yellow;options=bold>已存在，跳过</>');

            return self::SUCCESS;
        }

        return $this->call($command, $arguments);
    }

    /**
     * 需要添加的路由。
     */
    private function route(string $controller, ?string $model): string
    {
        $uri = Str::kebab($this->moduleName()).'/'.Str::of($model ?? Str::chopEnd($controller, 'Controller'))->kebab()->plural();

        return $model === null
            ? "Route::apiResource('{$uri}', {$controller}::class);"
            : "Route::apiResource('{$uri}', {$controller}::class)->only(['index', 'show']);";
    }
}
