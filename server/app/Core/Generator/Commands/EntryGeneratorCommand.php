<?php

namespace App\Core\Generator\Commands;

use Illuminate\Support\Str;

/**
 * 生成到入口中的类：app/Http/<入口>/<模块>/<子目录>，入口由 `--app=`（config/core.php 中的键，默认 admin）指定，
 * 模块由 `--module=` 指定且必须已存在（入口内部的目录与模块同名一一对应，设计文档 §4.4）。
 */
abstract class EntryGeneratorCommand extends ModuleGeneratorCommand
{
    /**
     * 选择入口的选项（各命令的 #[Signature] 中拼接）。
     */
    public const string ENTRY_OPTIONS = '{--app=admin : 入口（config/core.php 中的键），如 admin}';

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->entryNamespace().'\\'.$this->moduleName().'\\'.$this->subNamespace();
    }

    /**
     * 输入错误时返回错误信息：在模块相关的检查之外，入口必须存在。
     */
    protected function inputError(): ?string
    {
        return parent::inputError() ?? $this->appError();
    }

    /**
     * 入口的键（config/core.php 中的键，snake_case），如 admin。
     */
    protected function appKey(): string
    {
        return Str::snake($this->stringOption('app') ?? 'admin');
    }

    /**
     * 入口的目录名（StudlyCase），如 Admin。
     */
    protected function appName(): string
    {
        return Str::studly($this->appKey());
    }

    /**
     * 入口的命名空间，如 App\Http\Admin。
     */
    protected function entryNamespace(): string
    {
        return $this->laravel->getNamespace().'Http\\'.$this->appName();
    }

    /**
     * 入口目录下的路径。
     */
    protected function entryPath(string $path = ''): string
    {
        return app_path('Http/'.$this->appName().($path === '' ? '' : '/'.$path));
    }

    /**
     * 入口是否受信任（config/core.php 中的 trusted）：受限入口只能通过模块的 Contracts 访问数据（设计文档 §5.2）。
     */
    protected function trusted(): bool
    {
        return $this->registeredApps()[$this->appKey()] ?? false;
    }

    /**
     * --model 选项（StudlyCase），未提供时为 null。
     */
    protected function modelOption(): ?string
    {
        $model = $this->stringOption('model');

        return $model === null ? null : Str::studly($model);
    }

    /**
     * 模型相关选项（--model）的错误：模型须在模块中，且只有受信任的入口可以直接使用模型。
     */
    protected function modelError(string $model): ?string
    {
        if (($error = $this->nameError($model, '模型名')) !== null) {
            return $error;
        }

        if (! $this->trusted()) {
            return "入口 {$this->appKey()} 是受限的入口，不能直接使用模型，只能通过模块的 Contracts 访问数据（设计文档 §5.2）：请去掉 --model，或用 core:make-contract 为模块生成契约。";
        }

        return $this->moduleModel(Str::studly($model)) === null
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/".Str::studly($model).'.php] 不存在，请先执行 php artisan core:make-model '.Str::studly($model)." --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。'
            : null;
    }

    /**
     * 入口相关的模板替换内容。
     *
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [
            ...parent::replacements($class),
            'app' => $this->appName(),
            'appKey' => $this->appKey(),
            'entryNamespace' => $this->entryNamespace(),
        ];
    }

    /**
     * 入口不存在时的错误信息。
     */
    private function appError(): ?string
    {
        if (($error = $this->nameError($this->stringOption('app') ?? 'admin', '入口名')) !== null) {
            return $error;
        }

        if (! array_key_exists($this->appKey(), $this->registeredApps()) || ! is_dir($this->entryPath())) {
            return "入口 {$this->appKey()} 不存在（需要 app/Http/{$this->appName()} 并登记在 config/core.php），可用 php artisan core:make-app {$this->appKey()} 创建。";
        }

        return null;
    }
}
