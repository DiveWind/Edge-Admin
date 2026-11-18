<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\ModuleProviderFile;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-model：模块中的模型（UsesAppTimezone、Attribute 写法、casts()），并调用
 * core:make-migration、core:make-factory、core:make-policy 生成迁移、工厂与 Policy，
 * 最后在模块的 ServiceProvider 中登记多态别名（`Relation::morphMap()`，设计文档 §4.5）。
 *
 * 多态别名：业务模块为 `<模块>.<模型>`（snake_case，如 content.article，避免不同模块的同名模型冲突）；
 * 内核模块与 AppServiceProvider 中的 `user` 一致，为 `<模型>`。
 */
#[Signature('core:make-model
    {name : 模型名（单数），如 Article}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成模型，以及迁移、工厂、Policy，并登记多态别名')]
class ModelMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Model';

    protected function stubName(): string
    {
        return 'model';
    }

    protected function subNamespace(): string
    {
        return 'Models';
    }

    /**
     * 生成迁移、工厂、Policy，并登记多态别名。已存在的文件（未加 --force 时）跳过。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $model = class_basename($class);
        $arguments = ['name' => $model, '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')];

        $migrations = glob(database_path('migrations/*_create_'.Str::snake(Str::pluralStudly($model)).'_table.php')) ?: [];

        $status = max(
            $this->generateUnlessExists('core:make-migration', $arguments, $migrations[0] ?? ''),
            $this->generateUnlessExists('core:make-factory', $arguments, database_path('factories/'.$this->moduleKind().'/'.$this->moduleName()."/Models/{$model}Factory.php")),
            $this->generateUnlessExists('core:make-policy', $arguments, $this->modulePath("Policies/{$model}Policy.php")),
        );

        $provider = $this->moduleProvider();

        $this->register($provider['path'], "多态别名 {$this->morphAlias($model)}", fn (string $code): ?string => ModuleProviderFile::registerMorphAlias($code, $this->morphAlias($model), $class));

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = class_basename($class);

        return [
            ...parent::replacements($class),
            'factory' => $model.'Factory',
            'namespacedFactory' => 'Database\\Factories\\'.$this->moduleKind().'\\'.$this->moduleName().'\\Models\\'.$model.'Factory',
            'morphAlias' => $this->morphAlias($model),
        ];
    }

    private function morphAlias(string $model): string
    {
        return $this->option('core') ? Str::snake($model) : Str::snake($this->moduleName()).'.'.Str::snake($model);
    }
}
