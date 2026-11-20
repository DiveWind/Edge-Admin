<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Support\Str;
use Override;

/**
 * core:make-request：入口中的 Form Request（app/Http/<入口>/<模块>/Requests），只做输入格式校验（设计文档 §5.4、§10.4）。
 *
 * - `--list`：列表请求，继承入口的 Requests\ListRequest（page、pageSize、sort、filter[]，§6.3）；带模型（`--model=`，或名称为
 *   `<已有的模型>Index`）时按模型文件的写法校验筛选值（name、created_at、软删除的 trashed）；
 * - `--export`：导出请求（§6.10），继承同模块的列表请求（`<名称>Export` → `<名称>IndexRequest`，须已存在），去掉 page、pageSize，加 format；
 * - 只有 `--model=`：新增 / 修改的表单请求（占位字段 name 的校验与字段名）。
 */
#[Signature('core:make-request
    {name : 请求类名，如 StoreArticle（生成 StoreArticleRequest）}
    {--list : 列表请求，继承入口的 ListRequest}
    {--export : 导出请求，继承同模块的列表请求（<名称>Export → <名称>IndexRequest）}
    {--model= : 请求对应的模型（模块中的模型名）}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('在入口中生成 Form Request（--list 列表请求、--export 导出请求、--model 模型的表单请求）')]
class RequestMakeCommand extends EntryGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Request';

    protected function stubName(): string
    {
        return match (true) {
            (bool) $this->option('export') => 'request.export',
            (bool) $this->option('list') => 'request.list',
            $this->model() !== null => 'request.model',
            default => 'request',
        };
    }

    protected function subNamespace(): string
    {
        return 'Requests';
    }

    protected function suffix(): string
    {
        return 'Request';
    }

    protected function example(): string
    {
        return 'StoreArticle';
    }

    /**
     * 输入错误时返回错误信息：列表请求要求入口提供 ListRequest；导出请求要求列表请求已存在；--model 的模型须在模块中。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        if (($model = $this->modelOption()) !== null && ($error = $this->modelError($model)) !== null) {
            return $error;
        }

        if ($this->option('list') && $this->option('export')) {
            return '--list 与 --export 只能选一个。';
        }

        if (($this->option('list') || $this->option('export')) && ! $this->files->exists($this->entryPath('Requests/ListRequest.php'))) {
            return "入口 {$this->appKey()} 没有 Requests/ListRequest.php，不能生成列表或导出请求。";
        }

        if ($this->option('export') && ! $this->files->exists($this->entryPath("{$this->moduleName()}/Requests/{$this->parentRequest()}.php"))) {
            return "导出请求继承列表请求 {$this->parentRequest()}，请先执行 php artisan core:make-request ".Str::chopEnd($this->parentRequest(), 'Request')." --list --module={$this->moduleName()}".($this->option('core') ? ' --core' : '').'。';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->model();

        return [
            ...parent::replacements($class),
            ...($model === null ? [] : $this->modelReplacements($model)),
            'namespacedListRequest' => $this->entryNamespace().'\\Requests\\ListRequest',
            'parentRequest' => $this->parentRequest(),
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

        return $model === null ? [] : [...$this->modelFeatures($model), 'model' => true];
    }

    /**
     * 导出请求继承的列表请求：`<名称>Export` → `<名称>IndexRequest`。
     */
    private function parentRequest(): string
    {
        return Str::chopEnd(Str::chopEnd($this->getNameInput(), 'Request'), 'Export').'IndexRequest';
    }

    /**
     * 请求对应的模型：--model，或列表请求的名称为 `<已有的模型>Index`；都没有时为 null（受限入口不使用模型）。
     */
    private function model(): ?string
    {
        if (($model = $this->modelOption()) !== null) {
            return $model;
        }

        $base = Str::chopEnd($this->getNameInput(), 'Request');

        if (! $this->option('list') || ! str_ends_with($base, 'Index') || ! $this->trusted()) {
            return null;
        }

        $model = Str::chopEnd($base, 'Index');

        return $model !== '' && $this->moduleModel($model) !== null ? $model : null;
    }
}
