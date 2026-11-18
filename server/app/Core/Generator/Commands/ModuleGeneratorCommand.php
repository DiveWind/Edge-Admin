<?php

namespace App\Core\Generator\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

/**
 * 生成到模块（app/Modules/<模块>，加 --core 时为 app/Core/<模块>）中的类：`--module=` 指定模块，缺少时交互选择。
 */
abstract class ModuleGeneratorCommand extends CoreGeneratorCommand
{
    /**
     * 选择模块的选项（各命令的 #[Signature] 中拼接）。
     */
    public const string MODULE_OPTIONS = '{--module= : 所属模块，如 Content（缺少时交互选择）}
        {--core : 模块是内核模块（app/Core），需显式指定}
        {--f|force : 文件已存在时覆盖}';

    /**
     * 模块内的子目录（命名空间），如 Actions。
     */
    abstract protected function subNamespace(): string;

    /**
     * 输入错误时返回错误信息：名称不合法、未指定模块、模块不存在。
     */
    protected function inputError(): ?string
    {
        return parent::inputError() ?? $this->moduleError();
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->moduleNamespace().'\\'.$this->subNamespace();
    }

    /**
     * 缺少 --module 时交互选择（只在交互模式下；非交互模式下由 inputError() 报错）。
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $modules = $this->existingModules();

        if ($this->stringOption('module') === null && $modules !== []) {
            $input->setOption('module', select(label: '属于哪个模块？', options: $modules));
        }
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, array{string, string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => [$this->type.' 的名称？', '如 '.$this->example()]];
    }

    /**
     * 名称示例（用于交互提问）。
     */
    protected function example(): string
    {
        return 'Article';
    }

    /**
     * 模块名（StudlyCase）。
     */
    protected function moduleName(): string
    {
        return Str::studly($this->stringOption('module') ?? '');
    }

    /**
     * 模块类别：Core 或 Modules。
     */
    protected function moduleKind(): string
    {
        return $this->option('core') ? 'Core' : 'Modules';
    }

    /**
     * 模块的命名空间，如 App\Modules\Content。
     */
    protected function moduleNamespace(): string
    {
        return $this->laravel->getNamespace().$this->moduleKind().'\\'.$this->moduleName();
    }

    /**
     * 模块目录下的路径。
     */
    protected function modulePath(string $path = ''): string
    {
        return app_path($this->moduleKind().'/'.$this->moduleName().($path === '' ? '' : '/'.$path));
    }

    /**
     * 模块的 ServiceProvider（类名与路径）。
     *
     * @return array{class: string, path: string}
     */
    protected function moduleProvider(): array
    {
        return [
            'class' => $this->moduleNamespace().'\\'.$this->moduleName().'ServiceProvider',
            'path' => $this->modulePath($this->moduleName().'ServiceProvider.php'),
        ];
    }

    /**
     * 模块中的模型（完整类名）；模型文件不存在时返回 null。
     */
    protected function moduleModel(string $model): ?string
    {
        return $this->files->exists($this->modulePath('Models/'.$model.'.php'))
            ? $this->moduleNamespace().'\\Models\\'.$model
            : null;
    }

    /**
     * 已有的模块（按 --core 区分）。
     *
     * @return list<string>
     */
    protected function existingModules(): array
    {
        return array_map(basename(...), glob(app_path($this->moduleKind().'/*'), GLOB_ONLYDIR) ?: []);
    }

    /**
     * 模块相关的输入错误。
     */
    protected function moduleError(): ?string
    {
        $module = $this->stringOption('module');

        if ($module === null) {
            return '请用 --module= 指定所属模块，如 --module=Content'.($this->option('core') ? '' : '（内核模块再加 --core）').'。';
        }

        if (($error = $this->nameError($module, '模块名')) !== null) {
            return $error;
        }

        if (is_dir($this->modulePath())) {
            return null;
        }

        $other = $this->option('core') ? 'Modules' : 'Core';

        return is_dir(app_path($other.'/'.$this->moduleName()))
            ? "模块 {$this->moduleName()} 在 app/{$other} 中".($this->option('core') ? '，不要加 --core。' : '：内核模块需加 --core。')
            : "模块 [app/{$this->moduleKind()}/{$this->moduleName()}] 不存在，请先执行 php artisan core:make-module {$this->moduleName()}".($this->option('core') ? ' --core' : '').'。';
    }

    /**
     * 模块相关的模板替换内容。
     *
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [
            'module' => $this->moduleName(),
            'moduleNamespace' => $this->moduleNamespace(),
            'moduleProvider' => class_basename($this->moduleProvider()['class']),
        ];
    }
}
