<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-resource：入口中的 API Resource（app/Http/<入口>/<模块>/Resources），逐个列出字段，日期输出 ISO 8601（设计文档 §9、§10.1）。
 * `--model=` 生成某个模型的 Resource（只有受信任的入口可以直接使用模型）：按模型文件的写法列出 id、name、created_by、creator、
 * deleted_at 与时间字段，`relations()` 返回需要预加载的关联（设计文档 §10.4）。
 */
#[Signature('core:make-resource
    {name : Resource 类名，如 Article（生成 ArticleResource）}
    {--model= : 输出的模型（模块中的模型名）}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('在入口中生成 API Resource（--model 生成某个模型的 Resource）')]
class ResourceMakeCommand extends EntryGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Resource';

    protected function stubName(): string
    {
        return $this->modelOption() === null ? 'resource' : 'resource.model';
    }

    protected function subNamespace(): string
    {
        return 'Resources';
    }

    protected function suffix(): string
    {
        return 'Resource';
    }

    /**
     * 输入错误时返回错误信息：--model 的模型须在模块中，且入口受信任。
     */
    protected function inputError(): ?string
    {
        $model = $this->modelOption();

        return parent::inputError() ?? ($model === null ? null : $this->modelError($model));
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $model = $this->modelOption() ?? '';

        return [
            ...parent::replacements($class),
            ...($model === '' ? [] : $this->modelReplacements($model)),
        ];
    }

    /**
     * 按模型文件的写法（设计文档 §10.4）。
     *
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        $model = $this->modelOption();

        return $model === null ? [] : $this->modelFeatures($model);
    }
}
