<?php

namespace App\Core\Generator\Commands;

use App\Core\System\Models\User;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-query：模块中的 Query（只读，设计文档 §5.4、§6.10、§10.4）。
 *
 * - 列表查询（`--model=`，或名称为 `<已有的模型>List`）：`builder(User $actor, array $parameters): QueryBuilder`，与 Content 的 ArticleList 一致：
 *   数据范围、预加载、query-builder 的筛选与排序白名单、默认排序；软删除的模型另有 trashed 筛选与 viewTrashed 检查。列表与导出共用它；
 * - 其他查询（统计等）：`get(User $actor): array` 的骨架。
 */
#[Signature('core:make-query
    {name : Query 的类名，如 ArticleList（列表）或 ArticleStatistics}
    {--model= : 列表查询的模型（名称为 <模型>List 时可省略）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成 Query（列表查询的 builder，或统计查询的骨架，只读）')]
class QueryMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Query';

    protected function stubName(): string
    {
        return $this->model() === null ? 'query' : 'query.list';
    }

    protected function subNamespace(): string
    {
        return 'Queries';
    }

    protected function example(): string
    {
        return 'ArticleList';
    }

    /**
     * 输入错误时返回错误信息：--model 的模型必须已在模块中。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        $explicit = $this->stringOption('model');

        if ($explicit === null) {
            return null;
        }

        return $this->nameError($explicit, '模型名') ?? ($this->model() === null
            ? "模型 [app/{$this->moduleKind()}/{$this->moduleName()}/Models/".Str::studly($explicit).'.php] 不存在，请先执行 php artisan core:make-model '.Str::studly($explicit)." --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。'
            : null);
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->model();
        $user = $this->userProviderModel();
        $user = is_string($user) ? $user : User::class;

        return [
            ...parent::replacements($class),
            ...($model === null ? [] : $this->modelReplacements($model)),
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
        $model = $this->model();

        return $model === null ? [] : $this->modelFeatures($model);
    }

    /**
     * 列表查询的模型：--model，或名称去掉 List 后缀后是已有的模型；都没有时为 null。
     */
    private function model(): ?string
    {
        $explicit = $this->stringOption('model');
        $name = $this->getNameInput();
        $model = $explicit !== null ? Str::studly($explicit) : (str_ends_with($name, 'List') ? Str::chopEnd($name, 'List') : '');

        return $model !== '' && $this->moduleModel($model) !== null ? $model : null;
    }
}
