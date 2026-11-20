<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\ProvidersFile;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-module：业务模块 app/Modules/<名称>（加 --core 时为内核模块 app/Core/<名称>，设计文档 §4.3、§10.4）：
 * `<名称>ServiceProvider`（登记到 bootstrap/providers.php；boot() 已接上语言包、MenuRegistry 与 DictRegistry）、
 * 空的 resources/menus.php 与 resources/dicts.php、README.md（含“删除本模块”）。
 *
 * 不建空目录：模块内的 Models、Actions 等目录，以及各受信任入口中的同名目录（app/Http/<入口>/<名称>），
 * 都在用 core:make-* 生成其中的类时创建（git 不保存空目录）。
 *
 * `--crud=<模型>`：生成模块后接着执行 core:make-crud（完整增删改查），`--soft-deletes` 等选项原样传过去。
 */
#[Signature('core:make-module
    {name : 模块名，如 Content}
    {--core : 生成内核模块（app/Core），极少使用，需显式指定}
    {--crud= : 接着为这个模型生成完整增删改查（core:make-crud），如 --crud=Article}
    {--app=admin : --crud 的接口所在的入口}
    {--soft-deletes : --crud：软删除整套}
    {--export : --crud：异步导出}
    {--audit : --crud：审计日志}
    {--no-data-scope : --crud：不使用数据范围}
    {--f|force : 文件已存在时覆盖}')]
#[Description('生成模块：ServiceProvider（登记到 bootstrap/providers.php）、菜单与字典声明、README.md；--crud= 接着生成完整增删改查')]
class ModuleMakeCommand extends CoreGeneratorCommand
{
    /**
     * 不能用作模块名的名称：与入口目录中的公共目录冲突（app/Http/<入口>/<模块> 与 Requests、Middleware 等同级）。
     *
     * @var list<string>
     */
    public const array RESERVED = ['Controllers', 'Middleware', 'Requests', 'Resources', 'Responses'];

    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Module';

    protected function stubName(): string
    {
        return 'module.provider';
    }

    /**
     * 输入错误时返回错误信息。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        if (($crud = $this->stringOption('crud')) !== null && ($error = $this->nameError($crud, '模型名')) !== null) {
            return $error;
        }

        $name = $this->getNameInput();

        if (in_array($name, self::RESERVED, true)) {
            return "模块名“{$name}”与入口中的公共目录（".implode('、', self::RESERVED).'）冲突。';
        }

        $other = $this->option('core') ? 'Modules' : 'Core';

        return is_dir(app_path("{$other}/{$name}"))
            ? "app/{$other}/{$name} 已存在：内核模块与业务模块不能同名（入口中的同名目录会冲突）。"
            : null;
    }

    /**
     * 模块的 ServiceProvider：App\<Core|Modules>\<名称>\<名称>ServiceProvider。
     *
     * @param  string  $name
     */
    protected function qualifyClass($name): string
    {
        return $this->laravel->getNamespace().$this->kind().'\\'.$name.'\\'.$name.'ServiceProvider';
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, array{string, string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => ['模块名？', '如 Content']];
    }

    /**
     * 生成 README.md、resources/menus.php 与 dicts.php，登记 ServiceProvider；--crud 时接着执行 core:make-crud。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $status = self::SUCCESS;
        $files = ['module.readme' => 'README.md', 'module.menus' => 'resources/menus.php', 'module.dicts' => 'resources/dicts.php'];

        foreach ($files as $stub => $file) {
            $target = dirname($path).'/'.$file;

            if ($this->files->exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail("[{$this->relativePath($target)}]", '<fg=yellow;options=bold>已存在，跳过</>');
            } elseif (! $this->writeFromStub($stub, $target, $this->replacements($class), $file === 'README.md' ? 'README' : 'Declarations')) {
                $status = self::FAILURE;
            }
        }

        $this->register($this->laravel->bootstrapPath('providers.php'), class_basename($class), fn (string $code): ?string => ProvidersFile::register($code, $class));

        $module = class_basename(dirname($path));

        if (($model = $this->stringOption('crud')) !== null) {
            return max($status, $this->failed ? self::FAILURE : self::SUCCESS, $this->call('core:make-crud', [
                'name' => $model,
                '--module' => $module,
                '--core' => (bool) $this->option('core'),
                '--app' => $this->stringOption('app') ?? 'admin',
                '--soft-deletes' => (bool) $this->option('soft-deletes'),
                '--export' => (bool) $this->option('export'),
                '--audit' => (bool) $this->option('audit'),
                '--no-data-scope' => (bool) $this->option('no-data-scope'),
                '--force' => (bool) $this->option('force'),
            ]));
        }

        $this->nextSteps($module);

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $name = Str::beforeLast(class_basename($class), 'ServiceProvider');

        return [
            'module' => $name,
            'moduleProvider' => $name.'ServiceProvider',
            'kind' => $this->option('core') ? '内核模块' : '业务模块',
            'kindDir' => $this->kind(),
            'core' => $this->option('core') ? ' --core' : '',
            'directory' => 'app/'.$this->kind().'/'.$name,
            'entries' => implode('、', array_map(fn (string $key): string => '`app/Http/'.Str::studly($key).'/'.$name.'`', $this->trustedApps())) ?: '（暂无）',
            'translationNamespace' => Str::snake($name),
            'catalog' => Str::snake($name),
            'catalogPath' => Str::kebab($name),
            'routePrefix' => Str::kebab($name),
            'morphPrefix' => $this->option('core') ? '' : Str::snake($name).'.',
        ];
    }

    private function kind(): string
    {
        return $this->option('core') ? 'Core' : 'Modules';
    }

    /**
     * @return list<string>
     */
    private function trustedApps(): array
    {
        return array_keys(array_filter($this->registeredApps()));
    }

    private function nextSteps(string $module): void
    {
        $core = $this->option('core') ? ' --core' : '';
        $steps = [
            "完整增删改查：php artisan core:make-crud <模型> --module={$module}{$core} [--soft-deletes] [--export] [--audit] [--no-data-scope]",
            "模型：php artisan core:make-model <名称> --module={$module}{$core}",
            "写操作：php artisan core:make-action <名称> --module={$module}{$core}",
        ];

        foreach ($this->trustedApps() as $key) {
            $steps[] = "入口 {$key} 的接口（生成到 app/Http/".Str::studly($key)."/{$module}）：php artisan core:make-controller <名称> --app={$key} --module={$module}{$core} --model=<模型>";
        }

        $this->components->bulletList($steps);
    }
}
