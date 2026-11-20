<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-enum：模块中的枚举，实现 App\Core\System\Contracts\HasLabel（显示名称由后端统一下发，设计文档 §5.5、§10.1）；
 * `--color` 另实现 HasColor（语义色：表示“状态”的枚举用它，纯分类的不用）。
 */
#[Signature('core:make-enum
    {name : 枚举名，如 ArticleStatus}
    {--color : 同时实现 HasColor（语义色）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成枚举（字符串值，实现 HasLabel；--color 另实现 HasColor）')]
class EnumMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Enum';

    protected function stubName(): string
    {
        return 'enum';
    }

    protected function subNamespace(): string
    {
        return 'Enums';
    }

    /**
     * @return array<string, bool>
     */
    protected function sections(string $class): array
    {
        return ['color' => (bool) $this->option('color')];
    }

    protected function example(): string
    {
        return 'ArticleStatus';
    }
}
