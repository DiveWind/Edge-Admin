<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\ModuleProviderFile;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-model：模块中的模型（设计文档 §4.5、§10.4），写法与 Content 的 Article 一致：Attribute 写法与 casts()、`@property` 注释、
 * UsesAppTimezone、占位字段 name、创建人 created_by 与 creator()、HasDataScope（数据范围，`--no-data-scope` 去掉）、
 * SoftDeletes（`--soft-deletes`）、Monitor 的 Auditable（`--audit`：审计快照 auditSnapshot()，写操作的 Action 随之写审计日志）。
 *
 * 接着调用 core:make-migration、core:make-factory、core:make-policy（它们按模型文件的写法生成），生成本模型的语言包
 * （resources/lang/<语言>/<模型>.php，config('core.locales') 中的每种语言），最后在模块的 ServiceProvider 中登记多态别名
 * （`Relation::morphMap()`）与语言包（`loadTranslationsFrom()`）。
 *
 * 多态别名：业务模块为 `<模块>.<模型>`（snake_case，如 content.article，避免不同模块的同名模型冲突）；
 * 内核模块为 `<模型>`（如 System 的 `user`）。
 */
#[Signature('core:make-model
    {name : 模型名（单数），如 Article}
    {--soft-deletes : 软删除（SoftDeletes、deleted_at；恢复、彻底删除与“已删除”筛选随之生成，设计文档 §10.1）}
    {--no-data-scope : 不使用数据范围（HasDataScope，设计文档 §8.4）}
    {--audit : 审计日志：实现 Monitor 的 Auditable（auditSnapshot()），写操作随之调用 LogAudit（设计文档 §5.5）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成模型，以及迁移、工厂、Policy、语言包，并登记多态别名与语言包')]
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
     * 生成迁移、工厂、Policy 与语言包，并登记多态别名与语言包。已存在的文件（未加 --force 时）跳过。
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
            $this->generateTranslations($class),
        );

        $provider = $this->moduleProvider();

        $this->register($provider['path'], "多态别名 {$this->morphAlias($model)}", fn (string $code): ?string => ModuleProviderFile::registerMorphAlias($code, $this->morphAlias($model), $class));
        $this->register($provider['path'], "语言包（命名空间 {$this->translationNamespace()}）", fn (string $code): ?string => ModuleProviderFile::registerTranslations($code, $this->translationNamespace()));

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = class_basename($class);
        $traits = ['HasFactory', 'UsesAppTimezone'];

        if (! $this->option('no-data-scope')) {
            $traits[] = 'HasDataScope';
        }

        if ($this->option('soft-deletes')) {
            $traits[] = 'SoftDeletes';
        }

        sort($traits);

        return [
            ...parent::replacements($class),
            ...$this->modelReplacements($model),
            'factory' => $model.'Factory',
            'namespacedFactory' => 'Database\\Factories\\'.$this->moduleKind().'\\'.$this->moduleName().'\\Models\\'.$model.'Factory',
            'morphAlias' => $this->morphAlias($model),
            'modelSnake' => Str::snake($model),
            'traits' => implode(', ', $traits),
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return ['softDeletes' => (bool) $this->option('soft-deletes'), 'dataScope' => ! $this->option('no-data-scope'), 'audit' => (bool) $this->option('audit')];
    }

    /**
     * 本模型的语言包：config('core.locales') 中的每种语言（有 `lang.<语言>` 模板时用它，否则用英文模板）。
     */
    private function generateTranslations(string $class): int
    {
        $locales = config('core.locales');
        $status = self::SUCCESS;

        foreach (is_array($locales) && $locales !== [] ? array_filter($locales, is_string(...)) : ['en'] as $locale) {
            $path = $this->modulePath("resources/lang/{$locale}/".Str::snake(class_basename($class)).'.php');

            if ($this->files->exists($path) && ! $this->option('force')) {
                $this->components->twoColumnDetail("[{$this->relativePath($path)}]", '<fg=yellow;options=bold>已存在，跳过</>');

                continue;
            }

            $stub = file_exists($this->resolveStub("lang.{$locale}")) ? "lang.{$locale}" : 'lang.en';

            if (! $this->writeFromStub($stub, $path, $this->replacements($class), 'Translations', $this->sections($class))) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    private function morphAlias(string $model): string
    {
        return $this->identifierFor($model);
    }
}
