<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-migration：模块中模型的建表迁移（database/migrations/<时间>_create_<表>_table.php，设计文档 §14）。
 * 表名与 Eloquent 的默认推断一致（类名的复数 snake_case）；列按模型文件的写法生成：name、created_by（外键）、时间戳、软删除。同一张表已有建表迁移时视为已存在：--force 覆盖原文件，不会重复创建。
 */
#[Signature('core:make-migration
    {name : 模型名，如 Article（生成 create_articles_table）}
    '.self::MODULE_OPTIONS)]
#[Description('为模块中的模型生成建表迁移')]
class MigrationMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Migration';

    /**
     * 本次执行的迁移文件路径（同一次执行中多次计算时间戳可能不同，只算一次）。
     */
    private ?string $migrationPath = null;

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $this->migrationPath = null;

        return parent::handle();
    }

    protected function stubName(): string
    {
        return 'migration';
    }

    protected function subNamespace(): string
    {
        return 'Models';
    }

    /**
     * 输入错误时返回错误信息：模型必须已在模块中。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        return $this->moduleModel($this->getNameInput()) === null
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/{$this->getNameInput()}.php] 不存在，请先执行 php artisan core:make-model {$this->getNameInput()} --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。'
            : null;
    }

    /**
     * 同一张表已有建表迁移。
     *
     * @param  string  $rawName
     */
    protected function alreadyExists($rawName): bool
    {
        return $this->existingMigration() !== null;
    }

    /**
     * 迁移文件路径：已有同表的建表迁移时沿用它。
     *
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        return $this->migrationPath ??= $this->existingMigration()
            ?? database_path('migrations/'.now()->format('Y_m_d_His').'_create_'.$this->tableName().'_table.php');
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [...parent::replacements($class), 'model' => $this->getNameInput(), 'table' => $this->tableName()];
    }

    /**
     * 按模型文件的写法：name、created_by、软删除（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return $this->modelFeatures($this->getNameInput());
    }

    /**
     * 表名：与 Eloquent 的默认推断一致。
     */
    private function tableName(): string
    {
        return Str::snake(Str::pluralStudly($this->getNameInput()));
    }

    private function existingMigration(): ?string
    {
        $existing = glob(database_path('migrations/*_create_'.$this->tableName().'_table.php')) ?: [];

        return $existing === [] ? null : $existing[0];
    }
}
