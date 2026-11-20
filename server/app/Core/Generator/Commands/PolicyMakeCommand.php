<?php

namespace App\Core\Generator\Commands;

use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-policy：模块中模型的 Policy（设计文档 §8.3）：标准方法齐全、默认拒绝。
 * 框架按 `\Models\` → `\Policies\` 自动发现，不需要登记。
 *
 * 按标准写法先检查权限码：`<权限码>`（viewAny、view）、`<权限码>.create`、`.update`、`.delete`（delete、deleteAny），
 * 再做业务保护，最后检查数据范围（模型使用 HasDataScope 时 isVisibleTo）。模型使用 SoftDeletes 时另有 `.restore`、`.force-delete`
 * （restore、forceDelete 只对已删除的记录，提示文案在语言包中）与 viewTrashed（§10.1）；否则 restore 等四个方法一律拒绝。
 * 权限码默认为 `<模块>.<模型>`（snake_case，如 content.article），用 --permission 指定；
 * 权限码需在模块的 resources/menus.php 中声明（§8.2），命令只提示还没声明的。
 */
#[Signature('core:make-policy
    {name : 模型名或 Policy 类名，如 Article（生成 ArticlePolicy）}
    {--model= : 模型名（默认取名称去掉 Policy 后缀）}
    {--permission= : 菜单节点的权限码（默认 <模块>.<模型>，如 content.article）}
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

        if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/', $this->permission()) !== 1) {
            return "权限码“{$this->permission()}”不合法：应为小写点分，如 content.article。";
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
            'permission' => $this->permission(),
            'lang' => $this->modelReplacements($this->model())['lang'],
        ];
    }

    /**
     * 按模型文件的写法：数据范围、软删除（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return $this->modelFeatures($this->model());
    }

    /**
     * 提示在模块的 resources/menus.php 中声明用到的权限码。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $permission = $this->permission();
        $buttons = ['create', 'update', 'delete', ...($this->modelFeatures($this->model())['softDeletes'] ? ['restore', 'force-delete'] : [])];
        $declared = $this->declaredMenuNames();
        $missing = array_values(array_filter($buttons, fn (string $button): bool => ! in_array("{$permission}.{$button}", $declared, true)));

        if ($missing === [] && in_array($permission, $declared, true)) {
            return self::SUCCESS;
        }

        $this->components->info("请在 [{$this->relativePath($this->modulePath('resources/menus.php'))}] 中声明用到的权限码（设计文档 §8.2），声明后执行 php artisan core:sync-permissions：");
        $this->components->bulletList(array_values(array_filter([
            in_array($permission, $declared, true) ? null : "{$permission}（menu 节点：查看、列表）",
            $missing === [] ? null : implode('、', array_map(fn (string $button): string => "{$permission}.{$button}", $missing)).'（该节点的 buttons）',
        ])));

        return self::SUCCESS;
    }

    /**
     * 菜单节点的权限码：--permission，或 `<模块>.<模型>`（snake_case）。
     */
    private function permission(): string
    {
        return $this->stringOption('permission') ?? Str::snake($this->moduleName()).'.'.Str::snake($this->model());
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
