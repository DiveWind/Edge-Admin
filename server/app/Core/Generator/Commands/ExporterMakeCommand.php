<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\{ClassMapFile, ModuleProviderFile};
use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-exporter：模块中某个列表的导出类（Exporters/<模型>Exporter，设计文档 §6.10、§10.4），与 Content 的 ArticleExporter 一致：
 * 查询调用列表的 Query 类 `<模型>List`（缺少时一并生成），列与 map() 按模型文件的写法生成。
 *
 * 登记到模块的 resources/exporters.php（对外名称：业务模块 `<模块>.<模型>`，内核模块 `<模型>`，设计文档 §4.4；文件没有时创建，并在 ServiceProvider 的 boot() 中注册到 ExporterRegistry）；
 * 并提示还需要的：菜单的 `<菜单>.export` 按钮与导出入口（core:make-controller Export<模型复数> --invokable --model=<模型>）。
 */
#[Signature('core:make-exporter
    {name : 模型名或导出类名，如 Article（生成 ArticleExporter）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中为模型的列表生成导出类（调用列表的 Query 类），并登记到 ExporterRegistry')]
class ExporterMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Exporter';

    protected function stubName(): string
    {
        return 'exporter';
    }

    protected function subNamespace(): string
    {
        return 'Exporters';
    }

    protected function suffix(): string
    {
        return 'Exporter';
    }

    /**
     * 输入错误时返回错误信息：模型必须已在模块中。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        return $this->moduleModel($this->model()) === null
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/{$this->model()}.php] 不存在，请先执行 php artisan core:make-model {$this->model()} --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。'
            : null;
    }

    /**
     * 生成缺少的列表查询，登记到模块的 resources/exporters.php（没有时创建，并在 Provider 中注册到 ExporterRegistry），并提示还需要的按钮与入口。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $model = $this->model();
        $name = $this->permissionFor($model);
        $identifier = $this->identifierFor($model);

        $status = $this->generateUnlessExists('core:make-query', [
            'name' => "{$model}List",
            '--model' => $model,
            '--module' => $this->moduleName(),
            '--core' => (bool) $this->option('core'),
        ], $this->modulePath("Queries/{$model}List.php"));

        $declarations = $this->modulePath('resources/exporters.php');

        if (! $this->files->exists($declarations)) {
            $this->writeFromStub('module.exporters', $declarations, $this->replacements($class), 'Declarations');
        }

        $this->register($declarations, "导出类 {$identifier}", fn (string $code): ?string => ClassMapFile::register($code, $identifier, $class));
        $this->register($this->moduleProvider()['path'], '导出类声明 resources/exporters.php', fn (string $code): ?string => ModuleProviderFile::registerExporters($code));

        if (! in_array("{$name}.export", $this->declaredMenuNames(), true)) {
            $this->components->info('还需要：');
            $this->components->bulletList([
                "在 [{$this->relativePath($this->modulePath('resources/menus.php'))}] 的菜单 {$name} 下加按钮 'export' => 'common.export'",
                '导出入口：php artisan core:make-controller '.self::exportName($model)." --invokable --model={$model} --module={$this->moduleName()}".($this->option('core') ? ' --core' : ''),
            ]);
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $user = $this->userProviderModel();
        $user = is_string($user) ? $user : User::class;

        return [
            ...parent::replacements($class),
            ...$this->modelReplacements($this->model()),
            'userModel' => class_basename($user),
            'namespacedUserModel' => $user,
        ];
    }

    /**
     * 按模型文件的写法（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return $this->modelFeatures($this->model());
    }

    /**
     * 模型名：名称去掉 Exporter 后缀。
     */
    private function model(): string
    {
        return Str::chopEnd($this->getNameInput(), 'Exporter');
    }
}
