<?php

namespace App\Core\Generator\Commands;

use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-action：模块中的 Action（写操作用例，一个类一个用例，handle() 方法，设计文档 §5.4、§10.4）。
 *
 * 带模型（`--model=`，或名称就是“用例 + 已有的模型”，如 CreateArticle）时按名称识别标准用例，写法与 Content 一致：
 *
 * - `Create<模型>`：`handle(array $attributes, User $actor)`，事务中新增，created_by 为操作者；
 * - `Update<模型>`：`handle(<模型>, array $attributes, User $actor)`，事务中锁行后修改；
 * - `Delete<模型>`：锁行后删除（软删除的模型为软删除）；
 * - `Restore<模型>`、`ForceDelete<模型>`：软删除的模型才有，`withTrashed()->lockForUpdate()` 复核“已删除”，并发时 422；
 * - 其他名称：`handle(<模型>, User $actor)`，锁行 → 复核 → 修改 → 返回的骨架。
 *
 * 模型实现了 Monitor 的 Auditable（`core:make-model --audit`）时，写操作在同一事务中调用 Monitor 的 LogAudit 写审计日志（§5.5）。
 * 不带模型时生成带 `User $actor` 的骨架。
 */
#[Signature('core:make-action
    {name : Action 的类名，如 PublishArticle}
    {--model= : 用例针对的模型（模块中的模型名）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成 Action（写操作用例：一个类一个用例，handle() 方法；--model 生成标准用例）')]
class ActionMakeCommand extends ModuleGeneratorCommand
{
    /**
     * 按名称识别的标准用例：名称前缀 => 模板（名称为前缀 + 模型名）。
     *
     * @var array<string, string>
     */
    public const array VERBS = [
        'ForceDelete' => 'action.force-delete',
        'Create' => 'action.create',
        'Update' => 'action.update',
        'Delete' => 'action.delete',
        'Restore' => 'action.restore',
    ];

    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Action';

    protected function stubName(): string
    {
        $model = $this->model();

        if ($model === null) {
            return 'action';
        }

        $name = $this->getNameInput();

        return array_find(self::VERBS, fn (string $stub, string $verb): bool => $name === $verb.$model) ?? 'action.model';
    }

    protected function subNamespace(): string
    {
        return 'Actions';
    }

    protected function example(): string
    {
        return 'PublishArticle';
    }

    /**
     * 输入错误时返回错误信息：--model 的模型必须已在模块中；恢复、彻底删除只用于软删除的模型。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        $explicit = $this->stringOption('model');

        if ($explicit !== null && ($error = $this->nameError($explicit, '模型名')) !== null) {
            return $error;
        }

        $model = $this->model();

        if ($explicit !== null && $model === null) {
            return "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/".Str::studly($explicit).'.php] 不存在，请先执行 php artisan core:make-model '.Str::studly($explicit)." --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。';
        }

        if ($model !== null && in_array($this->stubName(), ['action.restore', 'action.force-delete'], true) && ! $this->modelFeatures($model)['softDeletes']) {
            return "模型 {$model} 没有软删除（SoftDeletes），不能生成 {$this->getNameInput()}。";
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->model();
        $user = $this->authUserModel();

        return [
            ...parent::replacements($class),
            ...($model === null ? [] : $this->modelReplacements($model)),
            'userModel' => class_basename($user),
            'namespacedUserModel' => $user,
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        $model = $this->model();
        $features = $model === null ? [] : $this->modelFeatures($model);

        return [...$features, 'needsActor' => ($features['audit'] ?? false) || ($features['createdBy'] ?? false)];
    }

    /**
     * 用例针对的模型：--model，或名称为“标准用例前缀 + 已有的模型”（如 CreateArticle）；都没有时为 null。
     */
    private function model(): ?string
    {
        if (($model = $this->stringOption('model')) !== null) {
            return $this->moduleModel(Str::studly($model)) === null ? null : Str::studly($model);
        }

        $name = $this->getNameInput();
        $candidates = array_map(fn (string $verb): ?string => str_starts_with($name, $verb) ? substr($name, strlen($verb)) : null, array_keys(self::VERBS));

        return array_find($candidates, fn (?string $candidate): bool => $candidate !== null && $candidate !== '' && $this->moduleModel($candidate) !== null);
    }

    /**
     * 认证用户的模型（config/auth.php）。
     */
    private function authUserModel(): string
    {
        $model = $this->userProviderModel();

        return is_string($model) ? $model : User::class;
    }
}
