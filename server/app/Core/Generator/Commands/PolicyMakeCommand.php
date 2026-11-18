<?php

namespace App\Core\Generator\Commands;

use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-policy：模块中模型的 Policy（设计文档 §8.3）：标准方法齐全、默认拒绝。
 * 框架按 `\Models\` → `\Policies\` 自动发现，不需要登记。
 */
#[Signature('core:make-policy
    {name : 模型名或 Policy 类名，如 Article（生成 ArticlePolicy）}
    {--model= : 模型名（默认取名称去掉 Policy 后缀）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中为模型生成 Policy（标准方法齐全，默认拒绝）')]
class PolicyMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Policy';

    protected function stubName(): string
    {
        return 'policy';
    }

    protected function subNamespace(): string
    {
        return 'Policies';
    }

    protected function suffix(): string
    {
        return 'Policy';
    }

    /**
     * 输入错误时返回错误信息：模型必须已在模块中。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        if (($error = $this->nameError($this->model(), '模型名')) !== null) {
            return $error;
        }

        return $this->moduleModel($this->model()) === null
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/{$this->model()}.php] 不存在：Policy 属于模型，请先执行 php artisan core:make-model {$this->model()} --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。'
            : null;
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
            'modelVariable' => Str::camel($this->model()),
            'userModel' => class_basename($this->userModel()),
        ];
    }

    /**
     * 认证用户的模型（config/auth.php），替换模板中的 `{{ namespacedUserModel }}`。
     */
    protected function userProviderModel(): string
    {
        return $this->userModel();
    }

    private function userModel(): string
    {
        $model = parent::userProviderModel();

        return is_string($model) ? $model : User::class;
    }

    /**
     * 模型名：--model，或名称去掉 Policy 后缀。
     */
    private function model(): string
    {
        return Str::studly($this->stringOption('model') ?? Str::chopEnd($this->getNameInput(), 'Policy'));
    }
}
