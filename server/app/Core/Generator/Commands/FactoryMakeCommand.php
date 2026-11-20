<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-factory：模块中模型的工厂，放在 database/factories 下与模型相同的路径（设计文档 §4.5），
 * 如 App\Modules\Content\Models\Article → Database\Factories\Modules\Content\Models\ArticleFactory。
 * 默认状态按模型文件的写法：name、created_by（新建一个用户）；软删除的模型另有 trashed() 状态。
 */
#[Signature('core:make-factory
    {name : 模型名，如 Article（生成 ArticleFactory）}
    '.self::MODULE_OPTIONS)]
#[Description('为模块中的模型生成工厂（database/factories 下与模型相同的路径）')]
class FactoryMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Factory';

    protected function stubName(): string
    {
        return 'factory';
    }

    protected function subNamespace(): string
    {
        return 'Models';
    }

    protected function suffix(): string
    {
        return 'Factory';
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
     * Get the root namespace for the class.
     */
    protected function rootNamespace(): string
    {
        return 'Database\\Factories\\';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\'.Str::after(parent::getDefaultNamespace($rootNamespace), $this->laravel->getNamespace());
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        return database_path('factories/'.str_replace('\\', '/', Str::after($name, $this->rootNamespace())).'.php');
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [
            ...parent::replacements($class),
            'model' => $this->model(),
            'namespacedModel' => $this->moduleNamespace().'\\Models\\'.$this->model(),
        ];
    }

    /**
     * 按模型文件的写法：name、created_by、软删除的 trashed() 状态（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return $this->modelFeatures($this->model());
    }

    /**
     * 模型名：名称去掉 Factory 后缀。
     */
    private function model(): string
    {
        return Str::chopEnd($this->getNameInput(), 'Factory');
    }
}
